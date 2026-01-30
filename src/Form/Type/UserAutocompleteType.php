<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\User;
use App\Entity\UserBlock;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class UserAutocompleteType extends AbstractType
{
    public function __construct(private readonly Security $security)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => User::class,
            'choice_label' => 'username',
            'placeholder' => 'select_user',
            'filter_query' => function (QueryBuilder $qb, string $query) {
                if ($currentUser = $this->security->getUser()) {
                    $qb
                        ->andWhere(
                            \sprintf(
                                'entity.id NOT IN (SELECT IDENTITY(ub.blocked) FROM %s ub WHERE ub.blocker = :user)',
                                UserBlock::class,
                            )
                        )
                        ->setParameter('user', $currentUser);
                }

                if (!$query) {
                    return;
                }

                $qb->andWhere('entity.username LIKE :filter')
                    ->andWhere('entity.visibility = :visibility')
                    ->setParameter('filter', '%'.$query.'%')
                    ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
                ;
            },
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
