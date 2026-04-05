<?php

declare(strict_types=1);

namespace App\Form\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class RemovePollFieldsOnEntryEdit implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [FormEvents::PRE_SET_DATA => 'preSetData'];
    }

    public function preSetData(FormEvent $event): void
    {
        $dto = $event->getData();
        $form = $event->getForm();

        if (!$dto || null === $dto->getId()) {
            return;
        }

        if (!$dto->addPoll) {
            $form->remove('isMultipleChoicePoll');
            $form->remove('pollEndsAt');
            $form->remove('choices');
        }
    }
}
