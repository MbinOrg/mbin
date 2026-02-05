<?php

declare(strict_types=1);

namespace App\Controller\Api\Post\Comments;

use App\Controller\Api\Post\PostsBaseApi;
use App\Controller\Traits\PrivateContentTrait;
use App\Entity\PostComment;
use App\Factory\ContentActivityDtoFactory;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class PostCommentsActivityApi extends PostsBaseApi
{
    use PrivateContentTrait;

    public function __invoke(
        #[MapEntity(id: 'comment_id')]
        PostComment $comment,
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
