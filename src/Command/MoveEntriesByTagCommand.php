<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\EntryComment;
use App\Entity\Favourite;
use App\Entity\Magazine;
use App\Entity\Report;
use App\Repository\EntryRepository;
use App\Repository\MagazineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:entries:move',
    description: 'This command allows you to move entries to a new magazine based on their tag.'
)]
class MoveEntriesByTagCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MagazineRepository $magazineRepository,
        private readonly EntryRepository $entryRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('magazine', InputArgument::REQUIRED)
            ->addArgument('tag', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $magazine = $this->magazineRepository->findOneByName($input->getArgument('magazine'));
        $tag = $input->getArgument('tag');

        if (!$magazine) {
            $io->error('The magazine does not exist.');

            return Command::FAILURE;
        }

        $entries = $this->entryRepository->createQueryBuilder('e')
            ->where('t.tag = :tag')
            ->join('e.hashtags', 'h')
            ->join('h.hashtag', 't')
            ->setParameter('tag', $tag)
            ->getQuery()
            ->getResult();

        foreach ($entries as $entry) {
            /*
             * @var Entry $entry
             */
            $entry->magazine = $magazine;

            $this->moveComments($entry->comments, $magazine);
            $this->moveReports($entry->reports, $magazine);
            $this->moveFavourites($entry->favourites, $magazine);
            $entry->badges->clear();

            $tags = array_diff($entry->tags, [$tag]);
            $entry->tags = \count($tags) ? array_values($tags) : null;

            $this->entityManager->persist($entry);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    /**
     * @param ArrayCollection<int, EntryComment>|Collection<int, EntryComment> $comments
     */
    private function moveComments(ArrayCollection|Collection $comments, Magazine $magazine): void
    {
        foreach ($comments as $comment) {
            /*
             * @var EntryComment $comment
             */
            $comment->magazine = $magazine;

            $this->moveReports($comment->reports, $magazine);
            $this->moveFavourites($comment->favourites, $magazine);

            $this->entityManager->persist($comment);
        }
    }

    /**
     * @param ArrayCollection<int, Report>|Collection<int, Report> $reports
     */
    private function moveReports(ArrayCollection|Collection $reports, Magazine $magazine): void
    {
        foreach ($reports as $report) {
            /*
             * @var Report $report
             */
            $report->magazine = $magazine;

            $this->entityManager->persist($report);
        }
    }

    /**
     * @param ArrayCollection<int, Favourite>|Collection<int, Favourite> $favourites
     */
    private function moveFavourites(ArrayCollection|Collection $favourites, Magazine $magazine): void
    {
        foreach ($favourites as $favourite) {
            /*
             * @var Favourite $favourite
             */
            $favourite->magazine = $magazine;

            $this->entityManager->persist($favourite);
        }
    }
}
