<?php

declare(strict_types=1);

namespace Themes\Rozier\Forms;

use RZ\Roadiz\CoreBundle\Entity\NodeType;
use RZ\Roadiz\CoreBundle\Form\ColorType;
use RZ\Roadiz\CoreBundle\Form\Constraint\NonSqlReservedWord;
use RZ\Roadiz\CoreBundle\Form\Constraint\SimpleLatinString;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class NodeTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (empty($options['name'])) {
            $builder->add('name', TextType::class, [
                'label' => 'name',
                'constraints' => [
                    new NotNull(),
                    new NotBlank(),
                    new NonSqlReservedWord(),
                    new SimpleLatinString(),
                ],
            ]);
        }
        $builder
            ->add('displayName', TextType::class, [
                'label' => 'nodeType.displayName',
                'constraints' => [
                    new NotNull(),
                    new NotBlank(),
                ],
            ])
            ->add('description', TextType::class, [
                'label' => 'description',
                'required' => false,
            ])
            ->add('visible', CheckboxType::class, [
                'label' => 'visible',
                'required' => false,
                'help' => 'this_node_type_will_be_available_for_creating_root_nodes',
            ])
            ->add('publishable', CheckboxType::class, [
                'label' => 'publishable',
                'required' => false,
                'help' => 'enables_published_at_field_for_time_based_publication',
            ])
            ->add('reachable', CheckboxType::class, [
                'label' => 'reachable',
                'required' => false,
                'help' => 'mark_this_typed_nodes_as_reachable_with_an_url',
            ])
            ->add('searchable', CheckboxType::class, [
                'label' => 'nodeType.searchable',
                'required' => false,
                'help' => 'allow_this_types_nodes_title_to_be_indexed_into_search_engine',
            ])
            ->add('hidingNodes', CheckboxType::class, [
                'label' => 'nodeType.hidingNodes',
                'required' => false,
                'help' => 'this_node_type_will_hide_all_children_nodes',
            ])
            ->add('hidingNonReachableNodes', CheckboxType::class, [
                'label' => 'nodeType.hidingNonReachableNodes',
                'required' => false,
                'help' => 'nodeType.hidingNonReachableNodes.help',
            ])
            ->add('color', ColorType::class, [
                'label' => 'nodeType.color',
                'required' => false,
            ])
            ->add('defaultTtl', IntegerType::class, [
                'label' => 'nodeType.defaultTtl',
                'required' => false,
                'help' => 'nodeType_default_ttl_when_creating_nodes',
                'constraints' => [
                    new GreaterThanOrEqual([
                        'value' => 0
                    ]),
                ],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'nodetypefield';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
            'name' => '',
            'data_class' => NodeType::class,
            'attr' => [
                'class' => 'uk-form node-type-form',
            ],
            'constraints' => [
                new UniqueEntity([
                    'fields' => ['name']
                ]),
                new UniqueEntity([
                    'fields' => ['displayName']
                ])
            ]
        ]);
    }
}
