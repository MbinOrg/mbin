<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\SettingsManager;
use App\Utils\DownvotesMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Psr\Log\LoggerInterface;
use App\Repository\SettingsRepository;
use App\Repository\InstanceRepository;

class SettingsManagerTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        // Reset static DTO to avoid leaking settings between tests
        SettingsManager::resetDto();
    }

    public function testGetMaxImageByteStringDefault(): void
    {
        SettingsManager::resetDto();

        // settings repository returns no override
        $settingsRepository = $this->createStub(SettingsRepository::class);
        $settingsRepository->method('findAll')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $requestStack = $this->createStub(RequestStack::class);
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('prod');
        $instanceRepository = $this->createStub(InstanceRepository::class);
        $logger = $this->createStub(LoggerInterface::class);

        $manager = new SettingsManager(
            entityManager: $entityManager,
            repository: $settingsRepository,
            requestStack: $requestStack,
            kernel: $kernel,
            instanceRepository: $instanceRepository,
            kbinDomain: 'domain.tld',
            kbinTitle: 'title',
            kbinMetaTitle: 'meta title',
            kbinMetaDescription: 'meta description',
            kbinMetaKeywords: 'meta keywords',
            kbinDefaultLang: 'en',
            kbinContactEmail: 'contact@domain.tld',
            kbinSenderEmail: 'sender@domain.tld',
            mbinDefaultTheme: 'light',
            kbinJsEnabled: true,
            kbinFederationEnabled: true,
            kbinRegistrationsEnabled: true,
            kbinHeaderLogo: true,
            kbinCaptchaEnabled: true,
            kbinFederationPageEnabled: true,
            kbinAdminOnlyOauthClients: true,
            mbinSsoOnlyMode: false,
            mbinMaxImageBytes: 1500000,
            mbinDownvotesMode: DownvotesMode::Enabled,
            mbinNewUsersNeedApproval: false,
            logger: $logger,
            mbinUseFederationAllowList: false
        );

        // SUT
        $this->assertSame('1.5 MB', $manager->getMaxImageByteString());
    }

    public function testGetMaxImageByteStringOverridden(): void
    {
        SettingsManager::resetDto();

        // settings repository returns an override for MBIN_MAX_IMAGE_BYTES
        // and should ignore the default configured in the constructor
        $overrideResult = (object) ['name' => 'MBIN_MAX_IMAGE_BYTES', 'value' => '1572864'];
        $settingsRepository = $this->createStub(SettingsRepository::class);
        $settingsRepository->method('findAll')->willReturn([$overrideResult]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $requestStack = $this->createStub(RequestStack::class);
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('prod');
        $instanceRepository = $this->createStub(InstanceRepository::class);
        $logger = $this->createStub(LoggerInterface::class);

        $manager = new SettingsManager(
            entityManager: $entityManager,
            repository: $settingsRepository,
            requestStack: $requestStack,
            kernel: $kernel,
            instanceRepository: $instanceRepository,
            kbinDomain: 'domain.tld',
            kbinTitle: 'title',
            kbinMetaTitle: 'meta title',
            kbinMetaDescription: 'meta description',
            kbinMetaKeywords: 'meta keywords',
            kbinDefaultLang: 'en',
            kbinContactEmail: 'contact@domain.tld',
            kbinSenderEmail: 'sender@domain.tld',
            mbinDefaultTheme: 'light',
            kbinJsEnabled: true,
            kbinFederationEnabled: true,
            kbinRegistrationsEnabled: true,
            kbinHeaderLogo: true,
            kbinCaptchaEnabled: true,
            kbinFederationPageEnabled: true,
            kbinAdminOnlyOauthClients: true,
            mbinSsoOnlyMode: false,
            mbinMaxImageBytes: 1500000,
            mbinDownvotesMode: DownvotesMode::Enabled,
            mbinNewUsersNeedApproval: false,
            logger: $logger,
            mbinUseFederationAllowList: false
        );

        // SUT
        $this->assertSame('1.57 MB', $manager->getMaxImageByteString());
    }
}
