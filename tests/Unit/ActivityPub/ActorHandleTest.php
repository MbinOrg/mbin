<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub;

use App\ActivityPub\ActorHandle;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ActorHandleTest extends TestCase
{
    #[DataProvider('handleProvider')]
    public function testHandleIsRecognized(string $input, array $output): void
    {
        $this->assertNotNull(ActorHandle::parse($input));
    }

    #[DataProvider('handleProvider')]
    public function testHandleIsParsedProperly(string $input, array $output): void
    {
        $handle = ActorHandle::parse($input);
        $this->assertEquals($handle->prefix, $output['prefix']);
        $this->assertEquals($handle->name, $output['name']);
        $this->assertEquals($handle->host, $output['host']);
        $this->assertEquals($handle->port, $output['port']);
    }

    public static function handleProvider(): array
    {
        $handleSamples = [
            'user@mbin.instance' => [
                'prefix' => null,
                'name' => 'user',
                'host' => 'mbin.instance',
                'port' => null,
            ],
            '@someone-512@mbin.instance' => [
                'prefix' => '@',
                'name' => 'someone-512',
                'host' => 'mbin.instance',
                'port' => null,
            ],
            '!engineering@ds9.space' => [
                'prefix' => '!',
                'name' => 'engineering',
                'host' => 'ds9.space',
                'port' => null,
            ],
            '@leon@pink.brainrot.internal:11037' => [
                'prefix' => '@',
                'name' => 'leon',
                'host' => 'pink.brainrot.internal',
                'port' => 11037,
            ],
        ];

        $inputs = array_keys($handleSamples);
        $outputs = array_values($handleSamples);

        return array_combine(
            $inputs,
            array_map(fn ($input, $output) => [$input, $output], $inputs, $outputs)
        );
    }
}
