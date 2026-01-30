<?php

declare(strict_types=1);

namespace App\Controller\Api\Entry\Moderate;

use App\Controller\Api\Entry\EntriesBaseApi;
use App\DTO\EntryResponseDto;
use App\Entity\Entry;
use App\Factory\EntryFactory;
use App\Schema\Errors\ForbiddenErrorSchema;
use App\Schema\Errors\NotFoundErrorSchema;
use App\Schema\Errors\TooManyRequestsErrorSchema;
use App\Schema\Errors\UnauthorizedErrorSchema;
use App\Service\EntryManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EntriesLockApi extends EntriesBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Entry lock status toggled',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: EntryResponseDto::class)
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You are not authorized to lock this entry',
        content: new OA\JsonContent(ref: new Model(type: ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Entry not found',
        content: new OA\JsonContent(ref: new Model(type: NotFoundErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new OA\JsonContent(ref: new Model(type: TooManyRequestsErrorSchema::class))
    )]
    #[OA\Parameter(
        name: 'entry_id',
        description: 'The entry to lock or unlock',
        in: 'path',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Tag(name: 'moderation/entry')]
    #[Security(name: 'oauth2', scopes: ['moderate:entry:lock'])]
    #[IsGranted('ROLE_OAUTH2_MODERATE:ENTRY:LOCK')]
    #[IsGranted('lock', subject: 'entry')]
    public function __invoke(
        #[MapEntity(id: 'entry_id')]
        Entry $entry,
        EntryManager $manager,
        EntryFactory $factory,
        RateLimiterFactory $apiModerateLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiModerateLimiter);

        $manager->toggleLock($entry, $this->getUserOrThrow());

        return new JsonResponse(
            $this->serializeEntry($factory->createDto($entry), $this->tagLinkRepository->getTagsOfContent($entry), $this->entryRepository->findCross($entry)),
            headers: $headers
        );
    }
}
