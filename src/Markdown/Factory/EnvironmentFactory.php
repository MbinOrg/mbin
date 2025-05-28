<?php

declare(strict_types=1);

namespace App\Markdown\Factory;

use App\Markdown\MarkdownExtension as KbinMarkdownExtension;
use App\Markdown\RenderTarget;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\Autolink\UrlAutolinkParser;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use Psr\Container\ContainerInterface;

class EnvironmentFactory
{
    public function __construct(
        private readonly ContainerInterface $container,
        private array $config,
    ) {
    }

    public function createEnvironment(
        RenderTarget $renderTarget,
        bool $richMention,
        bool $richMagazineMention,
        bool $richAPLink,
    ): EnvironmentInterface {
        $this->config['kbin'] = [
            'render_target' => $renderTarget,
            'richMention' => $richMention,
            'richMagazineMention' => $richMagazineMention,
            'richAPLink' => $richAPLink,
        ];

        $env = new Environment($this->config);

        $env->addInlineParser($this->container->get(UrlAutolinkParser::class))
            ->addExtension($this->container->get(CommonMarkCoreExtension::class))
            ->addExtension($this->container->get(StrikethroughExtension::class))
            ->addExtension($this->container->get(TableExtension::class))
            ->addExtension($this->container->get(KbinMarkdownExtension::class));

        return $env;
    }
}
