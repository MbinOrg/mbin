<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Enums\EApplicationStatus;
use App\Enums\EUserType;
use App\Message\RegistrationApprovalEmailMessage;
use App\MessageHandler\RegistrationApprovalEmailHandler;
use App\Repository\UserRepository;
use App\Service\SettingsManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationApprovalEmailHandlerTest extends TestCase
{
    public function testScreeningSnapshotIsNotSerialized(): void
    {
        $user = new User('user@example.org', 'applicant', 'password', EUserType::Person);
        $user->setRegistrationScreening(['ip' => '91.186.18.61', 'status' => 'matched']);
        $metadata = new \Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory(new \Symfony\Component\Serializer\Mapping\Loader\AttributeLoader());
        $normalizer = new \Symfony\Component\Serializer\Normalizer\ObjectNormalizer($metadata);
        self::assertSame([], $normalizer->normalize($user, context: [
            \Symfony\Component\Serializer\Normalizer\AbstractNormalizer::ATTRIBUTES => ['registrationScreening'],
        ]));
    }

    #[DataProvider('deliveryCases')]
    public function testDeliveryChecksCurrentEligibility(bool $enabled, bool $approvalEnabled, bool $adminRole, bool $notify, EApplicationStatus $status, bool $send): void
    {
        $user = new User('user@example.org', 'applicant', 'password', EUserType::Person, applicationStatus: $status);
        $user->setRegistrationScreening(['ip' => '91.186.18.61', 'status' => 'matched']);
        $admin = new User('admin@example.org', 'admin', 'password', EUserType::Person);
        $admin->roles = $adminRole ? ['ROLE_ADMIN'] : ['ROLE_MODERATOR'];
        $admin->notifyOnUserSignup = $notify;
        $repository = $this->createStub(UserRepository::class);
        $repository->method('find')->willReturnCallback(fn ($id): User => 1 === $id ? $user : $admin);
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(fn (string $sql, array $params): string => ('MBIN_STOPFORUMSPAM_ENABLED' === $params['name'] ? $enabled : $approvalEnabled) ? 'true' : 'false');
        $settings = $this->createStub(SettingsManager::class);
        $settings->method('get')->willReturnCallback(fn (string $name): string => 'KBIN_SENDER_EMAIL' === $name ? 'noreply@example.org' : 'example.org');
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Registration awaiting approval');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($send ? self::once() : self::never())->method('send')->with(self::callback(function (TemplatedEmail $email): bool {
            self::assertSame('admin@example.org', $email->getTo()[0]->getAddress());
            self::assertSame('91.186.18.61', $email->getContext()['screening']['ip']);

            return true;
        }));
        $handler = new RegistrationApprovalEmailHandler($this->createStub(EntityManagerInterface::class), $this->createStub(KernelInterface::class), $repository, $settings, $mailer, $translator, $connection);
        $handler->doWork(new RegistrationApprovalEmailMessage(1, 2));
    }

    public static function deliveryCases(): iterable
    {
        yield 'pending opted-in admin' => [true, true, true, true, EApplicationStatus::Pending, true];
        yield 'disabled after enqueue' => [false, true, true, true, EApplicationStatus::Pending, false];
        yield 'approval disabled' => [true, false, true, true, EApplicationStatus::Pending, false];
        yield 'moderator' => [true, true, false, true, EApplicationStatus::Pending, false];
        yield 'opted out' => [true, true, true, false, EApplicationStatus::Pending, false];
        yield 'already approved' => [true, true, true, true, EApplicationStatus::Approved, false];
        yield 'already rejected' => [true, true, true, true, EApplicationStatus::Rejected, false];
    }
}
