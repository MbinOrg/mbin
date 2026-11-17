<?php

declare(strict_types=1);

namespace App\Utils;

class M3u8EmbedFixture
{
    public const string MASTER_PATH = '/_dev/m3u8-embed/master.m3u8';

    public function __construct(private readonly string $kbinDomain)
    {
    }

    public function getMasterUrl(): string
    {
        return 'http://'.$this->kbinDomain.self::MASTER_PATH;
    }

    public function getIframeHtml(): string
    {
        return '<iframe src="'.htmlspecialchars($this->getMasterUrl(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" width="640" height="360" allowfullscreen></iframe>';
    }
}
