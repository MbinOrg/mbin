<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Factory\ActivityPub\PersonFactory;
use Sabberworm\CSS\CSSElement;
use Sabberworm\CSS\CSSList\CSSList;
use Sabberworm\CSS\CSSList\CSSListItem;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\CSSList\KeyFrame;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Position\Positionable;
use Sabberworm\CSS\Property\Declaration;
use Sabberworm\CSS\Property\KeyframeSelector;
use Sabberworm\CSS\Property\Selector\CompoundSelector;
use Sabberworm\CSS\RuleSet\AtRuleSet;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\Settings;
use Sabberworm\CSS\Value\URL;
use Sabberworm\CSS\Value\Value;
use Sabberworm\CSS\Value\ValueList;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class CustomCssService
{
    public const string SELECTOR_MARKER_GENERAL = '.fediCustomCss';
    public const string SELECTOR_MARKER_SOFTWARE = '.fediCustomCss-software-mbin';
    public const string SELECTOR_MARKER_USER = '.fediCustomCss-user-';

    public CustomCssProfile $localUserContentProfile;
    public CustomCssProfile $remoteUserContentProfile;
    public CustomCssProfile $localUserPageProfile;
    public CustomCssProfile $remoteUserPageProfile;
    public CustomCssProfile $localMagazineProfile;
    public CustomCssProfile $remoteMagazineProfile;

    public function __construct(
        private PersonFactory $personFactory,
    ) {
        $this->localUserContentProfile = new CustomCssProfile(CustomCssProfile::ENFORCE_SELECTOR_MODE_ADD, maxSizeKb: 128, allowKeyframes: true);
        $this->remoteUserContentProfile = new CustomCssProfile(CustomCssProfile::ENFORCE_SELECTOR_MODE_VERIFY, maxSizeKb: 128, allowKeyframes: true);
        $this->localUserPageProfile = new CustomCssProfile(CustomCssProfile::ENFORCE_SELECTOR_MODE_NONE, maxSizeKb: 512, allowKeyframes: true, requireSelectorStartsWithClass: false, allowSelectorStar: true, allowNonInlineUrls: true);
        $this->remoteUserPageProfile = new CustomCssProfile(CustomCssProfile::ENFORCE_SELECTOR_MODE_NONE, maxSizeKb: 512, allowKeyframes: true, requireSelectorStartsWithClass: false, allowSelectorStar: true, allowNonInlineUrls: true);
        $this->localMagazineProfile = new CustomCssProfile(CustomCssProfile::ENFORCE_SELECTOR_MODE_NONE, maxSizeKb: 512, allowKeyframes: true, requireSelectorStartsWithClass: false, allowSelectorStar: true, allowNonInlineUrls: true);
        $this->remoteMagazineProfile = new CustomCssProfile(CustomCssProfile::ENFORCE_SELECTOR_MODE_NONE, maxSizeKb: 512, allowKeyframes: true, requireSelectorStartsWithClass: false, allowSelectorStar: true, allowNonInlineUrls: true);
    }

    public function createConstraintSelector(User $user): string
    {
        return self::SELECTOR_MARKER_GENERAL.self::SELECTOR_MARKER_SOFTWARE.$this->userMarker($user);
    }

    public function createConstraintStyleClass(User $user): string
    {
        return str_replace('.', ' ', $this->createConstraintSelector($user));
    }

    public function validatePublicCss(string $src, CustomCssProfile $profile, ?User $user): string
    {
        $profile->checkNeedsUser($user);

        if ('' === $src) {
            throw new \InvalidArgumentException('Empty CSS src is invalid');
        }

        if (null === $user) {
            $srcSize = \strlen($src);
        } else {
            // don't count size with selector prefix as it would result in inconsistencies when css is submitted without selector prefix (which might be added automatically later)
            $srcSize = \strlen(str_replace($this->createConstraintSelector($user), '', $src));
        }
        if ($srcSize > $profile->maxSize()) {
            throw new BadRequestHttpException('The CSS is too big');
        }

        try {
            $parser = new Parser($src, Settings::create()->beStrict());
            $parsed = $parser->parse();

            $parsed->setComments([]);
            $this->validateCssList($profile, $parsed);

            if (CustomCssProfile::ENFORCE_SELECTOR_MODE_VERIFY === $profile->mode) {
                $this->enforceSelectorConstraints($parsed, $user, false);
            } elseif (CustomCssProfile::ENFORCE_SELECTOR_MODE_ADD === $profile->mode) {
                $this->enforceSelectorConstraints($parsed, $user, true);
            }

            return $parsed->render();
        } catch (\Exception $e) {
            if ($e instanceof BadRequestHttpException) {
                throw $e;
            }
            throw new BadRequestHttpException('The CSS syntax is invalid', $e);
        }
    }

    private function validateCssList(CustomCssProfile $profile, CSSList $css): void
    {
        if ($profile->allowAllDeclarations) {
            return;
        }

        foreach ($css->getContents() as $item) {
            if ($item instanceof DeclarationBlock) {
                $this->validateCssDeclarationBlock($profile, $item);
            } elseif ($item instanceof KeyFrame && $profile->allowKeyframes) {
                $this->validateKeyframesDeclarationBlock($profile, $item);
            } else {
                $this->throwInvalidCss($item);
            }
        }
    }

    /** @psalm-suppress TypeDoesNotContainType */
    private function validateCssDeclarationBlock(CustomCssProfile $profile, DeclarationBlock $css): void
    {
        foreach ($css->getSelectors() as $selector) {
            // The parser guarantees non-empty selector components, but keep this guard for malformed selector objects.
            if (0 === \count($selector->getComponents())) {
                $this->throwInvalidCss($css, 'The CSS contained rules with disallowed selectors');
            }

            if ($profile->requireSelectorStartsWithClass) {
                if (!($selector->getComponents()[0] instanceof CompoundSelector) || !str_starts_with($selector->getComponents()[0]->getValue(), '.')) {
                    $this->throwInvalidCss($css, 'The CSS contained rules with disallowed selectors');
                }
            }

            if (!$profile->allowSelectorStar) {
                foreach ($selector->getComponents() as $component) {
                    if (str_contains($component->getValue(), '*')) {
                        $this->throwInvalidCss($css, 'The CSS contained rules with disallowed selectors');
                    }
                }
            }
        }

        if ($profile->allowAllRules) {
            return;
        }

        if ($css->getRuleSet() instanceof AtRuleSet) {
            $this->throwInvalidCss($css);
        }

        foreach ($css->getDeclarations() as $declaration) {
            $this->validateCssRuleValue($profile, $declaration, $declaration->getValue());
        }
    }

    /** @psalm-suppress TypeDoesNotContainType */
    private function validateKeyframesDeclarationBlock(CustomCssProfile $profile, KeyFrame $css): void
    {
        if ($profile->allowAllDeclarations) {
            return;
        }

        if ('' === $css->getAnimationName()) {
            $this->throwInvalidCss($css, 'The CSS contained rules with disallowed selectors');
        }

        foreach ($css->getContents() as $frameDeclaration) {
            if (!$frameDeclaration instanceof DeclarationBlock) {
                $this->throwInvalidCss($frameDeclaration);
            }

            $selectors = $frameDeclaration->getSelectors();
            if (1 !== \count($selectors)) {
                $this->throwInvalidCss($frameDeclaration);
            }
            $selector = $selectors[0];
            if (
                !$selector instanceof KeyframeSelector
                || 1 !== \count($selector->getComponents())
                || !str_ends_with($selector->getComponents()[0]->getValue(), '%')
            ) {
                $this->throwInvalidCss($frameDeclaration);
            }

            if ($frameDeclaration->getRuleSet() instanceof AtRuleSet) {
                $this->throwInvalidCss($css);
            }

            foreach ($frameDeclaration->getDeclarations() as $declaration) {
                $this->validateCssRuleValue($profile, $declaration, $declaration->getValue());
            }
        }
    }

    private function validateCssRuleValue(CustomCssProfile $profile, Declaration $declaration, Value|string|null $value): void
    {
        if ($value instanceof ValueList) {
            foreach ($value->getListComponents() as $component) {
                $this->validateCssRuleValue($profile, $declaration, $component);
            }
        } else {
            if ($value instanceof URL && !$profile->allowNonInlineUrls) {
                if (!str_starts_with($value->getURL()->getString(), 'data:image/')) {
                    $this->throwInvalidCss($declaration);
                }
            }
        }
    }

    private function enforceSelectorConstraints(Document $css, User $user, bool $addMissingClasses): void
    {
        $constraintSelector = $this->createConstraintSelector($user);
        $constrainKeyframePrefix = substr($this->userMarker($user), 1).'-';

        foreach ($css->getContents() as $item) {
            if ($item instanceof DeclarationBlock) {
                $this->enforceDeclarationSelectorConstraints($item, $user, $constraintSelector, $addMissingClasses);
            } elseif ($item instanceof KeyFrame) {
                $this->enforceKeyframeSelectorConstraints($item, $constrainKeyframePrefix, $addMissingClasses);
            }
        }
    }

    private function enforceDeclarationSelectorConstraints(DeclarationBlock $css, User $user, string $constraintSelector, bool $addMissingClasses): void
    {
        foreach ($css->getSelectors() as $selector) {
            $components = $selector->getComponents();

            if ($components[0]->getValue() === $constraintSelector
                || $this->checkFediSelector($components[0]->getValue(), $user)
            ) {
                continue;
            }

            // check if a partial fedi selector exists -> throw exception as it is malformed
            foreach ($components as $component) {
                if (str_contains($component->getValue(), 'fediCustomCss')) {
                    $this->throwInvalidCss($css, 'The CSS has a block with invalid fediCustomCss selector');
                }
            }

            // no fedi selector exists at all -> add or throw
            if ($addMissingClasses) {
                array_unshift($components, new CompoundSelector($constraintSelector));
                $selector->setComponents($components);
            } else {
                $this->throwInvalidCss($css, 'The CSS has a block with missing fediCustomCss selector');
            }
        }
    }

    /** @psalm-suppress TypeDoesNotContainType */
    private function enforceKeyframeSelectorConstraints(KeyFrame $css, string $constraintPrefix, bool $addMissingClasses): void
    {
        $name = $css->getAnimationName();

        if ('' === $name) {
            $this->throwInvalidCss($css, 'The CSS contained rules with disallowed selectors');
        }

        if (\strlen($name) > \strlen($constraintPrefix) && str_starts_with($name, $constraintPrefix)) {
            return;
        }

        if (str_starts_with($name, 'fediCustomCss')) {
            $this->throwInvalidCss($css, 'The CSS has a block with invalid fediCustomCss selector');
        }

        // no fedi selector exists at all -> add or throw
        if ($addMissingClasses) {
            $css->setAnimationName($constraintPrefix.$name);
        } else {
            $this->throwInvalidCss($css, 'The CSS has a block with missing fediCustomCss selector');
        }
    }

    private function checkFediSelector(string $selector, User $user): bool
    {
        // $parts = ['', '.fediCustomCss', software_marker, user_marker]
        $parts = explode('.', $selector);
        if (\count($parts) < 4) {
            return false;
        }

        if ('' !== $parts[0] || ('.'.$parts[1]) !== self::SELECTOR_MARKER_GENERAL) {
            return false;
        }

        if (1 !== preg_match('/^fediCustomCss-software-[a-z0-9_]{3,64}$/', $parts[2])) {
            return false;
        }

        if (('.'.$parts[3]) !== $this->userMarker($user)) {
            return false;
        }

        return true;
    }

    private function userMarker(User $user): string
    {
        $userId = $this->personFactory->getActivityPubId($user);
        // convert base64 encoding to valid CSS identifier (https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Values/ident)
        $userId = base64_encode($userId);
        $userId = str_replace('+', '-', $userId);
        $userId = str_replace('/', '_', $userId);
        $userId = str_replace('=', '', $userId);

        return self::SELECTOR_MARKER_USER.$userId;
    }

    private function throwInvalidCss(CSSListItem|CSSElement $item, string $msg = 'The CSS contained rules which are not allowed'): never
    {
        $lineText = ($item instanceof Positionable) ? \sprintf(' at line %d column %d', $item->getLineNumber() ?? -1, $item->getColumnNumber() ?? -1) : '';
        throw new BadRequestHttpException($msg.$lineText);
    }
}
