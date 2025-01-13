<?php

declare(strict_types=1);

namespace App\Markdown\CommonMark;

use App\Controller\User\ThemeSettingsController;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Markdown\CommonMark\Node\ActivityPubMentionLink;
use App\Markdown\CommonMark\Node\ActorSearchLink;
use App\Markdown\CommonMark\Node\CommunityLink;
use App\Markdown\CommonMark\Node\MentionLink;
use App\Markdown\CommonMark\Node\RoutedMentionLink;
use App\Markdown\CommonMark\Node\TagLink;
use App\Markdown\CommonMark\Node\UnresolvableLink;
use App\Markdown\MarkdownConverter;
use App\Markdown\RenderTarget;
use App\Repository\ApActivityRepository;
use App\Repository\EmbedRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ImageManager;
use App\Service\SettingsManager;
use App\Utils\Embed;
use App\Utils\UrlUtils;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\RegexHelper;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class ExternalLinkRenderer implements NodeRendererInterface, ConfigurationAwareInterface
{
    private ConfigurationInterface $config;

    public function __construct(
        private readonly Embed $embed,
        private readonly EmbedRepository $embedRepository,
        private readonly SettingsManager $settingsManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
        private readonly ApActivityRepository $activityRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->config = $configuration;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        /* @var Link $node */
        Link::assertInstanceOf($node);

        $isApRequest = UrlUtils::isActivityPubRequest($this->requestStack->getCurrentRequest());
        if (!$isApRequest && $node instanceof MentionLink && $this->isExistingMentionType($node)) {
            $this->logger->debug("Got node of class {c}: kbinUsername: '{k}', title: '{t}', type: '{ty}', url: '{url}'", [
                'c' => \get_class($node),
                'k' => $node->getKbinUsername(),
                't' => $node->getTitle(),
                'ty' => $node->getType(),
                'url' => $node->getUrl(),
            ]);

            return new HtmlElement('span', contents: $this->renderMentionType($node, $childRenderer));
        } else {
            $this->logger->debug("Got node of class {c}: title: '{t}', url: '{url}'", [
                'c' => \get_class($node),
                't' => $node->getTitle(),
                'url' => $node->getUrl(),
            ]);
        }

        $apActivity = $this->activityRepository->findByObjectId($node->getUrl());
        if (!$isApRequest && null !== $apActivity && Message::class !== $apActivity['type']) {
            $this->logger->debug('Found activity with url {u}: {t} - {id}', [
                'u' => $node->getUrl(),
                't' => $apActivity['type'],
                'id' => $apActivity['id'],
            ]);
            /** @var Entry|EntryComment|Post|PostComment $entity */
            $entity = $this->entityManager->getRepository($apActivity['type'])->find($apActivity['id']);

            return new HtmlElement('div', contents: $this->renderInlineEntity($entity));
        }

        $renderTarget = $this->config->get('kbin')[MarkdownConverter::RENDER_TARGET];

        $url = $title = match ($node::class) {
            RoutedMentionLink::class => $this->generateUrlForRoute($node, $renderTarget),
            default => $node->getUrl(),
        };

        if (RegexHelper::isLinkPotentiallyUnsafe($url)) {
            return new HtmlElement(
                'span',
                ['class' => 'unsafe-link'],
                $title
            );
        }

        // skip rendering links inside the label (not allowed)
        if ($node->hasChildren()) {
            $cnodes = [];
            foreach ($node->children() as $n) {
                if (
                    ($n instanceof Link && $n instanceof StringContainerInterface)
                    || $n instanceof UnresolvableLink
                ) {
                    $cnodes[] = new Text($n->getLiteral());
                } else {
                    $cnodes[] = $n;
                }
            }
            $title = $childRenderer->renderNodes($cnodes);
        }

        if (
            !$this->isMentionType($node)
            && (ImageManager::isImageUrl($url) || $this->isEmbed($url, $title))
            && RenderTarget::Page === $renderTarget
        ) {
            return EmbedElement::buildEmbed($url, $title);
        }

        // create attributes for link
        $attr = $this->generateAttr($node, $renderTarget);

        // open non-local links in a new tab
        if (false !== filter_var($url, FILTER_VALIDATE_URL)
            && !$this->settingsManager->isLocalUrl($url)
            && RenderTarget::ActivityPub !== $renderTarget
        ) {
            $attr['rel'] = 'noopener noreferrer nofollow';
            $attr['target'] = '_blank';
        }

        return new HtmlElement(
            'a',
            ['href' => $url] + $attr,
            $title
        );
    }

    /**
     * @return array{
     *     class: string,
     *     title?: string,
     *     data-action?: string,
     *     data-mentions-username-param?: string,
     *     rel?: string,
     * }
     */
    private function generateAttr(Link $node, RenderTarget $renderTarget): array
    {
        $attr = match ($node::class) {
            ActivityPubMentionLink::class => $this->generateMentionLinkAttr($node),
            ActorSearchLink::class => [],
            CommunityLink::class => $this->generateCommunityLinkAttr($node),
            RoutedMentionLink::class => $this->generateMentionLinkAttr($node),
            TagLink::class => [
                'class' => 'hashtag tag',
                'rel' => 'tag',
            ],
            default => [
                'class' => 'kbin-media-link',
            ],
        };

        if (RenderTarget::ActivityPub === $renderTarget) {
            $attr = array_intersect_key($attr, ['class', 'title', 'rel']);
        }

        return $attr;
    }

    /**
     * @return array{
     *     class: string,
     *     title: string,
     *     data-action: string,
     *     data-mentions-username-param: string,
     * }
     */
    private function generateMentionLinkAttr(MentionLink $link): array
    {
        $data = [
            'class' => 'mention',
            'title' => $link->getTitle(),
            'data-mentions-username-param' => $link->getKbinUsername(),
        ];

        if (MentionType::Magazine === $link->getType() || MentionType::RemoteMagazine === $link->getType()) {
            $data['class'] = $data['class'].' mention--magazine';
            $data['data-action'] = 'mentions#navigate_magazine';
        }

        if (MentionType::User === $link->getType() || MentionType::RemoteUser === $link->getType()) {
            $data['class'] = $data['class'].' u-url mention--user';
            $data['data-action'] = 'mouseover->mentions#user_popup mouseout->mentions#user_popup_out mentions#navigate_user';
        }

        return $data;
    }

    /**
     * @return array{
     *     class: string,
     *     title: string,
     *     data-action: string,
     *     data-mentions-username-param: string,
     * }
     */
    private function generateCommunityLinkAttr(CommunityLink $link): array
    {
        $data = [
            'class' => 'mention mention--magazine',
            'title' => $link->getTitle(),
            'data-mentions-username-param' => $link->getKbinUsername(),
            'data-action' => 'mentions#navigate_magazine',
        ];

        return $data;
    }

    private function generateUrlForRoute(RoutedMentionLink $routedMentionLink, RenderTarget $renderTarget): string
    {
        return $this->urlGenerator->generate(
            $routedMentionLink->getRoute(),
            [$routedMentionLink->getParamName() => $routedMentionLink->getUrl()],
            RenderTarget::ActivityPub === $renderTarget
                ? UrlGeneratorInterface::ABSOLUTE_URL
                : UrlGeneratorInterface::ABSOLUTE_PATH
        );
    }

    private function isEmbed(string $url, string $title): bool
    {
        $embed = false;
        if (filter_var($url, FILTER_VALIDATE_URL) && $entity = $this->embedRepository->findOneBy(['url' => $url])) {
            $embed = $entity->hasEmbed;
        }

        return (bool) $embed;
    }

    private function isMentionType(Link $link): bool
    {
        $types = [
            ActivityPubMentionLink::class,
            ActorSearchLink::class,
            CommunityLink::class,
            RoutedMentionLink::class,
            TagLink::class,
        ];

        foreach ($types as $type) {
            if ($link instanceof $type) {
                return true;
            }
        }

        return false;
    }

    private function isExistingMentionType(Link $link): bool
    {
        if ($link instanceof CommunityLink || $link instanceof ActivityPubMentionLink || $link instanceof RoutedMentionLink) {
            if (MentionType::Unresolvable !== $link->getType() && MentionType::Search !== $link->getType()) {
                return true;
            }
        }

        return false;
    }

    private function renderMentionType(MentionLink $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (MentionType::User === $node->getType() || MentionType::RemoteUser === $node->getType()) {
            return $this->renderUser($node, $childRenderer);
        } elseif (MentionType::Magazine === $node->getType() || MentionType::RemoteMagazine === $node->getType()) {
            return $this->renderMagazine($node, $childRenderer);
        } else {
            throw new \LogicException('dont know type of '.\get_class($node));
        }
    }

    private function renderUser(MentionLink $node, ChildNodeRendererInterface $childRenderer): string
    {
        $username = $node->getKbinUsername();
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user) {
            $this->logger->error('cannot render {o}, couldn\'t find user {u}', ['o' => $node, 'u' => $username]);

            return '';
        }

        return $this->twig->render('components/user_inline.html.twig', [
            'user' => $user,
            'showAvatar' => true,
            'fullName' => ThemeSettingsController::getShowUserFullName($this->requestStack->getCurrentRequest()),
        ]);
    }

    private function renderMagazine(MentionLink $node, ChildNodeRendererInterface $childRenderer): string
    {
        $magName = $node->getKbinUsername();
        $magazine = $this->magazineRepository->findOneByName($magName);
        if (!$magazine) {
            $this->logger->error('cannot render {o}, couldn\'t find magazine {m}', ['o' => $node, 'm' => $magName]);

            return '';
        }

        return $this->twig->render('components/magazine_inline.html.twig', [
            'magazine' => $magazine,
            'stretchedLink' => false,
            'fullName' => ThemeSettingsController::getShowMagazineFullName($this->requestStack->getCurrentRequest()),
            'showAvatar' => true,
        ]);
    }

    private function renderInlineEntity(Entry|EntryComment|Post|PostComment $entity): string
    {
        if ($entity instanceof Entry) {
            return $this->twig->render('components/entry_inline_md.html.twig', [
                'entry' => $entity,
                'userFullName' => ThemeSettingsController::getShowUserFullName($this->requestStack->getCurrentRequest()),
                'magazineFullName' => ThemeSettingsController::getShowMagazineFullName($this->requestStack->getCurrentRequest()),
            ]);
        } elseif ($entity instanceof EntryComment) {
        } elseif ($entity instanceof Post) {
            return $this->twig->render('components/post_inline_md.html.twig', [
                'post' => $entity,
                'userFullName' => ThemeSettingsController::getShowUserFullName($this->requestStack->getCurrentRequest()),
                'magazineFullName' => ThemeSettingsController::getShowMagazineFullName($this->requestStack->getCurrentRequest()),
            ]);
        } elseif ($entity instanceof PostComment) {
        }

        return '';
    }
}
