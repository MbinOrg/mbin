<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\UserFilterListDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserFilterListType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class)
            ->add('expirationDate', DateType::class, [
                'required' => false,
                'row_attr' => [
                    'class' => 'checkbox',
                ],
            ])
            ->add('feeds', CheckboxType::class, [
                'required' => false,
                'row_attr' => [
                    'class' => 'checkbox',
                ],
                'help' => 'filter_lists_feeds_help',
            ])
            ->add('comments', CheckboxType::class, [
                'required' => false,
                'row_attr' => [
                    'class' => 'checkbox',
                ],
                'help' => 'filter_lists_comments_help',
            ])
            ->add('profile', CheckboxType::class, [
                'required' => false,
                'row_attr' => [
                    'class' => 'checkbox',
                ],
                'help' => 'filter_lists_profile_help',
            ])
            ->add('words', CollectionType::class, [
                'entry_type' => UserFilterWordType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'attr' => [
                    'class' => 'existing-words',
                ],
                'label' => 'filter_lists_filter_words',
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => UserFilterListDto::class,
            ]
        );
    }
}
