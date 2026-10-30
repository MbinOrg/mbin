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
use Sabberworm\CSS\Property\Selector;
use Sabberworm\CSS\Property\Selector\CompoundSelector;
use Sabberworm\CSS\RuleSet\AtRuleSet;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\RuleSet\RuleSet;
use Sabberworm\CSS\Settings;
use Sabberworm\CSS\Value\URL;
use Sabberworm\CSS\Value\ValueList;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use function PHPUnit\Framework\stringStartsWith;

readonly class CustomCssService
{

    public const string SELECTOR_MARKER_GENERAL = '.fediCustomCss';
    public const string SELECTOR_MARKER_SOFTWARE = '.fediCustomCss-software-mbin';
    public const string SELECTOR_MARKER_USER = '.fediCustomCss-user-';

    private const int CSS_MAX_SIZE = 10 * 1024;

    public function __construct(
        private PersonFactory $personFactory,
    )
    {

    }

    public function createConstraintSelector(User $user): string
    {
        $userId = $this->personFactory->getActivityPubId($user);
        // convert base64 encoding to valid CSS identifier (https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Values/ident)
        $userId = base64_encode($userId);
        $userId = str_replace('+', '-', $userId);
        $userId = str_replace('/', '_', $userId);
        $userId = str_replace('=', '', $userId);

        return self::SELECTOR_MARKER_GENERAL.self::SELECTOR_MARKER_SOFTWARE.self::SELECTOR_MARKER_USER.$userId;
    }

    public function createConstraintStyleClass(User $user): string {
        return str_replace('.', ' ', $this->createConstraintSelector($user));
    }

    public function validatePublicCss(string $src, ?User $user, bool $addSelectorConstraints): string {
        if($src === '') {
            throw new \InvalidArgumentException('empty CSS src is invalid');
        }
        if($addSelectorConstraints && $user === null) {
            throw new \InvalidArgumentException('if $addSelectorConstraints a User mit be supplied');
        }

        if(strlen($src) > self::CSS_MAX_SIZE) {
            throw new BadRequestHttpException('The CSS is too big');
        }

        try {
            $parser = new Parser($src, Settings::create()->beStrict());
            $parsed = $parser->parse();

            $parsed->setComments([]);
            $this->validateCssList($parsed);

            if($user !== null) {
                $this->enforceSelectorConstraints($parsed, $user, $addSelectorConstraints);
            }

            return $parsed->render();
        }catch (\Exception $e){
            if($e instanceof BadRequestHttpException) {
                throw $e;
            }
            throw new BadRequestHttpException('The CSS syntax is invalid', $e);
        }
    }

    private function validateCssList(CSSList $css): void {
        foreach ($css->getContents() as $item) {
            if($item instanceof DeclarationBlock) {
                $this->validateCssDeclarationBlock($item);
            } else {
                $lineText = ($item instanceof Positionable) ? ' at line '.$item->getLineNumber() : '';
                throw new BadRequestHttpException('The CSS contained rules which are not allowed'.$lineText);
            }

        }
    }

    private function validateCssDeclarationBlock(DeclarationBlock $css): void {
        foreach ($css->getSelectors() as $selector) {
            if(count($selector->getComponents()) === 0) {
                throw new BadRequestHttpException('The CSS contained rules with disallowed selectors at line '.$css->getLineNumber());
            }

            if(!($selector->getComponents()[0] instanceof CompoundSelector) || !str_starts_with($selector->getComponents()[0]->getValue(), '.')) {
                throw new BadRequestHttpException('The CSS contained rules with disallowed selectors at line '.$css->getLineNumber());
            }

            foreach ($selector->getComponents() as $component) {
                if(str_contains($component->getValue(), '*')) {//TODO maybe allow *
                    throw new BadRequestHttpException('The CSS contained rules with disallowed selectors at line '.$css->getLineNumber());
                }
            }
        }

        if($css->getRuleSet() instanceof AtRuleSet) {
            throw new BadRequestHttpException('The CSS contained rules which are not allowed at line '.$css->getLineNumber());
        }

        foreach ($css->getDeclarations() as $declaration) {
            $value = $declaration->getValue();
            if($value instanceof URL) {
                if(!str_starts_with($value->getURL()->getString(), 'data:image/')) {
                    throw new BadRequestHttpException('The CSS contained rules which are not allowed at line ' . $declaration->getLineNumber());
                }
            }
            if($value instanceof ValueList) {
                foreach ($value->getListComponents() as $component){
                    if($component instanceof URL) {
                        throw new BadRequestHttpException('The CSS contained rules which are not allowed at line '.$declaration->getLineNumber());
                    }
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

                if(count($components) >= 1 && $components[0]->getValue() === $constraintSelector) {
                    continue;
                }

                foreach ($components as $component) {
                    if(str_contains($component->getValue(), 'fediCustomCss')) {
                        throw new BadRequestHttpException('The CSS has a block with invalid fediCustomCss selector at line '.$block->getLineNumber());
                    }
                }

                if($addMissingClasses) {
                    array_unshift($components, new CompoundSelector($constraintSelector));
                    $selector->setComponents($components);
                } else {
                    throw new BadRequestHttpException('The CSS has a block with missing fediCustomCss selector at line '.$block->getLineNumber());
                }
            }
        }
    }
}