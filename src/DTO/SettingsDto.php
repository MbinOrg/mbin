<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema()]
class SettingsDto implements \JsonSerializable
{
    public function __construct(
        public string $KBIN_DOMAIN,
        public string $KBIN_TITLE,
        public string $KBIN_META_TITLE,
        public string $KBIN_META_KEYWORDS,
        public string $KBIN_META_DESCRIPTION,
        public string $KBIN_DEFAULT_LANG,
        public string $KBIN_CONTACT_EMAIL,
        public string $KBIN_SENDER_EMAIL,
        public string $MBIN_DEFAULT_THEME,
        public bool $KBIN_JS_ENABLED,
        public bool $KBIN_FEDERATION_ENABLED,
        public bool $KBIN_REGISTRATIONS_ENABLED,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
        public array $KBIN_BANNED_INSTANCES,
        public bool $KBIN_HEADER_LOGO,
        public bool $KBIN_CAPTCHA_ENABLED,
        public bool $KBIN_MERCURE_ENABLED,
        public bool $KBIN_FEDERATION_PAGE_ENABLED,
        public bool $KBIN_ADMIN_ONLY_OAUTH_CLIENTS,
        public bool $MBIN_SSO_ONLY_MODE,
        public bool $MBIN_PRIVATE_INSTANCE,
        public bool $KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN,
        public bool $MBIN_SIDEBAR_SECTIONS_LOCAL_ONLY,
        public bool $MBIN_SSO_REGISTRATIONS_ENABLED,
        public bool $MBIN_RESTRICT_MAGAZINE_CREATION,
        public bool $MBIN_SSO_SHOW_FIRST,
        public int $MAX_IMAGE_BYTES
    ) {
    }

