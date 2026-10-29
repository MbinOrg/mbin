<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Domain;
use App\Entity\Entry;
use App\Factory\ActivityPub\PersonFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\RuntimeExtensionInterface;

readonly class CustomCssRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private PersonFactory $personFactory,
    )
    {
    }

    public function subjectCustomCssMarker(ActivityPubActivityInterface $subject): string
    {
        $user = $subject->getUser();
        if (!$user) {
            return '';
        }

        $userId = $this->personFactory->getActivityPubId($user);
        return ' fediCustomCss fediCustomCss-mbin fediCustomCss-user-'.$this->userIdToCssId($userId);
    }

    private function userIdToCssId(string $userId): string {
        // convert base64 encoding to valid CSS identifier (https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Values/ident)
        $str = base64_encode($userId);
        $str = str_replace('+', '-', $str);
        $str = str_replace('/', '_', $str);
        $str = str_replace('=', '', $str);
        return $str;
    }
}
