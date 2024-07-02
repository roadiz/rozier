<?php

declare(strict_types=1);

namespace Themes\Rozier\Forms;

use RZ\Roadiz\CoreBundle\Entity\CustomFormField;
use RZ\Roadiz\CoreBundle\Form\DataListTextType;
use RZ\Roadiz\CoreBundle\Form\MarkdownType;
use RZ\Roadiz\CoreBundle\Repository\CustomFormFieldRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomFormFieldType extends AbstractType
{
    public function __construct(
        private readonly CustomFormFieldRepository $customFormFieldRepository
    ) {
    }

    /**
     * @param CustomFormField $field
     * @return string[]
     */
    protected function getAllGroupsNames(CustomFormField $field): array
    {
        return $this->customFormFieldRepository->findDistinctGroupNamesInCustomForm($field->getCustomForm());
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('label', TextType::class, [
                'label' => 'label',
                'empty_data' => '',
            ])
            ->add('description', MarkdownType::class, [
                'label' => 'description',
                'required' => false,
            ])
            ->add('placeholder', TextType::class, [
                'label' => 'placeholder',
                'required' => false,
                'help' => 'label_for_field_with_empty_data',
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'type',
                'required' => true,
                'choices' => array_flip(CustomFormField::$typeToHuman),
            ])
            ->add('required', CheckboxType::class, [
                'label' => 'required',
                'required' => false,
                'help' => 'make_this_field_mandatory_for_users',
            ])
            ->add('expanded', CheckboxType::class, [
                'label' => 'expanded',
                'help' => 'use_checkboxes_or_radio_buttons_instead_of_select_box',
                'required' => false,
            ])
            ->add(
                'defaultValues',
                TextareaType::class,
                [
                    'label' => 'customFormField.defaultValues',
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'enter_values_comma_separated',
                    ],
                ]
            )
            ->add('groupName', DataListTextType::class, [
                'label' => 'groupName',
                'required' => false,
                'help' => 'use_the_same_group_names_over_fields_to_gather_them_in_tabs',
                'list' => $this->getAllGroupsNames($builder->getData()),
                'listName' => 'group-names',
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('autocomplete', ChoiceType::class, [
                'label' => 'customForm.autocomplete',
                'help' => 'customForm.autocomplete.help',
                'choices' => [
                    'off',
                    'name',
                    'honorific-prefix',
                    'honorific-suffix',
                    'given-name',
                    'additional-name',
                    'family-name',
                    'nickname',
                    'email',
                    'username',
                    'organization-title',
                    'organization',
                    'street-address',
                    'country',
                    'country-name',
                    'postal-code',
                    'bday',
                    'bday-day',
                    'bday-month',
                    'bday-year',
                    'sex',
                    'tel',
                    'tel-national',
                    'url',
                    'photo',
                ],
                'placeholder' => 'autocomplete.no_autocomplete',
                'choice_label' => function ($choice, $key, $value) {
                    return 'autocomplete.' . $value;
                },
                'required' => false,
            ]);
    }

    public function getBlockPrefix(): string
    {
        return 'customformfield';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
            'fieldName' => '',
            'customForm' => null,
            'data_class' => CustomFormField::class,
            'attr' => [
                'class' => 'uk-form custom-form-field-form',
            ],
        ]);
    }
}
