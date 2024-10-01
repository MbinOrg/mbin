<?php

declare(strict_types=1);

namespace App\Controller\Entry;

use App\Entity\Entry;

trait EntryTemplateTrait
{
    private function getTemplateName(?string $type, ?bool $edit = false): string
    {
        $prefix = $edit ? 'edit' : 'create';

        if (!$type || Entry::ENTRY_TYPE_THREAD === $type) {
            return "entry/{$prefix}_thread.html.twig";
        }

        if (Entry::ENTRY_TYPE_IMAGE === $type) {
            return "entry/{$prefix}_image.html.twig";
        }

        return "entry/{$prefix}_link.html.twig";
    }
}
