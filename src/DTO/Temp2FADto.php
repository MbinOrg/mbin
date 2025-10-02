<?php

declare(strict_types=1);

namespace App\DTO;

use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;

class Temp2FADto implements TwoFactorInterface
{
    public function __construct(
        public string $forUsername,
        public string $secret,
    ) {
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->secret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->forUsername;
    }

    /**
     * Has to match User::getTotpAuthenticationConfiguration.
     *
     * @see User::getTotpAuthenticationConfiguration()
     */
    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        return new TotpConfiguration($this->secret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }
}
