<?php

declare(strict_types=1);

namespace Themes\Rozier\Forms;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

final class NodeTypeFieldSerializationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('excludedFromSerialization', CheckboxType::class, [
            'label' => 'nodeTypeField.excludedFromSerialization',
            'help' => 'exclude_this_field_from_api_serialization',
            'required' => false,
        ])
        ->add('serializationMaxDepth', IntegerType::class, [
            'label' => 'nodeTypeField.serializationMaxDepth',
            'required' => false,
            'attr' => [
                'placeholder' => 'default_value',
            ],
            'constraints' => [
                new GreaterThan([
                    'value' => 0,
                ]),
            ],
        ])
        ->add('serializationExclusionExpression', TextareaType::class, [
            'label' => 'nodeTypeField.serializationExclusionExpression',
            'required' => false,
            'help' => 'exclude_this_field_from_api_serialization_if_expression_result_is_true',
            'attr' => [
                'placeholder' => 'enter_symfony_expression_language_with_object_as_var_name',
            ],
        ])
        ->add('serializationGroups', CollectionType::class, [
            'label' => 'nodeTypeField.serializationGroups',
            'required' => false,
            'allow_add' => true,
            'allow_delete' => true,
            'attr' => [
                'class' => 'rz-collection-form-type',
            ],
            'entry_options' => [
                'label' => false,
            ],
            'entry_type' => TextType::class,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'nodeTypeField.serialization',
            'inherit_data' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }

    public function getParent(): ?string
    {
        return FormType::class;
    }
}
