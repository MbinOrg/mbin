<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ImageRepository;
use App\Service\ImageManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
            ->addOption('delete-empty-directories', null, InputOption::VALUE_NONE, 'Delete empty directories, this can cause the operation to take a lot longer')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->progressStart();
        $totalDeletedSize = 0;
        $totalDeletedImages = 0;
        $errors = 0;
        $dryRun = $input->getOption('dry-run');
        $deleteEmptyDirectories = $input->getOption('delete-empty-directories');
        $ignoredPaths = array_filter(
            array_map(fn (string $item) => trim($item), explode(',', $input->getOption('ignored-paths'))),
            fn (string $item) => '' !== $item
        );

        $io->info(\sprintf('Ignoring files in: %s', implode(', ', $ignoredPaths)));

        try {
            foreach ($this->imageManager->deleteOrphanedFiles($this->imageRepository, $dryRun, $deleteEmptyDirectories, $ignoredPaths) as $deletedImage) {
                if ($deletedImage['successful']) {
                    $io->progressAdvance();
                    if ($dryRun) {
                        $io->text(\sprintf('Would have deleted "%s"', $deletedImage['path']));
                    } else {
                        $io->text(\sprintf('Deleted "%s"', $deletedImage['path']));
                    }
                    if ($deletedImage['fileSize']) {
                        $totalDeletedSize += $deletedImage['fileSize'];
                    }
                    ++$totalDeletedImages;
                } else {
                    if (null !== $deletedImage['exception']) {
                        $io->warning(\sprintf('Failed to delete "%s". Message: "%s"', $deletedImage['path'], $deletedImage['exception']->getMessage()));
                    } else {
                        $io->warning(\sprintf('Failed to delete "%s".', $deletedImage['path']));
                    }
                    ++$errors;
                }
            }
        } catch (\Exception $e) {
            $io->progressFinish();
            $io->error(\sprintf('There was an error deleting the files: "%s" - %s', \get_class($e), $e->getMessage()));

            return Command::FAILURE;
        }

        $io->progressFinish();
        $megaBytes = round($totalDeletedSize / pow(1000, 2), 2);
        if ($dryRun) {
            $io->info(\sprintf('Would have deleted %s images, and freed up %sMB', $totalDeletedImages, $megaBytes));
        } else {
            $io->info(\sprintf('Deleted %s images, and freed up %sMB', $totalDeletedImages, $megaBytes));
        }
        if ($errors) {
            $io->warning(\sprintf('There were %s errors', $errors));
        }

        return Command::SUCCESS;
    }
}
