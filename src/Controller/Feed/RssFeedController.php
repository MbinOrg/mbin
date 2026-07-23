<?php

namespace App\Controller\Feed;

use App\Controller\AbstractController;
use App\Service\FeedManager;
use DateTimeZone;
use FeedIo\Formatter\XmlFormatter;
use FeedIo\FormatterInterface;
use FeedIo\Rule\DateTimeBuilder;
use FeedIo\Standard\Rss;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RssFeedController extends AbstractController
{

    private FormatterInterface $formatter;

    public function __construct(
        private readonly FeedManager $feedManager,
        private readonly LoggerInterface $logger,
    )
    {
        $dateTimeBuilder = new DateTimeBuilder($this->logger);
        $dateTimeBuilder->setFeedTimezone(new DateTimeZone('UTC'));
        $this->formatter = new XmlFormatter(new Rss($dateTimeBuilder));
    }

    public function feed(Request $request): Response
    {
        $feed = $this->feedManager->getFeed($request);
        $rss = $this->formatter->toString($feed);

        return new Response($rss, 200, [
            'Content-Type' => 'application/rss+xml; charset=utf-8',
        ]);
    }
}
