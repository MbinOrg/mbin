<?php

declare(strict_types=1);

namespace App\Service;

/**
 * A service that helps retrieving project information, like current version or project name.
 */
class ProjectInfoService
{
    // If updating version, please also update http client UA in [/config/packages/framework.yaml]
    private const VERSION = '1.7.2'; // TODO: Retrieve the version from git tags or getenv()?
    private const NAME = 'mbin';
    private const CANONICAL_NAME = 'Mbin';
    private const REPOSITORY_URL = 'https://github.com/MbinOrg/mbin';

    public function __construct(
        private readonly string $kbinDomain,
    ) {
    }

    /**
     * Get Mbin current project version.
     *
     * @return version
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Get project name.
     *
     * @return name
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Get project canonical name.
     *
     * @return string canonical name
     */
    public function getCanonicalName(): string
    {
        return self::CANONICAL_NAME;
    }

    /**
     * Get user-agent name usable as HTTP client requests.
     *
     * @return user-agent string
     */
    public function getUserAgent(): string
    {
        return "{$this->getCanonicalName()}/{$this->getVersion()} (+https://{$this->kbinDomain}/agent)";
    }

    /**
     * Get Mbin repository URL.
     *
     * @return URL
     */
    public function getRepositoryURL(): string
    {
        return self::REPOSITORY_URL;
    }
}
