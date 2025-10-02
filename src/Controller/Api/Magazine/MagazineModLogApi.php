<?php

declare(strict_types=1);

namespace App\Controller\Api\Magazine;

use App\DTO\MagazineLogResponseDto;
use App\Entity\Magazine;
use App\Entity\MagazineLog;
use App\Repository\MagazineLogRepository;
use App\Schema\PaginationSchema;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class MagazineModLogApi extends MagazineBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Returns the magazine\'s moderation log',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: MagazineLogResponseDto::class))
                ),
                new OA\Property(
                    property: 'pagination',
                    ref: new Model(type: PaginationSchema::class)
                ),
            ]
        ),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to expired token',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Page not found',
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
        name: 'magazine_id',
        description: 'Magazine to get mod log from',
        in: 'path',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'p',
        description: 'Page of moderation log to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: 'perPage',
        description: 'Number of moderation log items to retrieve per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: MagazineLogRepository::PER_PAGE, minimum: self::MIN_PER_PAGE, maximum: self::MAX_PER_PAGE)
    )]
    #[OA\Parameter(
        name: 'types[]',
        description: 'The types of magazine logs to retrieve',
        in: 'query',
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', enum: MagazineLog::CHOICES))
    )]
    #[OA\Tag('magazine')]
    /**
     * Retrieve information about moderation actions taken in the magazine.
     */
    public function collection(
        #[MapEntity(id: 'magazine_id')]
        Magazine $magazine,
        MagazineLogRepository $repository,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
        #[MapQueryParameter] ?array $types = null,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $request = $this->request->getCurrentRequest();
        $logs = $repository->findByCustom(
            $this->getPageNb($request),
            self::constrainPerPage($request->get('perPage', MagazineLogRepository::PER_PAGE)),
            types: $types,
            magazine: $magazine,
        );

        $dtos = [];
        foreach ($logs->getCurrentPageResults() as $value) {
            $dtos[] = $this->serializeLogItem($value);
        }

        return new JsonResponse(
            $this->serializePaginated($dtos, $logs),
            headers: $headers
        );
    }
}
