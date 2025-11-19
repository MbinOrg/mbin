<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Controller\AbstractController;
use App\Form\UserSettingsType;
use App\Service\UserSettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserSettingController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(UserSettingsManager $manager, Request $request): Response
    {
        $user = $this->getUserOrThrow();
        $dto = $manager->createDto($user);

        $form = $this->createForm(UserSettingsType::class, $dto);
        try {
            // Could thrown an error
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $manager->update($user, $dto);

                $this->addFlash('success', 'flash_user_settings_general_success');
                $this->redirectToRefererOrHome($request);
            }
        } catch (\Exception $e) {
            $this->logger->error('There was an error saving the user {u}\'s settings: {e} - {m}', ['u' => $user->username, 'e' => \get_class($e), 'm' => $e->getMessage()]);
            // Show an error to the user
            $this->addFlash('error', 'flash_user_settings_general_error');
        }

        return $this->render(
            'user/settings/general.html.twig',
            [
                'user' => $user,
                'form' => $form->createView(),
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
