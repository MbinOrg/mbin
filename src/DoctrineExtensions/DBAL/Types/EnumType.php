<?php

declare(strict_types=1);

namespace App\DoctrineExtensions\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * See example by doctrine: https://www.doctrine-project.org/projects/doctrine-orm/en/2.20/cookbook/mysql-enums.html#solution-2-defining-a-type.
 */
abstract class EnumType extends Type
{
    abstract public function getName(): string;

    /**
     * @return string[]
     */
    abstract public function getValues(): array;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $values = array_map(function ($val) { return "'".$val."'"; }, $this->getValues());

        return 'ENUM('.implode(', ', $values).')';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!\in_array($value, $this->getValues())) {
            throw new \InvalidArgumentException("Invalid '".$this->getName()."' value.");
        }

        return $value;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
