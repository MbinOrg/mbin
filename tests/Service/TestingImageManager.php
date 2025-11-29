<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Image;
use App\Service\ImageManager;
use App\Service\ImageManagerInterface;
use App\Service\SettingsManager;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[When(env: 'test')]
class TestingImageManager implements ImageManagerInterface
{
    private ImageManager $innerImageManager;
    private string $kibbyPath;

    public function __construct(
        string $storageUrl,
        FilesystemOperator $publicUploadsFilesystem,
        HttpClientInterface $httpClient,
        MimeTypesInterface $mimeTypeGuesser,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        SettingsManager $settings,
    ) {
        $this->innerImageManager = new ImageManager($storageUrl, $publicUploadsFilesystem, $httpClient, $mimeTypeGuesser, $validator, $logger, $settings);
    }

    public function setKibbyPath(string $kibbyPath): void
    {
        $this->kibbyPath = $kibbyPath;
    }

    public function store(string $source, string $filePath): bool
    {
        return $this->innerImageManager->store($source, $filePath);
    }

    public function download(string $url): ?string
    {
        // always return a copy of the kibby image path
        if (!file_exists(\dirname($this->kibbyPath).'/copy')) {
            mkdir(\dirname($this->kibbyPath).'/copy');
        }
        $tmpPath = \dirname($this->kibbyPath).'/copy/'.bin2hex(random_bytes(32)).'.png';
        $srcPath = \dirname($this->kibbyPath).'/'.basename($this->kibbyPath, '.png').'.png';
        if (!file_exists($srcPath)) {
            throw new \Exception('For some reason the kibby image got deleted');
        }
        copy($srcPath, $tmpPath);
        return $tmpPath;
    }

    public function getFilePathAndName(string $file): array
    {
        return $this->innerImageManager->getFilePathAndName($file);
    }

    public function getFilePath(string $file): string
    {
        return $this->innerImageManager->getFilePath($file);
    }

    public function getFileName(string $file): string
    {
        return $this->innerImageManager->getFileName($file);
    }

    public function remove(string $path): void
    {
        $this->innerImageManager->remove($path);
    }

    public function getPath(Image $image): string
    {
        return $this->innerImageManager->getPath($image);
    }

    public function getUrl(?Image $image): ?string
    {
        return $this->innerImageManager->getUrl($image);
    }

    public function getMimetype(Image $image): string
    {
        return $this->innerImageManager->getMimetype($image);
    }
}
