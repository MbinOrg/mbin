<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Service\ProjectInfoService;
use App\Service\SettingsManager;
use JetBrains\PhpStorm\Pure;
use Twig\Extension\RuntimeExtensionInterface;

class SettingsExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly SettingsManager $settings,
        private readonly ProjectInfoService $projectInfo
    ) {
    }

    #[Pure]
    public function kbinDomain(): string
    {
        return $this->settings->get('KBIN_DOMAIN');
    }

    public function kbinTitle(): string
    {
        return $this->settings->get('KBIN_TITLE');
    }

    #[Pure]
    public function kbinMetaTitle(): string
    {
        return $this->settings->get('KBIN_META_TITLE');
    }

    #[Pure]
    public function kbinDescription(): string
    {
        return $this->settings->get('KBIN_META_DESCRIPTION');
    }

    #[Pure]
    public function kbinKeywords(): string
    {
        return $this->settings->get('KBIN_META_KEYWORDS');
    }

    #[Pure]
    public function kbinRegistrationsEnabled(): bool
    {
        return $this->settings->get('KBIN_REGISTRATIONS_ENABLED');
    }

    #[Pure]
    public function mbinSsoRegistrationsEnabled(): bool
    {
        return $this->settings->get('MBIN_SSO_REGISTRATIONS_ENABLED');
    }

    public function mbinSsoOnlyMode(): bool
    {
        return $this->settings->get('MBIN_SSO_ONLY_MODE');
    }

    public function kbinDefaultLang(): string
    {
        return $this->settings->get('KBIN_DEFAULT_LANG');
    }

    #[Pure]
    public function mbinDefaultTheme(): string
    {
        return $this->settings->get('MBIN_DEFAULT_THEME');
    }

    public function kbinHeaderLogo(): bool
    {
        return $this->settings->get('KBIN_HEADER_LOGO');
    }

    public function kbinCaptchaEnabled(): bool
    {
        return $this->settings->get('KBIN_CAPTCHA_ENABLED');
    }

    public function kbinMercureEnabled(): bool
    {
        return $this->settings->get('KBIN_MERCURE_ENABLED');
    }

    public function kbinFederationPageEnabled(): bool
    {
        return $this->settings->get('KBIN_FEDERATION_PAGE_ENABLED');
    }

    public function kbinFederatedSearchOnlyLoggedIn(): bool
    {
        return $this->settings->get('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN');
    }

    public function mbinCurrentVersion(): string
    {
        return $this->projectInfo->getVersion();
    }

    public function mbinRestrictMagazineCreation(): bool
    {
        return $this->settings->get('MBIN_RESTRICT_MAGAZINE_CREATION');
    }

    public function mbinPrivateInstance(): bool
    {
        return $this->settings->get('MBIN_PRIVATE_INSTANCE');
    }

    public function mbinSsoShowFirst(): bool
    {
        return $this->settings->get('MBIN_SSO_SHOW_FIRST');
    }

    public function mbinLang(): string
    {
        return $this->settings->getLocale();
    }
}
