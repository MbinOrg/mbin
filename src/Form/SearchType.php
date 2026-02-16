<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Type\MagazineAutocompleteType;
use App\Form\Type\UserAutocompleteType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class SearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->setMethod('GET')
            ->add('q', TextType::class, [
                'required' => true,
                'attr' => [
                    'placeholder' => 'type_search_term_url_handle',
                ],
            ])
            ->add('magazine', MagazineAutocompleteType::class, ['required' => false, 'placeholder' => 'type_search_magazine'])
            ->add('user', UserAutocompleteType::class, ['required' => false, 'placeholder' => 'type_search_user'])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'search_type_all' => null,
                    'search_type_entry' => 'entry',
                    'search_type_post' => 'post',
                    'search_type_magazine' => 'magazine',
                    'search_type_user' => 'user',
                    'search_type_actors' => 'users+magazines',
                    'search_type_content' => 'entry+post',
                ],
            ])
            ->add('since', DateType::class, ['required' => false])
        ;
    }
}
