<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Image as MbinImage;
use App\Exception\CorruptedFileException;
use App\Exception\ImageDownloadTooLargeException;
use App\Repository\ImageRepository;
use App\Twig\Runtime\FormattingExtensionRuntime;
use App\Utils\GeneralUtil;
use Doctrine\ORM\EntityManagerInterface;
use Imagine\Gd\Imagine;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImageManager implements ImageManagerInterface
{
    public const array IMAGE_MIMETYPES = [
        'image/jpeg', 'image/jpg', 'image/gif', 'image/png',
        'image/jxl', 'image/heic', 'image/heif',
        'image/webp', 'image/avif',
    ];
    public const string IMAGE_MIMETYPE_STR = 'image/jpeg, image/jpg, image/gif, image/png, image/jxl, image/heic, image/heif, image/webp, image/avif';

    public function __construct(
        private readonly string $storageUrl,
        private readonly FilesystemOperator $publicUploadsFilesystem,
        private readonly HttpClientInterface $httpClient,
        private readonly MimeTypesInterface $mimeTypeGuesser,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
        private readonly SettingsManager $settings,
        private readonly FormattingExtensionRuntime $formattingExtensionRuntime,
        private readonly float $imageCompressionQuality,
        private readonly CacheManager $imagineCacheManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function isImageUrl(string $url): bool
    {
        $urlExt = mb_strtolower(pathinfo($url, PATHINFO_EXTENSION));

        $types = array_map(fn ($type) => str_replace('image/', '', $type), self::IMAGE_MIMETYPES);

        return \in_array($urlExt, $types);
    }

    public static function isImageType(string $mediaType): bool
    {
        return \in_array($mediaType, self::IMAGE_MIMETYPES);
    }

    /**
     * @throws \Exception if the file could not be found
     */
    public function store(string $source, string $filePath): bool
    {
        $fh = fopen($source, 'rb');

        try {
            if (filesize($source) > $this->settings->getMaxImageBytes()) {
                throw new ImageDownloadTooLargeException('the image is too large, max size is '.$this->settings->getMaxImageBytes());
            }

            $this->validate($source);

            $this->publicUploadsFilesystem->writeStream($filePath, $fh);

            if (!$this->publicUploadsFilesystem->has($filePath)) {
                throw new \Exception('File not found');
            }

            return true;
        } finally {
            \is_resource($fh) and fclose($fh);
        }
    }

    /**
     * Tries to compress an image until its size is smaller than $maxBytes. This overwrites the existing image.
     *
     * @return bool whether the image was compressed
     */
    public function compressUntilSize(string $filePath, string $extension, int $maxBytes): bool
    {
        if (-1 === $this->imageCompressionQuality || filesize($filePath) <= $maxBytes) {
            // don't compress images if disabled or smaller than max bytes
            return false;
        }
        $imagine = new Imagine();
        $image = $imagine->open($filePath);
        $bytes = filesize($filePath);
        $initialBytes = $bytes;
        $tempPath = "{$filePath}_temp_compress.$extension";
        $compressed = false;
        $quality = 0.9;
        if (0.1 <= $this->imageCompressionQuality && 1 > $this->imageCompressionQuality) {
            $quality = $this->imageCompressionQuality;
        }
        while ($bytes > $maxBytes && $quality > 0.1) {
            $this->logger->debug('[ImageManager::compressUntilSize] Trying to compress "{path}" with {q}% quality', ['path' => $tempPath, 'q' => $quality * 100]);
            $image->save($tempPath, [
                'jpeg_quality' => $quality * 100, // jpeg max value is 100
                'png_compression_level' => \intval((1 - $quality) * 9), // png max is 9, but it is not quality, but compression
                'webp_quality' => $quality * 100, // webp quality max is 100
            ]);
            $bytes = filesize($tempPath);
            if ($initialBytes === $bytes) {
                // there were no changes, so maybe it is in a format that cannot be compressed...
                break;
            }
            $compressed = true;
            $quality -= 0.05;
        }
        $copied = false;
        if ($compressed) {
            if (copy($tempPath, $filePath)) {
                $copied = true;
                $this->logger->debug('[ImageManager::compressUntilSize] successfully compressed "{path}" with {q}% quality: {bytesBefore} -> {bytesNow}', [
                    'path' => $filePath,
                    'q' => ($quality + 0.05) * 100, // re-add the last step, because it is always subtracted in the end if successful
                    'bytesBefore' => $this->formattingExtensionRuntime->abbreviateNumber($initialBytes).'B',
                    'bytesNow' => $this->formattingExtensionRuntime->abbreviateNumber($bytes).'B',
                ]);
            }
        }
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        return $compressed && $copied;
    }

    private function validate(string $filePath): bool
    {
        $violations = $this->validator->validate(
            $filePath,
            [
                new Image(detectCorrupted: true),
            ]
        );

        if (\count($violations) > 0) {
            throw new CorruptedFileException();
        }

        return true;
    }

    public function download(string $url): ?string
    {
        $tempFile = @tempnam('/', 'kbin');

        if (false === $tempFile) {
            throw new UnrecoverableMessageHandlingException('Couldn\'t create temporary file');
        }

        $fh = fopen($tempFile, 'wb');

        try {
            $response = $this->httpClient->request(
                'GET',
                $url,
                [
                    'timeout' => 5,
                    'headers' => [
                        'Accept' => implode(', ', array_diff(self::IMAGE_MIMETYPES, ['image/webp', 'image/avif'])),
                    ],
                ]
            );

            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($fh, $chunk->getContent());
            }

            fclose($fh);

            $this->validate($tempFile);
            $this->logger->debug('downloaded file from {url}', ['url' => $url]);
        } catch (\Exception $e) {
            if ($fh && \is_resource($fh)) {
                fclose($fh);
            }
            unlink($tempFile);
            $this->logger->warning("couldn't download file from {url}", ['url' => $url]);

            return null;
        }

        return $tempFile;
    }

    /**
     * @return array{string, string}
     */
    public function getFilePathAndName(string $file): array
    {
        $name = $this->getFileName($file);
        $path = $this->getFilePathFromName($name);

        return [$path, $name];
    }

    public function getFilePath(string $file): string
    {
        $name = $this->getFileName($file);

        return $this->getFilePathFromName($name);
    }

    private function getFilePathFromName(string $name): string
    {
        return \sprintf(
            '%s/%s/%s',
            substr($name, 0, 2),
            substr($name, 2, 2),
            $name
        );
    }

    public function getFileName(string $file): string
    {
        $hash = hash_file('sha256', $file);
        $mimeType = $this->mimeTypeGuesser->guessMimeType($file);

        if (!$mimeType) {
            throw new \RuntimeException("Couldn't guess MIME type of image");
        }

        $ext = $this->mimeTypeGuesser->getExtensions($mimeType)[0] ?? null;

        if (!$ext) {
            throw new \RuntimeException("Couldn't guess extension of image (invalid image?)");
        }

        return \sprintf('%s.%s', $hash, $ext);
    }

    public function remove(string $path): void
    {
        $this->publicUploadsFilesystem->delete($path);
        $this->imagineCacheManager->remove($path);
    }

    public function getPath(MbinImage $image): string
    {
        return $this->publicUploadsFilesystem->read($image->filePath);
    }

    public function getUrl(?MbinImage $image): ?string
    {
        if (!$image) {
            return null;
        }

        if ($image->filePath) {
            return $this->storageUrl.'/'.$image->filePath;
        }

        return $image->sourceUrl;
    }

    public function getMimetype(MbinImage $image): string
    {
        try {
            return $this->publicUploadsFilesystem->mimeType($image->filePath);
        } catch (\Throwable $e) {
            return 'none';
        }
    }

    public function deleteOrphanedFiles(ImageRepository $repository, bool $dryRun, array $ignoredPaths): iterable
    {
        foreach ($this->deleteOrphanedFilesIntern($repository, $dryRun, $ignoredPaths, '/') as $deletedPath) {
            yield $deletedPath;
        }
    }

    /**
     * @return iterable<array{path: string, internalPath: string, deleted: bool, successful: bool, fileSize: ?int, exception: ?\Throwable} the deleted files/directories
     *
     * @throws FilesystemException
     */
    private function deleteOrphanedFilesIntern(ImageRepository $repository, bool $dryRun, array $ignoredPaths, string $path): iterable
    {
        $contents = $this->publicUploadsFilesystem->listContents($path, deep: true);
        foreach ($contents as $content) {
            if (GeneralUtil::shouldPathBeIgnored($ignoredPaths, $content->path())) {
                continue;
            }

            if ($content->isFile() && $content instanceof FileAttributes) {
                [$internalImagePath, $fileName] = $this->getInternalImagePathAndName($content);
                $image = $repository->findOneBy(['fileName' => $fileName, 'filePath' => $internalImagePath]);
                if (!$image) {
                    try {
                        if (!$dryRun) {
                            $this->publicUploadsFilesystem->delete($content->path());
                        }
                        yield [
                            'path' => $content->path(),
                            'internalPath' => $internalImagePath,
                            'deleted' => true,
                            'successful' => true,
                            'fileSize' => $content->fileSize(),
                            'exception' => null,
                        ];
                    } catch (\Throwable $e) {
                        yield [
                            'path' => $content->path(),
                            'internalPath' => $internalImagePath,
                            'deleted' => true,
                            'successful' => false,
                            'fileSize' => $content->fileSize(),
                            'exception' => $e,
                        ];
                    }
                } else {
                    yield [
                        'path' => $content->path(),
                        'internalPath' => $internalImagePath,
                        'deleted' => false,
                        'successful' => true,
                        'fileSize' => $content->fileSize(),
                        'exception' => null,
                    ];
                }
            } elseif ($content->isDir()) {
                foreach ($this->deleteOrphanedFilesIntern($repository, $dryRun, $ignoredPaths, $content->path()) as $file) {
                    yield $file;
                }
            }
        }
    }

    /**
     * @return array{0: string, 1: string} 0=path 1=name
     */
    private function getInternalImagePathAndName(StorageAttributes $flySystemFile): array
    {
        if (!$flySystemFile->isFile()) {
            $parts = explode('/', $flySystemFile->path());

            return [$flySystemFile->path(), end($parts)];
        }

        $path = $flySystemFile->path();
        if (str_starts_with($path, '/')) {
            $path = substr($path, 1);
        }

        if (str_starts_with($path, 'cache')) {
            $parts = explode('/', $path);
            $newParts = \array_slice($parts, 2);
            $path = implode('/', $newParts);

            $doubleExtensions = ['jpg', 'jpeg', 'gif', 'png', 'webp'];
            foreach ($doubleExtensions as $extension) {
                if (str_ends_with($path, ".$extension.webp")) {
                    $path = str_replace(".$extension.webp", ".$extension", $path);
                    break;
                }
            }
        }
        $parts = explode('/', $path);

        return [$path, end($parts)];
    }

    public function removeCachedImage(MbinImage $image): bool
    {
        if (!$image->filePath || !$image->sourceUrl) {
            return false;
        }

        try {
            $this->publicUploadsFilesystem->delete($image->filePath);
            $this->imagineCacheManager->remove($image->filePath);
            $image->filePath = null;
            $image->downloadedAt = null;
            $this->entityManager->persist($image);
            $this->entityManager->flush();

            return true;
        } catch (\Exception|FilesystemException $e) {
            $this->logger->error('Unable to remove cached images for "{path}": {ex} - {m}', [
                'path' => $image->filePath,
                'ex' => \get_class($e),
                'm' => $e->getMessage(),
                'exception' => $e,
            ]);

            return false;
        }
    }
}
