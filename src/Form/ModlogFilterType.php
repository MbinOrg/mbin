<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\ModlogFilterDto;
use App\Form\Type\MagazineAutocompleteType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ModlogFilterType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('types', ChoiceType::class, [
                'choices' => [
                    $this->translator->trans('modlog_type_entry_deleted') => 'entry_deleted',
                    $this->translator->trans('modlog_type_entry_restored') => 'entry_restored',
                    $this->translator->trans('modlog_type_entry_comment_deleted') => 'entry_comment_deleted',
                    $this->translator->trans('modlog_type_entry_comment_restored') => 'entry_comment_restored',
                    $this->translator->trans('modlog_type_entry_pinned') => 'entry_pinned',
                    $this->translator->trans('modlog_type_entry_unpinned') => 'entry_unpinned',
                    $this->translator->trans('modlog_type_post_deleted') => 'post_deleted',
                    $this->translator->trans('modlog_type_post_restored') => 'post_restored',
                    $this->translator->trans('modlog_type_post_comment_deleted') => 'post_comment_deleted',
                    $this->translator->trans('modlog_type_post_comment_restored') => 'post_comment_restored',
                    $this->translator->trans('modlog_type_ban') => 'ban',
                    $this->translator->trans('modlog_type_moderator_add') => 'moderator_add',
                    $this->translator->trans('modlog_type_moderator_remove') => 'moderator_remove',
                    $this->translator->trans('modlog_type_entry_lock') => 'entry_locked',
                    $this->translator->trans('modlog_type_entry_unlock') => 'entry_unlocked',
                    $this->translator->trans('modlog_type_post_lock') => 'post_locked',
                    $this->translator->trans('modlog_type_post_unlock') => 'post_unlocked',
                ],
                'multiple' => true,
                'required' => false,
                'autocomplete' => true,
            ])
            ->add('magazine', MagazineAutocompleteType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => ModlogFilterDto::class,
            ]
        );
    }
}
