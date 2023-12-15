<?php

declare(strict_types=1);

namespace App\Twig\Components;

use kornrunner\Blurhash\Blurhash;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('blurhash_image')]
final class BlurhashImageComponent
{
    public string $blurhash;
    public int $width = 20;
    public int $height = 20;

    public function __construct(private CacheInterface $cache)
    {
    }

    public function createImage(string $blurhash, int $width = 20, int $height = 20): string
    {
        $context = [$blurhash, $width, $height];

        return $this->cache->get(
            'bh_'.hash('sha256', serialize($context)),
            function (ItemInterface $item) use ($blurhash, $width, $height) {
                $item->expiresAfter(3600);

                $pixels = Blurhash::decode($blurhash, $width, $height);
                $image = imagecreatetruecolor($width, $height);
                for ($y = 0; $y < $height; ++$y) {
                    for ($x = 0; $x < $width; ++$x) {
                        [$r, $g, $b] = $pixels[$y][$x];
                        imagesetpixel($image, $x, $y, imagecolorallocate($image, $r, $g, $b));
                    }
                }

                // I do not like this
                ob_start();
                imagepng($image);
                $out = ob_get_contents();
                ob_end_clean();

                return 'data:image/png;base64,'.base64_encode($out);
            }
        );
    }
}
