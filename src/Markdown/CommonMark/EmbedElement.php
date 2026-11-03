<?php

declare(strict_types=1);

namespace App\Markdown\CommonMark;

use App\Markdown\CommonMark\Node\UnresolvableLink;
use App\Service\DomainManager;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Util\HtmlElement;

class EmbedElement
{
    /**
     * @param string      $url   must not be escaped
     * @param string|null $label must be sanetized (unsafe elements escaped)
     *
     * @return HtmlElement a span showing a button with the media icon + a link to the url + a span for the preview container
     */
    public static function buildEmbed(string $url, ?string $label = null): HtmlElement
    {
        return new HtmlElement(
            'span',
            [
                'class' => 'preview',
                'data-controller' => 'preview',
            ],
            [
                new HtmlElement(
                    'button',
                    [
                        'class' => 'show-preview',
                        'data-action' => 'preview#show',
                        'data-preview-url-param' => $url,
                        'data-preview-ratio-param' => DomainManager::shouldRatio($url) ? '1' : '0',
                        'aria-label' => 'Show preview',
                    ],
                    new HtmlElement(
                        'i',
                        [
                            'class' => 'fas fa-photo-video',
                        ],
                    ),
                ),
                new HtmlElement(
                    'a',
                    [
                        'href' => $url,
                        'rel' => 'nofollow noopener noreferrer',
                        'target' => '_blank',
                    ],
                    $label
                ),
                new HtmlElement(
                    'span',
                    [
                        'class' => 'preview-target hidden',
                        'data-preview-target' => 'container',
                    ]
                ),
            ]
        );
    }

    /**
     * @param string      $url   must be escaped
     * @param string|null $label must be escaped
     *
     * @return HtmlElement a span showing the media icon followed by the label (if any) and the url
     */
    public static function buildDestructed(string $url, ?string $label = null): HtmlElement
    {
        return new HtmlElement(
            'span',
            [],
            [
                new HtmlElement('i', ['class' => 'fas fa-photo-video']),
                $label ? \sprintf(' %s (%s)', $label, $url) : ' '.$url,
            ]
        );
    }

    /**
     * returns true if the node has no descendents which are not inline elements or raw html.
     *
     * @param bool $forbidExternalLinks if true links with content and UnresolvableLink are also forbidden
     */
    public static function checkForbiddenDescendantNodes(Node $node, bool $forbidExternalLinks): bool
    {
        foreach ($node->children() as $n) {
            if (!($n instanceof AbstractInline)) {
                return false;
            }
            if ($n instanceof RawMarkupContainerInterface) {
                return false;
            }
            if ($forbidExternalLinks && (
                ($n instanceof Link && $n instanceof StringContainerInterface)
                || (Link::class === \get_class($n))
                || $n instanceof UnresolvableLink
            )) {
                return false;
            }
            if (!self::checkForbiddenDescendantNodes($n, $forbidExternalLinks)) {
                return false;
            }
        }
        return true;
    }
}
