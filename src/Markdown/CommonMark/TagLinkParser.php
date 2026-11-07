<?php

declare(strict_types=1);

namespace App\Markdown\CommonMark;

use App\Markdown\CommonMark\Node\TagLink;
use App\Utils\RegPatterns;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TagLinkParser implements InlineParserInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    #[\Override]
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex(RegPatterns::LOCAL_TAG_REGEX);
    }

    #[\Override]
    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $cursor->advanceBy($inlineContext->getFullMatchLength());

        [$tag] = $inlineContext->getSubMatches();

        $url = $this->urlGenerator->generate(
            'tag_overview',
            ['name' => $tag],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $inlineContext->getContainer()->appendChild(new TagLink($url, '#'.$tag));

        return true;
    }
}
