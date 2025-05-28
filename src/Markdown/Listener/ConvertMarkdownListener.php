<?php

declare(strict_types=1);

namespace App\Markdown\Listener;

use App\Markdown\Event\ConvertMarkdown;
use App\Markdown\Factory\ConverterFactory;
use App\Markdown\Factory\EnvironmentFactory;
use App\Markdown\MarkdownExtension;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class ConvertMarkdownListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConverterFactory $converterFactory,
        private readonly EnvironmentFactory $environmentFactory,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConvertMarkdown::class => ['onConvertMarkdown'],
        ];
    }

    public function onConvertMarkdown(ConvertMarkdown $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $richMdConfig = MarkdownExtension::getMdRichConfig($request, $event->getSourceType());
        $environment = $this->environmentFactory->createEnvironment(
            $event->getRenderTarget(),
            $richMdConfig['richMention'],
            $richMdConfig['richMagazineMention'],
            $richMdConfig['richAPLink'],
        );

        $converter = $this->converterFactory->createConverter($environment);
        $html = $converter->convert($event->getMarkdown());

        $event->setRenderedContent($html);
    }
}
