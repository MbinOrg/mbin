<?php

declare(strict_types=1);

namespace App\Controller\Api\Post\Comments\Moderate;

use App\Controller\Api\Post\PostsBaseApi;
use App\DTO\PostCommentResponseDto;
use App\Entity\PostComment;
use App\Factory\PostCommentFactory;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PostCommentsSetAdultApi extends PostsBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Comment isAdult status set',
        content: new Model(type: PostCommentResponseDto::class),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You are not authorized to moderate this comment',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Comment not found',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\NotFoundErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\TooManyRequestsErrorSchema::class)),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Parameter(
        name: 'comment_id',
        in: 'path',
        description: 'The comment to set adult status on',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'adult',
        in: 'path',
        description: 'new isAdult status',
        schema: new OA\Schema(type: 'boolean', default: true),
    )]
    #[OA\Tag(name: 'moderation/post_comment')]
    #[Security(name: 'oauth2', scopes: ['moderate:post_comment:set_adult'])]
    #[IsGranted('ROLE_OAUTH2_MODERATE:POST_COMMENT:SET_ADULT')]
    #[IsGranted('moderate', subject: 'comment')]
    public function __invoke(
        #[MapEntity(id: 'comment_id')]
        PostComment $comment,
        PostCommentFactory $factory,
        EntityManagerInterface $manager,
        RateLimiterFactory $apiModerateLimiter
    ): JsonResponse {
        $headers = $this->rateLimit($apiModerateLimiter);

        $request = $this->request->getCurrentRequest();
        // Returns true for "1", "true", "on" and "yes". Returns false otherwise.
        $comment->isAdult = filter_var($request->get('adult', 'true'), FILTER_VALIDATE_BOOL);

        $manager->flush();

        return new JsonResponse(
            $this->serializePostComment($factory->createDto($comment), $this->tagLinkRepository->getTagsOfPostComment($comment)),
            headers: $headers
        );
    }
}
