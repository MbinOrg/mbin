<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Controller\AbstractController;
use App\DTO\Temp2FADto;
use App\DTO\UserDto;
use App\Entity\User;
use App\Form\UserDisable2FAType;
use App\Form\UserRegenerate2FABackupType;
use App\Form\UserTwoFactorType;
use App\Service\TwoFactorManager;
use App\Service\UserManager;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException as CoreAccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class User2FAController extends AbstractController
{
    public const TOTP_SESSION_KEY = 'totp_user_secret';
    public const BACKUP_SESSION_KEY = 'totp_backup_codes';

    public function __construct(
        private readonly UserManager $manager,
        private readonly TwoFactorManager $twoFactorManager,
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function enable(Request $request): Response
    {
        $user = $this->getUserOrThrow();
        $this->denyAccessUnlessGranted('edit_profile', $user);

        if ($user->isSsoControlled()) {
            throw new CoreAccessDeniedException();
        }

        if ($user->isTotpAuthenticationEnabled()) {
            throw new SuspiciousOperationException('User accessed 2fa enable path with existing 2fa in place');
        }

        $totpSecret = $request->getSession()->get(self::TOTP_SESSION_KEY, null);
        if (null === $totpSecret || 'GET' === $request->getMethod()) {
            $totpSecret = $this->totpAuthenticator->generateSecret();
            $request->getSession()->set(self::TOTP_SESSION_KEY, $totpSecret);
        }

        $backupCodes = $request->getSession()->get(self::BACKUP_SESSION_KEY, null);
        if (null === $backupCodes || 'GET' === $request->getMethod()) {
            $backupCodes = $this->twoFactorManager->createBackupCodes($user);
            $request->getSession()->set(self::BACKUP_SESSION_KEY, $backupCodes);
        }

        $dto = $this->manager->createDto($user);
        $dto->totpSecret = $totpSecret;

        $temp2fa = new Temp2FADto($user->username, $totpSecret);
        $qrCodeContent = $this->totpAuthenticator->getQRContent($temp2fa);

        $form = $this->handleForm($this->createForm(UserTwoFactorType::class, $dto), $dto, $request);
        if (!$form instanceof FormInterface) {
            return $form;
        }

        return $this->render(
            'user/settings/2fa.html.twig',
            [
                'form' => $form->createView(),
                'two_fa_url' => $qrCodeContent,
                'codes' => $backupCodes,
                'secret' => $totpSecret,
            ],
            new Response(
                null,
                $form->isSubmitted() && !$form->isValid() ? 422 : 200
            )
        );
    }

    #[IsGranted('ROLE_USER')]
    public function disable(Request $request): Response
    {
        $user = $this->getUserOrThrow();
        if (!$user->isTotpAuthenticationEnabled()) {
            throw new SuspiciousOperationException('User accessed 2fa disable path without existing 2fa in place');
        }

        $dto = $this->manager->createDto($user);
        $dto->totpSecret = $user->getTotpSecret();
        $form = $this->createForm(UserDisable2FAType::class, $dto);
        $form->handleRequest($request);
        $this->handleCurrentPassword($form);
        $this->handleTotpCode($form, $dto);

        if ($form->isValid()) {
            $this->twoFactorManager->remove2FA($user);
        } else {
            $errors = $form->getErrors(true);
            foreach ($errors as $error) {
                /** @var FormError $error */
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted('ROLE_USER')]
    public function qrCode(Request $request): Response
    {
        $user = $this->getUserOrThrow();
        $this->denyAccessUnlessGranted('edit_profile', $user);

        $totpSecret = $request->getSession()->get(self::TOTP_SESSION_KEY, null);
        if (null === $totpSecret) {
            throw new AccessDeniedException('/settings/2fa/qrcode');
        }
        $temp2fa = new Temp2FADto($user->username, $totpSecret);

        $builder = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            data: $this->totpAuthenticator->getQRContent($temp2fa),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 250,
            margin: 0,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            logoPath: $this->getParameter('kernel.project_dir').'/public/logo.png',
            logoResizeToWidth: 60,
        );
        $result = $builder->build();

        return new Response($result->getString(), 200, ['Content-Type' => 'image/png']);
    }

    #[IsGranted('ROLE_ADMIN')]
    public function remove(#[MapEntity(mapping: ['username' => 'username'])] User $user, Request $request): Response
    {
        $this->validateCsrf('user_2fa_remove', $request->getPayload()->get('token'));

        $this->twoFactorManager->remove2FA($user);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                [
                    'has2FA' => false,
                ]
            );
        }

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted('ROLE_USER')]
    public function backup(Request $request): Response
    {
        $user = $this->getUserOrThrow();
        $this->denyAccessUnlessGranted('edit_profile', $user);

        if (!$user->isTotpAuthenticationEnabled()) {
            throw new SuspiciousOperationException('User accessed 2fa backup path without existing 2fa');
        }

        $dto = $this->manager->createDto($user);
        $dto->totpSecret = $user->getTotpSecret();
        $form = $this->createForm(UserRegenerate2FABackupType::class, $dto);
        $form->handleRequest($request);
        $this->handleCurrentPassword($form);
        $this->handleTotpCode($form, $dto);

        if (!$form->isValid()) {
            $errors = $form->getErrors(true);
            foreach ($errors as $error) {
                /** @var FormError $error */
                $this->addFlash('error', $error->getMessage());
            }

            return $this->redirectToRefererOrHome($request);
        }

        return $this->render(
            'user/settings/2fa_backup.html.twig',
            [
                'codes' => $this->twoFactorManager->createBackupCodes($user),
            ]
        );
    }

    private function handleForm(
        FormInterface $form,
        UserDto $dto,
        Request $request,
    ): FormInterface|Response {
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $form;
        }

        $this->handleTotpCode($form, $dto);

        if (!$form->isValid()) {
            $this->logger->warning('2fa error occurred user "{username}" submitting the form "{errors}"', [
                'username' => $dto->username,
                'errors' => $form->getErrors(),
            ]);
            $form->get('totpCode')->addError(new FormError($this->translator->trans('2fa.setup_error')));

            return $form;
        }

        $this->handleCurrentPassword($form);
        if (!$form->isValid()) {
            return $form;
        }

        $this->manager->edit($this->getUser(), $dto);

        if (!$dto->totpSecret) {
            return $this->redirectToRoute('user_settings_profile');
        }

        $this->security->logout(false);

        $this->addFlash('success', 'flash_account_settings_changed');

        return $this->redirectToRoute('app_login');
    }

    private function handleTotpCode(FormInterface $form, UserDto $dto): void
    {
        if ($form->has('totpCode')
            && !$this->setupHasValidCode($dto->totpSecret, $form->get('totpCode')->getData())) {
            $form->get('totpCode')->addError(new FormError($this->translator->trans('2fa.code_invalid')));
        }
    }

    private function handleCurrentPassword(FormInterface $form): void
    {
        if ($form->has('currentPassword')) {
            if (!$this->userPasswordHasher->isPasswordValid(
                $this->getUser(),
                $form->get('currentPassword')->getData()
            )) {
                $form->get('currentPassword')->addError(new FormError($this->translator->trans('Password is invalid')));
            }
        }
    }

    private function setupHasValidCode(string $totpSecret, string $submittedCode): bool
    {
        $user = $this->getUserOrThrow();
        $temp = new Temp2FADto($user->username, $totpSecret);

        $isValid = false;
        if ($this->totpAuthenticator->checkCode($temp, $submittedCode)) {
            $isValid = true;
        }

        return $isValid;
    }
}
