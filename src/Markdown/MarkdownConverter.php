<?php

declare(strict_types=1);

namespace App\Markdown;

use App\Markdown\Event\ConvertMarkdown;
use Psr\EventDispatcher\EventDispatcherInterface;

class MarkdownConverter
{
    public const RENDER_TARGET = 'render_target';

    public function __construct(private readonly EventDispatcherInterface $dispatcher)
    {
    }

    public function convertToHtml(string $markdown, string $sourceType = '', array $context = []): string
    {
        $event = new ConvertMarkdown($markdown, $sourceType);
        $event->mergeAttributes($context);

        $this->dispatcher->dispatch($event);

        return (string) $event->getRenderedContent();
    }
}
