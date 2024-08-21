<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\UserDto;
use App\Form\EventListener\AddFieldsOnUserEdit;
use App\Form\EventListener\AvatarListener;
use App\Form\EventListener\DisableFieldsOnUserEdit;
use App\Form\EventListener\ImageListener;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserBasicType extends AbstractType
{
    public function __construct(
        private readonly ImageListener $imageListener,
        private readonly AvatarListener $avatarListener,
        private readonly AddFieldsOnUserEdit $addAvatarFieldOnUserEdit,
        private readonly DisableFieldsOnUserEdit $disableUsernameFieldOnUserEdit
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, ['required' => false])
            ->add('about', TextareaType::class, ['required' => false])
            ->add('relatedSocialLinks', CollectionType::class , [
                'entry_type' => UserRelatedLinkType::class,
                'entry_options' => ['label' => false],
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                // 'by_reference' => false,
            ])
            ->add('submit', SubmitType::class)
        ;
        
        $builder->addModelTransformer(new CallbackTransformer(
            function ($associativeArrayData) {
                $para = '';
                return $associativeArrayData;
            },
            function ($dtoData) {
                $para = '';
                return $dtoData;
            }
        ));

        $builder->addEventSubscriber($this->disableUsernameFieldOnUserEdit);
        $builder->addEventSubscriber($this->addAvatarFieldOnUserEdit);
        $builder->addEventSubscriber($this->avatarListener->setFieldName('avatar'));
        $builder->addEventSubscriber($this->imageListener->setFieldName('cover'));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => UserDto::class,
            ]
        );
    }
}
