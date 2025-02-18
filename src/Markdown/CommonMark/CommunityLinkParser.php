<?php

declare(strict_types=1);

namespace App\Markdown\CommonMark;

use App\Markdown\CommonMark\Node\ActorSearchLink;
use App\Markdown\CommonMark\Node\CommunityLink;
use App\Markdown\CommonMark\Node\UnresolvableLink;
use App\Repository\MagazineRepository;
use App\Service\SettingsManager;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CommunityLinkParser implements InlineParserInterface
{
    public const COMMUNITY_REGEX = '\B!(\w{1,30})(?:@)?((?:[\pL\pN\pS\pM\-\_]++\.)+[\pL\pN\pM]++|[a-z0-9\-\_]++)?';

    public function __construct(
        private readonly MagazineRepository $magazineRepository,
        private readonly SettingsManager $settingsManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex(self::COMMUNITY_REGEX);
    }

    public function parse(InlineParserContext $ctx): bool
    {
        $cursor = $ctx->getCursor();
        $cursor->advanceBy($ctx->getFullMatchLength());

        $matches = $ctx->getSubMatches();
        $handle = $matches['0'];
        $domain = $matches['1'] ?? $this->settingsManager->get('KBIN_DOMAIN');

        $fullHandle = $handle.'@'.$domain;
        $isRemote = $this->isRemoteCommunity($domain);
        $magazine = $this->magazineRepository->findOneByName($isRemote ? $fullHandle : $handle);

        $this->removeSurroundingLink($ctx, $handle, $domain);

        if ($magazine) {
            $ctx->getContainer()->appendChild(
                new CommunityLink(
                    $this->urlGenerator->generate('front_magazine', ['name' => $magazine->name]),
                    '!'.$handle,
                    '!'.($isRemote ? $magazine->apId : $magazine->name),
                    $isRemote ? $magazine->apId : $magazine->name,
                    $isRemote ? MentionType::RemoteMagazine : MentionType::Magazine,
                ),
            );

            return true;
        }

        if ($isRemote) {
            $ctx->getContainer()->appendChild(
                new ActorSearchLink(
                    $this->urlGenerator->generate('search', ['search[q]' => $fullHandle], UrlGeneratorInterface::ABSOLUTE_URL),
                    '!'.$fullHandle,
                    '!'.$fullHandle,
                )
            );

            return true;
        }

        // unable to resolve a local '!' link so don't even try.
        $ctx->getContainer()->appendChild(new UnresolvableLink('!'.$handle));

        return true;
    }

    private function isRemoteCommunity(?string $domain): bool
    {
        return $domain !== $this->settingsManager->get('KBIN_DOMAIN');
    }

    /**
     * Removes a surrounding link from the parsing container if the link contains $handle and $domain.
     *
     * @param string $handle the user handle in [!@]handle@domain
     * @param string $domain the domain in [!@]handle@domain
     */
    public static function removeSurroundingLink(InlineParserContext $ctx, string $handle, string $domain): void
    {
        $cursor = $ctx->getCursor();
        $prev = $cursor->peek(-1 - $ctx->getFullMatchLength());
        $next = $cursor->peek(0);
        $nextNext = $cursor->peek(1);
        if ('[' === $prev && ']' === $next && '(' === $nextNext) {
            $closing = null;
            $link = '';
            for ($i = 2; $char = $cursor->peek($i); ++$i) {
                if (')' === $char) {
                    $closing = $i;
                    break;
                }
                $link .= $char;
            }
            if (null !== $closing && str_contains($link, $handle) && str_contains($link, $domain)) {
                // this is probably a lemmy community link a lá [!magazine@domain.tld](https://domain.tld/c/magazine]
                $container = $ctx->getContainer();
                $prev = $container->lastChild();
                if ('[' === $prev->getLiteral()) {
                    $prev->detach();
                }
                $ctx->getDelimiterStack()->removeBracket();
                $cursor->advanceBy($closing + 1);
                $current = $cursor->peek(0);
            }
        }
    }
}
