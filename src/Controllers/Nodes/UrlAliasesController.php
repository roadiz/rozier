<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers\Nodes;

use RZ\Roadiz\CoreBundle\Entity\Node;
use RZ\Roadiz\CoreBundle\Entity\NodesSources;
use RZ\Roadiz\CoreBundle\Entity\Redirection;
use RZ\Roadiz\CoreBundle\Entity\Translation;
use RZ\Roadiz\CoreBundle\Entity\UrlAlias;
use RZ\Roadiz\CoreBundle\Event\NodesSources\NodesSourcesUpdatedEvent;
use RZ\Roadiz\CoreBundle\Event\UrlAlias\UrlAliasCreatedEvent;
use RZ\Roadiz\CoreBundle\Event\UrlAlias\UrlAliasDeletedEvent;
use RZ\Roadiz\CoreBundle\Event\UrlAlias\UrlAliasUpdatedEvent;
use RZ\Roadiz\CoreBundle\Exception\EntityAlreadyExistsException;
use RZ\Roadiz\CoreBundle\Exception\NoTranslationAvailableException;
use RZ\Roadiz\CoreBundle\Form\UrlAliasType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Themes\Rozier\Forms\NodeSource\NodeSourceSeoType;
use Themes\Rozier\Forms\RedirectionType;
use Themes\Rozier\RozierApp;
use Twig\Error\RuntimeError;

class UrlAliasesController extends RozierApp
{
    private FormFactoryInterface $formFactory;

    /**
     * @param FormFactoryInterface $formFactory
     */
    public function __construct(FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;
    }

    /**
     * Return aliases form for requested node.
     *
     * @param Request $request
     * @param int $nodeId
     * @param int|null $translationId
     *
     * @return Response
     * @throws RuntimeError
     */
    public function editAliasesAction(Request $request, int $nodeId, ?int $translationId = null): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');

        if (null === $translationId || $translationId < 1) {
            $translation = $this->em()->getRepository(Translation::class)->findDefault();
        } else {
            $translation = $this->em()->find(Translation::class, $translationId);
        }
        /** @var NodesSources|null $source */
        $source = $this->em()
                       ->getRepository(NodesSources::class)
                       ->setDisplayingAllNodesStatuses(true)
                       ->setDisplayingNotPublishedNodes(true)
                       ->findOneBy(['translation' => $translation, 'node.id' => $nodeId]);

