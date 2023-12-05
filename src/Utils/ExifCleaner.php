<?php

declare(strict_types=1);

namespace App\Utils;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ExifCleaner
{
    protected const EXIFTOOL_COMMAND_NAME = 'exiftool';
    protected const EXIFTOOL_ARGS_COMMON = [
        '-overwrite_original', '-ignoreminorerrors',
    ];
    protected const EXIFTOOL_ARGS_SANITIZE = [
        '-GPS*=', '-*Serial*=',
    ];
    protected const EXIFTOOL_ARGS_SCRUB = [
        '-all=',
        '-tagsfromfile', '@',
        '-colorspacetags', '-commonifd0', '-orientation', '-icc_profile',
        '-XMP-dc:all', '-XMP-iptcCore:all', '-XMP-iptcExt:all',
        '-IPTC:all',
    ];
    protected const EXIFTOOL_TIMEOUT_SECONDS = 10;

    private readonly ?string $exiftoolPath;
    private readonly ?string $exiftool;
    private readonly int $timeout;

    public function __construct(
        private readonly ContainerBagInterface $params,
        private readonly LoggerInterface $logger,
    ) {
        $this->exiftoolPath = $params->get('exif_exiftool_path');
        $this->timeout = $params->get('exif_exiftool_timeout') ?? self::EXIFTOOL_TIMEOUT_SECONDS;
        $this->exiftool = $this->getExifToolBinary();
    }

    public function cleanImage(string $filePath, ExifCleanMode $mode)
    {
        if (ExifCleanMode::None === $mode) {
            $this->logger->debug("ExifCleaner:cleanImage: cleaning mode is 'None', nothing will be done.");

            return;
        }

        if (!$this->exiftool) {
            $this->logger->info('ExifCleaner:cleanImage: exiftool binary was not found, nothing will be done.');

            return;
        }

        try {
            $ps = $this->buildProcess($mode, $filePath, $this->exiftool);
            $ps->mustRun();
            $this->logger->debug(
                'ExifCleaner:cleanImage: exiftool success:',
                ['stdout' => $ps->getOutput()],
            );
        } catch (ProcessFailedException $e) {
            $this->logger->warning('ExifCleaner:cleanImage: exiftool failed: '.$e->getMessage());
        }
    }

    private function getExifToolBinary(): ?string
    {
        if ($this->exiftoolPath && is_executable($this->exiftoolPath)) {
            return $this->exiftoolPath;
        }

        $which = new ExecutableFinder();
        $cmdpath = $which->find(self::EXIFTOOL_COMMAND_NAME);

        return $cmdpath;
    }

    private function getCleaningArguments(ExifCleanMode $mode): array
    {
        return match ($mode) {
            ExifCleanMode::None => [],
            ExifCleanMode::Sanitize => self::EXIFTOOL_ARGS_SANITIZE,
            ExifCleanMode::Scrub => self::EXIFTOOL_ARGS_SCRUB,
        };
    }

    private function buildProcess(ExifCleanMode $mode, string $filePath, string $exiftool): Process
    {
        $ps = new Process(array_merge(
            [$exiftool, $filePath],
            self::EXIFTOOL_ARGS_COMMON,
            $this->getCleaningArguments($mode),
        ));
        $ps->setTimeout($this->timeout);

        return $ps;
    }
}
