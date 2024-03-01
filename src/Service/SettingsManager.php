<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\SettingsDto;
use App\Entity\Settings;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\Pure;
use Symfony\Component\HttpFoundation\RequestStack;

class SettingsManager
{
    private static ?SettingsDto $dto = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsRepository $repository,
        private readonly RequestStack $requestStack,
        private readonly string $kbinDomain,
        private readonly string $kbinTitle,
        private readonly string $kbinMetaTitle,
        private readonly string $kbinMetaDescription,
        private readonly string $kbinMetaKeywords,
        private readonly string $kbinDefaultLang,
        private readonly string $kbinContactEmail,
        private readonly string $kbinSenderEmail,
        private readonly string $mbinDefaultTheme,
        private readonly bool $kbinJsEnabled,
        private readonly bool $kbinFederationEnabled,
        private readonly bool $kbinRegistrationsEnabled,
        private readonly bool $kbinHeaderLogo,
        private readonly bool $kbinCaptchaEnabled,
        private readonly bool $kbinFederationPageEnabled,
        private readonly bool $kbinAdminOnlyOauthClients,
        private readonly bool $mbinSsoOnlyMode,
        private readonly int $maxImageBytes
    ) {
        if (!self::$dto) {
            $results = $this->repository->findAll();

            $maxImageBytesEdited = $this->find($results, 'MAX_IMAGE_BYTES', FILTER_VALIDATE_INT);
            if (null === $maxImageBytesEdited || 0 === $maxImageBytesEdited) {
                $maxImageBytesEdited = $this->maxImageBytes;
            }

            self::$dto = new SettingsDto(
                $this->kbinDomain,
                $this->find($results, 'KBIN_TITLE') ?? $this->kbinTitle,
                $this->find($results, 'KBIN_META_TITLE') ?? $this->kbinMetaTitle,
                $this->find($results, 'KBIN_META_KEYWORDS') ?? $this->kbinMetaKeywords,
                $this->find($results, 'KBIN_META_DESCRIPTION') ?? $this->kbinMetaDescription,
                $this->find($results, 'KBIN_DEFAULT_LANG') ?? $this->kbinDefaultLang,
                $this->find($results, 'KBIN_CONTACT_EMAIL') ?? $this->kbinContactEmail,
                $this->find($results, 'KBIN_SENDER_EMAIL') ?? $this->kbinSenderEmail,
                $this->find($results, 'MBIN_DEFAULT_THEME') ?? $this->mbinDefaultTheme,
                $this->find($results, 'KBIN_JS_ENABLED', FILTER_VALIDATE_BOOLEAN) ?? $this->kbinJsEnabled,
                $this->find(
                    $results,
                    'KBIN_FEDERATION_ENABLED',
                    FILTER_VALIDATE_BOOLEAN
                ) ?? $this->kbinFederationEnabled,
                $this->find(
                    $results,
                    'KBIN_REGISTRATIONS_ENABLED',
                    FILTER_VALIDATE_BOOLEAN
                ) ?? $this->kbinRegistrationsEnabled,
                $this->find($results, 'KBIN_BANNED_INSTANCES') ?? [],
                $this->find($results, 'KBIN_HEADER_LOGO', FILTER_VALIDATE_BOOLEAN) ?? $this->kbinHeaderLogo,
                $this->find($results, 'KBIN_CAPTCHA_ENABLED', FILTER_VALIDATE_BOOLEAN) ?? $this->kbinCaptchaEnabled,
                $this->find($results, 'KBIN_MERCURE_ENABLED', FILTER_VALIDATE_BOOLEAN) ?? false,
                $this->find($results, 'KBIN_FEDERATION_PAGE_ENABLED', FILTER_VALIDATE_BOOLEAN) ?? $this->kbinFederationPageEnabled,
                $this->find($results, 'KBIN_ADMIN_ONLY_OAUTH_CLIENTS', FILTER_VALIDATE_BOOLEAN) ?? $this->kbinAdminOnlyOauthClients,
                $this->find($results, 'MBIN_SSO_ONLY_MODE', FILTER_VALIDATE_BOOLEAN) ?? $this->mbinSsoOnlyMode,
                $this->find($results, 'MBIN_PRIVATE_INSTANCE', FILTER_VALIDATE_BOOLEAN) ?? false,
                $this->find($results, 'KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', FILTER_VALIDATE_BOOLEAN) ?? true,
                $this->find($results, 'MBIN_SIDEBAR_SECTIONS_LOCAL_ONLY', FILTER_VALIDATE_BOOLEAN) ?? false,
                $this->find($results, 'MBIN_SSO_REGISTRATIONS_ENABLED', FILTER_VALIDATE_BOOLEAN) ?? true,
                $this->find($results, 'MBIN_RESTRICT_MAGAZINE_CREATION', FILTER_VALIDATE_BOOLEAN) ?? false,
                $this->find($results, 'MBIN_SSO_SHOW_FIRST', FILTER_VALIDATE_BOOLEAN) ?? false,
                $maxImageBytesEdited,
                $this->find($results, 'MBIN_DOWNVOTES_MODE') ?? "enabled"
            );
        }
    }

    private function find(array $results, string $name, ?int $filter = null)
    {
        $res = array_values(array_filter($results, fn ($s) => $s->name === $name));

        if (\count($res)) {
            $res = $res[0]->value ?? $res[0]->json;

            if ($filter) {
                $res = filter_var($res, $filter);
            }

            return $res;
        }

        return null;
    }

    public function getDto(): SettingsDto
    {
        return self::$dto;
    }

    public function save(SettingsDto $dto): void
    {
        foreach ($dto as $name => $value) {
            $s = $this->repository->findOneByName($name);

            if (\is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            if (!\is_string($value) && !\is_array($value)) {
                $value = \strval($value);
            }

            if (!$s) {
                $s = new Settings($name, $value);
            }

            if (\is_array($value)) {
                $s->json = $value;
            } else {
                $s->value = $value;
            }

            $this->entityManager->persist($s);
        }

        $this->entityManager->flush();
    }

    #[Pure]
    public function isLocalUrl(string $url): bool
    {
        return parse_url($url, PHP_URL_HOST) === $this->get('KBIN_DOMAIN');
    }

    public function isBannedInstance(string $inboxUrl): bool
    {
        return \in_array(
            str_replace('www.', '', parse_url($inboxUrl, PHP_URL_HOST)),
            $this->get('KBIN_BANNED_INSTANCES') ?? []
        );
    }

    public function get(string $name)
    {
        return self::$dto->{$name};
    }

    public function set(string $name, $value): void
    {
        self::$dto->{$name} = $value;

        $this->save(self::$dto);
    }

    public static function getValue(string $name): string
    {
        return self::$dto->{$name};
    }

    public function getLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request->cookies->get('kbin_lang') ?? $request->getLocale() ?? $this->get('KBIN_DEFAULT_LANG');
    }

    public function getMaxImageByteString(): string
    {
        $megaBytes = round($this->maxImageBytes / 1024 / 1024, 2);

        return $megaBytes.'MB';
    }
}
