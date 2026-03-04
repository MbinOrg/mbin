<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Image;
use App\Event\ImagePostProcessEvent;
use App\Exception\ImageDownloadTooLargeException;
use App\Pagination\NativeQueryAdapter;
use App\Pagination\Pagerfanta;
use App\Pagination\Transformation\ContentPopulationTransformer;
use App\Service\ImageManagerInterface;
use App\Utils\ImageOrigin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use kornrunner\Blurhash\Blurhash;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @method Image|null find($id, $lockMode = null, $lockVersion = null)
 * @method Image|null findOneBy(array $criteria, array $orderBy = null)
 * @method Image|null findOneBySha256($sha256)
 * @method Image[]    findAll()
 * @method Image[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ImageManagerInterface $imageManager,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
        private readonly ContentPopulationTransformer $contentPopulationTransformer,
    ) {
        parent::__construct($registry, Image::class);
    }

    /**
     * Process and store an uploaded image.
     *
     * @param $upload UploadedFile file path of uploaded image
     *
     * @throws \RuntimeException if image type can't be identified
     */
    public function findOrCreateFromUpload(UploadedFile $upload): ?Image
    {
        return $this->findOrCreateFromSource($upload->getPathname(), ImageOrigin::Uploaded);
    }

    /**
     * Process and store an image from source path.
     *
     * @param $source string file path of the image
     *
     * @throws \RuntimeException              if image type can't be identified
     * @throws ImageDownloadTooLargeException
     */
    public function findOrCreateFromPath(string $source): ?Image
    {
        return $this->findOrCreateFromSource($source, ImageOrigin::External);
    }

    /**
     * Process and store an image from source file given path.
     *
     * @param string      $source file path of the image
     * @param ImageOrigin $origin where the image comes from
     *
     * @throws ImageDownloadTooLargeException
     */
    private function findOrCreateFromSource(string $source, ImageOrigin $origin): ?Image
    {
        [$filePath, $fileName] = $this->imageManager->getFilePathAndName($source);
        $sha256 = hash_file('sha256', $source, true);

        if ($image = $this->findOneBySha256($sha256)) {
            if (file_exists($source)) {
                unlink($source);
            }

            $this->logger->debug('found image by Sha256, imageId: {id}', ['id' => $image->getId()]);

            return $image;
        }

        [$width, $height] = @getimagesize($source);
        $blurhash = $this->blurhash($source);

        $image = new Image($fileName, $filePath, $sha256, $width, $height, $blurhash);

        if (!$image->width || !$image->height) {
            // why get size again?
            [$width, $height] = @getimagesize($source);
            $image->setDimensions($width, $height);
        }

        $previousFileSize = filesize($source);
        $image->originalSize = $previousFileSize;
        $this->dispatcher->dispatch(new ImagePostProcessEvent($source, $filePath, $origin));
        $afterProcessFileSize = filesize($source);
        if ($afterProcessFileSize < $previousFileSize) {
            $image->isCompressed = true;
        }

        try {
            $this->imageManager->store($source, $filePath);
            $image->localSize = $afterProcessFileSize;

            return $image;
        } catch (ImageDownloadTooLargeException $e) {
            if (ImageOrigin::External === $origin) {
                $this->logger->warning(
                    'findOrCreateFromSource: failed to store image file, because it is too big. Storing only a reference',
                    ['origin' => $origin, 'type' => \gettype($e)],
                );
                $image->filePath = null;
                $image->localSize = 0;
                $image->sourceTooBig = true;

                return $image;
            } else {
                $this->logger->error(
                    'findOrCreateFromSource: failed to store image file, because it is too big - {msg}',
                    ['origin' => $origin, 'type' => \gettype($e), 'msg' => $e->getMessage()],
                );
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'findOrCreateFromSource: failed to store image file: '.$e->getMessage(),
                ['origin' => $origin, 'type' => \gettype($e)],
            );
        } finally {
            if (file_exists($source)) {
                unlink($source);
            }
        }

        return null;
    }

    public function blurhash(string $filePath): ?string
    {
        $maxWidth = 20;

        $componentsX = 4;
        $componentsY = 3;

        try {
            $image = imagecreatefromstring(file_get_contents($filePath));
            $width = imagesx($image);
            $height = imagesy($image);

            if ($width > $maxWidth) {
                // resizing image with ratio exceeds max width would yield image with height < 1 and fail
                $ratio = $width / $height;
                $image = imagescale($image, $maxWidth, $componentsY * $ratio < $maxWidth ? -1 : $componentsY);
                if (!$image) {
                    throw new \Exception('Could not scale image');
                }

                $width = imagesx($image);
                $height = imagesy($image);
            }

            $pixels = [];
            for ($y = 0; $y < $height; ++$y) {
                $row = [];
                for ($x = 0; $x < $width; ++$x) {
                    $index = imagecolorat($image, $x, $y);
                    $colors = imagecolorsforindex($image, $index);

                    $row[] = [$colors['red'], $colors['green'], $colors['blue']];
                }
                $pixels[] = $row;
            }

            return Blurhash::encode($pixels, $componentsX, $componentsY);
        } catch (\Exception $e) {
            $this->logger->info('Failed to calculate blurhash: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param int $limit use a high limit, as this query takes a few seconds and the limit does not affect that, so we are using as high a number as we can -> we're limited by memory
     *
     * @return Pagerfanta<Image>
     *
     * @throws Exception
     */
    public function findOldRemoteMediaPaginated(int $olderThanDays, int $limit = 10000): Pagerfanta
    {
        $query = $this->createQueryBuilder('i')
            ->andWhere('i.downloadedAt < :date')
            ->andWhere('i.filePath IS NOT NULL')
            ->andWhere('i.sourceUrl IS NOT NULL')
            ->setParameter('date', new \DateTimeImmutable("now - $olderThanDays days"))
            ->getQuery();
        // this complicated looking query makes sure to not include avatars, covers, icons or banners
        $sql = 'SELECT id, MAX(last_active) as last_active, MAX(downloaded_at) as downloaded_at, \'image\' as type FROM (
            SELECT i.id, i.downloaded_at, e.last_active FROM image i
                INNER JOIN entry e ON i.id = e.image_id
                LEFT JOIN "user" u ON i.id = u.avatar_id
                LEFT JOIN "user" u2 ON i.id = u2.cover_id
                LEFT JOIN magazine m ON i.id = m.icon_id
                LEFT JOIN magazine m2 ON i.id = m2.banner_id
                WHERE u IS NULL AND u2 IS NULL AND m IS NULL AND m2 IS NULL AND i.file_path IS NOT NULL AND i.source_url IS NOT NULL
            UNION ALL
            SELECT i.id, i.downloaded_at, ec.last_active FROM image i
                INNER JOIN entry_comment ec ON i.id = ec.image_id
                LEFT JOIN "user" u ON i.id = u.avatar_id
                LEFT JOIN "user" u2 ON i.id = u2.cover_id
                LEFT JOIN magazine m ON i.id = m.icon_id
                LEFT JOIN magazine m2 ON i.id = m2.banner_id
                WHERE u IS NULL AND u2 IS NULL AND m IS NULL AND m2 IS NULL AND i.file_path IS NOT NULL AND i.source_url IS NOT NULL
            UNION ALL
            SELECT i.id, i.downloaded_at, p.last_active FROM image i
                INNER JOIN post p ON i.id = p.image_id
                LEFT JOIN "user" u ON i.id = u.avatar_id
                LEFT JOIN "user" u2 ON i.id = u2.cover_id
                LEFT JOIN magazine m ON i.id = m.icon_id
                LEFT JOIN magazine m2 ON i.id = m2.banner_id
                WHERE u IS NULL AND u2 IS NULL AND m IS NULL AND m2 IS NULL AND i.file_path IS NOT NULL AND i.source_url IS NOT NULL
            UNION ALL
            SELECT i.id, i.downloaded_at, pc.last_active FROM image i
                INNER JOIN post_comment pc ON i.id = pc.image_id
                LEFT JOIN "user" u ON i.id = u.avatar_id
                LEFT JOIN "user" u2 ON i.id = u2.cover_id
                LEFT JOIN magazine m ON i.id = m.icon_id
                LEFT JOIN magazine m2 ON i.id = m2.banner_id
                WHERE u IS NULL AND u2 IS NULL AND m IS NULL AND m2 IS NULL AND i.file_path IS NOT NULL AND i.source_url IS NOT NULL
        ) images WHERE last_active < :date AND (downloaded_at < :date OR downloaded_at IS NULL) GROUP BY id';

        $adapter = new NativeQueryAdapter($this->getEntityManager()->getConnection(), $sql, ['date' => new \DateTimeImmutable("now - $olderThanDays days")], transformer: $this->contentPopulationTransformer);
        $fanta = new Pagerfanta($adapter);
        $fanta->setCurrentPage(1);
        $fanta->setMaxPerPage($limit);

        return $fanta;
    }

    public function redownloadImage(Image $image): void
    {
        if ($image->filePath || !$image->sourceUrl || $image->sourceTooBig) {
            return;
        }

        $tempFilePath = $this->imageManager->download($image->sourceUrl);
        if (null === $tempFilePath) {
            return;
        }

        [$filePath, $fileName] = $this->imageManager->getFilePathAndName($tempFilePath);

        $previousFileSize = filesize($tempFilePath);
        $image->originalSize = $previousFileSize;
        $this->dispatcher->dispatch(new ImagePostProcessEvent($tempFilePath, $filePath, ImageOrigin::External));
        $afterProcessFileSize = filesize($tempFilePath);
        if ($afterProcessFileSize < $previousFileSize) {
            $image->isCompressed = true;
        }

        try {
            if ($this->imageManager->store($tempFilePath, $filePath)) {
                $image->filePath = $filePath;
                $image->localSize = $afterProcessFileSize;
                $image->downloadedAt = new \DateTimeImmutable('now');
            }
        } catch (ImageDownloadTooLargeException) {
            $image->localSize = 0;
            $image->sourceTooBig = true;
        } catch (\Exception) {
        }
    }

    /**
     * @param Image[] $images
     */
    public function redownloadImagesIfNecessary(array $images): void
    {
        foreach ($images as $image) {
            $this->logger->debug('Maybe redownloading images {i}', ['i' => implode(', ', array_map(fn (Image $image) => $image->getId(), $images))]);
            if ($image && null === $image->filePath && !$image->sourceTooBig && $image->sourceUrl) {
                // there is an image, but not locally, and it was not too big, and we have the source URL -> try redownloading it
                $this->redownloadImage($image);
            }
        }
        $this->getEntityManager()->flush();
    }
}
