<?php

namespace App\Controller;

use App\Repository\EmbedRepository;
use App\Utils\Embed;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsTwigComponent('embed', template: 'components/embed.html.twig')]
class EmbedController extends AbstractController
{
    public function __construct(
        private readonly EmbedRepository $repository,
        private readonly Embed $embed,
        private readonly LoggerInterface $logger,
    ) { }

    public string $url;
    public ?string $html = null;

    #[PostMount]
    public function postMount(): void
    {
        $this->logger->debug("EmbedController: rendering for url {url}", ["url" => $this->url]);
        $embedEntity = $this->repository->findOneByUrl($this->url);
        if (!$embedEntity || $embedEntity->hasEmbed) {
            $data = $this->embed->fetch($this->url);
            // only wrap embed link for image embed as it doesn't make much sense for any other type for embed
            if ($data->isImageUrl()) {
                $this->html = sprintf(
                    '<a href="%s" class="embed-link">%s</a>',
                    $data->url,
                    $data->html
                );
            } else {
                $this->html = $data->html;
            }
        }
    }
}
