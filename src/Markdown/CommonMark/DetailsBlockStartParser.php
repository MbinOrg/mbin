<?php

declare(strict_types=1);

namespace App\Markdown\CommonMark;

use App\Markdown\CommonMark\Node\DetailsBlock;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

final class DetailsBlockStartParser implements BlockStartParserInterface
{
    public const FENCE_START_PATTERN = '/^[ \t]*(?:\:{3,})(?!.*\:\:\:)/';

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || DetailsBlock::FENCE_CHAR !== $cursor->getNextNonSpaceCharacter()) {
            return BlockStart::none();
        }

        $indent = $cursor->getIndent();
        $fence = $cursor->match(self::FENCE_START_PATTERN);
        if (null === $fence) {
            return BlockStart::none();
        }

        // todo: maybe move title parsing into DetailsBlockParser
        $title = ltrim($cursor->getRemainder());
        $cursor->advanceToEnd();

        $fence = ltrim($fence, " \t");

        return BlockStart::of(new DetailsBlockParser($title, \strlen($fence), $indent))->at($cursor);
    }
}
