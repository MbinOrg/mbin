<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\UserSettingsDto;
use App\Entity\User;
use App\Enums\EDirectMessageSettings;
use App\Enums\EFrontContentOptions;
use App\Form\DataTransformer\FeaturedMagazinesBarTransformer;
use App\PageView\EntryCommentPageView;
use App\PageView\EntryPageView;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserSettingsType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $frontDefaultSortChoices = [];
        foreach (EntryPageView::SORT_OPTIONS as $option) {
            $frontDefaultSortChoices[$this->translator->trans($option)] = $option;
        }
        $commentDefaultSortChoices = [];
        foreach (EntryCommentPageView::SORT_OPTIONS as $option) {
            $commentDefaultSortChoices[$this->translator->trans($option)] = $option;
        }
        $directMessageSettingChoices = [];
        foreach (EDirectMessageSettings::getValues() as $option) {
            $directMessageSettingChoices[$this->translator->trans($option)] = $option;
        }
        $frontDefaultContentChoices = [
            $this->translator->trans('default_content_default') => null,
        ];
        foreach (EFrontContentOptions::OPTIONS as $option) {
            $frontDefaultContentChoices[$this->translator->trans('default_content_'.$option)] = $option;
        }
        $builder
            ->add(
                'hideAdult',
                CheckboxType::class,
                ['required' => false]
            )
            ->add('homepage', ChoiceType::class, [
                'autocomplete' => true,
                'choices' => [
                    $this->translator->trans('all') => User::HOMEPAGE_ALL,
                    $this->translator->trans('subscriptions') => User::HOMEPAGE_SUB,
                    $this->translator->trans('favourites') => User::HOMEPAGE_FAV,
                    $this->translator->trans('moderated') => User::HOMEPAGE_MOD,
                ],
            ]
            )
            ->add('frontDefaultSort', ChoiceType::class, [
                'autocomplete' => true,
                'choices' => $frontDefaultSortChoices,
            ])
            ->add('frontDefaultContent', ChoiceType::class, [
                'autocomplete' => true,
                'choices' => $frontDefaultContentChoices,
            ])
            ->add('commentDefaultSort', ChoiceType::class, [
                'autocomplete' => true,
                'choices' => $commentDefaultSortChoices,
            ])
            ->add('directMessageSetting', ChoiceType::class, [
                'autocomplete' => true,
                'choices' => $directMessageSettingChoices,
            ])
            ->add('discoverable', CheckboxType::class, [
                'required' => false,
                'help' => 'user_discoverable_help',
            ])
            ->add('indexable', CheckboxType::class, [
                'required' => false,
                'help' => 'user_indexable_by_search_engines_help',
            ])
            ->add('featuredMagazines', TextareaType::class, ['required' => false])
            ->add('preferredLanguages', LanguageType::class, [
                'required' => false,
                'preferred_choices' => [$this->translator->getLocale()],
                'autocomplete' => true,
                'multiple' => true,
                'choice_self_translation' => true,
            ])
            ->add('customCss', TextareaType::class, [
                'required' => false,
            ])
            ->add(
                'ignoreMagazinesCustomCss',
                CheckboxType::class,
                ['required' => false]
            )
            ->add(
                'showProfileSubscriptions',
                CheckboxType::class,
                ['required' => false]
            )
            ->add(
                'showProfileFollowings',
                CheckboxType::class,
                ['required' => false]
            )
            ->add(
                'notifyOnNewEntry',
                CheckboxType::class,
                ['required' => false]
            )
            ->add(
                'notifyOnNewEntryReply',
                CheckboxType::class,
                ['required' => false]
            )
            ->add(
                'notifyOnNewEntryCommentReply',
                CheckboxType::class,
                ['required' => false]
            )
            ->add(
                'notifyOnNewPost',
                CheckboxType::class,
                ['required' => false]
            )
            ->add(
                'notifyOnNewPostReply',
                CheckboxType::class,
                ['required' => false]
            )
            ->add(
                'notifyOnNewPostCommentReply',
                CheckboxType::class,
                ['required' => false]
            )
            ->add(
                'addMentionsEntries',
                CheckboxType::class,
                ['required' => false]
            )
            ->add(
                'addMentionsPosts',
                CheckboxType::class,
                ['required' => false]
            )
            ->add('submit', SubmitType::class);

        /** @var User $user */
        $user = $this->security->getUser();
        if ($user->isAdmin() or $user->isModerator()) {
            $builder->add('notifyOnUserSignup', CheckboxType::class, ['required' => false]);
        }

        $builder->get('featuredMagazines')->addModelTransformer(
            new FeaturedMagazinesBarTransformer()
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => UserSettingsDto::class,
            ]
        );
    }
}
