<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\Entity\Contracts\ActivityPubActivityInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ContextsProvider
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function embeddedContexts(): array
    {
        return array_values([
            ActivityPubActivityInterface::CONTEXT_URL,
            ActivityPubActivityInterface::SECURITY_URL,
            ...ActivityPubActivityInterface::ADDITIONAL_CONTEXTS,
        ]);
    }

    public function referencedContexts(): array
    {
        return [
            ActivityPubActivityInterface::CONTEXT_URL,
            $this->urlGenerator->generate('ap_contexts', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }
}
