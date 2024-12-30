<?php

declare(strict_types=1);

namespace App\Command\Update;

use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Service\TagExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'mbin:tag:update',
    description: 'This command allows refresh entries tags.'
)]
class TagsUpdateCommand extends Command
{
    public function __construct(
        private readonly TagExtractor $tagExtractor,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $comments = $this->entityManager->getRepository(EntryComment::class)->findAll();
        foreach ($comments as $comment) {
            // TODO: $comment->tags is undefined; should it be ->hashtags?
            $comment->tags = $this->tagExtractor->extract($comment->body, $comment->magazine->name);
            $this->entityManager->persist($comment);
        }

        $posts = $this->entityManager->getRepository(Post::class)->findAll();
        foreach ($posts as $post) {
            // TODO: $post->tags is undefined; should it be ->hashtags?
            $post->tags = $this->tagExtractor->extract($post->body, $post->magazine->name);
            $this->entityManager->persist($post);
        }

        $comments = $this->entityManager->getRepository(PostComment::class)->findAll();
        foreach ($comments as $comment) {
            // TODO: $comment->tags is undefined; should it be ->hashtags?
            $comment->tags = $this->tagExtractor->extract($comment->body, $comment->magazine->name);
            $this->entityManager->persist($comment);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
