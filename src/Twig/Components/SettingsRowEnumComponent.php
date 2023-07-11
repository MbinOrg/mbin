<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent ('settings_row_enum', template: 'components/_settings_row_enum.html.twig')]
class SettingsRowEnumComponent
{
    public string $title;
    public string $description;
    public string $settingsKey;
    public array $values;
    public string $defaultValue;
}