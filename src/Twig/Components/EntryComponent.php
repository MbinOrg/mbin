<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Entry;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('entry')]
final class EntryComponent extends AbstractSubjectComponent
{
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
    ) {
        parent::__construct($authorizationChecker);
    }

    public ?Entry $entry;
    public bool $isSingle = false;
    public bool $showShortSentence = true;
    public bool $showBody = false;
    public bool $showMagazineName = true;

    #[PostMount]
    public function postMount(array $attr): array
    {
        $this->init($this->entry);

        if (!$this->canSeeTrashed()) {
            $this->showBody = false;
            $this->showShortSentence = false;
        }

        if ($this->isSingle) {
            if (isset($attr['class'])) {
                $attr['class'] = trim('entry--single section--top '.$attr['class']);
            } else {
                $attr['class'] = 'entry--single section--top';
            }
        }

        return $attr;
    }
}
