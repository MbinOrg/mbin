<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('login_socials')]
final class LoginSocialsComponent
{
    public function __construct(
        #[Autowire('%oauth_google_id%')]
        private readonly ?string $oauthGoogleId,
        #[Autowire('%oauth_facebook_id%')]
        private readonly ?string $oauthFacebookId,
        #[Autowire('%oauth_github_id%')]
        private readonly ?string $oauthGithubId,
        #[Autowire('%oauth_privacyportal_id%')]
        private readonly ?string $oauthPrivacyPortalId,
        #[Autowire('%oauth_keycloak_id%')]
        private readonly ?string $oauthKeycloakId,
        #[Autowire('%oauth_simplelogin_id%')]
        private readonly ?string $oauthSimpleLoginId,
        #[Autowire('%oauth_zitadel_id%')]
        private readonly ?string $oauthZitadelId,
        #[Autowire('%oauth_authentik_id%')]
        private readonly ?string $oauthAuthentikId,
        #[Autowire('%oauth_azure_id%')]
        private readonly ?string $oauthAzureId,
    ) {
    }

    public function googleEnabled(): bool
    {
        return !empty($this->oauthGoogleId);
    }

    public function facebookEnabled(): bool
    {
        return !empty($this->oauthFacebookId);
    }

    public function githubEnabled(): bool
    {
        return !empty($this->oauthGithubId);
    }

    public function privacyPortalEnabled(): bool
    {
        return !empty($this->oauthPrivacyPortalId);
    }

    public function keycloakEnabled(): bool
    {
        return !empty($this->oauthKeycloakId);
    }

    public function simpleloginEnabled(): bool
    {
        return !empty($this->oauthSimpleLoginId);
    }

    public function zitadelEnabled(): bool
    {
        return !empty($this->oauthZitadelId);
    }

    public function authentikEnabled(): bool
    {
        return !empty($this->oauthAuthentikId);
    }

    public function azureEnabled(): bool
    {
        return !empty($this->oauthAzureId);
    }
}
