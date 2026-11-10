<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Enums\EUserType;
use App\Factory\ActivityPub\PersonFactory;
use App\Service\CustomCssProfile;
use App\Service\CustomCssService;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CustomCssServiceTest extends TestCase
{

    private CustomCssProfile $testProfileNoVerify;
    private CustomCssProfile $testProfileSelectorVerify;
    private CustomCssProfile $testProfileSelectorAdd;

    #[\Override]
    public function setUp(): void {
        // only test the strictest settings
        $this->testProfileNoVerify = new CustomCssProfile(CustomCssProfile::ENFORCE_SELECTOR_MODE_NONE, 4, true, false, false, false, false);
        $this->testProfileSelectorVerify = new CustomCssProfile(CustomCssProfile::ENFORCE_SELECTOR_MODE_VERIFY, 4, true, false, false, false, false);
        $this->testProfileSelectorAdd = new CustomCssProfile(CustomCssProfile::ENFORCE_SELECTOR_MODE_ADD, 4, true, false, false, false, false);
    }

    public function testDetectAtRules(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@charset "UTF-8"', $this->testProfileNoVerify, null));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@import "../style.css"', $this->testProfileNoVerify, null));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@keyframes kf {}', $this->testProfileNoVerify, null));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('@somerule r {}', $this->testProfileNoVerify, null));
    }

    public function testDetectInvalidSyntax(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('html {color: blue} .fail {invalid;}', $this->testProfileNoVerify, null));
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

        self::assertSame(".classA > .classB {prop1: 0;prop2: #000;}\n.any h1 {}", $service->validatePublicCss($css, $this->testProfileNoVerify, null));
    }

    public function testDetectsWildcardSelector(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('* {}', $this->testProfileNoVerify, null));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls * {}', $this->testProfileNoVerify, null));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('a, .cls * {}', $this->testProfileNoVerify, null));
    }

    public function testDetectsSelectorNotStartsWithClass(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('div .cls {}', $this->testProfileNoVerify, null));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss(':hover {}', $this->testProfileNoVerify, null));
    }

    public function testDetectsUrlValues(): void {
        $service = $this->createService();

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls { background-image: url("/image.png") }', $this->testProfileNoVerify, null));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls { content: url("/image.png") }', $this->testProfileNoVerify, null));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls { content: "a" url("/image.png")/"alt" }', $this->testProfileNoVerify, null));

        // should not throw
        $service->validatePublicCss('.cls { background-image: url("data:image/png;base64;......") }', $this->testProfileNoVerify, null);
        $service->validatePublicCss('.cls { content: "a" url("data:image/png;base64;......") }', $this->testProfileNoVerify, null);
    }

    public function testDetectsFaultyConstraintSelector(): void {
        $service = $this->createService();
        $user1 = new User('', '', '', EUserType::Person);
        $user1->username = 'testuser';
        $user2 = new User('', '', '', EUserType::Person);
        $user2->apId = 'https://remote.local/u/test';

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.cls {}', $this->testProfileSelectorVerify, $user1));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss {}', $this->testProfileSelectorVerify, $user1));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin {}', $this->testProfileSelectorVerify, $user1));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-a {}', $this->testProfileSelectorVerify, $user1));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-not_mbin.fediCustomCss-user-a {}', $this->testProfileSelectorVerify, $user1));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user1).' {}', $this->testProfileSelectorVerify, $user2));

        // should not throw
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user1).' {}', $this->testProfileSelectorVerify, $user1);
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user2).' {}', $this->testProfileSelectorVerify, $user2);
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user2).'.abc {}', $this->testProfileSelectorVerify, $user2);
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user2).' .abc {}', $this->testProfileSelectorVerify, $user2);
    }

    public function testDetectsNonMbinSelector(): void {
        $service = $this->createService();
        $user = new User('', '', '', EUserType::Person);
        $user->username = 'testuser';

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-soft-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user).' {}', $this->testProfileSelectorVerify, $user));

        // should not throw
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-lemmy.fediCustomCss-user-'.$this->expectedUserIdCss($user).' {}', $this->testProfileSelectorVerify, $user);
        $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-some123.fediCustomCss-user-'.$this->expectedUserIdCss($user).' {}', $this->testProfileSelectorVerify, $user);
    }

    public function testAddsConstraintSelector(): void {
        $service = $this->createService();
        $user1 = new User('', '', '', EUserType::Person);
        $user1->username = 'testuser';
        $user2 = new User('', '', '', EUserType::Person);
        $user2->apId = 'https://remote.local/u/test';

        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss {}', $this->testProfileSelectorAdd, $user1));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin {}', $this->testProfileSelectorAdd, $user1));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-a {}', $this->testProfileSelectorAdd, $user1));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-not_mbin.fediCustomCss-user-a {}', $this->testProfileSelectorAdd, $user1));
        self::assertThrows(BadRequestHttpException::class,
            fn() => $service->validatePublicCss('.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user1).' {}', $this->testProfileSelectorAdd, $user2));

        $user1Selector = '.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user1);
        self::assertSame(
            $user1Selector.'.cls {}',
            $service->validatePublicCss(' .cls {}', $this->testProfileSelectorAdd, $user1)
        );
        self::assertSame(
            '.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-'.$this->expectedUserIdCss($user2).'.cls {}',
            $service->validatePublicCss('.cls {}', $this->testProfileSelectorAdd, $user2)
        );
        self::assertSame(
            $user1Selector.".cls {}\n".$user1Selector.'.cls2 .a {}',
            $service->validatePublicCss(".cls {}\n.cls2 .a {}", $this->testProfileSelectorAdd, $user1)
        );
        self::assertSame(
            $user1Selector.'.cls, '.$user1Selector.'.cls a {}',
            $service->validatePublicCss('.cls, .cls a {}', $this->testProfileSelectorAdd, $user1)
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