<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\SettingsDto;
use App\Repository\Criteria;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('KBIN_DOMAIN')
            ->add('KBIN_CONTACT_EMAIL', EmailType::class)
            ->add('KBIN_TITLE')
            ->add('KBIN_META_TITLE')
            ->add('KBIN_META_DESCRIPTION')
            ->add('KBIN_META_KEYWORDS')
            ->add('MBIN_DEFAULT_THEME', ChoiceType::class, [
              'choices' => Criteria::THEME_OPTIONS,
            ])
            ->add('KBIN_HEADER_LOGO', CheckboxType::class, ['required' => false])
            ->add('KBIN_REGISTRATIONS_ENABLED', CheckboxType::class, ['required' => false])
            ->add('MBIN_SSO_REGISTRATIONS_ENABLED', CheckboxType::class, ['required' => false])
            ->add('KBIN_CAPTCHA_ENABLED', CheckboxType::class, ['required' => false])
            ->add('KBIN_FEDERATION_ENABLED', CheckboxType::class, ['required' => false])
            ->add('KBIN_MERCURE_ENABLED', CheckboxType::class, ['required' => false])
            ->add('KBIN_FEDERATION_PAGE_ENABLED', CheckboxType::class, ['required' => false])
            ->add('KBIN_ADMIN_ONLY_OAUTH_CLIENTS', CheckboxType::class, ['required' => false])
            ->add('MBIN_SSO_ONLY_MODE', CheckboxType::class, ['required' => false])
            ->add('MBIN_PRIVATE_INSTANCE', CheckboxType::class, ['required' => false])
            ->add('KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN', CheckboxType::class, ['required' => false])
            ->add('MBIN_SIDEBAR_SECTIONS_LOCAL_ONLY', CheckboxType::class, ['required' => false])
            ->add('MBIN_RESTRICT_MAGAZINE_CREATION', CheckboxType::class, ['required' => false])
            ->add('MBIN_SHOW_SOCIALS_FIRST', CheckboxType::class, ['required' => false])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => SettingsDto::class,
            ]
        );
    }
}
