<?php

declare(strict_types=1);

namespace App\DoctrineExtensions\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\TextType;

/**
 * original src: https://github.com/shiftonelabs/laravel-nomad/issues/2#issuecomment-463388050.
 */
final class Citext extends TextType
{
    public const CITEXT = 'citext';

    public function getName(): string
    {
        return self::CITEXT;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDoctrineTypeMapping(self::CITEXT);
    }
}
