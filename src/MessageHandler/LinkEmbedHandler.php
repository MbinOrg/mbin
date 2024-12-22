<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\Contracts\MessageInterface;
use App\Message\LinkEmbedMessage;
use App\Repository\EmbedRepository;
use App\Utils\Embed;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LinkEmbedHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmbedRepository $embedRepository,
        private readonly Embed $embed,
        private readonly CacheItemPoolInterface $markdownCache,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(LinkEmbedMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof LinkEmbedMessage)) {
            throw new \LogicException();
        }
        preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $message->body, $match);

        foreach ($match[0] as $url) {
            try {
                $embed = $this->embed->fetch($url)->html;
                if ($embed) {
                    $entity = new \App\Entity\Embed($url, true);
                    $this->embedRepository->add($entity);
                }
            } catch (\Exception $e) {
                $embed = false;
            }

            if (!$embed) {
                $entity = new \App\Entity\Embed($url, false);
                $this->embedRepository->add($entity);
            }
        }

        $this->markdownCache->deleteItem(hash('sha256', json_encode(['content' => $message->body])));
    }
}
