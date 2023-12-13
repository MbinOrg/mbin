<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TagManager;
use PHPUnit\Framework\TestCase;

class TagManagerTest extends TestCase
{
    /**
     * @dataProvider provider
     */
    public function testExtract(string $input, ?array $output): void
    {
        $this->assertEquals($output, (new TagManager())->extract($input, 'kbin'));
    }

    public static function provider(): array
    {
        return [
            ['Lorem #acme ipsum', ['acme']],
            ['#acme lorem ipsum', ['acme']],
            ['Lorem #acme #kbin #acme2 ipsum', ['acme', 'acme2']],
            ['Lorem ipsum#example', null],
            ['Lorem #acme#example', ['acme']],
            ['Lorem #Acme #acme ipsum', ['acme']],
            ['Lorem ipsum', null],
            ['#Test1_2_3', ['test1_2_3']],
            ['#_123_ABC_', ['_123_abc_']],
            ['Teraz #zażółć #gęślą #jaźń', ['zazolc', 'gesla', 'jazn']],
            ['#Göbeklitepe #çarpıcı #eğlence #şarkı #ören', ['gobeklitepe', 'carpici', 'eglence', 'sarki', 'oren']],
            ['#Viva #España #senõr', ['viva', 'espana', 'senor']],
        ];
    }
}
