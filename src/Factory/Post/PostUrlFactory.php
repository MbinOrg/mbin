<?php

namespace App\Factory\Post;

use App\Entity\Contracts\ContentInterface;
use App\Entity\Post;
use App\Factory\Contract\ContentUrlFactory;
use App\Service\Contracts\SwitchableService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements SwitchableService
 * @implements ContentUrlFactory<Post>
 */
readonly class PostUrlFactory implements SwitchableService, ContentUrlFactory
{

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ){}

    public function getSupportedTypes(): array
    {
        return [Post::class];
    }

    function getActivityPubId($subject): string
    {
        if ($subject->apId) {
            return $subject->apId;
        }

        return $this->urlGenerator->generate(
            'ap_post',
            ['magazine_name' => $subject->magazine->name, 'post_id' => $subject->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    function getLocalUrl($subject): string
    {
        return $this->urlGenerator->generate('post_single', [
            'magazine_name' => $subject->magazine->name,
            'post_id' => $subject->getId(),
            'slug' => empty($subject->slug) ? '-' : $subject->slug,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
