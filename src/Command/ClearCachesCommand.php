<?php
declare(strict_types=1);

namespace App\Command;

use League\Flysystem\Filesystem;
use League\Flysystem\StorageAttributes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:cache:clear',
    description: 'Clear a selection of caches.',
)]
class ClearCachesCommand extends Command
{
    private const array SUPPORTED_CACHES = [
        'redis',
        'images',
    ];

    private bool $verbose = false;
    private bool $dryRun = false;
    private SymfonyStyle $io;

    public function __construct(
        private readonly Filesystem $files,
        private readonly \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface $redis,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Clear a selection of caches.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run, don\'t delete anything.')
            ->addOption('show', 's', InputOption::VALUE_NONE, 'Show everything what is being deleted.')
            ->addArgument('caches', InputOption::VALUE_OPTIONAL, 'Caches to clear, separated by space. Allowed values are: '.implode(', ', self::SUPPORTED_CACHES), ['redis']);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->verbose = $input->getOption('show');
        $this->dryRun = $input->getOption('dry-run');

        $toClear = $input->getArgument('caches');
        if ($diff = array_diff($toClear, self::SUPPORTED_CACHES)) {
            $this->io->error('The following cache types are not supported: '.implode(' ', $diff));
            return 1;
        }
        if ([] === $toClear) {
            $toClear = ['redis'];
        }

        $success = true;
        foreach ($toClear as $cache) {
            switch ($cache) {
                case 'redis':
                    $success &= $this->clearRedis();
                    break;
                case 'images':
                    $success &= $this->clearImages();
                    break;
            }
        }

        if ($success) {
            $this->io->success('All requested caches have been cleared.');
            return 0;
        } else {
            $this->io->error('Not all requested caches could be cleared.');
            return 2;
        }
    }

    private function clearRedis(): bool
    {
        $this->io->section('Clearing Redis');

        try {
            $keys = $this->redis->keys('sf_s*');
            if ($this->verbose) {
                $this->io->text('Keys to delete: '.implode(', ', $keys));
            }
            if (!$this->dryRun) {
                $this->redis->del($keys);
            }
        } catch (\Throwable $e) {
            $this->io->error(\get_class($e).': '.$e->getMessage());
            return false;
        }

        return true;
    }

    private function clearImages(): bool
    {
        $this->io->section('Clearing images');

        try {
            $cacheFiles = $this->files->listContents('cache', true);
            foreach ($cacheFiles as $file) {
                /** @var StorageAttributes $file */
                if (!$file->isFile()) {
                    continue;
                }
                if ($this->verbose) {
                    $this->io->text('Deleting '.$file->path());
                }
                if (!$this->dryRun) {
                    $this->files->delete($file->path());
                }
            }
        } catch (\Throwable $e) {
            $this->io->error(\get_class($e).': '.$e->getMessage());
            return false;
        }

        return true;
    }
}
