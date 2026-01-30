<?php

declare(strict_types=1);

namespace App\Controller\Api\Instance;

use App\Entity\User;
use App\Factory\ActivityPub\PersonFactory;
use App\Service\ProjectInfoService;
use App\Service\SettingsManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class InstanceRetrieveInfoApi extends InstanceBaseApi
{
    #[OA\Response(
        response: 200,
        description: 'Get general instance information (eg. software name and version)',
        content: new OA\JsonContent(ref: new Model(type: \App\Schema\InfoSchema::class)),
        headers: [
            new OA\Header(header: 'X-RateLimit-Remaining', schema: new OA\Schema(type: 'integer'), description: 'Number of requests left until you will be rate limited'),
            new OA\Header(header: 'X-RateLimit-Retry-After', schema: new OA\Schema(type: 'integer'), description: 'Unix timestamp to retry the request after'),
            new OA\Header(header: 'X-RateLimit-Limit', schema: new OA\Schema(type: 'integer'), description: 'Number of requests available'),
        ]
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
    #[OA\Tag(name: 'instance')]
    /**
     * Retrieve instance information (like the software name and version plus general website info).
     */
    public function __invoke(
        SettingsManager $settings,
        ProjectInfoService $projectInfo,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
        PersonFactory $userFactory,
    ): JsonResponse {
        $userToJson = function (User $admin) use ($userFactory) {
            $json = $userFactory->create($admin);
            unset($json['@context']);

            return $json;
        };
        $adminUsers = $this->userRepository->findAllAdmins();
        $admins = array_map($userToJson, $adminUsers);
        $moderatorUsers = $this->userRepository->findAllModerators();
        $moderators = array_map($userToJson, $moderatorUsers);

        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);
        $body = [
            'softwareName' => $projectInfo->getName(),
            'softwareVersion' => $projectInfo->getVersion(),
            'softwareRepository' => $projectInfo->getRepositoryURL(),
            'websiteDomain' => $settings->get('KBIN_DOMAIN'),
            'websiteContactEmail' => $settings->get('KBIN_CONTACT_EMAIL'),
            'websiteTitle' => $settings->get('KBIN_TITLE'),
            'websiteOpenRegistrations' => $settings->get('KBIN_REGISTRATIONS_ENABLED'),
            'websiteFederationEnabled' => $settings->get('KBIN_FEDERATION_ENABLED'),
            'websiteDefaultLang' => $settings->get('KBIN_DEFAULT_LANG'),
            'instanceModerators' => $moderators,
            'instanceAdmins' => $admins,
        ];

        return new JsonResponse(
            $body,
            headers: $headers
        );
    }
}
