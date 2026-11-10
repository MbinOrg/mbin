<?php

namespace App\Service;

use App\Entity\User;
use App\Factory\ActivityPub\PersonFactory;
use Sabberworm\CSS\CSSList\AtRuleBlockList;
use Sabberworm\CSS\CSSList\CSSBlockList;
use Sabberworm\CSS\CSSList\CSSList;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Position\Positionable;
use Sabberworm\CSS\Property\Declaration;
use Sabberworm\CSS\Property\Selector;
use Sabberworm\CSS\Property\Selector\CompoundSelector;
use Sabberworm\CSS\RuleSet\AtRuleSet;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\RuleSet\RuleSet;
use Sabberworm\CSS\Settings;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\URL;
use Sabberworm\CSS\Value\Value;
use Sabberworm\CSS\Value\ValueList;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use function PHPUnit\Framework\stringStartsWith;

readonly class CustomCssService
{

    public const string SELECTOR_MARKER_GENERAL = '.fediCustomCss';
    public const string SELECTOR_MARKER_SOFTWARE = '.fediCustomCss-software-mbin';
    public const string SELECTOR_MARKER_USER = '.fediCustomCss-user-';

    public CustomCssProfile $localUserContentProfile;
    public CustomCssProfile $remoteUserContentProfile;
    public CustomCssProfile $localUserPageProfile;
    public CustomCssProfile $remoteUserPageProfile;

    public function __construct(
        private PersonFactory $personFactory,
    )
    {
        $this->localUserContentProfile = new CustomCssProfile(CustomCssProfile::ENFORCE_SELECTOR_MODE_ADD);
        $this->remoteUserContentProfile = new CustomCssProfile(CustomCssProfile::ENFORCE_SELECTOR_MODE_VERIFY);
        $this->localUserPageProfile = new CustomCssProfile(CustomCssProfile::ENFORCE_SELECTOR_MODE_NONE, maxSizeKb: 32, requireSelectorStartsWithClass: false, allowSelectorStar: true, allowNonInlineUrls: true);
        $this->remoteUserPageProfile = new CustomCssProfile(CustomCssProfile::ENFORCE_SELECTOR_MODE_NONE, maxSizeKb: 32, requireSelectorStartsWithClass: false, allowSelectorStar: true, allowNonInlineUrls: true);
    }

    public function createConstraintSelector(User $user): string
    {
        return self::SELECTOR_MARKER_GENERAL.self::SELECTOR_MARKER_SOFTWARE.$this->userMarker($user);
    }

    public function createConstraintStyleClass(User $user): string {
        return str_replace('.', ' ', $this->createConstraintSelector($user));
    }

    public function validatePublicCss(string $src, CustomCssProfile $profile, ?User $user): string {
        $profile->checkNeedsUser($user);

        if($src === '') {
            throw new \InvalidArgumentException('empty CSS src is invalid');
        }

        if(strlen($src) > $profile->maxSize()) {
            throw new BadRequestHttpException('The CSS is too big');
        }

        try {
            $parser = new Parser($src, Settings::create()->beStrict());
            $parsed = $parser->parse();

            $parsed->setComments([]);
            $this->validateCssList($profile, $parsed);

            if($profile->mode === CustomCssProfile::ENFORCE_SELECTOR_MODE_VERIFY) {
                $this->enforceSelectorConstraints($parsed, $user, false);
            } elseif ($profile->mode === CustomCssProfile::ENFORCE_SELECTOR_MODE_ADD) {
                $this->enforceSelectorConstraints($parsed, $user, true);
            }

            return $parsed->render();
        }catch (\Exception $e){
            if($e instanceof BadRequestHttpException) {
                throw $e;
            }
            throw new BadRequestHttpException('The CSS syntax is invalid', $e);
        }
    }

    private function validateCssList(CustomCssProfile $profile, CSSList $css): void {
        if($profile->allowAllDeclarations) {
            return;
        }

        foreach ($css->getContents() as $item) {
            if($item instanceof DeclarationBlock) {
                $this->validateCssDeclarationBlock($profile, $item);
            } else {
                $lineText = ($item instanceof Positionable) ? ' at line '.$item->getLineNumber() : '';
                throw new BadRequestHttpException('The CSS contained rules which are not allowed'.$lineText);
            }
        }
    }

    private function validateCssDeclarationBlock(CustomCssProfile $profile, DeclarationBlock $css): void {
        foreach ($css->getSelectors() as $selector) {
            if(count($selector->getComponents()) === 0) {
                throw new BadRequestHttpException('The CSS contained rules with disallowed selectors at line '.$css->getLineNumber());
            }

            if($profile->requireSelectorStartsWithClass) {
                if (!($selector->getComponents()[0] instanceof CompoundSelector) || !str_starts_with($selector->getComponents()[0]->getValue(), '.')) {
                    throw new BadRequestHttpException('The CSS contained rules with disallowed selectors at line ' . $css->getLineNumber());
                }
            }

            if(!$profile->allowSelectorStar) {
                foreach ($selector->getComponents() as $component) {
                    if (str_contains($component->getValue(), '*')) {//TODO maybe allow *
                        throw new BadRequestHttpException('The CSS contained rules with disallowed selectors at line ' . $css->getLineNumber());
                    }
                }
            }
        }

        if($profile->allowAllRules) {
            return;
        }

        //TODO maybe allow some at-rules like keyframes (possibly prefixed)
        if($css->getRuleSet() instanceof AtRuleSet) {
            throw new BadRequestHttpException('The CSS contained rules which are not allowed at line '.$css->getLineNumber());
        }

        foreach ($css->getDeclarations() as $declaration) {
            $this->validateCssRuleValue($profile, $declaration, $declaration->getValue());
        }
    }

    private function validateCssRuleValue(CustomCssProfile $profile, Declaration $declaration, Value|string|null $value): void
    {
        if($value instanceof ValueList) {
            foreach ($value->getListComponents() as $component) {
                $this->validateCssRuleValue($profile, $declaration, $component);
            }
        } else {
            if ($value instanceof URL && !$profile->allowNonInlineUrls) {
                if (!str_starts_with($value->getURL()->getString(), 'data:image/')) {
                    throw new BadRequestHttpException('The CSS contained rules which are not allowed at line ' . $declaration->getLineNumber());
                }
            }
        }
    }

    private function enforceSelectorConstraints(Document $css, User $user, bool $addMissingClasses): void
    {
        $constraintSelector = $this->createConstraintSelector($user);

        foreach ($css->getAllDeclarationBlocks() as $block) {
            foreach ($block->getSelectors() as $selector) {
                $components = $selector->getComponents();

                if(count($components) >= 1 && (
                    $components[0]->getValue() === $constraintSelector
                    || $this->checkFediSelector($components[0]->getValue(), $user))
                ) {
                    continue;
                }

                // check if a partial fedi selector exists -> throw exception as it is malformed
                foreach ($components as $component) {
                    if(str_contains($component->getValue(), 'fediCustomCss')) {
                        throw new BadRequestHttpException('The CSS has a block with invalid fediCustomCss selector at line '.$block->getLineNumber());
                    }
                }

                // no fedi selector exists at all -> add or throw
                if($addMissingClasses) {
                    array_unshift($components, new CompoundSelector($constraintSelector));
                    $selector->setComponents($components);
                } else {
                    throw new BadRequestHttpException('The CSS has a block with missing fediCustomCss selector at line '.$block->getLineNumber());
                }
            }
        }
    }

    private function checkFediSelector(string $selector, User $user): bool
    {
        // $parts = ['', '.fediCustomCss', software_marker, user_marker]
        $parts = explode('.', $selector);
        if(count($parts) < 4) {
            return false;
        }

        if($parts[0] !== '' || ('.'.$parts[1]) !== self::SELECTOR_MARKER_GENERAL) {
            return false;
        }

        if(preg_match('/^fediCustomCss-software-[a-z0-9]{3,64}$/', $parts[2]) !== 1) {
            return false;
        }

        if(('.'.$parts[3]) !== $this->userMarker($user)) {
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
}

readonly class CustomCssProfile {

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
        public bool $allowAllRules = false,
        public bool $allowAllDeclarations = false,
    )
    {
        if(
            $mode !== self::ENFORCE_SELECTOR_MODE_NONE
            && $mode !== self::ENFORCE_SELECTOR_MODE_VERIFY
            && $mode !== self::ENFORCE_SELECTOR_MODE_ADD
        ) {
            throw new \InvalidArgumentException('invalid mode');
        }
    }

    public function checkNeedsUser(?User $user): void {
        if($this->mode === self::ENFORCE_SELECTOR_MODE_NONE && $user !== null) {
            throw new \InvalidArgumentException('on no selector enforcement no User may be set');
        }
        if($this->mode !== self::ENFORCE_SELECTOR_MODE_NONE && $user === null) {
            throw new \InvalidArgumentException('selector enforcement requires a User');
        }
    }

    public function maxSize(): int
    {
        return $this->maxSizeKb * 1024;
    }
}