    public function mergeIntoDto(SettingsDto $dto): SettingsDto
    {
        $dto->KBIN_DOMAIN = $this->KBIN_DOMAIN ?? $dto->KBIN_DOMAIN;
        $dto->KBIN_TITLE = $this->KBIN_TITLE ?? $dto->KBIN_TITLE;
        $dto->KBIN_META_TITLE = $this->KBIN_META_TITLE ?? $dto->KBIN_META_TITLE;
        $dto->KBIN_META_KEYWORDS = $this->KBIN_META_KEYWORDS ?? $dto->KBIN_META_KEYWORDS;
        $dto->KBIN_META_DESCRIPTION = $this->KBIN_META_DESCRIPTION ?? $dto->KBIN_META_DESCRIPTION;
        $dto->KBIN_DEFAULT_LANG = $this->KBIN_DEFAULT_LANG ?? $dto->KBIN_DEFAULT_LANG;
        $dto->KBIN_CONTACT_EMAIL = $this->KBIN_CONTACT_EMAIL ?? $dto->KBIN_CONTACT_EMAIL;
        $dto->KBIN_SENDER_EMAIL = $this->KBIN_SENDER_EMAIL ?? $dto->KBIN_SENDER_EMAIL;
        $dto->MBIN_DEFAULT_THEME = $this->MBIN_DEFAULT_THEME ?? $dto->MBIN_DEFAULT_THEME;
        $dto->KBIN_JS_ENABLED = $this->KBIN_JS_ENABLED ?? $dto->KBIN_JS_ENABLED;
        $dto->KBIN_FEDERATION_ENABLED = $this->KBIN_FEDERATION_ENABLED ?? $dto->KBIN_FEDERATION_ENABLED;
        $dto->KBIN_REGISTRATIONS_ENABLED = $this->KBIN_REGISTRATIONS_ENABLED ?? $dto->KBIN_REGISTRATIONS_ENABLED;
        $dto->KBIN_BANNED_INSTANCES = $this->KBIN_BANNED_INSTANCES ?? $dto->KBIN_BANNED_INSTANCES;
        $dto->KBIN_HEADER_LOGO = $this->KBIN_HEADER_LOGO ?? $dto->KBIN_HEADER_LOGO;
        $dto->KBIN_CAPTCHA_ENABLED = $this->KBIN_CAPTCHA_ENABLED ?? $dto->KBIN_CAPTCHA_ENABLED;
        $dto->KBIN_MERCURE_ENABLED = $this->KBIN_MERCURE_ENABLED ?? $dto->KBIN_MERCURE_ENABLED;
        $dto->KBIN_FEDERATION_PAGE_ENABLED = $this->KBIN_FEDERATION_PAGE_ENABLED ?? $dto->KBIN_FEDERATION_PAGE_ENABLED;
        $dto->KBIN_ADMIN_ONLY_OAUTH_CLIENTS = $this->KBIN_ADMIN_ONLY_OAUTH_CLIENTS ?? $dto->KBIN_ADMIN_ONLY_OAUTH_CLIENTS;
        $dto->MBIN_SSO_ONLY_MODE = $this->MBIN_SSO_ONLY_MODE ?? $dto->MBIN_SSO_ONLY_MODE;
        $dto->MBIN_PRIVATE_INSTANCE = $this->MBIN_PRIVATE_INSTANCE ?? $dto->MBIN_PRIVATE_INSTANCE;
        $dto->KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN = $this->KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN ?? $dto->KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN;
        $dto->MBIN_SIDEBAR_SECTIONS_LOCAL_ONLY = $this->MBIN_SIDEBAR_SECTIONS_LOCAL_ONLY ?? $dto->MBIN_SIDEBAR_SECTIONS_LOCAL_ONLY;
        $dto->MBIN_SSO_REGISTRATIONS_ENABLED = $this->MBIN_SSO_REGISTRATIONS_ENABLED ?? $dto->MBIN_SSO_REGISTRATIONS_ENABLED;
        $dto->MBIN_RESTRICT_MAGAZINE_CREATION = $this->MBIN_RESTRICT_MAGAZINE_CREATION ?? $dto->MBIN_RESTRICT_MAGAZINE_CREATION;
        $dto->MBIN_SSO_SHOW_FIRST = $this->MBIN_SSO_SHOW_FIRST ?? $dto->MBIN_SSO_SHOW_FIRST;
        $dto->MAX_IMAGE_BYTES = $this->MAX_IMAGE_BYTES ?? $dto->MAX_IMAGE_BYTES;

        return $dto;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'KBIN_DOMAIN' => $this->KBIN_DOMAIN,
            'KBIN_TITLE' => $this->KBIN_TITLE,
            'KBIN_META_TITLE' => $this->KBIN_META_TITLE,
            'KBIN_META_KEYWORDS' => $this->KBIN_META_KEYWORDS,
            'KBIN_META_DESCRIPTION' => $this->KBIN_META_DESCRIPTION,
            'KBIN_DEFAULT_LANG' => $this->KBIN_DEFAULT_LANG,
            'KBIN_CONTACT_EMAIL' => $this->KBIN_CONTACT_EMAIL,
            'KBIN_SENDER_EMAIL' => $this->KBIN_SENDER_EMAIL,
            'MBIN_DEFAULT_THEME' => $this->MBIN_DEFAULT_THEME,
            'KBIN_JS_ENABLED' => $this->KBIN_JS_ENABLED,
            'KBIN_FEDERATION_ENABLED' => $this->KBIN_FEDERATION_ENABLED,
            'KBIN_REGISTRATIONS_ENABLED' => $this->KBIN_REGISTRATIONS_ENABLED,
            'KBIN_BANNED_INSTANCES' => $this->KBIN_BANNED_INSTANCES,
            'KBIN_HEADER_LOGO' => $this->KBIN_HEADER_LOGO,
            'KBIN_CAPTCHA_ENABLED' => $this->KBIN_CAPTCHA_ENABLED,
            'KBIN_MERCURE_ENABLED' => $this->KBIN_MERCURE_ENABLED,
            'KBIN_FEDERATION_PAGE_ENABLED' => $this->KBIN_FEDERATION_PAGE_ENABLED,
            'KBIN_ADMIN_ONLY_OAUTH_CLIENTS' => $this->KBIN_ADMIN_ONLY_OAUTH_CLIENTS,
            'MBIN_SSO_ONLY_MODE' => $this->MBIN_SSO_ONLY_MODE,
            'MBIN_PRIVATE_INSTANCE' => $this->MBIN_PRIVATE_INSTANCE,
            'KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN' => $this->KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN,
            'MBIN_SIDEBAR_SECTIONS_LOCAL_ONLY' => $this->MBIN_SIDEBAR_SECTIONS_LOCAL_ONLY,
            'MBIN_SSO_REGISTRATIONS_ENABLED' => $this->MBIN_SSO_REGISTRATIONS_ENABLED,
            'MBIN_RESTRICT_MAGAZINE_CREATION' => $this->MBIN_RESTRICT_MAGAZINE_CREATION,
            'MBIN_SSO_SHOW_FIRST' => $this->MBIN_SSO_SHOW_FIRST,
            'MAX_IMAGE_BYTES' => $this->MAX_IMAGE_BYTES,
        ];
    }
}
