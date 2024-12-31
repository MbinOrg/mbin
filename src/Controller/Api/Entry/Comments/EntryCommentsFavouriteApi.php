<?php

declare(strict_types=1);

namespace App\Controller\Api\Entry\Comments;

use App\Controller\Api\Entry\EntriesBaseApi;
use App\DTO\EntryCommentResponseDto;
use App\Entity\EntryComment;
use App\Factory\EntryCommentFactory;
use App\Service\FavouriteManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EntryCommentsFavouriteApi extends EntriesBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Comment favourite status toggled',
        content: new Model(type: EntryCommentResponseDto::class),
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
        description: 'The comment to favourite',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'd',
        in: 'query',
        description: 'Comment tree depth to retrieve (-1 for unlimited depth)',
        schema: new OA\Schema(type: 'integer', default: -1),
    )]
    #[OA\Tag(name: 'entry_comment')]
    // TODO: Bots should not be able to vote
    //       *sad beep boop*
    #[Security(name: 'oauth2', scopes: ['entry_comment:vote'])]
    #[IsGranted('ROLE_OAUTH2_ENTRY_COMMENT:VOTE')]
    public function __invoke(
        #[MapEntity(id: 'comment_id')]
        EntryComment $comment,
        FavouriteManager $manager,
        EntryCommentFactory $factory,
        RateLimiterFactory $apiVoteLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiVoteLimiter);

        $manager->toggle($this->getUserOrThrow(), $comment);

        return new JsonResponse(
            $this->serializeComment($factory->createDto($comment), $this->tagLinkRepository->getTagsOfEntryComment($comment)),
            headers: $headers
        );
    }
}
