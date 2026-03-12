<?php

declare(strict_types=1);

namespace App\Service\Contracts;

use App\Entity\Contracts\ContentInterface;
use App\Entity\Contracts\ContentVisibilityInterface;
use App\Entity\User;

interface ContentManagerInterface extends ManagerInterface
{

    function restore(User $moderator, ContentVisibilityInterface $content): void;

    function delete(User $moderator, ContentInterface $subject): void;
}
