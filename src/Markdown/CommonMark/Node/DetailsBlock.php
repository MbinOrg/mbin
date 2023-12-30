<?php

declare(strict_types=1);

namespace App\Markdown\CommonMark\Node;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Node;

class DetailsBlock extends AbstractBlock
{
    public const FENCE_CHAR = ':';

    private bool $spoiler = false;

    public function __construct(
        private string $title,
        private int $length,
        private int $offset,
    ) {
        parent::__construct();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    private function isSummaryBlock(Node $node): bool
    {
        return 'summary' === $node->data->get('section', '');
    }

    public function getSummary(): ?Node
    {
        foreach ($this->children() as $cnode) {
            if ($this->isSummaryBlock($cnode)) {
                return $cnode;
            }
        }

        return null;
    }

    /** @return iterable<Node> */
    public function getContents(): iterable
    {
        $children = [];
        foreach ($this->children() as $cnode) {
            if (!$this->isSummaryBlock($cnode)) {
                $children[] = $cnode;
            }
        }

        return $children;
    }

    public function isSpoiler(): bool
    {
        return $this->spoiler;
    }

    public function setSpoiler(bool $spoiler): void
    {
        $this->spoiler = $spoiler;
        if ($spoiler) {
            $this->data->append('attributes/class', 'spoiler');
        } else {
            $classes = $this->data->get('attributes/class', '');
            $this->data->set('attributes/class', array_diff(explode(' ', $classes), ['spoiler']));
        }
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function setLength(int $length): void
    {
        $this->length = $length;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): void
    {
        $this->offset = $offset;
    }
}
