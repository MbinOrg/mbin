<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Image;
use App\Service\PostManager;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:images:delete',
    description: 'This command allows you to delete images from old federated content.'
)]
class RemoveOldImagesCommand extends Command
{
    private int $batchSize = 25;
    private int $monthsAgo = 3;
    private bool $noActivity = false;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PostManager $postManager,
        private readonly UserManager $userManager
    ) {
        parent::__construct();
    }

    public function configure()
    {
        $this
            ->addArgument('type', InputArgument::OPTIONAL, 'Type of images to delete either posts or users', 'posts')
            ->addArgument('monthsAgo', InputArgument::OPTIONAL, 'Delete images older than x months', 3)
            ->addOption('noActivity', null, InputOption::VALUE_OPTIONAL, 'Delete image that doesn\'t have recorded activity (comments, upvotes, boosts)', false)
            ->addOption('batchSize', null, InputOption::VALUE_OPTIONAL, 'Number of images to delete at a time', 25);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = $input->getArgument('type');
        $this->monthsAgo = (int) $input->getArgument('monthsAgo');
        if ($input->getOption('noActivity')) {
            $this->noActivity = (bool) $input->getOption('noActivity');
        }
        $this->batchSize = (int) $input->getOption('batchSize');

        if ('posts' === $type) {
            $this->deletePostsImages($output);
        } elseif ('users' === $type) {
            $this->deleteUsersImages();
        } else {
            $io->error('Invalid type of images to delete. Try \'posts\' or \'users\'.');

            return Command::FAILURE;
        }

        $this->entityManager->clear();

        return Command::SUCCESS;
    }

    private function deletePostsImages(OutputInterface $output): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $timeAgo = new \DateTime("-{$this->monthsAgo} months");

        $query = $queryBuilder
            ->select('p')
            ->from('App\Entity\Post', 'p')
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->lt('p.createdAt', ':timeAgo'),
                    $queryBuilder->expr()->neq('i.id', 1),
                    $queryBuilder->expr()->isNotNull('p.apId'),
                    $this->all ? null : $queryBuilder->expr()->eq('p.upVotes', 0),
                    $this->all ? null : $queryBuilder->expr()->eq('p.commentCount', 0),
                    $this->all ? null : $queryBuilder->expr()->isNull('p.tags'),
                    $this->all ? null : $queryBuilder->expr()->eq('p.favouriteCount', 0),
                    $this->all ? null : $queryBuilder->expr()->isNotNull('p.image')
                )
            )
            ->leftJoin('p.image', 'i')
            ->orderBy('p.id', 'ASC')
            ->setParameter('timeAgo', $timeAgo)
            ->setMaxResults($this->batchSize)
            ->getQuery();

        $posts = $query->getResult();

        $output->writeln(sprintf('Found %d posts to delete', \count($posts)));

        foreach ($posts as $post) {
            $output->writeln(sprintf('Deleting post %d', $post->id));
        }
        /*

        $placeholder = $this->entityManager->getRepository(Image::class)->find(1);

        foreach ($posts as $post) {
            $this->postManager->detachImage($post);
            $post->image = $placeholder;
            $this->entityManager->flush();
        }
        */
    }

    private function deleteUsersImages()
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $timeAgo = new \DateTime("-{$this->monthsAgo} months");

        $query = $queryBuilder
            ->select('u')
            ->from('App\Entity\User', 'u')
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->isNotNull('u.avatar'),
                        $queryBuilder->expr()->isNotNull('u.cover')
                    ),
                    $queryBuilder->expr()->lt('u.apFetchedAt', ':timeAgo'),
                    $queryBuilder->expr()->isNotNull('u.apId')
                )
            )
            ->orderBy('u.apFetchedAt', 'ASC')
            ->setParameter('timeAgo', $timeAgo)
            ->setMaxResults($this->batchSize)
            ->getQuery();

        $users = $query->getResult();

        foreach ($users as $user) {
            $this->userManager->detachCover($user);
            $this->userManager->detachAvatar($user);
        }
    }
}
