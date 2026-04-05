<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\PostDto;
use App\Form\Constraint\ImageConstraint;
use App\Form\EventListener\DefaultLanguage;
use App\Form\EventListener\ImageListener;
use App\Form\Type\LanguageType;
use App\Form\Type\MagazineAutocompleteType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PostType extends AbstractType
{
    public function __construct(
        private readonly ImageListener $imageListener,
        private readonly DefaultLanguage $defaultLanguage,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('body', TextareaType::class, ['required' => false, 'empty_data' => ''])
            ->add(
                'image',
                FileType::class,
                [
                    'constraints' => ImageConstraint::default(),
                    'mapped' => false,
                    'required' => false,
                ]
            )
            ->add('magazine', MagazineAutocompleteType::class)
            ->add('lang', LanguageType::class)
            ->add('imageUrl', UrlType::class, ['required' => false, 'default_protocol' => 'https'])
            ->add('imageAlt', TextareaType::class, ['required' => false])
            ->add('isAdult', CheckboxType::class, ['required' => false])
            ->add('addPoll', CheckboxType::class, [
                'required' => false,
                'row_attr' => [
                    'class' => 'checkbox',
                ],
            ])
            ->add('isMultipleChoicePoll', CheckboxType::class, [
                'required' => false,
                'label' => 'poll_is_multiple_choice',
                'row_attr' => [
                    'class' => 'checkbox',
                ],
            ])
            ->add('pollEndsAt', DateTimeType::class, [
                'attr' => [
                    'class' => 'form-control',
                ],
                'required' => false,
                'label' => 'poll_ends_at',
            ])
            ->add('choices', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'attr' => [
                    'class' => 'existing-collection-items',
                ],
                'label' => 'poll_choices',
            ])
            ->add('submit', SubmitType::class);

        $builder->addEventSubscriber($this->defaultLanguage);
        $builder->addEventSubscriber($this->imageListener);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => PostDto::class,
            ]
        );
    }
}
