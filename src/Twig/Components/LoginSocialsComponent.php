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
        #[Autowire('%oauth_keycloak_id%')]
        private readonly ?string $oauthKeycloakId,
        #[Autowire('%oauth_zitadel_id%')]
        private readonly ?string $oauthZitadelId,
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

    public function keycloakEnabled(): bool
    {
        return !empty($this->oauthKeycloakId);
    }

    public function zitadelEnabled(): bool
    {
        return !empty($this->oauthZitadelId);
    }
}
