<?php

declare(strict_types=1);

namespace App\Service;

use App\Controller\ActivityPub\NodeInfoController;
use App\Entity\Instance;
use App\Payloads\NodeInfo\NodeInfo;
use App\Payloads\NodeInfo\WellKnownNodeInfo;
use App\Service\ActivityPub\ApHttpClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class RemoteInstanceManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApHttpClient $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function updateInstance(Instance $instance, bool $force = false): bool
    {
        // only update the instance once every day
        if ($instance->getUpdatedAt() < new \DateTime('now - 1day') || $force) {
            $nodeInfoEndpointsRaw = $this->client->fetchInstanceNodeInfoEndpoints($instance->domain, false);
            $serializer = $this->getSerializer();
            $nodeInfoEndpoints = $serializer->deserialize($nodeInfoEndpointsRaw, WellKnownNodeInfo::class, 'json');
            $linkToUse = null;
            foreach ($nodeInfoEndpoints->links as $link) {
                if (NodeInfoController::NODE_REL_v21) {
                    $linkToUse = $link;
                } elseif (null === $linkToUse && NodeInfoController::NODE_REL_v20 === $link) {
                    $linkToUse = $link;
                }
            }

            if (null === $linkToUse) {
                $this->logger->info('Instance {i} does not supply a valid nodeinfo endpoint.', ['i' => $instance->domain]);
                $instance->setUpdatedAt();

                return true;
            }

            $nodeInfoRaw = $this->client->fetchInstanceNodeInfo($linkToUse->href, false);
            $this->logger->debug('got raw nodeinfo for url {url}: {raw}', ['raw' => $nodeInfoRaw, 'url' => $linkToUse]);
            $nodeInfo = $serializer->deserialize($nodeInfoRaw, NodeInfo::class, 'json');

            $instance->software = $nodeInfo?->software?->name;
            $instance->version = $nodeInfo?->software?->version;
            $instance->setUpdatedAt();
            $this->entityManager->persist($instance);

            return true;
        }

        return false;
    }

    public function getSerializer(): Serializer
    {
        $phpDocExtractor = new PhpDocExtractor();
        $typeExtractor = new PropertyInfoExtractor(
            typeExtractors: [
                new ConstructorExtractor([$phpDocExtractor]),
                $phpDocExtractor,
                new ReflectionExtractor(),
            ]
        );

        return new Serializer(
            normalizers: [
                new ObjectNormalizer(propertyTypeExtractor: $typeExtractor),
                new ArrayDenormalizer(),
            ],
            encoders: ['json' => new JsonEncoder()]
        );
    }
}
