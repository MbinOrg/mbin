<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentController extends AbstractController
{
    public function __invoke(Request $request): Response
    {
        return $this->render('page/agent.html.twig');
    }
}
