<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Message;
use App\Entity\User;
use App\Markdown\MarkdownConverter;
use App\Service\ActivityPub\ContextsProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MessageFactory
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MarkdownConverter $markdownConverter,
        private readonly ContextsProvider $contextsProvider,
    ) {
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
            'content' => $this->markdownConverter->convertToHtml($message->body),
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
