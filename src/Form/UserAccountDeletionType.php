<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class UserAccountDeletionType extends AbstractType
{
    public function __construct()
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'mapped' => false,
                'row_attr' => [
                    'class' => 'password-preview',
                    'data-controller' => 'password-preview',
                ],
                ])
            ->add('instantDelete', CheckboxType::class, ['required' => false])
            ->add('submit', SubmitType::class);
    }
}
