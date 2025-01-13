<?php

declare(strict_types=1);

namespace App\Markdown\Event;

use App\Controller\User\ThemeSettingsController;
use App\Utils\UrlUtils;
use Symfony\Component\HttpFoundation\Request;

/**
 * Event dispatched to build a hash key for Markdown context.
 */
class BuildCacheContext
{
    private array $context = [];

    public function __construct(
        private readonly ConvertMarkdown $convertMarkdownEvent,
        private readonly ?Request $request,
    ) {
        $this->addToContext('content', $convertMarkdownEvent->getMarkdown());
        $this->addToContext('target', $convertMarkdownEvent->getRenderTarget()->name);
        $this->addToContext('userFullName', ThemeSettingsController::getShowUserFullName($this->request) ? '1' : '0');
        $this->addToContext('magazineFullName', ThemeSettingsController::getShowMagazineFullName($this->request) ? '1' : '0');
        $this->addToContext('apRequest', UrlUtils::isActivityPubRequest($this->request) ? '1' : '0');
    }

    public function addToContext(string $key, ?string $value = null): void
    {
        $this->context[$key] = $value;
    }

    public function getConvertMarkdownEvent(): ConvertMarkdown
    {
        return $this->convertMarkdownEvent;
    }

    public function getCacheKey(): string
    {
        ksort($this->context);

        $jsonContext = json_encode($this->context);
        $hash = hash('sha256', $jsonContext);

        return "md_$hash";
    }

    public function hasContext(string $key, ?string $value = null): bool
    {
        if (!\array_key_exists($key, $this->context)) {
            return false;
        }

        if (\func_num_args() < 2) {
            return true;
        }

        return $this->context[$key] === $value;
    }
}
