<?php

declare(strict_types=1);

namespace App\EventSubscriber\ActivityPub;

use App\ActivityPub\JsonRdLink;
use App\Entity\Magazine;
use App\Event\ActivityPub\WebfingerResponseEvent;
use App\Repository\MagazineRepository;
use App\Service\ActivityPub\Webfinger\WebFingerParameters;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GroupWebFingerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly WebFingerParameters $webfingerParameters,
        private readonly MagazineRepository $magazineRepository,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    #[ArrayShape([WebfingerResponseEvent::class => 'string'])]
    public static function getSubscribedEvents(): array
    {
        return [
            WebfingerResponseEvent::class => ['buildResponse', 195],
        ];
    }

    public function buildResponse(WebfingerResponseEvent $event): void
    {
        $request = $event->request;
        $params = $this->webfingerParameters->getParams($request);
        $jsonRd = $event->jsonRd;

        $subject = $request->query->get('resource') ?: '';
        if (!empty($subject)) {
            $jsonRd->setSubject($subject);
        }

        if (
            isset($params[WebFingerParameters::ACCOUNT_KEY_NAME])
            && $actor = $this->getActor($params[WebFingerParameters::ACCOUNT_KEY_NAME])
        ) {
            $accountHref = $this->urlGenerator->generate(
                'ap_magazine',
                ['name' => $actor->name],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $jsonRd->addAlias($accountHref);
            $link = new JsonRdLink();
            $link->setRel('https://webfinger.net/rel/profile-page')
                ->setType('text/html')
                ->setHref($accountHref);
            $jsonRd->addLink($link);
        }
    }

    protected function getActor($name): ?Magazine
    {
        if ('random' === $name) {
            return null;
        }

        return $this->magazineRepository->findOneByName($name);
    }
}
