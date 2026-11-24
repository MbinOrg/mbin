<?php
declare(strict_types=1);

namespace App\Form;

use App\DTO\UserStylesDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<UserStylesDto> */
class UserStylesType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customCss', TextareaType::class, [
                'required' => false,
            ])
            ->add('customPublicContentCss', TextareaType::class, [
                'required' => false,
            ])
            ->add('customPublicProfileCss', TextareaType::class, [
                'required' => false,
            ])
            ->add(
                'ignoreUserContentCss',
                CheckboxType::class,
                ['required' => false]
            )
            ->add(
                'ignoreUserProfileCss',
                CheckboxType::class,
                ['required' => false]
            )
            ->add(
                'ignoreMagazinesCustomCss',
                CheckboxType::class,
                ['required' => false]
            )
            ->add('submit', SubmitType::class);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => UserStylesDto::class,
            ]
        );
    }
}
