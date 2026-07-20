<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Message;
use App\Entity\User;
use App\Factory\Contract\ActivityFactoryInterface;
use App\Markdown\MarkdownConverter;
use App\Markdown\RenderTarget;
use App\Service\ActivityPub\ContextsProvider;
use App\Service\Contracts\SwitchableService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements SwitchableService
 * @implements ActivityPubActivityInterface<Message>
 */
class MessageFactory implements SwitchableService, ActivityFactoryInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MarkdownConverter $markdownConverter,
        private readonly ContextsProvider $contextsProvider,
    ) {
    }

    public function getSupportedTypes(): array
    {
        return [Message::class];
    }

    public function create($subject, array $tags, bool $context = false): array
    {
        return $this->build($subject, $context);
    }

    public function build(Message $message, bool $includeContext = true): array
    {
        $actorUrl = null === $message->sender->apId ? $this->urlGenerator->generate('ap_user', ['username' => $message->sender->username], UrlGeneratorInterface::ABSOLUTE_URL) : $message->sender->apPublicUrl;
        $toUsers = array_values(array_filter($message->thread->participants->toArray(), fn (User $item) => $item->getId() !== $message->sender->getId()));
        $to = array_map(fn (User $user) => !$user->apId ? $this->urlGenerator->generate('ap_user', ['username' => $user->username], UrlGeneratorInterface::ABSOLUTE_URL) : $user->apPublicUrl, $toUsers);

        $result = [
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_message', ['uuid' => $message->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'attributedTo' => $actorUrl,
            'to' => $to,
            'cc' => [],
            'type' => 'ChatMessage',
            'published' => $message->createdAt->format(DATE_ATOM),
            'content' => $this->markdownConverter->convertToHtml($message->body, context: [MarkdownConverter::RENDER_TARGET => RenderTarget::ActivityPub]),
            'mediaType' => 'text/html',
            'source' => [
                'mediaType' => 'text/markdown',
                'content' => $message->body,
            ],
        ];

        if (!$includeContext) {
            unset($result['@context']);
        }

        return $result;
    }
}
