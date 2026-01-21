<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\MonitoringExecutionContextFilterDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class MonitoringExecutionContextFilterType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('executionType', ChoiceType::class, [
                'label' => $this->translator->trans('monitoring_execution_type'),
                'required' => false,
                'choices' => [
                    $this->translator->trans('monitoring_request') => 'request',
                    $this->translator->trans('monitoring_messenger') => 'messenger',
                ],
            ])
            ->add('userType', ChoiceType::class, [
                'label' => $this->translator->trans('monitoring_user_type'),
                'required' => false,
                'choices' => [
                    $this->translator->trans('monitoring_anonymous') => 'anonymous',
                    $this->translator->trans('monitoring_user') => 'user',
                    $this->translator->trans('monitoring_activity_pub') => 'activity_pub',
                    $this->translator->trans('monitoring_ajax') => 'ajax',
                ],
            ])
            ->add('path', TextType::class, [
                'label' => $this->translator->trans('monitoring_path'),
                'required' => false,
            ])
            ->add('handler', TextType::class, [
                'label' => $this->translator->trans('monitoring_handler'),
                'required' => false,
            ])
            ->add('createdFrom', DateTimeType::class, [
                'label' => $this->translator->trans('monitoring_created_from'),
                'required' => false,
            ])
            ->add('createdTo', DateTimeType::class, [
                'label' => $this->translator->trans('monitoring_created_to'),
                'required' => false,
            ])
            ->add('durationMinimum', NumberType::class, [
                'label' => $this->translator->trans('monitoring_duration_minimum'),
                'required' => false,
            ])
            ->add('hasException', ChoiceType::class, [
                'label' => $this->translator->trans('monitoring_has_exception'),
                'required' => false,
                'choices' => [
                    $this->translator->trans('yes') => true,
                    $this->translator->trans('no') => false,
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => $this->translator->trans('monitoring_submit')])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => MonitoringExecutionContextFilterDto::class,
                'csrf_protection' => false,
            ]
        );
    }
}
