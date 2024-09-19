<?php

declare(strict_types=1);

namespace App\Controller\Entry;

use App\DTO\EntryDto;
use App\Entity\Entry;
use App\Form\EntryArticleType;
use App\Form\EntryImageType;
use App\Form\EntryLinkType;
use Symfony\Component\Form\FormInterface;

/**
 * @method FormInterface createForm(string $class, mixed $data = null, array $options = [])
 */
trait EntryFormTrait
{
    private function createFormByType(string $type, ?EntryDto $dto = null): FormInterface
    {
        if (Entry::ENTRY_TYPE_THREAD === $type) {
            return $this->createForm(EntryArticleType::class, $dto);
        }

        if (Entry::ENTRY_TYPE_IMAGE === $type) {
            return $this->createForm(EntryImageType::class, $dto);
        }

        return $this->createForm(EntryLinkType::class, $dto);
    }
}
