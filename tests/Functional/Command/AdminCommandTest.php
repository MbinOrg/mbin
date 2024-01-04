<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\DTO\UserDto;
use App\Repository\UserRepository;
use App\Service\UserManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class AdminCommandTest extends KernelTestCase
{
    private Command $command;
    private ?UserRepository $repository;

    public function testCreateUser(): void
    {
        $dto = (new UserDto())->create('actor', 'contact@example.com');
        $dto->plainPassword = 'secret';

        $this->getContainer()->get(UserManager::class)
            ->create($dto, false);

        $this->assertFalse($this->repository->findOneByUsername('actor')->isAdmin());

        $tester = new CommandTester($this->command);
        $tester->execute(['username' => 'actor']);

        $this->assertStringContainsString('Administrator privileges have been granted.', $tester->getDisplay());
        $this->assertTrue($this->repository->findOneByUsername('actor')->isAdmin());
    }

    protected function setUp(): void
    {
        $application = new Application(self::bootKernel());

        $this->command = $application->find('mbin:user:admin');
        $this->repository = $this->getContainer()->get(UserRepository::class);
    }
}
