<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use League\HTMLToMarkdown\Configuration;
use League\HTMLToMarkdown\ConfigurationAwareInterface;
use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\ElementInterface;

/**
 * Inspired by https://github.com/thephpleague/html-to-markdown/blob/master/src/Converter/EmphasisConverter.php.
 */
class StrikethroughConverter implements ConverterInterface, ConfigurationAwareInterface
{
    protected Configuration $config;

    public function getSupportedTags(): array
    {
        return ['del', 'strike'];
    }

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        $value = $element->getValue();
        if (!trim($value)) {
            return $value;
        }

        $prefix = ltrim($value) !== $value ? ' ' : '';
        $suffix = rtrim($value) !== $value ? ' ' : '';

        /* If this node is immediately preceded or followed by one of the same type don't emit
         * the start or end $style, respectively. This prevents <del>foo</del><del>bar</del> from
         * being converted to ~~foo~~~~bar~~ which is incorrect. We want ~~foobar~~ instead.
         */
        $preStyle = \in_array($element->getPreviousSibling()?->getTagName(), $this->getSupportedTags()) ? '' : '~~';
        $postStyle = \in_array($element->getNextSibling()?->getTagName(), $this->getSupportedTags()) ? '' : '~~';

        return $prefix.$preStyle.trim($value).$postStyle.$suffix;
    }
}
