<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Entry;
use App\Entity\Post;
use App\Markdown\MarkdownConverter;
use App\PageView\ContentPageView;
use App\Repository\ContentRepository;
use App\Repository\Criteria;
use App\Repository\MagazineRepository;
use App\Repository\TagLinkRepository;
use App\Repository\UserRepository;
use App\Twig\Runtime\MediaExtensionRuntime;
use App\Utils\IriGenerator;
use FeedIo\Feed;
use FeedIo\Feed\Item;
use FeedIo\Feed\Node\Category;
use FeedIo\FeedInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FeedManager
{
    public function __construct(
        private readonly SettingsManager $settings,
        private readonly ContentRepository $contentRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly UserRepository $userRepository,
        private readonly TagLinkRepository $tagLinkRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly MediaExtensionRuntime $mediaExtensionRuntime,
        private readonly MentionManager $mentionManager,
        private readonly ImageManager $imageManager,
        private readonly MarkdownConverter $markdownConverter,
    ) {
    }

    public function getFeed(Request $request): FeedInterface
    {
        $criteria = $this->getCriteriaFromRequest($request);
        $feed = $this->createFeed($criteria);

        $items = $this->contentRepository->findByCriteria($criteria);

        $items = $this->getItems($items->getCurrentPageResults());

        foreach ($items as $item) {
            $feed->add($item);
        }

        return $feed;
    }

    private function createFeed(Criteria $criteria): Feed
    {
        $feed = new Feed();
        if ($criteria->magazine) {
            $title = "{$criteria->magazine->title} - {$this->settings->get('KBIN_META_TITLE')}";
            $url = $this->urlGenerator->generate('front_magazine', ['name' => $criteria->magazine->name, 'content' => $criteria->content], UrlGeneratorInterface::ABSOLUTE_URL);
        } elseif ($criteria->user) {
            $title = "{$criteria->user->username} - {$this->settings->get('KBIN_META_TITLE')}";
            $url = $this->urlGenerator->generate('user_overview', ['username' => $criteria->user->username, 'content' => $criteria->content], UrlGeneratorInterface::ABSOLUTE_URL);
        } else {
            $title = $this->settings->get('KBIN_META_TITLE');
            $url = $this->urlGenerator->generate('front', ['content' => $criteria->content], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $feed->setTitle($title);
        $feed->setDescription($this->settings->get('KBIN_META_DESCRIPTION'));
        $feed->setUrl($url);

        return $feed;
    }

    public function getItems(iterable $content): \Generator
    {
        /** @var Entry|Post $subject */
        foreach ($content as $subject) {
            $item = new Item();
            $item->setLastModified(\DateTime::createFromImmutable($subject->createdAt));
            $item->setPublicId(IriGenerator::getIriFromResource($subject));
            $item->setAuthor((new Item\Author())->setName($this->mentionManager->getUsername($subject->user->username, true)));

            if ($subject->image) {
                $media = new Item\Media();
                $media->setUrl($this->mediaExtensionRuntime->getPublicPath($subject->image));
                $media->setTitle($subject->image->altText);
                $media->setType($this->imageManager->getMimetype($subject->image));
                $item->addMedia($media);
            }

            if ($subject instanceof Entry) {
                $link = $this->urlGenerator->generate('entry_single', [
                    'magazine_name' => $subject->magazine->name,
                    'entry_id' => $subject->getId(),
                    'slug' => $subject->slug,
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                $item->setContent($this->markdownConverter->convertToHtml($subject->body ?? '', 'entry'));
                $item->setSummary($subject->getShortDesc());
                $item->setTitle($subject->title);
                $item->setLink($link);
                $item->set('comments', $link.'#comments');
            } elseif ($subject instanceof Post) {
                $link = $this->urlGenerator->generate('post_single', [
                    'magazine_name' => $subject->magazine->name,
                    'post_id' => $subject->getId(),
                    'slug' => $subject->slug,
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                $item->setContent($this->markdownConverter->convertToHtml($subject->body ?? '', 'post'));
                $item->setSummary($subject->getShortTitle());
                $item->setLink($link);
                $item->set('comments', $link.'#comments');
            } else {
                continue;
            }

            foreach ($this->tagLinkRepository->getTagsOfContent($subject) as $tag) {
                $category = new Category();
                $category->setLabel($tag);

                $item->addCategory($category);
            }

            yield $item;
        }
    }

    private function getCriteriaFromRequest(Request $request): ContentPageView
    {
        $criteria = new ContentPageView(1, $this->security);
        $criteria->sortOption = Criteria::SORT_NEW;

        $content = $request->get('content');
        if ($content && \in_array($content, Criteria::CONTENT_OPTIONS, true)) {
            $criteria->setContent($content);
        } else {
            $criteria->setContent(Criteria::CONTENT_THREADS);
        }

        if ($magazine = $request->get('magazine')) {
            $magazine = $this->magazineRepository->findOneBy(['name' => $magazine]);
            if (!$magazine) {
                throw new NotFoundHttpException();
            }
            $criteria->magazine = $magazine;
        }

        if ($user = $request->get('user')) {
            $user = $this->userRepository->findOneByUsername($user);
            if (!$user) {
                throw new NotFoundHttpException();
            }
            $criteria->user = $user;
        }

        if ($domain = $request->get('domain')) {
            $criteria->setDomain($domain);
        }

        if ($tag = $request->get('tag')) {
            $criteria->tag = $tag;
        }

        if ($sortBy = $request->get('sortBy')) {
            $criteria->showSortOption($sortBy);
        }

        // Since we currently do not have a way of authenticating the user, these feeds do not work.
        // They are also not being generated and therefore not used in the sidebar.
        // $id = $request->get('id');
        // if ('sub' === $id) {
        //     $criteria->subscribed = true;
        // } elseif ('mod' === $id) {
        //     $criteria->moderated = true;
        // }

        return $criteria;
    }
}
