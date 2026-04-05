<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\EntryDto;
use App\Form\Constraint\ImageConstraint;
use App\Form\DataTransformer\TagTransformer;
use App\Form\EventListener\DefaultLanguage;
use App\Form\EventListener\DisableFieldsOnEntryEdit;
use App\Form\EventListener\ImageListener;
// use App\Form\Type\BadgesType;
use App\Form\EventListener\RemovePollFieldsOnEntryEdit;
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

class EntryEditType extends AbstractType
{
    public function __construct(
        private readonly ImageListener $imageListener,
        private readonly DefaultLanguage $defaultLanguage,
        private readonly DisableFieldsOnEntryEdit $disableFieldsOnEntryEdit,
        private readonly RemovePollFieldsOnEntryEdit $removePollFieldsOnEntryEdit,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', UrlType::class, [
                'required' => false,
                'default_protocol' => 'https',
            ])
            ->add('title', TextareaType::class, [
                'required' => true,
            ])
            ->add('body', TextareaType::class, [
                'required' => false,
            ])
            ->add('magazine', MagazineAutocompleteType::class)
            ->add('tags', TextType::class, [
                'required' => false,
                'autocomplete' => true,
                'tom_select_options' => [
                    'create' => true,
                    'createOnBlur' => true,
                    'delimiter' => ' ',
                ],
            ])
            // ->add(
            //     'badges',
            //     BadgesType::class,
            //     [
            //         'required' => false,
            //     ]
            // )
            ->add(
                'image',
                FileType::class,
                [
                    'constraints' => ImageConstraint::default(),
                    'mapped' => false,
                    'required' => false,
                ]
            )
            ->add('imageUrl', UrlType::class, [
                'required' => false,
                'default_protocol' => 'https',
            ])
            ->add('imageAlt', TextType::class, [
                'required' => false,
            ])
            ->add('isAdult', CheckboxType::class, [
                'required' => false,
            ])
            ->add('lang', LanguageType::class)
            ->add('isOc', CheckboxType::class, [
                'required' => false,
            ])
            ->add('isMultipleChoicePoll', CheckboxType::class, [
                'required' => false,
                'label' => 'poll_is_multiple_choice',
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

        $builder->get('tags')->addModelTransformer(
            new TagTransformer()
        );

        $builder->addEventSubscriber($this->defaultLanguage);
        $builder->addEventSubscriber($this->disableFieldsOnEntryEdit);
        $builder->addEventSubscriber($this->imageListener);
        $builder->addEventSubscriber($this->removePollFieldsOnEntryEdit);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => EntryDto::class,
            ]
        );
    }
}
