<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Post;
use App\Entity\User;
use App\Repository\PostRepository;
use App\Service\MentionManager;
use App\Service\SettingsManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('related_posts')]
final class RelatedPostsComponent
{
    public const TYPE_TAG = 'tag';
    public const TYPE_MAGAZINE = 'magazine';
    public const TYPE_RANDOM = 'random';

    public int $limit = 4;
    public ?string $type = self::TYPE_RANDOM;
    public ?Post $post = null;
    public string $title = 'random_posts';
    /** @var Post[] */
    public array $posts = [];

    public function __construct(
        private readonly PostRepository $repository,
        private readonly CacheInterface $cache,
        private readonly SettingsManager $settingsManager,
        private readonly MentionManager $mentionManager,
        private readonly Security $security,
    ) {
    }

    public function mount(?string $magazine, ?string $tag): void
    {
        if ($tag) {
            $this->title = 'related_posts';
            $this->type = self::TYPE_TAG;
        }

        if ($magazine) {
            $this->title = 'related_posts';
            $this->type = self::TYPE_MAGAZINE;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();

        $postId = $this->post?->getId();
        $magazine = str_replace('@', '', $magazine ?? '');

        $postIds = $this->cache->get(
            "related_posts_{$magazine}_{$tag}_{$postId}_{$this->type}_{$this->settingsManager->getLocale()}_{$user?->getId()}",
            function (ItemInterface $item) use ($magazine, $tag, $user) {
                $item->expiresAfter(60 * 5); // 5 minutes

                $posts = match ($this->type) {
                    self::TYPE_TAG => $this->repository->findRelatedByMagazine($tag, $this->limit + 20, user: $user),
                    self::TYPE_MAGAZINE => $this->repository->findRelatedByTag(
                        $this->mentionManager->getUsername($magazine),
                        $this->limit + 20,
                        user: $user
                    ),
                    default => $this->repository->findLast($this->limit + 150, user: $user),
                };

                $posts = array_filter($posts, fn (Post $p) => !$p->isAdult && !$p->magazine->isAdult);

                if (\count($posts) > $this->limit) {
                    shuffle($posts); // randomize the order
                    $posts = \array_slice($posts, 0, $this->limit);
                }

                return array_map(fn (Post $post) => $post->getId(), $posts);
            }
        );

        $this->posts = $this->repository->findBy(['id' => $postIds]);
    }
}
