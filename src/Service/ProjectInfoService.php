<?php

declare(strict_types=1);

namespace App\Service;

/**
 * A service that helps retrieving project information, like current version or project name.
 */
class ProjectInfoService
{
    // If updating version, please also update http client UA in [/config/packages/framework.yaml]
    private const VERSION = '1.7.1-rc3'; // TODO: Retrieve the version from git tags or getenv()?
    private const NAME = 'mbin';
    private const USER_AGENT = 'Mbin';
    private const REPOSITORY_URL = 'https://github.com/MbinOrg/mbin';

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
     * Get user-agent name we use as HTTP client requests.
     *
     * @return user-agent string
     */
    public function getUserAgent(): string
    {
        return self::USER_AGENT;
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
