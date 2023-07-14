<?php

declare(strict_types=1);

namespace App\Markdown\CommonMark;

use App\Markdown\CommonMark\Node\ActorSearchLink;
use App\Markdown\CommonMark\Node\CommunityLink;
use App\Markdown\CommonMark\Node\MentionLink;
use App\Markdown\CommonMark\Node\TagLink;
use App\Repository\EmbedRepository;
use App\Service\ImageManager;
use App\Service\SettingsManager;
use App\Utils\Embed;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\RegexHelper;

final class ExternalLinkRenderer implements NodeRendererInterface
{
    public function __construct(
        private readonly Embed $embed,
        private readonly EmbedRepository $embedRepository,
        private readonly SettingsManager $settingsManager
    ) {
    }

    public function render(
        Node $node,
        ChildNodeRendererInterface $childRenderer
    ): HtmlElement {
        /** @var Link $node */
        Link::assertInstanceOf($node);

        $url = $title = $node->getUrl();

        if ($node->firstChild() instanceof Text) {
            $title = $childRenderer->renderNodes([$node->firstChild()]);
        }

        if (
            ! $this->isMentionType($node)
                && (ImageManager::isImageUrl($url) 
                    || $this->isEmbed($url, $title)
                )
        ) {
            return EmbedElement::buildEmbed($url, $title);
        }

        $attr = match ($node::class) {
            ActorSearchLink::class => [],
            CommunityLink::class   => $this->generateCommunityLinkData($node),
            MentionLink::class     => $this->generateMentionLinkData($node),
            TagLink::class         => [
                'class' => 'hashtag tag', 
                'rel'  =>  'tag',
            ],
            default => [
                'class' => 'kbin-media-link', 
                'rel'   => 'nofollow noopener noreferrer',
            ],
        };

        if (false !== filter_var($url, FILTER_VALIDATE_URL) && !$this->settingsManager->isLocalUrl($url)) {
            $attr['rel'] = 'noopener noreferrer nofollow';
            $attr['target'] = '_blank';
        }

        if (RegexHelper::isLinkPotentiallyUnsafe(trim($url))) {
            return new HtmlElement(
                'span',
                [],
                $title
            );
        }

        return new HtmlElement(
            'a',
            ['href' => $url] + $attr,
            $title
        );
    }

    /**
     * @param MentionLink $link
     * @return array{
     *     class: string,
     *     title: string,
     *     data-action: string,
     *     data-mentions-username-param: string,
     * }
     */
    private function generateMentionLinkData(MentionLink $link): array
    {
        $data = [
            'class'                        => 'mention',
            'title'                        => $link->getTitle(),
            'data-mentions-username-param' => $link->getKbinUsername(),
        ];

        if ($link->getType() === MentionType::Magazine || $link->getType() === MentionType::RemoteMagazine) {
            $data['class']       = $data['class'] . ' mention--magazine';
            $data['data-action'] = 'mentions#navigate_magazine';
        }

        if ($link->getType() === MentionType::User || $link->getType() === MentionType::RemoteUser) {
            $data['class']       = $data['class'] . ' u-url mention--user';
            $data['data-action'] = 'mouseover->mentions#user_popup mentions#navigate_user';
        }

        return $data;    
    }

    /**
     * @param CommunityLink $link
     * @return array{
     *     class: string,
     *     title: string,
     *     data-action: string,
     *     data-mentions-username-param: string,
     * }
     */
    private function generateCommunityLinkData(CommunityLink $link): array
    {
        $data = [
            'class'                        => 'mention  mention--magazine',
            'title'                        => $link->getTitle(),
            'data-mentions-username-param' => $link->getKbinUsername(),
            'data-action'                  => 'mentions#navigate_magazine',
        ];

        return $data;    
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
            ActorSearchLink::class,
            CommunityLink::class,
            MentionLink::class,
            TagLink::class,
        ];

        foreach ($types as $type) {
            if ($link instanceof $type) {
                return true;
            }
        }

        return false;
    }
}
