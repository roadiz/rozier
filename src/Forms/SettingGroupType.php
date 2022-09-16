<?php

declare(strict_types=1);

namespace Themes\Rozier\Forms;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class SettingGroupType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'name',
            TextType::class,
            [
                'label' => 'name',
                'empty_data' => '',
                'constraints' => [
                    new NotNull(),
                    new NotBlank()
                ],
            ]
        )
        ->add(
            'inMenu',
            CheckboxType::class,
            [
                'label' => 'settingGroup.in.menu',
                'required' => false,
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('constraints', [
            new UniqueEntity([
                'fields' => [
                    'name'
                ]
            ])
        ]);
    }
}
