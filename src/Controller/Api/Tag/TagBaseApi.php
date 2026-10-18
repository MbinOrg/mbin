<?php
declare(strict_types=1);

namespace App\Controller\Api\Tag;

use App\Controller\Api\BaseApi;
use App\DTO\HashtagResponseDto;
use App\Entity\Hashtag;
use App\Factory\HashtagFactory;
use App\Repository\TagRepository;
use Symfony\Contracts\Service\Attribute\Required;

abstract class TagBaseApi extends BaseApi
{
    private readonly HashtagFactory $factory;
    protected readonly TagRepository $repository;

    #[Required]
    public function setFactory(HashtagFactory $factory): void
    {
        $this->factory = $factory;
    }

    #[Required]
    public function setRepository(TagRepository $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * Serialize a domain to JSON.
     */
    protected function serializeHashtag(Hashtag $tag): HashtagResponseDto
    {
        return $this->factory->createDto($tag);
    }
}
