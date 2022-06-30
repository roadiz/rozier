<?php

declare(strict_types=1);

namespace Themes\Rozier\Forms;

use RZ\Roadiz\CMS\Forms\Constraints\UniqueEntity;
use RZ\Roadiz\CoreBundle\Entity\Role;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;

class RoleType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'name',
            'constraints' => [
                new NotNull(),
                new NotBlank(),
                new Regex([
                    'pattern' => '#^ROLE_([A-Z0-9\_]+)$#',
                    'message' => 'role.name.must_comply_with_standard',
                ]),
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('data_class', Role::class);
        $resolver->setDefault('constraints', [
            new UniqueEntity([
                'fields' => [
                    'name'
                ]
            ])
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'role';
    }
}
