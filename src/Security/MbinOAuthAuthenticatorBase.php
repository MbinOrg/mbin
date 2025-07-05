<?php

declare(strict_types=1);

namespace App\Security;

use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

abstract class MbinOAuthAuthenticatorBase extends OAuth2Authenticator
{
    public function __construct(
        protected readonly RouterInterface $router,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $session = $request->getSession();
        if ($url = $session->get('_security.main.target_path')) {
            $targetUrl = $url;
        } elseif ($session->get('is_newly_created')) {
            $targetUrl = $this->router->generate('user_settings_profile');
            $session->remove('is_newly_created');
        } else {
            $targetUrl = $this->router->generate('front');
        }

        return new RedirectResponse($targetUrl);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        if ('MBIN_SSO_REGISTRATIONS_ENABLED' === $message) {
            $session = $request->getSession();
            $session->getFlashBag()->add('error', 'sso_registrations_enabled.error');

            return new RedirectResponse($this->router->generate('app_login'));
        }

        return new Response($message, Response::HTTP_FORBIDDEN);
    }
}
