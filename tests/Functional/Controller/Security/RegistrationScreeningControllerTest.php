<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Security;

use App\DTO\UserDto;
use App\Entity\User;
use App\Enums\EApplicationStatus;
use App\Exception\RegistrationRejectedException;
use App\Service\RegistrationScreening;
use App\Service\SettingsManager;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class RegistrationScreeningControllerTest extends WebTestCase
{
    private MockHttpClient $http;

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], -1);
        SettingsManager::resetDto();
        parent::tearDown();
    }

    private function setupScreening(bool $enabled = true, bool $reject = false, string $body = '{"success":1,"ip":{"value":"91.186.18.61","appears":1,"frequency":5,"confidence":95}}'): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $this->http = new MockHttpClient(new MockResponse($body));
        self::getContainer()->set(RegistrationScreening::class, new RegistrationScreening(
            $this->http, new ArrayAdapter(), new RateLimiterFactory([
                'id' => 'sfs', 'policy' => 'sliding_window', 'limit' => 90000, 'interval' => '24 hours',
            ], new InMemoryStorage()), new NullLogger(), 'test-secret',
        ));
        self::getContainer()->set('limiter.user_register', new RateLimiterFactory([
            'id' => 'registration', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '6 hours',
        ], new InMemoryStorage()));
        $settings = self::getContainer()->get(SettingsManager::class);
        $settings->set('MBIN_STOPFORUMSPAM_ENABLED', $enabled);
        $settings->set('MBIN_STOPFORUMSPAM_AUTO_REJECT', $reject);
        $settings->set('MBIN_STOPFORUMSPAM_MIN_CONFIDENCE', 95.0);
        $settings->set('MBIN_STOPFORUMSPAM_MIN_FREQUENCY', 5);
        $settings->set('MBIN_NEW_USERS_NEED_APPROVAL', false);
        self::getContainer()->get('limiter.user_register')->create('91.186.18.61')->reset();
    }

    private function register(bool $approval = false): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', '/register', server: ['REMOTE_ADDR' => '91.186.18.61']);
        $data = [
            'user_register[username]' => 'ScreenedUser',
            'user_register[email]' => 'screened@example.org',
            'user_register[plainPassword][first]' => 'secret',
            'user_register[plainPassword][second]' => 'secret',
            'user_register[agreeTerms]' => true,
        ];
        if ($approval) {
            $data['user_register[applicationText]'] = 'Please approve this application';
        }
        $client->submit($crawler->filter('form[name=user_register]')->selectButton('Register')->form($data), serverParameters: ['REMOTE_ADDR' => '91.186.18.61']);
    }

    public function testAdminSettingsSaveAndValidation(): void
    {
        $this->setupScreening(false);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $admin = new User('settings@example.org', 'settingsadmin', 'password', \App\Enums\EUserType::Person);
        $admin->roles = ['ROLE_ADMIN'];
        $em->persist($admin);
        $em->flush();
        $client = self::getClient();
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="settings[MBIN_STOPFORUMSPAM_MIN_CONFIDENCE]"][value="95"]');
        $client->submit($crawler->filter('form[name=settings]')->selectButton('Save')->form([
            'settings[MBIN_STOPFORUMSPAM_ENABLED]' => true,
            'settings[MBIN_STOPFORUMSPAM_AUTO_REJECT]' => true,
            'settings[MBIN_STOPFORUMSPAM_MIN_CONFIDENCE]' => '97.5',
            'settings[MBIN_STOPFORUMSPAM_MIN_FREQUENCY]' => '8',
        ]));
        self::assertResponseRedirects();
        $crawler = $client->request('GET', '/admin/settings');
        self::assertSelectorExists('input[name="settings[MBIN_STOPFORUMSPAM_MIN_FREQUENCY]"][value="8"]');
        $client->submit($crawler->filter('form[name=settings]')->selectButton('Save')->form([
            'settings[MBIN_STOPFORUMSPAM_MIN_FREQUENCY]' => '0',
        ]));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#main', 'This value should be positive');
        $row = self::getContainer()->get(EntityManagerInterface::class)->getRepository(\App\Entity\Settings::class)->findOneBy(['name' => 'MBIN_STOPFORUMSPAM_MIN_FREQUENCY']);
        self::assertSame('8', $row->value);
    }

    public function testDisabledDoesNotLookup(): void
    {
        $this->setupScreening(false, true);
        $this->register();
        self::assertResponseRedirects('/login');
        self::assertSame(0, $this->http->getRequestsCount());
        self::assertEmailCount(1);
    }

    public function testRejectionCreatesNoAccountOrMessages(): void
    {
        $this->setupScreening(true, true);
        $this->register();
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#main', 'Registration could not be accepted');
        self::assertNull(self::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['username' => 'ScreenedUser']));
        self::assertEmailCount(0);
    }

    public function testUnavailableLookupContinuesRegistration(): void
    {
        $this->setupScreening(true, true, '{"success":0,"error":"unavailable"}');
        $this->register();
        self::assertResponseRedirects('/login');
        self::assertEmailCount(1);
    }

    #[DataProvider('decisions')]
    public function testPendingResultEmailAndRetention(string $decision): void
    {
        $this->setupScreening();
        $settings = self::getContainer()->get(SettingsManager::class);
        $settings->set('MBIN_NEW_USERS_NEED_APPROVAL', true);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['admin' => true, 'silent' => false, 'moderator' => true] as $name => $notify) {
            $admin = new User($name.'@example.org', $name, 'password', \App\Enums\EUserType::Person);
            $admin->roles = ['moderator' === $name ? 'ROLE_MODERATOR' : 'ROLE_ADMIN'];
            $admin->notifyOnUserSignup = $notify;
            $em->persist($admin);
        }
        $em->flush();
        $this->register(true);
        self::assertResponseRedirects('/login');
        self::assertEmailCount(2);
        $email = $this->getMailerMessage();
        self::assertEmailHeaderSame($email, 'To', 'admin@example.org');
        self::assertStringContainsString('91.186.18.61', $email->getHtmlBody());
        self::assertStringContainsString('95%', $email->getHtmlBody());
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'ScreenedUser']);
        self::assertSame(EApplicationStatus::Pending, $user->getApplicationStatus());
        self::assertSame('matched', $user->getRegistrationScreening()['status']);
        self::getClient()->loginUser($em->getRepository(User::class)->findOneBy(['username' => 'admin']));
        self::getClient()->request('GET', '/admin/signup_requests');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#main', '91.186.18.61');
        $user = self::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['username' => 'ScreenedUser']);
        $manager = self::getContainer()->get(UserManager::class);
        if ('deleteRequest' === $decision) {
            $manager->deleteRequest($user, false);
        } else {
            $manager->$decision($user);
        }
        self::assertNull($user->getRegistrationScreening());
    }

    public static function decisions(): iterable
    {
        yield 'approval' => ['approveUserApplication'];
        yield 'rejection' => ['rejectUserApplication'];
        yield 'deletion request' => ['deleteRequest'];
    }

    public function testRateLimitRunsBeforeLookup(): void
    {
        $this->setupScreening(true, true);
        self::getContainer()->get('limiter.user_register')->create('91.186.18.61')->consume(2);
        $this->register();
        self::assertResponseStatusCodeSame(429);
        self::assertSame(0, $this->http->getRequestsCount());
    }

    #[DataProvider('creationPaths')]
    public function testSharedCreationBoundary(bool $public, bool $remote, bool $trusted = false): void
    {
        $this->setupScreening(true, true);
        $stack = self::getContainer()->get(RequestStack::class);
        if ($trusted) {
            Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_FOR);
        }
        $stack->push(Request::create('/oauth/callback', server: [
            'REMOTE_ADDR' => $trusted ? '10.0.0.1' : '91.186.18.61',
            'HTTP_X_FORWARDED_FOR' => $trusted ? '91.186.18.61' : '1.2.3.4',
            'HTTP_CF_CONNECTING_IP' => '1.2.3.4',
            'HTTP_FASTLY_CLIENT_IP' => '1.2.3.4',
        ]));
        $dto = UserDto::create('BoundaryUser', 'boundary@example.org', apId: $remote ? 'https://remote.example/u/boundary' : null);
        $dto->plainPassword = 'secret';
        if ($public && !$remote) {
            $this->expectException(RegistrationRejectedException::class);
        }
        try {
            self::getContainer()->get(UserManager::class)->create($dto, false, rateLimit: false, preApprove: true, publicRegistration: $public);
            self::assertSame(0, $this->http->getRequestsCount());
        } finally {
            if ($public && !$remote) {
                self::assertSame('91.186.18.61', $dto->ip);
                self::assertSame(1, $this->http->getRequestsCount());
            }
            $stack->pop();
        }
    }

    public static function creationPaths(): iterable
    {
        yield 'SSO-like public signup' => [true, false];
        yield 'trusted proxy' => [true, false, true];
        yield 'operator creation' => [false, false];
        yield 'federated account' => [false, true];
    }
}
