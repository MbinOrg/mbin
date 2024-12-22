<?php

declare(strict_types=1);

namespace App\Security;

use App\DTO\UserDto;
use App\Entity\Image;
use App\Entity\User;
use App\Factory\ImageFactory;
use App\Repository\ImageRepository;
use App\Service\ImageManager;
use App\Service\IpResolver;
use App\Service\SettingsManager;
use App\Service\UserManager;
use App\Utils\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\FacebookUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class FacebookAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly RouterInterface $router,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserManager $userManager,
        private readonly ImageManager $imageManager,
        private readonly ImageFactory $imageFactory,
        private readonly ImageRepository $imageRepository,
        private readonly IpResolver $ipResolver,
        private readonly Slugger $slugger,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'oauth_facebook_verify' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('facebook');
        $slugger = $this->slugger;

        $accessToken = $this->fetchAccessToken($client);

        try {
            $provider = $client->getOAuth2Provider();
            $accessToken = $provider->getLongLivedAccessToken($accessToken->getToken());
        } catch (\Exception $e) {
        }

        $rememberBadge = new RememberMeBadge();
        $rememberBadge = $rememberBadge->enable();

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $slugger, $request) {
                /** @var FacebookUser $facebookUser */
                $facebookUser = $client->fetchUserFromToken($accessToken);

                $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(
                    ['oauthFacebookId' => $facebookUser->getId()]
                );

                if ($existingUser) {
                    return $existingUser;
                }

                $user = $this->entityManager->getRepository(User::class)->findOneBy(
                    ['email' => $facebookUser->getEmail()]
                );

                if ($user) {
                    $user->oauthFacebookId = $facebookUser->getId();

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    return $user;
                }

                if (false === $this->settingsManager->get('MBIN_SSO_REGISTRATIONS_ENABLED')) {
                    throw new CustomUserMessageAuthenticationException('MBIN_SSO_REGISTRATIONS_ENABLED');
                }

                $dto = (new UserDto())->create(
                    $slugger->slug($facebookUser->getName()).rand(1, 999),
                    $facebookUser->getEmail()
                );

                $avatar = $this->getAvatar($facebookUser->getPictureUrl());

                if ($avatar) {
                    $dto->avatar = $this->imageFactory->createDto($avatar);
                }

                $dto->plainPassword = bin2hex(random_bytes(20));
                $dto->ip = $this->ipResolver->resolve();

                $user = $this->userManager->create($dto, false);
                $user->oauthFacebookId = $facebookUser->getId();
                $user->avatar = $this->getAvatar($facebookUser->getPictureUrl());
                $user->isVerified = true;

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $request->getSession()->set('is_newly_created', true);

                return $user;
            }),
            [
                $rememberBadge,
            ]
        );
    }

    private function getAvatar(?string $pictureUrl): ?Image
    {
        if (!$pictureUrl) {
            return null;
        }

        try {
            $tempFile = $this->imageManager->download($pictureUrl);
        } catch (\Exception $e) {
            $tempFile = null;
        }

        if ($tempFile) {
            $image = $this->imageRepository->findOrCreateFromPath($tempFile);
            if ($image) {
                $this->entityManager->persist($image);
                $this->entityManager->flush();
            }
        }

        return $image ?? null;
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

        if ('MBIN_SSO_REGISTRATIONS_ENABLED' === $message) {
            $session = $request->getSession();
            $session->getFlashBag()->add('error', 'sso_registrations_enabled.error');

            return new RedirectResponse($this->router->generate('app_login'));
        }

        return new Response($message, Response::HTTP_FORBIDDEN);
    }
}
