<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Service\EntryCommentManager;
use App\Service\EntryManager;
use App\Service\PostCommentManager;
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
    description: 'This command allows you to delete images from (old) federated content.'
)]
class RemoveOldImagesCommand extends Command
{
    private int $batchSize = 800;
    private int $monthsAgo = 12;
    private bool $noActivity = false;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntryManager $entryManager,
        private readonly EntryCommentManager $entryCommentManager,
        private readonly PostManager $postManager,
        private readonly PostCommentManager $postCommentManager,
        private readonly UserManager $userManager,
    ) {
        parent::__construct();
    }

    public function configure()
    {
        $this
            ->addArgument('type', InputArgument::OPTIONAL, 'Type of images to delete either: "all" (except for users), "threads", "thread_comments", "posts", "post_comments" or "users"', 'all')
            ->addArgument('monthsAgo', InputArgument::OPTIONAL, 'Delete images older than x months', $this->monthsAgo)
            ->addOption('noActivity', null, InputOption::VALUE_OPTIONAL, 'Delete image that doesn\'t have recorded activity (comments, upvotes, boosts)', false)
            ->addOption('batchSize', null, InputOption::VALUE_OPTIONAL, 'Number of images to delete at a time (for each type)', $this->batchSize);
    }

    /**
     * Starting point, switch what image will get deleted based on the type input arg.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = $input->getArgument('type');
        $this->monthsAgo = (int) $input->getArgument('monthsAgo');
        if ($input->getOption('noActivity')) {
            $this->noActivity = (bool) $input->getOption('noActivity');
        }
        $this->batchSize = (int) $input->getOption('batchSize');

        if ('all' === $type) {
            $nrDeletedImages = $this->deleteAllImages($output); // Except for user avatars and covers
        } elseif ('threads' === $type) {
            $nrDeletedImages = $this->deleteThreadsImages($output);
        } elseif ('thread_comments' === $type) {
            $nrDeletedImages = $this->deleteThreadCommentsImages($output);
        } elseif ('posts' === $type) {
            $nrDeletedImages = $this->deletePostsImages($output);
        } elseif ('post_comments' === $type) {
            $nrDeletedImages = $this->deletePostCommentsImages($output);
        } elseif ('users' === $type) {
            $nrDeletedImages = $this->deleteUsersImages($output);
        } else {
            $io->error('Invalid type of images to delete. Try \'all\', \'threads\', \'thread_comments\', \'posts\', \'post_comments\' or \'users\'.');

            return Command::FAILURE;
        }

        $this->entityManager->clear();

        $output->writeln(''); // New line
        $output->writeln(\sprintf('Total images deleted during this run: %d', $nrDeletedImages));

        return Command::SUCCESS;
    }

    /**
     * Call all delete methods below, _except_ for the delete users images.
     * Since users on the instance can be several years old and not getting fetched,
     * however we shouldn't remove their avatar/cover images just like that.
     *
     * @return number Total number of removed records from database
     */
    private function deleteAllImages($output): int
    {
        $threadsImagesRemoved = $this->deleteThreadsImages($output);
        $threadCommentsImagesRemoved = $this->deleteThreadCommentsImages($output);
        $postsImagesRemoved = $this->deletePostsImages($output);
        $postCommentsImagesRemoved = $this->deletePostCommentsImages($output);

        return $threadsImagesRemoved + $threadCommentsImagesRemoved + $postsImagesRemoved + $postCommentsImagesRemoved;
    }

    /**
     * Delete thread images, check on created_at database column for the age.
     * Limit by batch size.
     *
     * @return number Number of removed records from database
     */
    private function deleteThreadsImages(OutputInterface $output): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $timeAgo = new \DateTime("-{$this->monthsAgo} months");

        $query = $queryBuilder
            ->select('e')
            ->from(Entry::class, 'e')
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->lt('e.createdAt', ':timeAgo'),
                    $queryBuilder->expr()->neq('i.id', 1),
                    $queryBuilder->expr()->isNotNull('e.apId'),
                    $this->noActivity ? $queryBuilder->expr()->eq('e.upVotes', 0) : null,
                    $this->noActivity ? $queryBuilder->expr()->eq('e.commentCount', 0) : null,
                    $this->noActivity ? $queryBuilder->expr()->eq('e.favouriteCount', 0) : null
                )
            )
            ->innerJoin('e.image', 'i')
            ->orderBy('e.id', 'ASC')
            ->setParameter('timeAgo', $timeAgo)
            ->setMaxResults($this->batchSize)
            ->getQuery();

        $entries = $query->getResult();

        foreach ($entries as $entry) {
            $output->writeln(\sprintf('Deleting image from thread ID: %d, with ApId: %s', $entry->getId(), $entry->getApId()));
            $this->entryManager->detachImage($entry);
        }

        // Return total number of elements deleted
        return \count($entries);
    }

    /**
     * Delete thread comment images, check on created_at database column for the age.
     * Limit by batch size.
     *
     * @return number Number of removed records from database
     */
    private function deleteThreadCommentsImages(OutputInterface $output): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $timeAgo = new \DateTime("-{$this->monthsAgo} months");

        $query = $queryBuilder
            ->select('c')
            ->from(EntryComment::class, 'c')
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->lt('c.createdAt', ':timeAgo'),
                    $queryBuilder->expr()->neq('i.id', 1),
                    $queryBuilder->expr()->isNotNull('c.apId'),
                    $this->noActivity ? $queryBuilder->expr()->eq('c.upVotes', 0) : null,
                    $this->noActivity ? $queryBuilder->expr()->eq('c.favouriteCount', 0) : null
                )
            )
            ->innerJoin('c.image', 'i')
            ->orderBy('c.id', 'ASC')
            ->setParameter('timeAgo', $timeAgo)
            ->setMaxResults($this->batchSize)
            ->getQuery();

        $comments = $query->getResult();

        foreach ($comments as $comment) {
            $output->writeln(\sprintf('Deleting image from thread comment ID: %d, with ApId: %s', $comment->getId(), $comment->getApId()));
            $this->entryCommentManager->detachImage($comment);
        }

        // Return total number of elements deleted
        return \count($comments);
    }

    /**
     * Delete post images, check on created_at database column for the age.
     * Limit by batch size.
     *
     * @return number Number of removed records from database
     */
    private function deletePostsImages(OutputInterface $output): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $timeAgo = new \DateTime("-{$this->monthsAgo} months");

        $query = $queryBuilder
            ->select('p')
            ->from(Post::class, 'p')
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->lt('p.createdAt', ':timeAgo'),
                    $queryBuilder->expr()->neq('i.id', 1),
                    $queryBuilder->expr()->isNotNull('p.apId'),
                    $this->noActivity ? $queryBuilder->expr()->eq('p.upVotes', 0) : null,
                    $this->noActivity ? $queryBuilder->expr()->eq('p.commentCount', 0) : null,
                    $this->noActivity ? $queryBuilder->expr()->eq('p.favouriteCount', 0) : null
                )
            )
            ->innerJoin('p.image', 'i')
            ->orderBy('p.id', 'ASC')
            ->setParameter('timeAgo', $timeAgo)
            ->setMaxResults($this->batchSize)
            ->getQuery();

        $posts = $query->getResult();

        foreach ($posts as $post) {
            $output->writeln(\sprintf('Deleting image from post ID: %d, with ApId: %s', $post->getId(), $post->getApId()));
            $this->postManager->detachImage($post);
        }

        // Return total number of elements deleted
        return \count($posts);
    }

    /**
     * Delete post comment images, check on created_at database column for the age.
     * Limit by batch size.
     *
     * @return number Number of removed records from database
     */
    private function deletePostCommentsImages(OutputInterface $output): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $timeAgo = new \DateTime("-{$this->monthsAgo} months");

        $query = $queryBuilder
            ->select('c')
            ->from(PostComment::class, 'c')
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->lt('c.createdAt', ':timeAgo'),
                    $queryBuilder->expr()->neq('i.id', 1),
                    $queryBuilder->expr()->isNotNull('c.apId'),
                    $this->noActivity ? $queryBuilder->expr()->eq('c.upVotes', 0) : null,
                    $this->noActivity ? $queryBuilder->expr()->eq('c.favouriteCount', 0) : null
                )
            )
            ->innerJoin('c.image', 'i')
            ->orderBy('c.id', 'ASC')
            ->setParameter('timeAgo', $timeAgo)
            ->setMaxResults($this->batchSize)
            ->getQuery();

        $comments = $query->getResult();

        foreach ($comments as $comment) {
            $output->writeln(\sprintf('Deleting image from post comment ID: %d, with ApId: %s', $comment->getId(), $comment->getApId()));
            $this->postCommentManager->detachImage($comment);
        }

        // Return total number of elements deleted
        return \count($comments);
    }

    /**
     * Delete user avatar and user cover images. Check ap_fetched_at column for the age.
     * Limit by batch size.
     *
     * @return number Number of removed records from database
     */
    private function deleteUsersImages(OutputInterface $output): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $timeAgo = new \DateTime("-{$this->monthsAgo} months");

        $query = $queryBuilder
            ->select('u')
            ->from(User::class, 'u')
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
            $output->writeln(\sprintf('Deleting image from username: %s', $user->getUsername()));
            $this->userManager->detachCover($user);
            $this->userManager->detachAvatar($user);
        }

        // Return total number of elements deleted
        return \count($users) * 2;
    }
}
