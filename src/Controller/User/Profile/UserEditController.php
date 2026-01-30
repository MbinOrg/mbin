<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Controller\AbstractController;
use App\DTO\UserDto;
use App\Entity\User;
use App\Exception\ImageDownloadTooLargeException;
use App\Form\UserBasicType;
use App\Form\UserDisable2FAType;
use App\Form\UserEmailType;
use App\Form\UserPasswordType;
use App\Form\UserRegenerate2FABackupType;
use App\Service\SettingsManager;
use App\Service\UserManager;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserEditController extends AbstractController
{
    public function __construct(
        private readonly UserManager $manager,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly SettingsManager $settingsManager,
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function profile(Request $request): Response
    {
        $user = $this->getUserOrThrow();
        $this->denyAccessUnlessGranted('edit_profile', $user);

        $dto = $this->manager->createDto($user);

        $form = $this->createForm(UserBasicType::class, $dto);
        $formHandler = $this->handleForm($form, $dto, $request, $user);
        if (null === $formHandler) {
            $this->addFlash('error', 'flash_user_edit_profile_error');
        } else {
            if (!$formHandler instanceof FormInterface) {
                return $formHandler;
            }
        }

        return $this->render(
            'user/settings/profile.html.twig',
            [
                'user' => $user,
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
        $user = $this->getUserOrThrow();
        $this->denyAccessUnlessGranted('edit_profile', $user);

        $dto = $this->manager->createDto($user);

        $form = $this->createForm(UserEmailType::class, $dto);
        $formHandler = $this->handleForm($form, $dto, $request, $user);
        if (null === $formHandler) {
            $this->addFlash('error', 'flash_user_edit_email_error');
        } else {
            if (!$formHandler instanceof FormInterface) {
                return $formHandler;
            }
        }

        return $this->render(
            'user/settings/email.html.twig',
            [
                'user' => $user,
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
        $user = $this->getUserOrThrow();
        $this->denyAccessUnlessGranted('edit_profile', $user);

        if ($user->isSsoControlled()) {
            throw new AccessDeniedException();
        }

        $dto = $this->manager->createDto($user);

        $form = $this->createForm(UserPasswordType::class, $dto);
        $formHandler = $this->handleForm($form, $dto, $request, $user);
        if (null === $formHandler) {
            $this->addFlash('error', 'flash_user_edit_password_error');
        } else {
            if (!$formHandler instanceof FormInterface) {
                return $formHandler;
            }
        }

        $dto2 = $this->manager->createDto($user);
        $disable2faForm = $this->createForm(UserDisable2FAType::class, $dto2);

        $dto3 = $this->manager->createDto($user);
        $regenerateBackupCodesForm = $this->createForm(UserRegenerate2FABackupType::class, $dto3);

        return $this->render(
            'user/settings/password.html.twig',
            [
                'user' => $user,
                'form' => $form->createView(),
                'disable2faForm' => $disable2faForm->createView(),
                'regenerateBackupCodes' => $regenerateBackupCodesForm->createView(),
                'has2fa' => $user->isTotpAuthenticationEnabled(),
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
        Request $request,
        User $user,
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

            if ($form->isSubmitted() && $form->has('totpCode') && $user->isTotpAuthenticationEnabled()) {
                if (!$this->totpAuthenticator->checkCode(
                    $this->getUser(),
                    $form->get('totpCode')->getData()
                )) {
                    $form->get('totpCode')->addError(new FormError($this->translator->trans('2fa.code_invalid')));
                }
            }

            if ($form->has('newEmail')) {
                $dto->email = $form->get('newEmail')->getData();
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $email = $this->getUser()->email;
                $this->manager->edit($this->getUser(), $dto);

                // Check successful to use if profile was changed (which contains the about field)
                if ($form->has('about')) {
                    $this->addFlash('success', 'flash_user_edit_profile_success');
                }

                // Show successful message to user and tell them to re-login
                // In case of an email change or password change
                if ($dto->email !== $email || $dto->plainPassword) {
                    $this->security->logout(false);

                    $this->addFlash('success', 'flash_account_settings_changed');

                    return $this->redirectToRoute('app_login');
                }

                return $this->redirectToRoute('user_settings_profile');
            }

            return $form;
        } catch (ImageDownloadTooLargeException $e) {
            $this->addFlash('error', $this->translator->trans('flash_image_download_too_large_error', ['%bytes%' => $this->settingsManager->getMaxImageByteString()]));

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
