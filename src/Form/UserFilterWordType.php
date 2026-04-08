<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\UserFilterWordDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserFilterWordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('word', TextType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('exactMatch', CheckboxType::class, [
                'required' => false,
                'label' => 'filter_lists_word_exact_match',
                'row_attr' => [
                    'class' => 'checkbox',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => UserFilterWordDto::class,
            ]
        );
    }
}
