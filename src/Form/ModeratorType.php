<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\ModeratorDto;
use App\Form\DataTransformer\UserTransformer;
use App\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ModeratorType extends AbstractType
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', options: [
                'attr' => ['autocomplete' => 'new-password']
            ])
            ->add('submit', SubmitType::class);

        $builder->get('user')->addModelTransformer(
            new UserTransformer($this->userRepository)
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => ModeratorDto::class,
            ]
        );
    }
}
