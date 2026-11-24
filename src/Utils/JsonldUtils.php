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

    public static function getApId(string|array $value): string
    {
        if (\is_string($value)) {
            return $value;
        } elseif (\is_array($value)) {
            $value = $value['id'] ?? null;
            if (!\is_string($value)) {
                throw new \LogicException('JsonldUtils::getApId(): value is array but did not contain valid `id` element');
            }
            return $value;
        } else {
            throw new \LogicException('JsonldUtils::getApId(): value is neither a string nor an array');
        }
    }
}
