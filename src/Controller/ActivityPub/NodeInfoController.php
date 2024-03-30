<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub;

use App\Factory\ActivityPub\NodeInfoFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NodeInfoController
{
    private const NODE_REL_v20 = 'http://nodeinfo.diaspora.software/ns/schema/2.0';
    private const NODE_REL_v21 = 'http://nodeinfo.diaspora.software/ns/schema/2.1';

    public function __construct(
        private readonly NodeInfoFactory $nodeInfoFactory,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * Returning NodeInfo JSON response for path: .well-known/nodeinfo.
     */
    public function nodeInfo(): JsonResponse
    {
        return new JsonResponse($this->getLinks());
    }

    /**
     * Returning NodeInfo JSON response for path: nodeinfo/2.x.
     *
     * @param string $version version number of NodeInfo
     */
    public function nodeInfoV2(string $version): JsonResponse
    {
        return new JsonResponse($this->nodeInfoFactory->create($version));
    }

    /**
     * Get list of links for well-known nodeinfo.
     */
    private function getLinks(): array
    {
        return [
            'links' => [
                [
                    'rel' => self::NODE_REL_v21,
                    'href' => $this->urlGenerator->generate('ap_node_info_v2', ['version' => '2.1'], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
                [
                    'rel' => self::NODE_REL_v20,
                    'href' => $this->urlGenerator->generate('ap_node_info_v2', ['version' => '2.0'], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
            ],
        ];
    }
}
