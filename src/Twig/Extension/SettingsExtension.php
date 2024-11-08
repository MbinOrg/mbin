<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\SettingsExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SettingsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('kbin_domain', [SettingsExtensionRuntime::class, 'kbinDomain']),
            new TwigFunction('kbin_title', [SettingsExtensionRuntime::class, 'kbinTitle']),
            new TwigFunction('kbin_meta_title', [SettingsExtensionRuntime::class, 'kbinMetaTitle']),
            new TwigFunction('kbin_meta_description', [SettingsExtensionRuntime::class, 'kbinDescription']),
            new TwigFunction('kbin_meta_keywords', [SettingsExtensionRuntime::class, 'kbinKeywords']),
            new TwigFunction('kbin_default_lang', [SettingsExtensionRuntime::class, 'kbinDefaultLang']),
            new TwigFunction('mbin_default_theme', [SettingsExtensionRuntime::class, 'mbinDefaultTheme']),
            new TwigFunction('kbin_registrations_enabled', [SettingsExtensionRuntime::class, 'kbinRegistrationsEnabled']),
            new TwigFunction('mbin_sso_registrations_enabled', [SettingsExtensionRuntime::class, 'mbinSsoRegistrationsEnabled']),
            new TwigFunction('mbin_sso_only_mode', [SettingsExtensionRuntime::class, 'mbinSsoOnlyMode']),
            new TwigFunction('kbin_header_logo', [SettingsExtensionRuntime::class, 'kbinHeaderLogo']),
            new TwigFunction('kbin_captcha_enabled', [SettingsExtensionRuntime::class, 'kbinCaptchaEnabled']),
            new TwigFunction('kbin_mercure_enabled', [SettingsExtensionRuntime::class, 'kbinMercureEnabled']),
            new TwigFunction('kbin_federation_page_enabled', [SettingsExtensionRuntime::class, 'kbinFederationPageEnabled']),
            new TwigFunction('mbin_downvotes_mode', [SettingsExtensionRuntime::class, 'mbinDownvotesMode']),
            new TwigFunction('mbin_current_version', [SettingsExtensionRuntime::class, 'mbinCurrentVersion']),
            new TwigFunction('mbin_restrict_magazine_creation', [SettingsExtensionRuntime::class, 'mbinRestrictMagazineCreation']),
            new TwigFunction('mbin_private_instance', [SettingsExtensionRuntime::class, 'mbinPrivateInstance']),
            new TwigFunction('mbin_sso_show_first', [SettingsExtensionRuntime::class, 'mbinSsoShowFirst']),
            new TwigFunction('mbin_lang', [SettingsExtensionRuntime::class, 'mbinLang']),
        ];
    }
}
