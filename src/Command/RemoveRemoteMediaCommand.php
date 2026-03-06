<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ImageRepository;
use App\Service\ImageManager;
use App\Twig\Runtime\FormattingExtensionRuntime;
use App\Utils\GeneralUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:images:remove-remote',
    description: 'Remove cached remote media',
)]
class RemoveRemoteMediaCommand extends Command
{
    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly ImageManager $imageManager,
        private readonly LoggerInterface $logger,
        private readonly FormattingExtensionRuntime $formatter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Delete media that is older than x days, if you omit this parameter or set it to 0 it will remove all cached remote media');
        $this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'The number of images to handle at once, the higher the number the faster the command, but it also takes more memory', '10000');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do a trial without removing any media');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = \intval($input->getOption('days'));
        if ($days < 0) {
            $io->error('Days must be at least 0');

            return Command::FAILURE;
        }

        GeneralUtil::useProgressbarFormatsWithMessage();

        $dryRun = \boolval($input->getOption('dry-run'));
        $batchSize = \intval($input->getOption('batch-size'));
        $images = $this->imageRepository->findOldRemoteMediaPaginated($days, $batchSize);
        $count = $images->count();
        $progressBar = $io->createProgressBar($count);
        $progressBar->setMessage('');
        $progressBar->start();
        $totalDeletedFiles = 0;
        $totalDeletedSize = 0;

        for ($i = 0; $i < $images->getNbPages(); ++$i) {
            $progressBar->setMessage(\sprintf('Fetching images %s - %s', ($i * $batchSize) + 1, ($i + 1) * $batchSize));
            $progressBar->display();
            foreach ($images->getCurrentPageResults() as $image) {
                $progressBar->advance();
                ++$totalDeletedFiles;
                $totalDeletedSize += $image->localSize;

                if (!$dryRun) {
                    if ($this->imageManager->removeCachedImage($image)) {
                        $progressBar->setMessage(\sprintf('Removed "%s" (%s)', $image->filePath, $image->getId()));
                        $progressBar->display();
                        $this->logger->debug('Removed "{path}" ({id})', ['path' => $image->filePath, 'id' => $image->getId()]);
                    }
                } else {
                    $progressBar->setMessage(\sprintf('Would have removed "%s" (%s)', $image->filePath, $image->getId()));
                    $this->logger->debug('Would have removed "{path}" ({id})', ['path' => $image->filePath, 'id' => $image->getId()]);
                }
            }
            if ($images->hasNextPage()) {
                $images->setCurrentPage($images->getNextPage());
            }
        }
        $io->writeln('');
        if (!$dryRun) {
            $io->success(\sprintf('Removed %s files (~%sB)', $totalDeletedFiles, $this->formatter->abbreviateNumber($totalDeletedSize)));
        } else {
            $io->success(\sprintf('Would have removed %s files (~%sB)', $totalDeletedFiles, $this->formatter->abbreviateNumber($totalDeletedSize)));
        }

        return Command::SUCCESS;
    }
}
