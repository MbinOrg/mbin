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
        $query = [];

        $query['isNsfw'] = $entry->isAdult ? '1' : '0';
        $query['isOc'] = $entry->isOc ? '1' : '0';

        if ('' !== $entry->title) {
            $query['title'] = $entry->title;
        }
        if (null !== $entry->url && '' !== $entry->url) {
            $query['url'] = $entry->url;
        }

        if (null !== $entry->image) {
            $query['imageHash'] = strtok($entry->image->fileName, '.');

            if (null !== $entry->image->altText && '' !== $entry->image->altText) {
                $query['imageAlt'] = $entry->image->altText;
            }
        }

        $tagNum = 0;
        foreach ($entry->hashtags as $hashtag) {
            /* @var $hashtag \App\Entity\HashtagLink */
            $query["tags[$tagNum]"] = $hashtag->hashtag->tag;
            ++$tagNum;
        }

        $entryUrl = $this->urlGenerator->generate(
            'ap_entry',
            ['magazine_name' => $entry->magazine->name, 'entry_id' => $entry->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $body = 'Crossposted from ['.$entryUrl.']('.$entryUrl.')';
        if (null !== $entry->body && '' !== $entry->body) {
            $bodyLines = explode("\n", $entry->body);
            $body = $body."\n";
            foreach ($bodyLines as $line) {
                $body = $body."\n> ".$line;
            }
        }
        $query['body'] = $body;

        return $this->redirectToRoute('entry_create', $query);
    }
}
