<?php

declare(strict_types=1);

namespace Themes\Rozier\Forms;

use RZ\Roadiz\CMS\Forms\ColorType;
use RZ\Roadiz\CMS\Forms\Constraints\UniqueEntity;
use RZ\Roadiz\CMS\Forms\MarkdownType;
use RZ\Roadiz\CoreBundle\Entity\CustomForm;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CustomFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('displayName', TextType::class, [
                'label' => 'customForm.displayName',
                'constraints' => [
                    new NotNull(),
                    new NotBlank(),
                    new Length([
                        'max' => 255,
                    ]),
                ],
            ])
            ->add('description', MarkdownType::class, [
                'label' => 'description',
                'required' => false,
            ])
            ->add('email', TextType::class, [
                'label' => 'email',
                'required' => false,
                'constraints' => [
                    new Callback(function ($value, ExecutionContextInterface $context) {
                        $emails = array_filter(
                            array_map('trim', explode(',', $value ?? ''))
                        );
                        foreach ($emails as $email) {
                            if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $context->buildViolation('{{ value }} is not a valid email address.')
                                    ->setParameter('{{ value }}', $email)
                                    ->setCode(Email::INVALID_FORMAT_ERROR)
                                    ->addViolation();
                            }
                        }
                    }),
                ],
            ])
            ->add('open', CheckboxType::class, [
                'label' => 'customForm.open',
                'required' => false,
            ])
            ->add('closeDate', DateTimeType::class, [
                'label' => 'customForm.closeDate',
                'required' => false,
                'date_widget' => 'single_text',
                'date_format' => 'yyyy-MM-dd',
                'attr' => [
                    'class' => 'rz-datetime-field',
                ],
                'placeholder' => [
                    'hour' => 'hour',
                    'minute' => 'minute',
                ],
            ])
            ->add('color', ColorType::class, [
                'label' => 'customForm.color',
                'required' => false,
            ]);
    }

    public function getBlockPrefix(): string
    {
        return 'customform';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
            'name' => '',
            'data_class' => CustomForm::class,
            'attr' => [
                'class' => 'uk-form custom-form-form',
            ],
            'constraints' => [
                new UniqueEntity([
                    'fields' => ['name']
                ])
            ]
        ]);
        $resolver->setAllowedTypes('name', 'string');
    }
}
