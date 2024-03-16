<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Controller\AbstractController;
use App\Form\UserAccountDeletionType;
use App\Service\IpResolver;
use App\Service\UserManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class AccountDeletionController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly UserManager $userManager,
        private readonly RateLimiterFactory $userDeleteLimiter,
        private readonly IpResolver $ipResolver,
        private readonly LoggerInterface $logger,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('edit_profile', $this->getUserOrThrow());

        $form = $this->createForm(UserAccountDeletionType::class);
        $user = $this->getUserOrThrow();
        try {
            // Could throw an error
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->has('currentPassword')) {
                if (!$this->userPasswordHasher->isPasswordValid($user, $form->get('currentPassword')->getData())) {
                    $form->get('currentPassword')->addError(new FormError($this->translator->trans('Password is invalid')));
                }
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $limiter = $this->userDeleteLimiter->create($this->ipResolver->resolve());
                if (false === $limiter->consume()->isAccepted()) {
                    throw new TooManyRequestsHttpException();
                }
                $this->userManager->deleteRequest($user, true === $form->get('instantDelete')->getData());
                $this->security->logout(false);

                return $this->redirect('/');
            }
        } catch (\Exception $e) {
            // Show an error to the user
            $this->logger->error('An error occurred during account deletion of user {username}: {error}', ['username' => $user->username, 'error' => \get_class($e).': '.$e->getMessage()]);
            $this->addFlash('error', 'flash_user_settings_general_error');
        }

        return $this->render(
            'user/settings/account_deletion.html.twig',
            ['user' => $user, 'form' => $form->createView()],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
