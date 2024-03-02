<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Contracts\ActivityPubActivityInterface;
use JetBrains\PhpStorm\ArrayShape;

class UndoWrapper
{
    #[ArrayShape([
        '@context' => 'string',
        'id' => 'string',
        'type' => 'string',
        'actor' => 'mixed',
        'object' => 'array',
    ])]
    public function build(
        array $object,
    ): array {
        unset($object['@context']);

        return [
            '@context' => ActivityPubActivityInterface::CONTEXT_URL,
            'id' => $object['id'].'#unfollow',
            'type' => 'Undo',
            'actor' => $object['actor'],
            'object' => $object,
        ];
    }
}
