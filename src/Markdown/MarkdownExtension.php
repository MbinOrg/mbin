<?php

declare(strict_types=1);

namespace App\Markdown;

use App\Controller\User\ThemeSettingsController;
use App\Markdown\CommonMark\CommunityLinkParser;
use App\Markdown\CommonMark\DetailsBlockRenderer;
use App\Markdown\CommonMark\DetailsBlockStartParser;
use App\Markdown\CommonMark\ExternalImagesRenderer;
use App\Markdown\CommonMark\ExternalLinkRenderer;
use App\Markdown\CommonMark\MentionLinkParser;
use App\Markdown\CommonMark\Node\DetailsBlock;
use App\Markdown\CommonMark\Node\UnresolvableLink;
use App\Markdown\CommonMark\TagLinkParser;
use App\Markdown\CommonMark\UnresolvableLinkRenderer;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ConfigurableExtensionInterface;
use League\Config\ConfigurationBuilderInterface;
use Nette\Schema\Expect;
use Symfony\Component\HttpFoundation\Request;

final class MarkdownExtension implements ConfigurableExtensionInterface
{
    public function __construct(
        private readonly CommunityLinkParser $communityLinkParser,
        private readonly MentionLinkParser $mentionLinkParser,
        private readonly TagLinkParser $tagLinkParser,
        private readonly ExternalLinkRenderer $linkRenderer,
        private readonly ExternalImagesRenderer $imagesRenderer,
        private readonly UnresolvableLinkRenderer $unresolvableLinkRenderer,
        private readonly DetailsBlockStartParser $detailsBlockStartParser,
        private readonly DetailsBlockRenderer $detailsBlockRenderer,
    ) {
    }

    public function configureSchema(ConfigurationBuilderInterface $builder): void
    {
        $builder->addSchema('kbin', Expect::structure([
            'render_target' => Expect::type(RenderTarget::class),
            'richMention' => Expect::bool(true),
            'richMagazineMention' => Expect::bool(true),
            'richAPLink' => Expect::bool(true),
        ]));
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser($this->detailsBlockStartParser);

        $environment->addInlineParser($this->communityLinkParser);
        $environment->addInlineParser($this->mentionLinkParser);
        $environment->addInlineParser($this->tagLinkParser);

        $environment->addRenderer(Link::class, $this->linkRenderer, 1);
        $environment->addRenderer(Image::class, $this->imagesRenderer, 1);
        $environment->addRenderer(UnresolvableLink::class, $this->unresolvableLinkRenderer, 1);
        $environment->addRenderer(DetailsBlock::class, $this->detailsBlockRenderer, 1);
    }

    /**
     * @return array{richMention: bool, richMagazineMention: bool, richAPLink: bool}
     */
    public static function getMdRichConfig(?Request $request, string $sourceType = ''): array
    {
        if ('entry' === $sourceType) {
            return [
                'richMention' => ThemeSettingsController::getShowRichMentionEntry($request),
                'richMagazineMention' => ThemeSettingsController::getShowRichMagazineMentionEntry($request),
                'richAPLink' => ThemeSettingsController::getShowRichAPLinkEntries($request),
            ];
        } elseif ('post' === $sourceType) {
            return [
                'richMention' => ThemeSettingsController::getShowRichMentionPosts($request),
                'richMagazineMention' => ThemeSettingsController::getShowRichMagazineMentionPosts($request),
                'richAPLink' => ThemeSettingsController::getShowRichAPLinkPosts($request),
            ];
        } else {
            return [
                'richMention' => true,
                'richMagazineMention' => true,
                'richAPLink' => true,
            ];
        }
    }
}
