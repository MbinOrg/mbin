<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SettingsManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:db:migrate-search-lang',
    description: 'Migrates all ts_vector columns to the current language',
)]
class MigrateDbTsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsManager $settingsManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $lang = $this->settingsManager->getSearchLang();

        if (!$this->checkLanguage($lang)) {
            $io->error("the language '$lang' is not supported by the database");

            return Command::FAILURE;
        }
        $io->info("migrating ts_vectors to '$lang'");

        $conn = $this->entityManager->getConnection();
        $this->recreateColumn($conn, 'entry', 'title_ts', 'title', 'entry_title_ts_idx', $lang, $io);
        $this->recreateColumn($conn, 'entry', 'body_ts', 'body', 'entry_body_ts_idx', $lang, $io);
        $this->recreateColumn($conn, 'post', 'body_ts', 'body', 'post_body_ts_idx', $lang, $io);
        $this->recreateColumn($conn, 'post_comment', 'body_ts', 'body', 'post_comment_body_ts_idx', $lang, $io);
        $this->recreateColumn($conn, 'entry_comment', 'body_ts', 'body', 'entry_comment_body_ts_idx', $lang, $io);
        $this->recreateColumn($conn, 'magazine', 'name_ts', 'name', 'magazine_name_ts', $lang, $io);
        $this->recreateColumn($conn, 'magazine', 'title_ts', 'title', 'magazine_title_ts', $lang, $io);
        $this->recreateColumn($conn, 'magazine', 'description_ts', 'description', 'magazine_description_ts', $lang, $io);
        $this->recreateColumn($conn, '"user"', 'username_ts', 'username', 'user_username_ts', $lang, $io);
        $this->recreateColumn($conn, '"user"', 'title_ts', 'title', 'user_title_ts', $lang, $io);
        $this->recreateColumn($conn, '"user"', 'about_ts', 'about', 'user_about_ts', $lang, $io);

        $io->success('done');

        return Command::SUCCESS;
    }

    private function checkLanguage(string $lang): bool
    {
        $conn = $this->entityManager->getConnection();
        $supportedLanguages = $conn->executeQuery('SELECT cfgname FROM pg_ts_config;')->fetchFirstColumn();

        return \in_array($lang, $supportedLanguages, true);
    }

    private function recreateColumn(Connection $conn, string $table, string $column, string $srcColumn, string $idxName, string $lang, SymfonyStyle $io): void
    {
        $conn->executeStatement("DROP INDEX $idxName;");
        $conn->executeStatement("ALTER TABLE $table DROP COLUMN $column;");
        $conn->executeStatement("ALTER TABLE $table ADD COLUMN $column tsvector GENERATED ALWAYS AS (to_tsvector('$lang', $srcColumn)) STORED;");
        $conn->executeStatement("CREATE INDEX $idxName ON $table USING GIN ($column);");

        $io->writeln("$table.$column");
    }
}
