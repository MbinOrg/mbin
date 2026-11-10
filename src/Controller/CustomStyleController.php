<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\SettingsManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomStyleController extends AbstractController
{

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SettingsManager $settings,
    ) {

    }

    public function __invoke(Request $request, MagazineRepository $repository): Response
    {
        $includeSiteStyles = $request->query->get('includeSiteStyles', 'true') === 'true';

        $magazineName = $request->query->get('magazine');
        $magazine = $repository->findOneByName($magazineName);

        $userNames = $request->query->get('userNames', '');
        $users = $this->findUsers($userNames);

        $profileName = $request->query->get('profile', '');
        $profile = $this->findUsers($profileName);
        if(count($profile) > 0) {
            $profile = $profile[0];
        } else {
            $profile = null;
        }

        $css = $this->renderView('styles/custom.css.twig', [
            'includeSiteStyles' => $includeSiteStyles,
            'magazine' => $magazine,
            'profile' => $profile,
            'cssUsers' => $users,
        ]);

        return $this->createResponse($request, $css, $includeSiteStyles);
    }

    private function createResponse(Request $request, ?string $customCss, bool $private): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/css');

        if($private) {
            $response->setPrivate();
        } else {
            $response->setPublic();
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }

        if (!empty($customCss)) {
            $response->setContent($customCss);
            $response->setEtag(hash('sha256', $response->getContent()));
            $response->isNotModified($request);
        } else {
            $response->setStatusCode(Response::HTTP_NOT_FOUND);
        }

        return $response;
    }

    private function findUsers(string $userNames): array
    {
        $names = explode(',', $userNames);
        $localPostfix = '@'.$this->settings->getDto()->KBIN_DOMAIN;
        $users = [];

        foreach ($names as $name) {
            if(str_ends_with($name, $localPostfix)) {
                $name = substr($name, 0, -strlen($localPostfix));
                if(str_starts_with($name, '@')) {
                    $name = substr($name, 1);
                }
            }

            $user = $this->userRepository->findOneBy(['username' => $name]);
            if($user) {
                $users[] = $user;
            }
        }

        return $users;
    }
}
