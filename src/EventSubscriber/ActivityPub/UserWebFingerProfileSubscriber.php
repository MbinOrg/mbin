<?php

declare(strict_types=1);

namespace App\EventSubscriber\ActivityPub;

use App\ActivityPub\JsonRdLink;
use App\Event\ActivityPub\WebfingerResponseEvent;
use App\Repository\UserRepository;
use App\Service\ActivityPub\Webfinger\WebFingerParameters;
use App\Service\ImageManager;
use App\Service\SettingsManager;
use JetBrains\PhpStorm\ArrayShape;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserWebFingerProfileSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly WebFingerParameters $webfingerParameters,
        private readonly UserRepository $userRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
        private readonly ImageManager $imageManager,
    ) {
    }

    #[ArrayShape([WebfingerResponseEvent::class => 'string'])]
    public static function getSubscribedEvents(): array
    {
        return [
            WebfingerResponseEvent::class => ['buildResponse', 999],
        ];
    }

    public function buildResponse(WebfingerResponseEvent $event): void
    {
        $params = $this->webfingerParameters->getParams($event->request);
        $jsonRd = $event->jsonRd;

        if (isset($params[WebFingerParameters::ACCOUNT_KEY_NAME])) {
            $query = $params[WebFingerParameters::ACCOUNT_KEY_NAME];
            $this->logger->debug("got webfinger query for $query");

            $domain = $this->settingsManager->get('KBIN_DOMAIN');
            if ($domain === $query) {
                $accountHref = $this->urlGenerator->generate('ap_instance', [], UrlGeneratorInterface::ABSOLUTE_URL);
                $link = new JsonRdLink();
                $link->setRel('self')
                    ->setType('application/activity+json')
                    ->setHref($accountHref);
                $jsonRd->addLink($link);

                return;
            }

            $actor = $this->getActor($query);
            if ($actor) {
                $accountHref = $this->urlGenerator->generate(
                    'ap_user',
                    ['username' => $actor->getUserIdentifier()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $link = new JsonRdLink();
                $link->setRel('self')
                    ->setType('application/activity+json')
                    ->setHref($accountHref);
                $jsonRd->addLink($link);

                if ($actor->avatar) {
                    $link = new JsonRdLink();
                    $link->setRel('https://webfinger.net/rel/avatar')
                        ->setHref(
                            $this->imageManager->getUrl($actor->avatar),
                        ); // @todo media url
                    $jsonRd->addLink($link);
                }
            }
        }
    }

    protected function getActor($name): ?UserInterface
    {
        return $this->userRepository->findOneByUsername($name);
    }
}
