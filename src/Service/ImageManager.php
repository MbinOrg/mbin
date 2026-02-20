<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\CorruptedFileException;
use App\Exception\ImageDownloadTooLargeException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[WhenNot(env: 'test')]
class ImageManager implements ImageManagerInterface
{
    public const IMAGE_MIMETYPES = [
        'image/jpeg', 'image/jpg', 'image/gif', 'image/png',
        'image/jxl', 'image/heic', 'image/heif',
        'image/webp', 'image/avif',
    ];
    public const IMAGE_MIMETYPE_STR = 'image/jpeg, image/jpg, image/gif, image/png, image/jxl, image/heic, image/heif, image/webp, image/avif';

    public function __construct(
        private readonly string $storageUrl,
        private readonly FilesystemOperator $publicUploadsFilesystem,
        private readonly HttpClientInterface $httpClient,
        private readonly MimeTypesInterface $mimeTypeGuesser,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
        private readonly SettingsManager $settings,
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

    private function validate(string $filePath): bool
    {
        $violations = $this->validator->validate(
            $filePath,
            [
                new Image(['detectCorrupted' => true]),
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
    }

    public function getPath(\App\Entity\Image $image): string
    {
        return $this->publicUploadsFilesystem->read($image->filePath);
    }

    public function getUrl(?\App\Entity\Image $image): ?string
    {
        if (!$image) {
            return null;
        }

        if ($image->filePath) {
            return $this->storageUrl.'/'.$image->filePath;
        }

        return $image->sourceUrl;
    }

    public function getMimetype(\App\Entity\Image $image): string
    {
        try {
            return $this->publicUploadsFilesystem->mimeType($image->filePath);
        } catch (\Throwable $e) {
            return 'none';
        }
    }
}
