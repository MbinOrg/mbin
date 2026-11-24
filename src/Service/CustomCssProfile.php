<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

readonly class CustomCssProfile
{
    /** do not check the selectors */
    public const int ENFORCE_SELECTOR_MODE_NONE = 0;
    /** check the selectors if they start with a valid fediCustomCss class */
    public const int ENFORCE_SELECTOR_MODE_VERIFY = 1;
    /** check the selectors if they start with a valid fediCustomCss and add the classes if the selector has none */
    public const int ENFORCE_SELECTOR_MODE_ADD = 2;

    // defaults should be the strictest available
    public function __construct(
        public int $mode,
        public int $maxSizeKb = 4,
        public bool $requireSelectorStartsWithClass = true,
        public bool $allowSelectorStar = false,
        public bool $allowNonInlineUrls = false,
        public bool $allowKeyframes = false,
        public bool $allowAllRules = false,
        public bool $allowAllDeclarations = false,
    ) {
        if (
            self::ENFORCE_SELECTOR_MODE_NONE !== $mode
            && self::ENFORCE_SELECTOR_MODE_VERIFY !== $mode
            && self::ENFORCE_SELECTOR_MODE_ADD !== $mode
        ) {
            throw new \InvalidArgumentException('invalid mode');
        }
    }

    public function checkNeedsUser(?User $user): void
    {
        if (self::ENFORCE_SELECTOR_MODE_NONE === $this->mode && null !== $user) {
            throw new \InvalidArgumentException('on no selector enforcement no User may be set');
        }
        if (self::ENFORCE_SELECTOR_MODE_NONE !== $this->mode && null === $user) {
            throw new \InvalidArgumentException('selector enforcement requires a User');
        }
    }

    public function maxSize(): int
    {
        return $this->maxSizeKb * 1024;
    }
}
