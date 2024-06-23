<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Controller\AbstractController;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthentikController extends AbstractController
{
    public function connect(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('authentik')
            ->redirect([
                'openid',
                'email',
                'profile',
            ]);
    }

    public function verify(Request $request, ClientRegistry $client)
    {
    }
}
