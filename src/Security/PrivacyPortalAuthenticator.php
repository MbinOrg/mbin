<?php

declare(strict_types=1);

namespace App\Security;

use App\DTO\UserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\IpResolver;
use App\Service\SettingsManager;
use App\Service\UserManager;
use App\Utils\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\PrivacyPortalResourceOwner;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class PrivacyPortalAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly RouterInterface $router,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserManager $userManager,
        private readonly SettingsManager $settingsManager,
        private readonly UserRepository $userRepository,
        private readonly IpResolver $ipResolver,
        private readonly Slugger $slugger
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'oauth_privacyportal_verify' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('privacyportal');
        $accessToken = $this->fetchAccessToken($client);
        $slugger = $this->slugger;

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $slugger, $request) {
                /** @var PrivacyPortalResourceOwner $privacyPortalUser */
                $privacyPortalUser = $client->fetchUserFromToken($accessToken);

                $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(
                    ['oauthPrivacyPortalId' => (string) $privacyPortalUser->getId()]
                );

                if ($existingUser) {
                    return $existingUser;
                }

                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $privacyPortalUser->getEmail()]
                );

                if ($user) {
                    $user->oauthPrivacyPortalId = (string) $privacyPortalUser->getId();

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    return $user;
                }

                if (false === $this->settingsManager->get('MBIN_SSO_REGISTRATIONS_ENABLED')) {
                    throw new CustomUserMessageAuthenticationException('MBIN_SSO_REGISTRATIONS_ENABLED');
                }

                $username = $slugger->slug($privacyPortalUser->getName());

                if ($this->userRepository->count(['username' => $username]) > 0) {
                    $username .= rand(1, 9999);
                    $request->getSession()->set('is_newly_created', true);
                }

                $dto = (new UserDto())->create(
                    $username,
                    $privacyPortalUser->getEmail()
                );

                $dto->plainPassword = bin2hex(random_bytes(20));
                $dto->ip = $this->ipResolver->resolve();

                $user = $this->userManager->create($dto, false);
                $user->oauthPrivacyPortalId = (string) $privacyPortalUser->getId();
                $user->isVerified = true;

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($request->getSession()->get('is_newly_created')) {
            $targetUrl = $this->router->generate('user_settings_profile');
            $request->getSession()->remove('is_newly_created');
        } else {
            $targetUrl = $this->router->generate('front');
        }

        return new RedirectResponse($targetUrl);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }
}
