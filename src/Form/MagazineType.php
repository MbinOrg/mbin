<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\MagazineDto;
use App\Form\EventListener\DisableFieldsOnMagazineEdit;
use App\Form\EventListener\RemoveRulesFieldIfEmpty;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MagazineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['required' => true])
            ->add('title', TextType::class, ['required' => true])
            ->add('description', TextareaType::class, ['required' => false])
            ->add('rules', TextareaType::class, [
                'required' => false,
                'help' => 'magazine_rules_deprecated',
            ])
            ->add('isAdult', CheckboxType::class, ['required' => false])
            ->add('isPostingRestrictedToMods', CheckboxType::class, ['required' => false])
            ->add('discoverable', CheckboxType::class, [
                'required' => false,
                'help' => 'magazine_discoverable_help',
            ])
            ->add('indexable', CheckboxType::class, [
                'required' => false,
                'help' => 'magazine_indexable_by_search_engines_help',
            ])
            // this is removed through the event subscriber below on magazine edit
            ->add('nameAsTag', CheckboxType::class, [
                'required' => false,
                'help' => 'magazine_name_as_tag_help',
            ])
            ->add('submit', SubmitType::class);

        $builder->addEventSubscriber(new DisableFieldsOnMagazineEdit());
        $builder->addEventSubscriber(new RemoveRulesFieldIfEmpty());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => MagazineDto::class,
            ]
        );
    }
}
