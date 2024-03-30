<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Repository\StatsContentRepository;
use App\Service\ProjectInfoService;
use App\Service\SettingsManager;

class NodeInfoFactory
{
    private const NODE_PROTOCOL = 'activitypub';

    public function __construct(
        private readonly StatsContentRepository $repository,
        private readonly SettingsManager $settingsManager,
        private readonly ProjectInfoService $projectInfo
    ) {
    }

    /**
     * Create and return a NodeInfo PHP array depending on the version input.
     *
     * @param string $version NodeInfo version string (eg. "2.0")
     */
    public function create(string $version): array
    {
        switch ($version) {
            case '2.0':
                $software = [
                    'name' => $this->projectInfo->getName(),
                    'version' => $this->projectInfo->getVersion(),
                ];
                break;
            case '2.1':
            default:
                // Used for 2.1 and as fallback
                $software = [
                    'name' => $this->projectInfo->getName(),
                    'version' => $this->projectInfo->getVersion(),
                    'repository' => $this->projectInfo->getRepositoryURL(),
                ];
                break;
        }

        return [
            'version' => $version,
            'software' => $software,
            'protocols' => [
                self::NODE_PROTOCOL,
            ],
            'services' => [
                'outbound' => [],
                'inbound' => [],
            ],
            'usage' => [
                'users' => [
                    'total' => $this->repository->countUsers(),
                    'activeHalfyear' => $this->repository->countUsers((new \DateTime('now'))->modify('-6 months')),
                    'activeMonth' => $this->repository->countUsers((new \DateTime('now'))->modify('-1 month')),
                ],
                'localPosts' => $this->repository->countLocalPosts(),
                'localComments' => $this->repository->countLocalComments(),
            ],
            'openRegistrations' => $this->settingsManager->get('KBIN_REGISTRATIONS_ENABLED'),
            'metadata' => [
                'nodeName' => $this->settingsManager->get('KBIN_META_TITLE'),
                'nodeDescription' => $this->settingsManager->get('KBIN_META_DESCRIPTION'),
            ],
        ];
    }
}
