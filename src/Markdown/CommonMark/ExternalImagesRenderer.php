<?php

declare(strict_types=1);

namespace App\Markdown\CommonMark;

use App\Markdown\CommonMark\Node\UnresolvableLink;
use App\Markdown\MarkdownConverter;
use App\Markdown\RenderTarget;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;

final class ExternalImagesRenderer implements NodeRendererInterface, ConfigurationAwareInterface
{
    private ConfigurationInterface $config;

    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->config = $configuration;
    }

    /**
     * @param Image $node
     */
    public function render(
        Node $node,
        ChildNodeRendererInterface $childRenderer,
    ): HtmlElement {
        Image::assertInstanceOf($node);

        $renderTarget = $this->config->get('kbin')[MarkdownConverter::RENDER_TARGET];

        $url = $node->getUrl();
        $label = null;

        if (RenderTarget::Page === $renderTarget) {
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
                $label = $childRenderer->renderNodes($cnodes);
            }

            // self destructs rendering if parent is a link
            // because while commonmark permits putting image inside link label,
            // html does not allow nested interactive contents inside <a>
            // see: https://spec.commonmark.org/0.30/#example-516
            // and: https://developer.mozilla.org/en-US/docs/Web/HTML/Element/a#technical_summary
            if ($node->parent() && $node->parent() instanceof Link) {
                return EmbedElement::buildDestructed($node->getUrl(), $this->getAltText($node));
            }

            return EmbedElement::buildEmbed($url, $label ?? $url);
        }

        return new HtmlElement(
            'img',
            [
                'src' => $url,
                'alt' => $node->hasChildren() ? $this->getAltText($node) : false,
            ],
            '',
            true
        );
    }

    // literally lifted from league/commonmark ImageRenderer
    // see: https://github.com/thephpleague/commonmark/blob/7af3307679b2942d825562bfad202a52a03b4513/src/Extension/CommonMark/Renderer/Inline/ImageRenderer.php#L93
    private function getAltText(Image $node): string
    {
        $altText = '';

        foreach ((new NodeIterator($node)) as $n) {
            if ($n instanceof StringContainerInterface) {
                $altText .= $n->getLiteral();
            } elseif ($n instanceof Newline) {
                $altText .= ' ';
            }
        }

        return $altText;
    }
}
