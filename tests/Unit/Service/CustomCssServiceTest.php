<?php

namespace App\Tests\Unit\Service;

use App\Service\CustomCssService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CustomCssServiceTest extends TestCase
{

    public function testDetectAtRules(): void {
        $service = new CustomCssService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@charset "UTF-8"'));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@import "../style.css"'));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@keyframes kf {}'));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@somerule r {}'));
    }

    public function testDetectInvalidSyntax(): void {
        $service = new CustomCssService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('html {color: blue} .fail {invalid;}'));
    }

    public function testSimplifiesCss(): void {
        $service = new CustomCssService();
        $css = '
        .classA     >  .classB{
                prop1: 0;
                prop2: rgb(0, 0, 0);
        }
        ';

        self::assertSame('.classA > .classB {prop1: 0;prop2: #000;}', $service->validatePublicCss($css));
    }

    public function testDetectsWildcardSelector(): void {
        $service = new CustomCssService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('* {}'));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls * {}'));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('a, .cls * {}'));
    }

    public function testDetectsUrlValues(): void {
        $service = new CustomCssService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls { background-image: url("/image.png") }'));
        // should not throw
        $service->validatePublicCss('.cls { background-image: url("data:image/png;base64;......") }');
    }

    private static function assertThrows(string $exceptionClass, callable $block): void {
        try {
            $block();
        } catch (\Throwable $e) {
            self::assertInstanceOf($exceptionClass, $e);
        }
    }
}