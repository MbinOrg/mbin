<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\AbstractController;
use App\Entity\Client;
use App\Entity\OAuth2UserConsent;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginSecondController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(): Response
    {
        // if ($user = $this->getUser()) {
        //     return $this->redirectToRoute('front');
        // }

        // $error = $utils->getLastAuthenticationError();
        // $lastUsername = $utils->getLastUsername();

        return new Response(
            '<html><body>Hello world</body></html>'
        );
    }
}
