<?php

namespace App\Factory\Entry;

use App\Entity\Entry;
use App\Factory\Contract\ContentUrlFactory;
use App\Service\Contracts\SwitchableService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements SwitchableService
 * @implements ContentUrlFactory<Entry>
 */
readonly class EntryUrlFactory implements SwitchableService, ContentUrlFactory
{

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ){}

    public function getSupportedTypes(): array
    {
        return [Entry::class];
    }

    function getActivityPubId($subject): string
    {
        if ($subject->apId) {
            return $subject->apId;
        }

        return $this->urlGenerator->generate(
            'ap_entry',
            ['magazine_name' => $subject->magazine->name, 'entry_id' => $subject->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    function getLocalUrl($subject): string
    {
        return $this->urlGenerator->generate('entry_single', [
            'magazine_name' => $subject->magazine->name,
            'entry_id' => $subject->getId(),
            'slug' => $subject->slug ?? '-'
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
