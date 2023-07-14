<?php

namespace App\Form\Type;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Magazine;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\ParentEntityAutocompleteType;

#[AsEntityAutocompleteField]
class MagazineAutocompleteType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Magazine::class,
            'choice_label' => 'name',
            'placeholder' => 'select_magazine',
            'filter_query' => function (QueryBuilder $qb, string $query) {
                if (!$query) {
                    return;
                }

                $qb->andWhere('entity.name LIKE :filter OR entity.title LIKE :filter')
                    ->andWhere('entity.apId IS NULL')
                    ->andWhere('entity.visibility = :visibility')
                    ->setParameters(
                        ['filter' => '%'.$query.'%', 'visibility' => VisibilityInterface::VISIBILITY_VISIBLE]
                    );
            },
        ]);
    }

    public function getParent(): string
    {
        return ParentEntityAutocompleteType::class;
    }
}
