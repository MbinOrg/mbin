<?php

declare(strict_types=1);

namespace App\Controller\Api\Magazine\Admin;

use App\Controller\Api\Magazine\MagazineBaseApi;
use App\DTO\MagazineThemeResponseDto;
use App\Entity\Magazine;
use App\Service\MagazineManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MagazineDeleteBannerApi extends MagazineBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Banner removed',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new Model(type: MagazineThemeResponseDto::class)
    )]
    #[OA\Response(
        response: 401,
        description: 'Permission denied due to missing or expired token',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\UnauthorizedErrorSchema::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'You do not have permission to delete this magazine\'s banner',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\ForbiddenErrorSchema::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Magazine not found',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\NotFoundErrorSchema::class))
    )]
    #[OA\Response(
        response: 429,
        description: 'You are being rate limited',
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', description: 'Number of requests left until you will be rate limited', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Retry-After', description: 'Unix timestamp to retry the request after', schema: new OA\Schema(type: 'integer')),
            new OA\Header(header: 'X-RateLimit-Limit', description: 'Number of requests available', schema: new OA\Schema(type: 'integer')),
        ],
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\Errors\TooManyRequestsErrorSchema::class))
    )]
    #[OA\Parameter(
        name: 'magazine_id',
        description: 'The id of the magazine to remove the banner from',
        in: 'path',
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Tag(name: 'moderation/magazine/owner')]
    #[Security(name: 'oauth2', scopes: ['moderate:magazine_admin:theme'])]
    #[IsGranted('ROLE_OAUTH2_MODERATE:MAGAZINE_ADMIN:THEME')]
    #[IsGranted('edit', subject: 'magazine')]
    /**
     * Update the magazine's theme.
     */
    public function __invoke(
        #[MapEntity(id: 'magazine_id')]
        Magazine $magazine,
        MagazineManager $manager,
        RateLimiterFactory $apiModerateLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiModerateLimiter);

        $manager->detachBanner($magazine);

        $iconDto = $magazine->icon ? $this->imageFactory->createDto($magazine->icon) : null;
        $dto = MagazineThemeResponseDto::create($manager->createDto($magazine), $magazine->customCss, $iconDto, banner: null);

        return new JsonResponse(
            $dto,
            headers: $headers
        );
    }
}
