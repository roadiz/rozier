<?php

declare(strict_types=1);

namespace Themes\Rozier\Forms;

use RZ\Roadiz\CoreBundle\Entity\Translation;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class TranslationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'name',
            'constraints' => [
                new NotNull(),
                new NotBlank(),
                new Length([
                    'max' => 255,
                ])
            ],
        ])
        ->add('locale', ChoiceType::class, [
            'label' => 'locale',
            'required' => true,
            'choices' => array_flip(Translation::$availableLocales),
        ])
        ->add('available', CheckboxType::class, [
            'label' => 'available',
            'required' => false,
        ])
        ->add('overrideLocale', TextType::class, [
            'label' => 'overrideLocale',
            'required' => false,
            'constraints' => [
                new Length([
                    'max' => 7,
                ])
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'translation';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
            'locale' => '',
            'overrideLocale' => '',
            'data_class' => Translation::class,
            'attr' => [
                'class' => 'uk-form translation-form',
            ],
            'constraints' => [
                new UniqueEntity([
                    'fields' => ['locale']
                ]),
                new UniqueEntity([
                    'fields' => ['overrideLocale']
                ])
            ]
        ]);
    }
}
