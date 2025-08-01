<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Post;
use App\Repository\MagazineRepository;
use App\Repository\PostRepository;
use App\Repository\TagLinkRepository;
use App\Service\PostManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'mbin:posts:magazines',
    description: 'This command allows to assign a magazine to a post.',
)]
class PostMagazinesUpdateCommand extends Command
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly PostManager $postManager,
        private readonly TagLinkRepository $tagLinkRepository,
        private readonly MagazineRepository $magazineRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $posts = $this->postRepository->findTaggedFederatedInRandomMagazine();
        foreach ($posts as $post) {
            $this->handleMagazine($post, $output);
        }

        return Command::SUCCESS;
    }

    private function handleMagazine(Post $post, OutputInterface $output): void
    {
        $tags = $this->tagLinkRepository->getTagsOfContent($post);

        $output->writeln((string) $post->getId());
        foreach ($tags as $tag) {
            if ($magazine = $this->magazineRepository->findOneByName($tag)) {
                $output->writeln($magazine->name);
                $this->postManager->changeMagazine($post, $magazine);
                break;
            }

            if ($magazine = $this->magazineRepository->findByTag($tag)) {
                $output->writeln($magazine->name);
                $this->postManager->changeMagazine($post, $magazine);
                break;
            }
        }
    }
}
