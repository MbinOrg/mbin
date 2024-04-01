<?php

declare(strict_types=1);

namespace App\Service;

use App\Utils\RegPatterns;

class TagExtractor
{
    public function joinTagsToBody(string $body, array $tags): string
    {
        $current = $this->extract($body) ?? [];

        $join = array_unique(array_merge(array_diff($tags, $current)));

        if (!empty($join)) {
            if (!empty($body)) {
                $lastTag = end($current);
                if (($lastTag && !str_ends_with($body, $lastTag)) || !$lastTag) {
                    $body = $body.PHP_EOL.PHP_EOL;
                }
            }

            $body = $body.' #'.implode(' #', $join);
        }

        return $body;
    }

    public function extract(string $val, string $magazineName = null): ?array
    {
        preg_match_all(RegPatterns::LOCAL_TAG, $val, $matches);

        $result = $matches[1];
        $result = array_map(fn ($tag) => mb_strtolower(trim($tag)), $result);

        $result = array_values($result);

        $result = array_map(fn ($tag) => $this->transliterate($tag), $result);

        if ($magazineName) {
            $result = array_diff($result, [$magazineName]);
        }

        return \count($result) ? array_unique(array_values($result)) : null;
    }

    /**
     * transliterate and normalize a hashtag identifier.
     *
     * mostly recreates Mastodon's hashtag normalization rules, using ICU rules
     * - try to transliterate modified latin characters to ASCII regions
     * - normalize widths for fullwidth/halfwidth letters
     * - strip characters that shouldn't be part of a hashtag
     *   (borrowed the character set from Mastodon)
     *
     * @param string $tag input hashtag identifier to normalize
     *
     * @return string normalized hashtag identifier
     *
     * @see https://github.com/mastodon/mastodon/blob/main/app/lib/hashtag_normalizer.rb
     * @see https://github.com/mastodon/mastodon/blob/main/app/models/tag.rb
     */
    public function transliterate(string $tag): string
    {
        $rules = <<<'ENDRULE'
        :: Latin-ASCII;
        :: [\uFF00-\uFFEF] NFKC;
        :: [^[:alnum:][\u0E47-\u0E4E][_\u00B7\u30FB\u200c]] Remove;
        ENDRULE;

        $normalizer = \Transliterator::createFromRules($rules);

        return iconv('UTF-8', 'UTF-8//TRANSLIT', $normalizer->transliterate($tag));
    }
}
