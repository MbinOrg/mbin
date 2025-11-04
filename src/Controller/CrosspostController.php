<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Entry;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CrosspostController extends AbstractController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(
        #[MapEntity(id: 'id')]
        Entry $entry,
        Request $request,
    ): Response {
        $fragment = '';

        $fragment = $fragment.'prefill-nsfw='.($entry->isAdult ? '1' : '0');
        $fragment = $fragment.'&prefill-oc='.($entry->isOc ? '1' : '0');

        if ('' !== $entry->title) {
            $fragment = $fragment.'&prefill-title='.urlencode($entry->title);
        }
        if (null !== $entry->url && '' !== $entry->url) {
            $fragment = $fragment.'&prefill-url='.urlencode($entry->url);
        }
        if (null !== $entry->image && null !== $entry->image->altText && '' !== $entry->image->altText) {
            $fragment = $fragment.'&prefill-imageAlt='.urlencode($entry->image->altText);
        }

        foreach ($entry->hashtags as $hashtag) {
            /* @var $hashtag \App\Entity\HashtagLink */
            $fragment = $fragment.'&prefill-tags='.urlencode($hashtag->hashtag->tag);
        }

        $entryUrl = $this->urlGenerator->generate(
            'ap_entry',
            ['magazine_name' => $entry->magazine->name, 'entry_id' => $entry->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $body = '[Crosspost]('.$entryUrl.')';
        if (null !== $entry->body && '' !== $entry->body) {
            $body = $body."\n\n".$entry->body;
        }
        $fragment = $fragment.'&prefill-body='.urlencode($body);

        return $this->redirect('/new_entry#'.$fragment);
    }
}
