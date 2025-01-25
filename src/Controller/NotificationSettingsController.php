<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\User;
use App\Enums\ENotificationStatus;
use App\Repository\NotificationSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class NotificationSettingsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationSettingsRepository $repository,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function changeSetting(int $subject_id, string $subject_type, string $status, Request $request): Response
    {
        $status = ENotificationStatus::getFromString($status);
        $subject = $this->entityManager->getRepository(self::GetClassFromSubjectType($subject_type))->findOneBy(['id' => $subject_id]);
        $user = $this->getUserOrThrow();
        $this->repository->setStatusByTarget($user, $subject, $status);
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'html' => $this->renderView('components/_ajax.html.twig', [
                    'component' => 'notification_switch',
                    'attributes' => [
                        'target' => $subject,
                    ],
                ]
                ),
            ]);
        }

        return $this->redirect($request->headers->get('Referer'));
    }

    protected static function GetClassFromSubjectType(string $subjectType): string
    {
        return match ($subjectType) {
            'entry' => Entry::class,
            'post' => Post::class,
            'user' => User::class,
            'magazine' => Magazine::class,
            default => throw new \LogicException("cannot match type $subjectType"),
        };
    }
}
