<?php

declare(strict_types=1);

namespace App\Controller\Api\Post;

use App\Controller\Traits\PrivateContentTrait;
use App\Entity\Post;
use App\Factory\ContentActivityDtoFactory;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class PostsActivityApi extends PostsBaseApi
{
    use PrivateContentTrait;

    public function __invoke(
        #[MapEntity(id: 'post_id')]
        Post $post,
        ContentActivityDtoFactory $dtoFactory,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $this->handlePrivateContent($post);

        $dto = $dtoFactory->createActivitiesDto($post);

        return new JsonResponse(
            $dto,
            headers: $headers
        );
    }
}