        if ($source !== null && null !== $node = $source->getNode()) {
            $redirections = $this->em()
                ->getRepository(Redirection::class)
                ->findBy([
                    'redirectNodeSource' => $node->getNodeSources()->toArray()
                ]);
            $uas = $this->em()
                        ->getRepository(UrlAlias::class)
                        ->findAllFromNode($node->getId());
            $availableTranslations = $this->em()
                ->getRepository(Translation::class)
                ->findAvailableTranslationsForNode($node);

            $this->assignation['node'] = $node;
            $this->assignation['source'] = $source;
            $this->assignation['aliases'] = [];
            $this->assignation['redirections'] = [];
            $this->assignation['translation'] = $translation;
            $this->assignation['available_translations'] = $availableTranslations;

            /*
             * SEO Form
             */
            $seoForm = $this->createForm(NodeSourceSeoType::class, $source);
            $seoForm->handleRequest($request);
            if ($seoForm->isSubmitted() && $seoForm->isValid()) {
                $this->em()->flush();
                $msg = $this->getTranslator()->trans('node.seo.updated');
                $this->publishConfirmMessage($request, $msg, $source);
                /*
                 * Dispatch event
                 */
                $this->dispatchEvent(new NodesSourcesUpdatedEvent($source));
                return $this->redirectToRoute(
                    'nodesEditSEOPage',
                    ['nodeId' => $node->getId(), 'translationId' => $translationId]
                );
            }

            if (null !== $response = $this->handleAddRedirection($source, $request)) {
                return $response;
            }
            /*
             * each url alias edit form
             */
            /** @var UrlAlias $alias */
            foreach ($uas as $alias) {
                if (null !== $response = $this->handleSingleUrlAlias($alias, $request)) {
                    return $response;
                }
            }

            /** @var Redirection $redirection */
            foreach ($redirections as $redirection) {
                if (null !== $response = $this->handleSingleRedirection($redirection, $request)) {
                    return $response;
                }
            }

            /*
             * Main ADD url alias form
             */
            $alias = new UrlAlias();
            $addAliasForm = $this->formFactory->createNamed(
                'add_urlalias_' . $node->getId(),
                UrlAliasType::class,
                $alias,
                [
                    'with_translation' => true
                ]
            );
            $addAliasForm->handleRequest($request);
            if ($addAliasForm->isSubmitted() && $addAliasForm->isValid()) {
                try {
                    $alias = $this->addNodeUrlAlias($alias, $node, $addAliasForm->get('translation')->getData());
                    $msg = $this->getTranslator()->trans('url_alias.%alias%.created.%translation%', [
                        '%alias%' => $alias->getAlias(),
                        '%translation%' => $alias->getNodeSource()->getTranslation()->getName(),
                    ]);
                    $this->publishConfirmMessage($request, $msg, $source);
                    /*
                     * Dispatch event
                     */
                    $this->dispatchEvent(new UrlAliasCreatedEvent($alias));

                    return $this->redirect($this->generateUrl(
                        'nodesEditSEOPage',
                        ['nodeId' => $node->getId(), 'translationId' => $translationId]
                    ) . '#manage-aliases');
                } catch (EntityAlreadyExistsException $e) {
                    $addAliasForm->addError(new FormError($e->getMessage()));
                } catch (NoTranslationAvailableException $e) {
                    $addAliasForm->addError(new FormError($e->getMessage()));
                }
            }

            $this->assignation['form'] = $addAliasForm->createView();
            $this->assignation['seoForm'] = $seoForm->createView();

            return $this->render('@RoadizRozier/nodes/editAliases.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param UrlAlias $alias
     * @param Node $node
     * @param Translation $translation
     * @return UrlAlias
     */
    private function addNodeUrlAlias(UrlAlias $alias, Node $node, Translation $translation): UrlAlias
    {
        /** @var NodesSources|null $nodeSource */
        $nodeSource = $this->em()
                           ->getRepository(NodesSources::class)
                           ->setDisplayingAllNodesStatuses(true)
                           ->setDisplayingNotPublishedNodes(true)
                           ->findOneBy(['node' => $node, 'translation' => $translation]);

        if ($nodeSource !== null) {
            $alias->setNodeSource($nodeSource);
            $this->em()->persist($alias);
            $this->em()->flush();

            return $alias;
        } else {
            $msg = $this->getTranslator()->trans('url_alias.no_translation.%translation%', [
                '%translation%' => $translation->getName()
            ]);
            throw new NoTranslationAvailableException($msg);
        }
    }

    /**
     * @param UrlAlias $alias
     * @param Request  $request
     *
     * @return RedirectResponse|null
     */
    private function handleSingleUrlAlias(UrlAlias $alias, Request $request): ?RedirectResponse
    {
        $editForm = $this->formFactory->createNamed(
            'edit_urlalias_' . $alias->getId(),
            UrlAliasType::class,
            $alias
        );
        $deleteForm = $this->formFactory->createNamed('delete_urlalias_' . $alias->getId());
        // Match edit
        $editForm->handleRequest($request);
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                try {
                    $this->em()->flush();
                    $msg = $this->getTranslator()->trans(
                        'url_alias.%alias%.updated',
                        ['%alias%' => $alias->getAlias()]
                    );
                    $this->publishConfirmMessage($request, $msg, $alias->getNodeSource());
                    /*
                     * Dispatch event
                     */
                    $this->dispatchEvent(new UrlAliasUpdatedEvent($alias));
                    /** @var Translation $translation */
                    $translation = $alias->getNodeSource()->getTranslation();

                    return $this->redirect($this->generateUrl(
                        'nodesEditSEOPage',
                        [
                            'nodeId' => $alias->getNodeSource()->getNode()->getId(),
                            'translationId' => $translation->getId()
                        ]
                    ) . '#manage-aliases');
                } catch (\RuntimeException $exception) {
                    $editForm->addError(new FormError($exception->getMessage()));
                }
            } catch (EntityAlreadyExistsException $e) {
                $editForm->addError(new FormError($e->getMessage()));
            }
        }

        // Match delete
        $deleteForm->handleRequest($request);
        if ($deleteForm->isSubmitted() && $deleteForm->isValid()) {
            $this->em()->remove($alias);
            $this->em()->flush();
            $msg = $this->getTranslator()->trans('url_alias.%alias%.deleted', ['%alias%' => $alias->getAlias()]);
            $this->publishConfirmMessage($request, $msg, $alias->getNodeSource());

            /*
             * Dispatch event
             */
            $this->dispatchEvent(new UrlAliasDeletedEvent($alias));

            /** @var Translation $translation */
            $translation = $alias->getNodeSource()->getTranslation();

            return $this->redirect($this->generateUrl(
                'nodesEditSEOPage',
                [
                    'nodeId' => $alias->getNodeSource()->getNode()->getId(),
                    'translationId' => $translation->getId()
                ]
            ) . '#manage-aliases');
        }

        $this->assignation['aliases'][] = [
            'alias' => $alias,
            'editForm' => $editForm->createView(),
            'deleteForm' => $deleteForm->createView(),
        ];

        return null;
    }

