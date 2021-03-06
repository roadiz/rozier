<?php

declare(strict_types=1);

namespace Themes\Rozier\Forms;

use RZ\Roadiz\CoreBundle\Entity\User;
use RZ\Roadiz\CoreBundle\Form\CreatePasswordType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * @package Themes\Rozier\Forms
 */
class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
                'label' => 'email',
                'constraints' => [
                    new NotNull(),
                    new Email(),
                    new NotBlank(),
                    new Length([
                        'max' => 200,
                    ])
                ],
            ])
            ->add('username', TextType::class, [
                'label' => 'username',
                'constraints' => [
                    new NotNull(),
                    new NotBlank(),
                    new Length([
                        'max' => 200
                    ])
                ],
            ])
            ->add('plainPassword', CreatePasswordType::class, [
                'invalid_message' => 'password.must.match',
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'user';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'label' => false,
            'email' => '',
            'username' => '',
            'data_class' => User::class,
            'attr' => [
                'class' => 'uk-form user-form',
            ],
            'constraints' => [
                new UniqueEntity([
                    'fields' => [
                        'email'
                    ]
                ]),
                new UniqueEntity([
                    'fields' => [
                        'username',
                    ]
                ])
            ]
        ]);
    }
}
