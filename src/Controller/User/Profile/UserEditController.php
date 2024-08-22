<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Controller\AbstractController;
use App\DTO\UserDto;
use App\Form\UserBasicType;
use App\Form\UserEmailType;
use App\Form\UserPasswordType;
use App\Service\UserManager;
use Error;
use Exception;
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
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function profile(Request $request): Response
    {
        $user = $this->getUserOrThrow();
        $this->denyAccessUnlessGranted('edit_profile', $user);

        $dto = $this->manager->createDto($user);

        // $link1 = [
        //     'relatedName' => 'name1',
        //     'relatedLink' => 'link1'
        // ];

        // $link2 = [
        //     'relatedName' => 'name2',
        //     'relatedLink' => 'link2'
        // ];

        // $dto->relatedSocialLinks[] = $link1;
        // $dto->relatedSocialLinks[] = $link2;

        try {
            $form = $this->createForm(UserBasicType::class, $dto);
            $formHandler = $this->handleForm($form, $dto, $request);
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
        } catch (Error | Exception $e) {
            $para = '';
            throw $e;
        }
    }

    #[IsGranted('ROLE_USER')]
    public function email(Request $request): Response
    {
        $user = $this->getUserOrThrow();
        $this->denyAccessUnlessGranted('edit_profile', $user);

        $dto = $this->manager->createDto($user);

        $form = $this->createForm(UserEmailType::class, $dto);
        $formHandler = $this->handleForm($form, $dto, $request);
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
        $formHandler = $this->handleForm($form, $dto, $request);
        if (null === $formHandler) {
            $this->addFlash('error', 'flash_user_edit_password_error');
        } else {
            if (!$formHandler instanceof FormInterface) {
                return $formHandler;
            }
        }

        return $this->render(
            'user/settings/password.html.twig',
            [
                'user' => $user,
                'form' => $form->createView(),
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

                // Check succcessful to use if profile was changed (which contains the about field)
                if ($form->has('about')) {
                    $this->addFlash('success', 'flash_user_edit_profile_success');
                }

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
