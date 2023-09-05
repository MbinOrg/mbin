<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UserCommandTest extends KernelTestCase
{
    private Command $command;
    private ?UserRepository $repository;

    public function testCreateUser(): void
    {
        $tester = new CommandTester($this->command);
        $tester->execute(
            [
                'username' => 'actor',
                'email' => 'contact@example.com',
                'password' => 'secret',
            ]
        );

        $this->assertStringContainsString('A user has been created.', $tester->getDisplay());
        $this->assertInstanceOf(User::class, $this->repository->findOneByUsername('actor'));
    }

    public function testCreateAdminUser(): void
    {
        $tester = new CommandTester($this->command);
        $tester->execute(
            [
                'username' => 'actor',
                'email' => 'contact@example.com',
                'password' => 'secret',
                '--admin' => true,
            ],
        );

        $this->assertStringContainsString('A user has been created.', $tester->getDisplay());

        $actor = $this->repository->findOneByUsername('actor');
        $this->assertInstanceOf(User::class, $actor);
        $this->assertTrue($actor->isAdmin());
    }

    protected function setUp(): void
    {
        $application = new Application(self::bootKernel());

        $this->command = $application->find('kbin:user:create');
        $this->repository = $this->getContainer()->get(UserRepository::class);
    }
}
