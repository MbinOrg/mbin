<?php

declare(strict_types=1);

namespace App\Controller\Api\Entry\Comments;

use App\Controller\Api\Entry\EntriesBaseApi;
use App\Controller\Traits\PrivateContentTrait;
use App\Entity\EntryComment;
use App\Factory\ContentActivityDtoFactory;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class EntryCommentsActivityApi extends EntriesBaseApi
{
    use PrivateContentTrait;

    public function __invoke(
        #[MapEntity(id: 'comment_id')]
        EntryComment $comment,
        ContentActivityDtoFactory $dtoFactory,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $this->handlePrivateContent($comment);

        $dto = $dtoFactory->createActivitiesDto($comment);

        return new JsonResponse(
            $dto,
            headers: $headers
        );
    }
}
