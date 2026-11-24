<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class RegistrationRejectedException extends CustomUserMessageAuthenticationException
{
    public function __construct()
    {
        parent::__construct('stopforumspam_registration_rejected');
    }
}
