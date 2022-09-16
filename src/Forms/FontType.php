<?php

declare(strict_types=1);

namespace Themes\Rozier\Forms;

use RZ\Roadiz\CoreBundle\Entity\Font;
use RZ\Roadiz\CoreBundle\Form\FontVariantsType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * FontType.
 */
class FontType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
                'label' => 'font.name',
                'empty_data' => '',
                'help' => 'font_name_should_be_the_same_for_all_variants',
                'constraints' => [
                    new NotNull(),
                    new NotBlank(),
                    new Length([
                        'max' => 100,
                    ])
                ],
            ])
            ->add('hash', TextType::class, [
                'label' => 'font.cssfamily',
                'empty_data' => '',
                'help' => 'css_font_family_hash_is_automatically_generated_from_font_name',
            ])
            ->add('variant', FontVariantsType::class, [
                'label' => 'font.variant',
            ])
            ->add('woffFile', FileType::class, [
                'label' => 'font.woffFile',
                'required' => false,
                'multiple' => false,
                'constraints' => [
                    new File([
                        'mimeTypes' => [
                            Font::MIME_WOFF,
                            'application/x-font-woff',
                            Font::MIME_DEFAULT,
                        ],
                        'mimeTypesMessage' => 'file.is_not_a.valid.font.file',
                    ]),
                ],
            ])
            ->add('woff2File', FileType::class, [
                'label' => 'font.woff2File',
                'required' => false,
                'multiple' => false,
                'constraints' => [
                    new File([
                        'mimeTypes' => [
                            Font::MIME_WOFF2,
                            Font::MIME_DEFAULT,
                        ],
                        'mimeTypesMessage' => 'file.is_not_a.valid.font.file',
                    ]),
                ],
            ]);
    }

    public function getBlockPrefix(): string
    {
        return 'font';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
            'name' => '',
            'variant' => Font::REGULAR,
            'data_class' => Font::class,
            'attr' => [
                'class' => 'uk-form font-form',
            ],
            'constraints' => [
                new UniqueEntity([
                    'fields' => [
                        'name',
                        'variant'
                    ]
                ])
            ]
        ]);

        $resolver->setAllowedTypes('name', 'string');
        $resolver->setAllowedTypes('variant', 'integer');
    }
}
