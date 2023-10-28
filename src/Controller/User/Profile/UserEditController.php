<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Controller\AbstractController;
use App\DTO\UserDto;
use App\Form\UserBasicType;
use App\Form\UserEmailType;
use App\Form\UserPasswordType;
use App\Service\UserManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserEditController extends AbstractController
{
    public function __construct(
        private readonly UserManager $manager,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function profile(Request $request): Response
    {
        $this->denyAccessUnlessGranted('edit_profile', $this->getUserOrThrow());

        $dto = $this->manager->createDto($this->getUserOrThrow());

        $form = $this->handleForm($this->createForm(UserBasicType::class, $dto), $dto, $request);
        if (null === $form) {
            $this->addFlash('error', 'flash_user_edit_profile_error');
        } else {
            if (!$form instanceof FormInterface) {
                $this->addFlash('success', 'flash_user_edit_profile_success');
                return $form;
            }
            if ($form->isSubmitted() && $form->isValid()) {
                $this->addFlash('success', 'flash_user_edit_profile_success');
            }
        }

        return $this->render(
            'user/settings/profile.html.twig',
            [
                'form' => $form->createView(),
            ],
            new Response(
                null,
                $form->isSubmitted() && !$form->isValid() ? 422 : 200
            )
        );
    }

    #[IsGranted('ROLE_USER')]
    public function email(Request $request): Response
    {
        $this->denyAccessUnlessGranted('edit_profile', $this->getUserOrThrow());

        $dto = $this->manager->createDto($this->getUserOrThrow());

        $form = $this->handleForm($this->createForm(UserEmailType::class, $dto), $dto, $request);
        if (null === $form) {
            $this->addFlash('error', 'flash_user_edit_email_error');
        } else {
            if (!$form instanceof FormInterface) {
                return $form;
            }
        }

        return $this->render(
            'user/settings/email.html.twig',
            [
                'form' => $form->createView(),
            ],
            new Response(
                null,
                $form->isSubmitted() && !$form->isValid() ? 422 : 200
            )
        );
    }

    #[IsGranted('ROLE_USER')]
    public function password(Request $request): Response
    {
        $this->denyAccessUnlessGranted('edit_profile', $this->getUserOrThrow());

        $dto = $this->manager->createDto($this->getUserOrThrow());

        $form = $this->handleForm($this->createForm(UserPasswordType::class, $dto), $dto, $request);
        if (null === $form) {
            $this->addFlash('error', 'flash_user_edit_password_error');
        } else {
            if (!$form instanceof FormInterface) {
                return $form;
            }
        }

        return $this->render(
            'user/settings/password.html.twig',
            [
                'form' => $form->createView(),
                'has2fa' => $this->getUserOrThrow()->isTotpAuthenticationEnabled(),
            ],
            new Response(
                null,
                $form->isSubmitted() && !$form->isValid() ? 422 : 200
            )
        );
    }

    /**
     * Handle form submit request.
     */
    private function handleForm(
        FormInterface $form,
        UserDto $dto,
        Request $request
    ): FormInterface|Response|null {
        try {
            // Could thrown an error on event handlers (eg. onPostSubmit if a user upload an incorrect image)
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->has('currentPassword')) {
                if (!$this->userPasswordHasher->isPasswordValid(
                    $this->getUser(),
                    $form->get('currentPassword')->getData()
                )) {
                    $form->get('currentPassword')->addError(new FormError($this->translator->trans('Password is invalid')));
                }
            }

            if ($form->has('newEmail')) {
                $dto->email = $form->get('newEmail')->getData();
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $email = $this->getUser()->email;
                $this->manager->edit($this->getUser(), $dto);

                // Show succcessful message to user and tell them to re-login
                // In case of an email change or password change
                if ($dto->email !== $email || $dto->plainPassword) {
                    $this->security->logout(false);

                    $this->addFlash('success', 'flash_account_settings_changed');

                    return $this->redirectToRoute('app_login');
                }

                return $this->redirectToRoute('user_settings_profile');
            }

            return $form;
        } catch (\Exception $e) {
            return null;
        }
    }
}
