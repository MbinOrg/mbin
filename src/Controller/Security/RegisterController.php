<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Controller\AbstractController;
use App\DTO\UserDto;
use App\Form\UserRegisterType;
use App\Service\IpResolver;
use App\Service\SettingsManager;
use App\Service\UserManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RegisterController extends AbstractController
{
    public function __construct(
        private readonly UserManager $manager,
        private readonly IpResolver $ipResolver,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (true === $this->settingsManager->get('MBIN_SSO_ONLY_MODE')) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->getUser()) {
            return $this->redirectToRoute('front');
        }

        $form = $this->createForm(UserRegisterType::class, options: [
            'antispam_profile' => 'default',
        ]);
        $form->handleRequest($request);
        $submitted = $form->isSubmitted();

        if ($submitted && $form->isValid()) {
            /** @var UserDto $dto */
            $dto = $form->getData();
            $dto->ip = $this->ipResolver->resolve();

            $this->manager->create($dto);

            $this->addFlash(
                'success',
                'flash_register_success'
            );

            if ($this->settingsManager->getNewUsersNeedApproval()) {
                $this->addFlash(
                    'success',
                    'flash_application_info'
                );
            }

            return $this->redirectToRoute('app_login');
        }

        if ($submitted) {
            // Validation details can include submitted values.
            $errorCount = \count($form->getErrors(true));
            $this->logger->warning('[RegisterController::__invoke] Registration form failed validation ({error_count} '.(1 === $errorCount ? 'error' : 'errors').')', [
                'error_count' => $errorCount,
            ]);
        }

        return $this->render(
            'user/register.html.twig',
            [
                'form' => $form->createView(),
            ],
            new Response(null, $submitted ? 422 : 200)
        );
    }
}
