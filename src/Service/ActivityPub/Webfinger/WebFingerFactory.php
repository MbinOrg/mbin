<?php

declare(strict_types=1);

/*
 * This file is part of the ActivityPhp package, with modifications.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace App\Service\ActivityPub\Webfinger;

use App\ActivityPub\ActorHandle;
use App\Service\ActivityPub\ApHttpClient;

/**
 * A simple WebFinger discoverer tool.
 */
class WebFingerFactory
{
    public const WEBFINGER_URL = '%s://%s%s/.well-known/webfinger?resource=acct:%s';

    public function __construct(private readonly ApHttpClient $client)
    {
    }

    public function get(string $handle, string $scheme = 'https')
    {
        $actorHandle = ActorHandle::parse($handle);

        if (!$actorHandle) {
            throw new \Exception("WebFinger handle is malformed '{$handle}'");
        }

        // Build a WebFinger URL
        $url = sprintf(
            self::WEBFINGER_URL,
            $scheme,
            $actorHandle->host,
            $actorHandle->getPortString(),
            $actorHandle->plainHandle(),
        );

        $content = $this->client->getWebfingerObject($url);

        if (!\is_array($content) || !\count($content)) {
            throw new \Exception('WebFinger fetching has failed, no contents returned');
        }

        return new WebFinger($content);
    }
}
