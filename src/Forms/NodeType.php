<?php

declare(strict_types=1);

namespace Themes\Rozier\Forms;

use RZ\Roadiz\CoreBundle\Entity\Node;
use RZ\Roadiz\CoreBundle\Form\Constraint\UniqueNodeName;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class NodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('nodeName', TextType::class, [
                'label' => 'nodeName',
                'empty_data' => '',
                'help' => 'node.nodeName.help',
                'constraints' => [
                    new NotNull(),
                    new UniqueNodeName([
                        'currentValue' => $options['nodeName'],
                    ]),
                    new Length([
                        'max' => 255,
                    ])
                ],
            ])
            ->add('dynamicNodeName', CheckboxType::class, [
                'label' => 'node.dynamicNodeName',
                'required' => false,
                'help' => 'dynamic_node_name_will_follow_any_title_change_on_default_translation',
            ])
        ;

        if (null !== $builder->getData() && $builder->getData()->getNodeType()->isReachable()) {
            $builder->add('home', CheckboxType::class, [
                'label' => 'node.isHome',
                'required' => false,
                'attr' => ['class' => 'rz-boolean-checkbox'],
            ]);
        }

        $builder->add('childrenOrder', ChoiceType::class, [
                'label' => 'node.childrenOrder',
                'choices' => Node::$orderingFields,
            ])
            ->add('childrenOrderDirection', ChoiceType::class, [
                'label' => 'node.childrenOrderDirection',
                'choices' => [
                    'ascendant' => 'ASC',
                    'descendant' => 'DESC',
                ],
            ])
        ;

        if (null !== $builder->getData() && $builder->getData()->getNodeType()->isReachable()) {
            $builder->add('ttl', IntegerType::class, [
                'label' => 'node.ttl',
                'help' => 'node_time_to_live_cache_on_front_controller',
                'constraints' => [
                    new GreaterThanOrEqual([
                        'value' => 0
                    ])
                ]
            ]);
        }
    }

    public function getBlockPrefix(): string
    {
        return 'node';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'label' => false,
            'nodeName' => null,
            'data_class' => Node::class,
            'attr' => [
                'class' => 'uk-form node-form',
            ],
        ]);

        $resolver->setAllowedTypes('nodeName', 'string');
    }
}
