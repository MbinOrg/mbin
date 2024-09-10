<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Site;
use App\Utils\DownvotesMode;
use OpenApi\Attributes as OA;

#[OA\Schema()]
class SiteResponseDto implements \JsonSerializable
{
    public const PAGES = [
        'about',
        'contact',
        'faq',
        'privacyPolicy',
        'terms',
    ];

    public ?string $about = null;
    public ?string $contact = null;
    public ?string $faq = null;
    public ?string $privacyPolicy = null;
    public ?string $terms = null;
    public DownvotesMode $downvotesMode = DownvotesMode::Enabled;

    public function __construct(?Site $site, DownvotesMode $downvotesMode)
    {
        $this->terms = $site?->terms;
        $this->privacyPolicy = $site?->privacyPolicy;
        $this->faq = $site?->faq;
        $this->about = $site?->about;
        $this->contact = $site?->contact;
        $this->downvotesMode = $downvotesMode;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'about' => $this->about,
            'contact' => $this->contact,
            'faq' => $this->faq,
            'privacyPolicy' => $this->privacyPolicy,
            'terms' => $this->terms,
        ];
    }
}
