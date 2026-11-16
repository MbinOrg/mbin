<?php

declare(strict_types=1);

namespace App\Controller\User\Profile;

use App\Controller\AbstractController;
use App\Form\UserStylesType;
use App\Service\UserSettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserStylesController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(UserSettingsManager $manager, Request $request): Response
    {
        $user = $this->getUserOrThrow();
        $dto = $manager->createStylesDto($user);

        $form = $this->createForm(UserStylesType::class, $dto);
        $cssError = null;
        try {
            // Could throw an error
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $manager->updateStyles($user, $dto);

                $this->addFlash('success', 'flash_user_settings_general_success');
                $this->redirectToRefererOrHome($request);
            }
        } catch (\Exception $e) {
            $this->logger->error('There was an error saving the user {u}\'s styles: {e} - {m}', ['u' => $user->username, 'e' => \get_class($e), 'm' => $e->getMessage()]);

            // Show an error to the user
            $this->addFlash('error', 'flash_user_settings_general_error');
            if ($e instanceof BadRequestHttpException) {
                $cssError = $e->getMessage();
            }
        }

        return $this->render(
            'user/settings/styles.html.twig',
            [
                'user' => $user,
                'cssError' => $cssError,
                'form' => $form->createView(),
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
