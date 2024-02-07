<?php

declare(strict_types=1);

namespace Themes\Rozier\Forms\NodeSource;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use RZ\Roadiz\CoreBundle\Entity\NodeTypeField;
use RZ\Roadiz\CoreBundle\Explorer\AbstractExplorerItem;
use RZ\Roadiz\CoreBundle\Explorer\AbstractExplorerProvider;
use RZ\Roadiz\CoreBundle\Explorer\ExplorerProviderInterface;
use RZ\Roadiz\CoreBundle\Form\DataTransformer\ProviderDataTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class NodeSourceProviderType extends AbstractConfigurableNodeSourceFieldType
{
    protected ContainerInterface $container;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param ContainerInterface $container
     */
    public function __construct(ManagerRegistry $managerRegistry, ContainerInterface $container)
    {
        parent::__construct($managerRegistry);
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('multiple', true);
        $resolver->setDefault('asMultiple', false);
        $resolver->setAllowedTypes('multiple', ['bool']);
        $resolver->setAllowedTypes('asMultiple', ['bool']);
        $resolver->setNormalizer('asMultiple', function (Options $options) {
            /** @var NodeTypeField $nodeTypeField */
            $nodeTypeField = $options['nodeTypeField'];
            if ($nodeTypeField->isMultipleProvider()) {
                return true;
            }
            return false;
        });
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $configuration = $this->getFieldConfiguration($options);

        $builder->addModelTransformer(
            new ProviderDataTransformer(
                $options['nodeTypeField'],
                $this->getProvider($configuration, $options)
            )
        );
    }

    protected function getProvider(array $configuration, array $options): ExplorerProviderInterface
    {
        if ($this->container->has($configuration['classname'])) {
            $provider = $this->container->get($configuration['classname']);
        } else {
            /** @var ExplorerProviderInterface $provider */
            $provider = new $configuration['classname']();
        }

        if ($provider instanceof AbstractExplorerProvider) {
            $provider->setContainer($this->container);
        }

        return $provider;
    }

    /**
     * Pass data to form twig template.
     *
     * @param FormView $view
     * @param FormInterface $form
     * @param array $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $configuration = $this->getFieldConfiguration($options);
        if (isset($configuration['options'])) {
            $providerOptions = $configuration['options'];
        } else {
            $providerOptions = [];
        }

        $provider = $this->getProvider($configuration, $options);

        $displayableData = [];
        /** @var callable $callable */
        $callable = [$options['nodeSource'], $options['nodeTypeField']->getGetterName()];
        $ids = call_user_func($callable);
        if (!is_array($ids)) {
            $entities = $provider->getItemsById([$ids]);
        } else {
            $entities = $provider->getItemsById($ids);
        }

        /** @var AbstractExplorerItem $entity */
        foreach ($entities as $entity) {
            $displayableData[] = $entity->toArray();
        }

        $view->vars['data'] = $displayableData;

        if (isset($options['max_length']) && $options['max_length'] > 0) {
            $view->vars['attr']['data-max-length'] = $options['max_length'];
        }
        if (isset($options['min_length']) && $options['min_length'] > 0) {
            $view->vars['attr']['data-min-length'] = $options['min_length'];
        }

        $view->vars['provider_class'] = $configuration['classname'];

        if (is_array($providerOptions) && count($providerOptions) > 0) {
            $view->vars['provider_options'] = [];
            foreach ($providerOptions as $providerOption) {
                $view->vars['provider_options'][$providerOption['name']] = $providerOption['value'];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'provider';
    }
}
