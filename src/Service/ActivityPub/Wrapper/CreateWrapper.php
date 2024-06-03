<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Factory\ActivityPub\ActivityFactory;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Uid\Uuid;

class CreateWrapper
{
    public function __construct(private readonly ActivityFactory $factory)
    {
    }

    #[ArrayShape([
        '@context' => 'mixed',
        'id' => 'mixed',
        'type' => 'string',
        'actor' => 'mixed',
        'published' => 'mixed',
        'to' => 'mixed',
        'cc' => 'mixed',
        'object' => 'array',
    ])]
    public function build(ActivityPubActivityInterface $item): array
    {
        $item = $this->factory->create($item, true);
        $id = Uuid::v4()->toRfc4122();

        $context = $item['@context'];
        unset($item['@context']);

        return [
            '@context' => $context,
            'id' => $id,
            'type' => 'Create',
            'actor' => $item['attributedTo'],
            'published' => $item['published'],
            'to' => $item['to'],
            'cc' => $item['cc'],
            'object' => $item,
        ];
    }
}
