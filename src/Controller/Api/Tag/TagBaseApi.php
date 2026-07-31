<?php
declare(strict_types=1);

namespace App\Controller\Api\Tag;

use App\Controller\Api\BaseApi;
use App\DTO\HashtagResponseDto;
use App\Entity\Hashtag;
use App\Factory\HashtagFactory;
use App\Repository\TagRepository;
use App\Service\TagManager;
use Symfony\Contracts\Service\Attribute\Required;

abstract class TagBaseApi extends BaseApi
{
    private readonly HashtagFactory $factory;

    protected readonly TagManager $tagManager;
    protected readonly TagRepository $tagRepository;

    #[Required]
    public function setTagRepository(TagRepository $tagRepository): void
    {
        $this->tagRepository = $tagRepository;
    }

    #[Required]
    public function setTagManager(TagManager $tagManager): void
    {
        $this->tagManager = $tagManager;
    }

    #[Required]
    public function setFactory(HashtagFactory $factory): void
    {
        $this->factory = $factory;
    }

    /**
     * Serialize a hashtag to JSON.
     */
    protected function serializeHashtag(Hashtag $tag): HashtagResponseDto
    {
        return $this->factory->createDto($tag);
    }
}
