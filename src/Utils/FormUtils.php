<?php
declare(strict_types=1);

namespace App\Utils;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class FormUtils
{
    public static function addPollFieldsToType(FormBuilderInterface $builder, bool $addAddPollField = true, ?string $checkboxRowClass = null): void
    {
        if ($addAddPollField) {
            $options = [
                'required' => false,
            ];
            if ($checkboxRowClass) {
                $options['row_attr'] = [
                    'class' => $checkboxRowClass,
                ];
            }
            $builder->add('addPoll', CheckboxType::class, $options);
        }

        $multipleChoiceOptions = [
            'required' => false,
            'label' => 'poll_is_multiple_choice',
        ];
        if ($checkboxRowClass) {
            $multipleChoiceOptions['row_attr'] = [
                'class' => $checkboxRowClass,
            ];
        }
        $builder
            ->add('isMultipleChoicePoll', CheckboxType::class, $multipleChoiceOptions)
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
            ]);
    }
}
