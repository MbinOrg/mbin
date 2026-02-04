<?php

declare(strict_types=1);

namespace App\Form\EventListener;

use App\Entity\Magazine;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class RemoveRulesFieldIfEmpty implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [FormEvents::PRE_SET_DATA => 'preSetData'];
    }

    public function preSetData(FormEvent $event): void
    {
        /** @var Magazine $magazine */
        $magazine = $event->getData();
        $form = $event->getForm();

        $field = $form->get('rules');

        if (!$field->isEmpty() || !empty($magazine?->rules)) {
            return;
        }
        $form->remove($field->getName());
    }
}
