<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Enums\EUserType;
use App\Factory\ActivityPub\PersonFactory;
use App\Service\CustomCssService;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CustomCssServiceTest extends TestCase
{

    public function testDetectAtRules(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@charset "UTF-8"', null, false));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@import "../style.css"', null, false));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@keyframes kf {}', null, false));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@somerule r {}', null, false));
    }

    public function testDetectInvalidSyntax(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('html {color: blue} .fail {invalid;}', null, false));
    }

    public function testSimplifiesCss(): void {
        $service = $this->createService();
        $css = '
        .classA     >  .classB{
                prop1: 0;
                prop2: rgb(0, 0, 0);
        }
        
        h1 {;;}
        ';

        self::assertSame(".classA > .classB {prop1: 0;prop2: #000;}\nh1 {}", $service->validatePublicCss($css, null, false));
    }

    public function testDetectsWildcardSelector(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('* {}', null, false));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls * {}', null, false));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('a, .cls * {}', null, false));
    }

    public function testDetectsSelectorNotStartsWithClass(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('div .cls {}', null, false));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss(':hover {}', null, false));
    }

    public function testDetectsUrlValues(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls { background-image: url("/image.png") }', null, false));
        // should not throw
        $service->validatePublicCss('.cls { background-image: url("data:image/png;base64;......") }', null, false);
    }

    public function testDetectsFaultyConstraintSelector(): void {
        $service = $this->createService();
        $user1 = new User('', '', '', EUserType::Person);
        $user1->username = 'testuser';
        $user2 = new User('', '', '', EUserType::Person);
        $user2->apId = 'https://remote.local/u/test';

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls {}', $user1, false));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss {}', $user1, false));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin {}', $user1, false));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-a {}', $user1, false));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-not_mbin.fediCustomCss-user-a {}', $user1, false));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user1).' {}', $user2, false));

        // should not throw
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user1).' {}', $user1, false);
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user2).' {}', $user2, false);
    }

    public function testAddsConstraintSelector(): void {
        $service = $this->createService();
        $user1 = new User('', '', '', EUserType::Person);
        $user1->username = 'testuser';
        $user2 = new User('', '', '', EUserType::Person);
        $user2->apId = 'https://remote.local/u/test';

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss {}', $user1, true));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin {}', $user1, true));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-a {}', $user1, true));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-not_mbin.fediCustomCss-user-a {}', $user1, true));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user1).' {}', $user2, true));

        $user1Selector = '.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user1);
        self::assertSame(
            $user1Selector.'.cls {}',
            $service->validatePublicCss(' .cls {}', $user1, true)
        );
        self::assertSame(
            '.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user2).'.cls {}',
            $service->validatePublicCss('.cls {}', $user2, true)
        );
        self::assertSame(
            $user1Selector.".cls {}\n".$user1Selector.'.cls2 .a {}',
            $service->validatePublicCss(".cls {}\n.cls2 .a {}", $user1, true)
        );
        self::assertSame(
            $user1Selector.'.cls, '.$user1Selector.'.cls a {}',
            $service->validatePublicCss('.cls, .cls a {}', $user1, true)
        );
    }

    private function createService(): CustomCssService
    {
        $personFactory = self::createStub(PersonFactory::class);
        $personFactory->method('getActivityPubId')->willReturnCallback(function (User $user) {
            return $user->apId ?? 'https://test.local/'.$user->username;
        });

        return new CustomCssService($personFactory);
    }

    private function expectedUserIdCss(User $user): string {
        $userId = $user->apId ?? 'https://test.local/'.$user->username;
        return str_replace('=', '', str_replace('/', '_', str_replace('+', '-', base64_encode($userId))));
    }

    private static function assertThrows(string $exceptionClass, callable $block): void {
        try {
            $block();
            self::fail('Expected that exception '.$exceptionClass.' was thrown');
        } catch (\Throwable $e) {
            if($e instanceof AssertionFailedError) {
                throw $e;
            }
            self::assertInstanceOf($exceptionClass, $e);
        }
    }
}