<?php

declare(strict_types=1);

namespace App\Entity\Contracts;

interface ActivityPubActivityInterface
{
    public const FOLLOWERS = 'followers';
    public const FOLLOWING = 'following';
    public const INBOX = 'inbox';
    public const OUTBOX = 'outbox';
    public const CONTEXT = 'context';
    public const CONTEXT_URL = 'https://www.w3.org/ns/activitystreams';
    public const SECURITY_URL = 'https://w3id.org/security/v1';
    public const PUBLIC_URL = 'https://www.w3.org/ns/activitystreams#Public';

    public const ADDITIONAL_CONTEXTS = [
        'ostatus' => 'http://ostatus.org#',
        'toot' => 'http://joinmastodon.org/ns#',
        'pt' => 'https://joinpeertube.org/ns#',
        'lemmy' => 'https://join-lemmy.org/ns#',
        'Hashtag' => 'as:Hashtag',
        'sensitive' => 'as:sensitive',
        'blurhash' => 'toot:blurhash',
        'focalPoint' => 'toot:focalPoint',
        'votersCount' => 'toot:votersCount',
        'commentsEnabled' => 'pt:commentsEnabled',
        'stickied' => 'lemmy:stickied',
    ];
}
