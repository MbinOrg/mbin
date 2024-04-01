<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Repository\TagRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Extension\RuntimeExtensionInterface;

readonly class AdminExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private Security $security,
        private TagRepository $tagRepository,
    ) {
    }

    public function isTagBanned(string $tag): bool
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException();
        }

        $hashtag = $this->tagRepository->findOneBy(['tag' => $tag]);
        if (null === $hashtag) {
            throw new NotFoundHttpException();
        }

        return $hashtag->banned;
    }
}
