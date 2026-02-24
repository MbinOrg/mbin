<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ImageRepository;
use League\Flysystem\FilesystemException;

interface ImageManagerInterface
{
    /**
     * @throws \Exception if the file could not be found
     */
    public function store(string $source, string $filePath): bool;

    public function download(string $url): ?string;

    /**
     * @return array{string, string}
     */
    public function getFilePathAndName(string $file): array;

    public function getFilePath(string $file): string;

    public function getFileName(string $file): string;

    public function remove(string $path): void;

    public function getPath(\App\Entity\Image $image): string;

    public function getUrl(?\App\Entity\Image $image): ?string;

    public function getMimetype(\App\Entity\Image $image): string;

    /**
     * @param string[] $ignoredPaths
     *
     * @return iterable<array{path: string, internalPath: string, successful: bool, fileSize: ?int, exception: ?\Throwable} the deleted files/directories
     *
     * @throws FilesystemException
     */
    public function deleteOrphanedFiles(ImageRepository $repository, bool $dryRun, bool $deleteEmptyDirectories, array $ignoredPaths): iterable;
}
