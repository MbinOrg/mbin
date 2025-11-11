<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class FederationSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('federationEnabled', CheckboxType::class, ['required' => false])
            ->add('federationUsesAllowList',
                CheckboxType::class,
                [
                    'required' => false,
                    'help' => 'federation_page_use_allowlist_help',
                ],
            )
            ->add('federationPageEnabled', CheckboxType::class, ['required' => false])
            ->add('submit', SubmitType::class)
        ;
    }
}
