<?php

namespace App\Service;

use Sabberworm\CSS\CSSList\AtRuleBlockList;
use Sabberworm\CSS\CSSList\CSSBlockList;
use Sabberworm\CSS\CSSList\CSSList;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Position\Positionable;
use Sabberworm\CSS\RuleSet\AtRuleSet;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\RuleSet\RuleSet;
use Sabberworm\CSS\Settings;
use Sabberworm\CSS\Value\URL;
use Sabberworm\CSS\Value\ValueList;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CustomCssService
{

    private const int CSS_MAX_SIZE = 10 * 1024;

    public function validatePublicCss(string $src): string {
        if($src === '') {
            throw new \InvalidArgumentException('empty CSS src is invalid');
        }
        if(strlen($src) > self::CSS_MAX_SIZE) {
            throw new BadRequestHttpException('The CSS is too big');
        }

        try {
            $parser = new Parser($src, Settings::create()->beStrict());
            $parsed = $parser->parse();

            $parsed->setComments([]);
            $this->validateCssList($parsed);

            return $parsed->render();
        }catch (\Exception $e){
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
            foreach ($selector->getComponents() as $component) {
                if(str_contains($component->getValue(), '*')) {
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
}