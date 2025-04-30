<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TagExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TagExtractorTest extends TestCase
{
    #[DataProvider('provider')]
    public function testExtract(string $input, ?array $output): void
    {
        $this->assertEquals($output, (new TagExtractor())->extract($input, 'kbin'));
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
            ['#イラスト # #一次創作', ['イラスト', '一次創作']],
            ['#ทำตัวไม่ถูกเลยเรา', ['ทำตัวไม่ถูกเลยเรา']],
            ['#ไกด์ช้างม่วง #ทวิตล่ม', ['ไกด์ช้างม่วง', 'ทวิตล่ม']],
            ['#Ｓｙｎｔｈｗａｖｅ', ['synthwave']],
            ['#ｼｰｻｲﾄﾞﾗｲﾅｰ', ['シーサイドライナー']],
            ['#ぼっち・ざ・ろっく', ['ぼっち・ざ・ろっく']],
            ['https://www.site.tld/somepath/#heading', null]
        ];
    }
}
