<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Image;
use App\Event\ImagePostProcessEvent;
use App\Exception\ImageDownloadTooLargeException;
use App\Service\ImageManager;
use App\Utils\ImageOrigin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
        private readonly ImageManager $imageManager,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
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
        $fileName = $this->imageManager->getFileName($source);
        $filePath = $this->imageManager->getFilePath($source);
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

        $this->dispatcher->dispatch(new ImagePostProcessEvent($source, $origin));

        try {
            $this->imageManager->store($source, $filePath);

            return $image;
        } catch (ImageDownloadTooLargeException $e) {
            if (ImageOrigin::External === $origin) {
                $this->logger->warning(
                    'findOrCreateFromSource: failed to store image file, because it is too big. Storing only a reference',
                    ['origin' => $origin, 'type' => \gettype($e)],
                );
                $image->filePath = null;

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
}
