<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\EntryComment;
use App\Entity\PostComment;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Utils\RegPatterns;

class MentionManager
{
    public const ALL = 1;
    public const LOCAL = 2;
    public const REMOTE = 3;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    /**
     * @return User[]
     */
    public function getUsersFromArray(?array $users): array
    {
        if ($users) {
            return $this->userRepository->findByUsernames($users);
        }

        return [];
    }

    public function handleChain(ActivityPubActivityInterface $activity): array
    {
        $subject = match (true) {
            $activity instanceof EntryComment => $activity->parent ?? $activity->entry,
            $activity instanceof PostComment => $activity->parent ?? $activity->post,
            default => throw new \LogicException(),
        };

        $activity->mentions = array_unique(
            array_merge($activity->mentions ?? [], $this->extract($activity->body) ?? [])
        );

        $subjectActor = ['@'.ltrim($subject->user->username, '@')];

        $result = array_unique(
            array_merge(
                empty($subject->mentions) ? [] : $subject->mentions,
                empty($activity->mentions) ? [] : $activity->mentions,
                $subjectActor
            )
        );

        $result = array_filter(
            $result,
            function ($val) {
                preg_match(RegPatterns::LOCAL_USER, $val, $l);

                return preg_match(RegPatterns::AP_USER, $val) || $val === $l[0] ?? '';
            }
        );

        return array_filter(
            $result,
            fn ($val) => !\in_array(
                $val,
                [
                    '@'.$activity->user->username,
                    '@'.$activity->user->username.'@'.$this->settingsManager->get('KBIN_DOMAIN'),
                ]
            )
        );
    }

    /**
     * Try to extract mentions from the body (eg. @username@domain.tld).
     *
     * @param val Body input string
     * @param type Type of mentions to extract (ALL, LOCAL only or REMOTE only)
     * @return string[]
     */
    public function extract(?string $body, $type = self::ALL): ?array
    {
        if (!$body) {
            return null;
        }

        $result = match ($type) {
            self::ALL => array_merge($this->byApPrefix($body), $this->byPrefix($body)),
            self::LOCAL => $this->byPrefix($body),
            self::REMOTE => $this->byApPrefix($body),
        };

        $result = array_map(fn ($val) => trim($val), $result);

        return \count($result) ? array_unique($result) : null;
    }

    /**
     * Remote activitypub prefix, like @username@domain.tld.
     *
     * @param value Input string
     * @return string[]
     */
    private function byApPrefix(string $value): array
    {
        preg_match_all(
            '/(?<!\/)\B@([a-zA-Z0-9._-]+@?)(@)(([\pL\pN\pS\pM\-\_]++\.)+[\pL\pN\pM]++|[a-z0-9\-\_]++)/u',
            $value,
            $matches
        );

        return \count($matches[0]) ? array_unique(array_values($matches[0])) : [];
    }

    /**
     * Local username prefix, like @username.
     *
     * @param value Input string
     * @return string[]
     */
    private function byPrefix(string $value): array
    {
        preg_match_all('/(?<!\/)\B@([a-zA-Z0-9_-]{1,30}@?)/u', $value, $matches);
        $results = array_filter($matches[0], fn ($val) => !str_ends_with($val, '@'));

        return \count($results) ? array_unique(array_values($results)) : [];
    }

    public function joinMentionsToBody(string $body, array $mentions): string
    {
        $current = $this->extract($body) ?? [];
        $current = self::addHandle($current);
        $mentions = self::addHandle($mentions);

        $join = array_unique(array_merge(array_diff($mentions, $current)));

        if (!empty($join)) {
            $body .= PHP_EOL.PHP_EOL.implode(' ', $join);
        }

        return $body;
    }

    public function addHandle(array $mentions): array
    {
        $res = array_map(
            fn ($val) => 0 === substr_count($val, '@') ? '@'.$val : $val,
            $mentions
        );

        return array_map(
            fn ($val) => substr_count($val, '@') < 2 ? $val.'@'.SettingsManager::getValue('KBIN_DOMAIN') : $val,
            $res
        );
    }

    public function getUsername(string $value, ?bool $withApPostfix = false): string
    {
        $value = $this->addHandle([$value])[0];

        if (true === $withApPostfix) {
            return $value;
        }

        return explode('@', $value)[1];
    }

    public function getDomain(string $value): string
    {
        if (str_starts_with($value, '@')) {
            $value = substr($value, 1);
        }
        $parts = explode('@', $value);
        if (\count($parts) < 2) {
            return SettingsManager::getValue('KBIN_DOMAIN');
        } else {
            return $parts[1];
        }
    }

    public static function clearLocal(?array $mentions): array
    {
        if (null === $mentions) {
            return [];
        }

        $domain = '@'.SettingsManager::getValue('KBIN_DOMAIN');

        $mentions = array_map(fn ($val) => preg_replace('/'.preg_quote($domain, '/').'$/', '', $val), $mentions);

        $mentions = array_map(fn ($val) => ltrim($val, '@'), $mentions);

        return array_filter($mentions, fn ($val) => !str_contains($val, '@'));
    }

    public static function getRoute(?array $mentions): array
    {
        if (null === $mentions) {
            return [];
        }

        $domain = '@'.SettingsManager::getValue('KBIN_DOMAIN');

        $mentions = array_map(fn ($val) => preg_replace('/'.preg_quote($domain, '/').'$/', '', $val), $mentions);

        $mentions = array_map(fn ($val) => ltrim($val, '@'), $mentions);

        return array_map(fn ($val) => ltrim($val, '@'), $mentions);
    }
}
