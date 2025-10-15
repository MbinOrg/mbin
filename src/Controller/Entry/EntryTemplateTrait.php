<?php

declare(strict_types=1);

namespace App\Controller\Entry;

trait EntryTemplateTrait
{
    private function getTemplateName(?bool $edit = false): string
    {
        $prefix = $edit ? 'edit' : 'create';

        return "entry/{$prefix}_entry.html.twig";
    }
}
