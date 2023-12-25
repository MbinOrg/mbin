<?php

declare(strict_types=1);

namespace App\Markdown\CommonMark;

use App\Markdown\CommonMark\Node\DetailsBlock;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class DetailsBlockRenderer implements NodeRendererInterface
{
    /** @param DetailsBlock $node */
    public function render(
        Node $node,
        ChildNodeRendererInterface $childRenderer
    ): HtmlElement {
        DetailsBlock::assertInstanceOf($node);

        $attrs = $node->data->get('attributes');

        $summary = $node->getSummary();
        $contents = $node->getContents();

        return new HtmlElement(
            'details',
            $attrs,
            [
                new HtmlElement(
                    'summary',
                    [],
                    $summary ? $childRenderer->renderNodes($summary->children()) : '',
                ),
                new HtmlElement(
                    'div',
                    [
                        'class' => 'content',
                    ],
                    $childRenderer->renderNodes($contents),
                ),
            ]
        );
    }
}
