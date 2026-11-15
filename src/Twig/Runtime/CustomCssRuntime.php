<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Service\CustomCssService;
use Twig\Extension\RuntimeExtensionInterface;

readonly class CustomCssRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private CustomCssService $cssService,
    ) {
    }

    public function subjectCustomCssMarker(ActivityPubActivityInterface $subject): string
    {
        $user = $subject->getUser();
        if (!$user) {
            return '';
        }

        return $this->cssService->createConstraintStyleClass($user);
    }
}
