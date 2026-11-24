<?php

declare(strict_types=1);

namespace App\Entity\Contracts;

use App\Entity\User;

interface ActivityPubActivityInterface
{
    public const string FOLLOWERS = 'followers';
    public const string FOLLOWING = 'following';
    public const string INBOX = 'inbox';
    public const string OUTBOX = 'outbox';
    public const string CONTEXT = 'context';
    public const string CONTEXT_URL = 'https://www.w3.org/ns/activitystreams';
    public const string SECURITY_URL = 'https://w3id.org/security/v1';
    public const string PUBLIC_URL = 'https://www.w3.org/ns/activitystreams#Public';
    public const string PUBLIC_URL_NS = 'as:Public';
    public const string PUBLIC_URL_SHORT = 'Public';

    public const ADDITIONAL_CONTEXTS = [
        // namespaces
        'ostatus' => 'http://ostatus.org#',
        'schema' => 'http://schema.org#',
        'toot' => 'http://joinmastodon.org/ns#',
        'pt' => 'https://joinpeertube.org/ns#',
        'lemmy' => 'https://join-lemmy.org/ns#',
        // objects
        'Hashtag' => 'as:Hashtag',
        'PropertyValue' => 'schema:PropertyValue',
        // properties
        'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
        'sensitive' => 'as:sensitive',
        'value' => 'schema:value',
        'blurhash' => 'toot:blurhash',
        'focalPoint' => 'toot:focalPoint',
        'votersCount' => 'toot:votersCount',
        'featured' => 'toot:featured',
        'commentsEnabled' => 'pt:commentsEnabled',
        'postingRestrictedToMods' => 'lemmy:postingRestrictedToMods',
        'stickied' => 'lemmy:stickied',
    ];

    public function getUser(): ?User;
}
