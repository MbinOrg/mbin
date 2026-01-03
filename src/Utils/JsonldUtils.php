<?php

declare(strict_types=1);

namespace App\Utils;

class JsonldUtils
{
    public static function getArrayValue(array $object, string $key): array
    {
        if (!\array_key_exists($key, $object)) {
            return [];
        }
        if (\is_array($object[$key])) {
            return $object[$key];
        }

        return [$object[$key]];
    }
}
