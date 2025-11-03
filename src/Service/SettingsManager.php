<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\SettingsDto;
use App\Entity\Settings;
use App\Repository\SettingsRepository;
use App\Utils\DownvotesMode;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\Pure;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class SettingsManager
{
    private static ?SettingsDto $dto = null;

    private SettingsDto $instanceDto;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsRepository $repository,
        private readonly RequestStack $requestStack,
        private readonly KernelInterface $kernel,
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
        private readonly int $maxImageBytes,
        private readonly DownvotesMode $mbinDownvotesMode,
        private readonly bool $mbinNewUsersNeedApproval,
        private readonly LoggerInterface $logger,
    ) {
        if (!self::$dto || 'test' === $this->kernel->getEnvironment()) {
            $results = $this->repository->findAll();

            $maxImageBytesEdited = $this->find($results, 'MAX_IMAGE_BYTES', FILTER_VALIDATE_INT);
            if (null === $maxImageBytesEdited || 0 === $maxImageBytesEdited) {
                $maxImageBytesEdited = $this->maxImageBytes;
            }

            $newUsersNeedApprovalDb = $this->find($results, 'MBIN_NEW_USERS_NEED_APPROVAL');
            if ('true' === $newUsersNeedApprovalDb) {
                $newUsersNeedApprovalEdited = true;
            } elseif ('false' === $newUsersNeedApprovalDb) {
                $newUsersNeedApprovalEdited = false;
            } else {
                $newUsersNeedApprovalEdited = $this->mbinNewUsersNeedApproval;
            }

            $dto = new SettingsDto(
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
                $this->find($results, 'MBIN_DOWNVOTES_MODE') ?? $this->mbinDownvotesMode->value,
                $newUsersNeedApprovalEdited,
            );
            $this->instanceDto = $dto;
        } else {
            $this->instanceDto = self::$dto;
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
        return $this->instanceDto;
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

    /**
     * Check if an instance is banned by
     * checking if the instance URL has a match with the banned instances list.
     *
     * @param string $inboxUrl the inbox URL to check
     */
    public function isBannedInstance(string $inboxUrl): bool
    {
        $host = parse_url($inboxUrl, PHP_URL_HOST);
        if (null === $host) {
            // Try to retrieve the caller function
            $bt = debug_backtrace();
            $caller_function = ($bt[1]) ? $bt[1]['function'] : 'Unknown function caller';

            $this->logger->error('SettingsManager::isBannedInstance: unable to parse host from inbox URL: {url}, called from function: {caller}', ['url' => $inboxUrl, 'caller' => $caller_function]);

            // Do not retry, retrying will always cause a failure
            throw new UnrecoverableMessageHandlingException(\sprintf('Invalid inbox URL provided: %s', $inboxUrl));
        }

        return \in_array(
            str_replace('www.', '', $host),
            $this->get('KBIN_BANNED_INSTANCES') ?? []
        );
    }

    public function get(string $name)
    {
        return $this->instanceDto->{$name};
    }

    public function getDownvotesMode(): DownvotesMode
    {
        return DownvotesMode::from($this->get('MBIN_DOWNVOTES_MODE'));
    }

    public function getNewUsersNeedApproval(): bool
    {
        return $this->get('MBIN_NEW_USERS_NEED_APPROVAL');
    }

    public function set(string $name, $value): void
    {
        $this->instanceDto->{$name} = $value;

        $this->save($this->instanceDto);
    }

    public function getValue(string $name): string
    {
        return $this->instanceDto->{$name};
    }

    public function getLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request->cookies->get('mbin_lang') ?? $request->getLocale() ?? $this->get('KBIN_DEFAULT_LANG');
    }

    public function getMaxImageByteString(): string
    {
        $megaBytes = round($this->maxImageBytes / 1024 / 1024, 2);

        return $megaBytes.'MB';
    }

    /**
     * this should only be called in the test environment.
     */
    public static function resetDto(): void
    {
        self::$dto = null;
    }
}
