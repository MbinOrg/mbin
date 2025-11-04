<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Entry;
use App\Entity\Image;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CrosspostController extends AbstractController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $storageUrl,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function __invoke(
        #[MapEntity(id: 'id')]
        Entry $entry,
        Request $request,
    ): Response {
        $query = '';

        $query = $query.'isNsfw='.($entry->isAdult ? '1' : '0');
        $query = $query.'&isOc='.($entry->isOc ? '1' : '0');

        if ('' !== $entry->title) {
            $query = $query.'&title='.urlencode($entry->title);
        }
        if (null !== $entry->url && '' !== $entry->url) {
            $query = $query.'&url='.urlencode($entry->url);
        }

        if (null !== $entry->image) {
            $imgUrl = $this->getImageUrl($entry->image);
            if (null !== $imgUrl) {
                if (null !== $entry->url && '' !== $entry->url) {
                    $query = $query.'&imageUrl='.urlencode($imgUrl);
                } else {
                    $query = $query.'&url='.urlencode($imgUrl);
                }
            }

            if (null !== $entry->image->altText && '' !== $entry->image->altText) {
                $query = $query.'&imageAlt='.urlencode($entry->image->altText);
            }
        }

        foreach ($entry->hashtags as $hashtag) {
            /* @var $hashtag \App\Entity\HashtagLink */
            $query = $query.'&tags[]='.urlencode($hashtag->hashtag->tag);
        }

        $entryUrl = $this->urlGenerator->generate(
            'ap_entry',
            ['magazine_name' => $entry->magazine->name, 'entry_id' => $entry->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $body = 'Crossposted from ['.$entryUrl.']('.$entryUrl.')';
        if (null !== $entry->body && '' !== $entry->body) {
            $body = $body."\n\n".$entry->body;
        }
        $query = $query.'&body='.urlencode($body);

        return $this->redirect('/new_entry?'.$query);
    }

    private function getImageUrl(Image $image): ?string
    {
        if (null !== $image->filePath) {
            return $this->storageUrl.'/'.$image->filePath;
        } else {
            return $image->sourceUrl;
        }
    }
}
