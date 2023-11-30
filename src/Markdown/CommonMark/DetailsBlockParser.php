<?php

declare(strict_types=1);

namespace App\Markdown\CommonMark;

use App\Markdown\CommonMark\Node\DetailsBlock;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Block\BlockContinueParserWithInlinesInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\InlineParserEngineInterface;
use League\CommonMark\Util\RegexHelper;

final class DetailsBlockParser extends AbstractBlockContinueParser implements BlockContinueParserWithInlinesInterface
{
    public const FENCE_END_PATTERN = '/^(?:\:{3,})(?= *$)/';

    private DetailsBlock $block;

    public function __construct(string $title, int $fenceLength, int $fenceOffset)
    {
        $this->block = new DetailsBlock($title, $fenceLength, $fenceOffset);
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        // Check for closing fence
        if (!$cursor->isIndented()
            && DetailsBlock::FENCE_CHAR === $cursor->getNextNonSpaceCharacter()) {
            $match = RegexHelper::matchFirst(
                self::FENCE_END_PATTERN,
                $cursor->getLine(),
                $cursor->getNextNonSpacePosition()
            );

            if (null !== $match && \strlen($match[0]) >= $this->block->getLength()) {
                // closing fence found - finalize block
                return BlockContinue::finished();
            }
        }

        // Skip optional spaces of fence offset
        // Optimization: don't attempt to match if we're at a non-space position
        if ($cursor->getNextNonSpacePosition() > $cursor->getPosition()) {
            $cursor->match('/^ {0,'.$this->block->getOffset().'}/');
        }

        return BlockContinue::at($cursor);
    }

    public function closeBlock(): void
    {
        $title = $this->block->getTitle();

        if ($title && preg_match('/^spoiler\b/', $title)) {
            $this->block->setSpoiler(true);

            $title = preg_replace('/^spoiler\s*/', '', $title);
            $this->block->setTitle($title);
        }
    }

    public function parseInlines(InlineParserEngineInterface $inlineParser): void
    {
        $titleBlock = new Paragraph();
        $inlineParser->parse($this->block->getTitle(), $titleBlock);
        if ($titleBlock->hasChildren()) {
            $titleBlock->data->set('section', 'summary');
            $this->block->prependChild($titleBlock);
        }
    }

    public function getBlock(): DetailsBlock
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        if ($childBlock instanceof DetailsBlock) {
            return $this->block->getLength() > $childBlock->getLength();
        }

        return true;
    }
}
