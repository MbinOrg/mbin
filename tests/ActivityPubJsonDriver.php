<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Assert;
use Spatie\Snapshots\Drivers\JsonDriver;
use Spatie\Snapshots\Exceptions\CantBeSerialized;

class ActivityPubJsonDriver extends JsonDriver
{
    public function serialize($data): string
    {
        if (\is_string($data)) {
            $data = json_decode($data);
        }

        if (\is_resource($data)) {
            throw new CantBeSerialized('Resources can not be serialized to json');
        }

        $data = $this->scrub($data);

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    public function match($expected, $actual): void
    {
        if (\is_string($actual)) {
            $actual = json_decode($actual, false, 512, JSON_THROW_ON_ERROR);
        }

        $actual = $this->scrub($actual);

        $expected = json_decode($expected, false, 512, JSON_THROW_ON_ERROR);
        Assert::assertJsonStringEqualsJsonString(json_encode($expected), json_encode($actual));
    }

    protected function scrub(mixed $data): mixed
    {
        if (\is_array($data)) {
            return $this->scrubArray($data);
        } elseif (\is_object($data)) {
            return $this->scrubObject($data);
        }

        return $this;
    }

    protected function scrubArray(array $data): array
    {
        if (isset($data['id'])) {
            $data['id'] = 'SCRUBBED_ID';
        }

        if (isset($data['type']) && 'Note' === $data['type'] && isset($data['url'])) {
            $data['url'] = 'SCRUBBED_ID';
        }

        if (isset($data['inReplyTo'])) {
            $data['inReplyTo'] = 'SCRUBBED_ID';
        }

        if (isset($data['published'])) {
            $data['published'] = 'SCRUBBED_DATE';
        }

        if (isset($data['updated'])) {
            $data['updated'] = 'SCRUBBED_DATE';
        }

        if (isset($data['publicKey'])) {
            $data['publicKey'] = 'SCRUBBED_KEY';
        }

        if (isset($data['object']) && \is_string($data['object'])) {
            $data['object'] = 'SCRUBBED_ID';
        }

        if (isset($data['object']) && (\is_array($data['object']) || \is_object($data['object']))) {
            $data['object'] = $this->scrub($data['object']);
        }

        if (isset($data['orderedItems']) && \is_array($data['orderedItems'])) {
            $items = [];
            foreach ($data['orderedItems'] as $item) {
                $items[] = $this->scrub($item);
            }
            $data['orderedItems'] = $items;
        }

        return $data;
    }

    protected function scrubObject(object $data): object
    {
        if (isset($data->id)) {
            $data->id = 'SCRUBBED_ID';
        }

        if (isset($data->type) && 'Note' === $data->type && isset($data->url)) {
            $data->url = 'SCRUBBED_ID';
        }

        if (isset($data->inReplyTo)) {
            $data->inReplyTo = 'SCRUBBED_ID';
        }

        if (isset($data->published)) {
            $data->published = 'SCRUBBED_DATE';
        }

        if (isset($data->updated)) {
            $data->updated = 'SCRUBBED_DATE';
        }

        if (isset($data->publicKey)) {
            $data->publicKey = 'SCRUBBED_KEY';
        }

        if (isset($data->object) && \is_string($data->object)) {
            $data->object = 'SCRUBBED_ID';
        }

        if (isset($data->object) && (\is_array($data->object) || \is_object($data->object))) {
            $data->object = $this->scrub($data->object);
        }

        return $data;
    }
}
