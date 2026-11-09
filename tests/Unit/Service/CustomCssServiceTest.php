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
            fn() => $service->validatePublicCss('@charset "UTF-8"', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@import "../style.css"', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@keyframes kf {}', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@somerule r {}', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));
    }

    public function testDetectInvalidSyntax(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('html {color: blue} .fail {invalid;}', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));
    }

    public function testSimplifiesCss(): void {
        $service = $this->createService();
        $css = '
        .classA     >  .classB{
                prop1: 0;
                prop2: rgb(0, 0, 0);
        }
        
        .any h1 {;;}
        ';

        self::assertSame(".classA > .classB {prop1: 0;prop2: #000;}\n.any h1 {}", $service->validatePublicCss($css, null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));
    }

    public function testDetectsWildcardSelector(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('* {}', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls * {}', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('a, .cls * {}', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));
    }

    public function testDetectsSelectorNotStartsWithClass(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('div .cls {}', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss(':hover {}', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));
    }

    public function testDetectsUrlValues(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls { background-image: url("/image.png") }', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls { content: url("/image.png") }', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls { content: "a" url("/image.png")/"alt" }', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE));

        // should not throw
        $service->validatePublicCss('.cls { background-image: url("data:image/png;base64;......") }', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE);
        $service->validatePublicCss('.cls { content: "a" url("data:image/png;base64;......") }', null, CustomCssService::ENFORCE_SELECTOR_MODE_NONE);
    }

    public function testDetectsFaultyConstraintSelector(): void {
        $service = $this->createService();
        $user1 = new User('', '', '', EUserType::Person);
        $user1->username = 'testuser';
        $user2 = new User('', '', '', EUserType::Person);
        $user2->apId = 'https://remote.local/u/test';

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls {}', $user1, CustomCssService::ENFORCE_SELECTOR_MODE_VERIFY));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss {}', $user1, CustomCssService::ENFORCE_SELECTOR_MODE_VERIFY));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin {}', $user1, CustomCssService::ENFORCE_SELECTOR_MODE_VERIFY));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-a {}', $user1, CustomCssService::ENFORCE_SELECTOR_MODE_VERIFY));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-not_mbin.fediCustomCss-user-a {}', $user1, CustomCssService::ENFORCE_SELECTOR_MODE_VERIFY));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user1).' {}', $user2, CustomCssService::ENFORCE_SELECTOR_MODE_VERIFY));

        // should not throw
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user1).' {}', $user1, CustomCssService::ENFORCE_SELECTOR_MODE_VERIFY);
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user2).' {}', $user2, CustomCssService::ENFORCE_SELECTOR_MODE_VERIFY);
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user2).'.abc {}', $user2, CustomCssService::ENFORCE_SELECTOR_MODE_VERIFY);
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user2).' .abc {}', $user2, CustomCssService::ENFORCE_SELECTOR_MODE_VERIFY);
    }

    public function testDetectsNonMbinSelector(): void {
        $service = $this->createService();
        $user = new User('', '', '', EUserType::Person);
        $user->username = 'testuser';

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-soft-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user).' {}', $user, CustomCssService::ENFORCE_SELECTOR_MODE_VERIFY));

        // should not throw
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-lemmy.fediCustomCss-user-'.$this->expectedUserIdCss($user).' {}', $user, CustomCssService::ENFORCE_SELECTOR_MODE_VERIFY);
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-some123.fediCustomCss-user-'.$this->expectedUserIdCss($user).' {}', $user, CustomCssService::ENFORCE_SELECTOR_MODE_VERIFY);
    }

    public function testAddsConstraintSelector(): void {
        $service = $this->createService();
        $user1 = new User('', '', '', EUserType::Person);
        $user1->username = 'testuser';
        $user2 = new User('', '', '', EUserType::Person);
        $user2->apId = 'https://remote.local/u/test';

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss {}', $user1, CustomCssService::ENFORCE_SELECTOR_MODE_ADD));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin {}', $user1, CustomCssService::ENFORCE_SELECTOR_MODE_ADD));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-a {}', $user1, CustomCssService::ENFORCE_SELECTOR_MODE_ADD));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-not_mbin.fediCustomCss-user-a {}', $user1, CustomCssService::ENFORCE_SELECTOR_MODE_ADD));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user1).' {}', $user2, CustomCssService::ENFORCE_SELECTOR_MODE_ADD));

        $user1Selector = '.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user1);
        self::assertSame(
            $user1Selector.'.cls {}',
            $service->validatePublicCss(' .cls {}', $user1, CustomCssService::ENFORCE_SELECTOR_MODE_ADD)
        );
        self::assertSame(
            '.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user2).'.cls {}',
            $service->validatePublicCss('.cls {}', $user2, CustomCssService::ENFORCE_SELECTOR_MODE_ADD)
        );
        self::assertSame(
            $user1Selector.".cls {}\n".$user1Selector.'.cls2 .a {}',
            $service->validatePublicCss(".cls {}\n.cls2 .a {}", $user1, CustomCssService::ENFORCE_SELECTOR_MODE_ADD)
        );
        self::assertSame(
            $user1Selector.'.cls, '.$user1Selector.'.cls a {}',
            $service->validatePublicCss('.cls, .cls a {}', $user1, CustomCssService::ENFORCE_SELECTOR_MODE_ADD)
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