    /**
     * @param NodesSources $source
     * @param Request $request
     * @return RedirectResponse|null
     */
    private function handleAddRedirection(NodesSources $source, Request $request): ?RedirectResponse
    {
        $redirection = new Redirection();
        $redirection->setRedirectNodeSource($source);
        $redirection->setType(Response::HTTP_MOVED_PERMANENTLY);

        $addForm = $this->formFactory->createNamed(
            'add_redirection',
            RedirectionType::class,
            $redirection,
            [
                'placeholder' => $this->generateUrl($source),
                'only_query' => true
            ]
        );

        $addForm->handleRequest($request);
        if ($addForm->isSubmitted() && $addForm->isValid()) {
            $this->em()->persist($redirection);
            $this->em()->flush();

            /** @var Translation $translation */
            $translation = $redirection->getRedirectNodeSource()->getTranslation();

            return $this->redirect($this->generateUrl(
                'nodesEditSEOPage',
                [
                    'nodeId' => $redirection->getRedirectNodeSource()->getNode()->getId(),
                    'translationId' => $translation->getId()
                ]
            ) . '#manage-redirections');
        }

        $this->assignation['addRedirection'] = $addForm->createView();

        return null;
    }

    /**
     * @param Redirection $redirection
     * @param Request $request
     * @return RedirectResponse|null
     */
    private function handleSingleRedirection(Redirection $redirection, Request $request): ?RedirectResponse
    {
        $editForm = $this->formFactory->createNamed(
            'edit_redirection_' . $redirection->getId(),
            RedirectionType::class,
            $redirection,
            [
                'only_query' => true
            ]
        );

        /** @var Translation $translation */
        $translation = $redirection->getRedirectNodeSource()->getTranslation();

        $deleteForm = $this->formFactory->createNamed('delete_redirection_' . $redirection->getId());

        $editForm->handleRequest($request);
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->em()->flush();
            return $this->redirect($this->generateUrl(
                'nodesEditSEOPage',
                [
                    'nodeId' => $redirection->getRedirectNodeSource()->getNode()->getId(),
                    'translationId' => $translation->getId()
                ]
            ) . '#manage-redirections');
        }

        // Match delete
        $deleteForm->handleRequest($request);
        if ($deleteForm->isSubmitted() && $deleteForm->isValid()) {
            $this->em()->remove($redirection);
            $this->em()->flush();
            return $this->redirect($this->generateUrl(
                'nodesEditSEOPage',
                [
                    'nodeId' => $redirection->getRedirectNodeSource()->getNode()->getId(),
                    'translationId' => $translation->getId()
                ]
            ) . '#manage-redirections');
        }
        $this->assignation['redirections'][] = [
            'redirection' => $redirection,
            'editForm' => $editForm->createView(),
            'deleteForm' => $deleteForm->createView(),
        ];

        return null;
    }
}
