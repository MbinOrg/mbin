<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ImageRepository;
use App\Service\ImageManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:images:remove-orphaned',
    description: 'This command removes orphaned images from your configured filesystem.',
)]
class DeleteOrphanedImagesCommand extends Command
{
    public function __construct(
        private readonly ImageManagerInterface $imageManager,
        private readonly ImageRepository $imageRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'ignored-paths',
                null,
                InputArgument::OPTIONAL,
                'A comma seperated list of paths to be ignored in this process. If the path starts with one of the supplied string it will be skipped. e.g. "/cache"',
                ''
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run, don\'t delete anything')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $totalFiles = 0;
        $totalDeletedSize = 0;
        $totalDeletedImages = 0;
        $errors = 0;
        $dryRun = $input->getOption('dry-run');
        $ignoredPaths = array_filter(
            array_map(fn (string $item) => trim($item), explode(',', $input->getOption('ignored-paths'))),
            fn (string $item) => '' !== $item
        );

        if (\sizeof($ignoredPaths)) {
            $io->info(\sprintf('Ignoring files in: %s', implode(', ', $ignoredPaths)));
        }

        ProgressBar::setFormatDefinition('custom_orphaned', '%current% deleted file(s) | %checked% checked file(s) (in %elapsed%) - %message%');

        $progress = $io->createProgressBar();
        $progress->setFormat('custom_orphaned');
        $progress->setMessage('');
        $progress->start();

        try {
            foreach ($this->imageManager->deleteOrphanedFiles($this->imageRepository, $dryRun, $ignoredPaths) as $file) {
                ++$totalFiles;
                $progress->setMessage($totalFiles.'', 'checked');
                if ($file['deleted']) {
                    if ($file['successful']) {
                        $progress->advance();
                        if ($dryRun) {
                            $progress->setMessage(\sprintf('Would have deleted "%s"', $file['path']));
                        } else {
                            $progress->setMessage(\sprintf('Deleted "%s"', $file['path']));
                        }
                        $progress->display();
                        if ($file['fileSize']) {
                            $totalDeletedSize += $file['fileSize'];
                        }
                        ++$totalDeletedImages;
                    } else {
                        if (null !== $file['exception']) {
                            $io->warning(\sprintf('Failed to delete "%s". Message: "%s"', $file['path'], $file['exception']->getMessage()));
                        } else {
                            $io->warning(\sprintf('Failed to delete "%s".', $file['path']));
                        }
                        ++$errors;
                    }
                }
            }
        } catch (\Exception $e) {
            $progress->finish();
            $io->error(\sprintf('There was an error deleting the files: "%s" - %s', \get_class($e), $e->getMessage()));

            return Command::FAILURE;
        }

        $progress->finish();
        $megaBytes = round($totalDeletedSize / pow(1000, 2), 2);
        if ($dryRun) {
            $io->info(\sprintf('Would have deleted %s of %s images, and freed up %sMB', $totalDeletedImages, $totalFiles, $megaBytes));
        } else {
            $io->info(\sprintf('Deleted %s of %s images, and freed up %sMB', $totalDeletedImages, $totalFiles, $megaBytes));
        }
        if ($errors) {
            $io->warning(\sprintf('There were %s errors', $errors));
        }

        return Command::SUCCESS;
    }
}
