<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ImageRepository;
use App\Utils\GeneralUtil;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:images:refresh-meta',
    description: 'Refresh meta information about your media',
)]
class RefreshImageMetaDataCommand extends Command
{
    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly FilesystemOperator $publicUploadsFilesystem,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'The number of images to handle at once, the higher the number the faster the command, but it also takes more memory', '10000');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do a trial without removing any media');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        GeneralUtil::useProgressbarFormatsWithMessage();

        $dryRun = \boolval($input->getOption('dry-run'));
        $batchSize = \intval($input->getOption('batch-size'));
        $images = $this->imageRepository->findSavedImagesPaginated($batchSize);
        $count = $images->count();
        $progressBar = $io->createProgressBar($count);
        $progressBar->setMessage('');
        $progressBar->start();
        $totalCheckedFiles = 0;
        $totalUpdateFiles = 0;

        for ($i = 0; $i < $images->getNbPages(); ++$i) {
            $progressBar->setMessage(\sprintf('Fetching images %s - %s', ($i * $batchSize) + 1, ($i + 1) * $batchSize));
            $progressBar->display();
            foreach ($images->getCurrentPageResults() as $image) {
                $progressBar->advance();
                ++$totalCheckedFiles;

                try {
                    if ($this->publicUploadsFilesystem->has($image->filePath)) {
                        ++$totalUpdateFiles;
                        $fileSize = $this->publicUploadsFilesystem->fileSize($image->filePath);
                        if (!$dryRun) {
                            $image->localSize = $fileSize;
                            $progressBar->setMessage(\sprintf('Refreshed meta data of "%s" (%s)', $image->filePath, $image->getId()));
                            $this->logger->debug('Refreshed meta data of "{path}" ({id})', ['path' => $image->filePath, 'id' => $image->getId()]);
                        } else {
                            $progressBar->setMessage(\sprintf('Would have refreshed meta data of "%s" (%s)', $image->filePath, $image->getId()));
                        }
                        $progressBar->display();
                    } else {
                        $previousPath = $image->filePath;
                        // mark it as not present on the media storage
                        if (!$dryRun) {
                            $image->filePath = null;
                            $image->localSize = 0;
                            $image->downloadedAt = null;
                            $progressBar->setMessage(\sprintf('Marked "%s" (%s) as not present on the media storage', $previousPath, $image->getId()));
                        } else {
                            $progressBar->setMessage(\sprintf('Would have marked "%s" (%s) as not present on the media storage', $image->filePath, $image->getId()));
                        }
                        $progressBar->display();
                    }
                } catch (FilesystemException $e) {
                    $this->logger->error('There was an exception refreshing the meta data of "{path}" ({id}): {exClass} - {message}', [
                        'path' => $image->filePath,
                        'id' => $image->getId(),
                        'exClass' => \get_class($image),
                        'message' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                    $progressBar->setMessage(\sprintf('Error checking meta data of "%s" (%s)', $image->filePath, $image->getId()));
                    $progressBar->display();
                }
            }
            if (!$dryRun) {
                $this->entityManager->flush();
            }
            if ($images->hasNextPage()) {
                $images->setCurrentPage($images->getNextPage());
            }
        }
        $io->writeln('');
        if (!$dryRun) {
            $io->success(\sprintf('Refreshed %s files', $totalUpdateFiles));
        } else {
            $io->success(\sprintf('Would have refreshed %s files', $totalUpdateFiles));
        }

        return Command::SUCCESS;
    }
}
