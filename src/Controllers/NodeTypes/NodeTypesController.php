<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers\NodeTypes;

use RZ\Roadiz\Core\Entities\NodeType;
use RZ\Roadiz\Core\Exceptions\EntityAlreadyExistsException;
use RZ\Roadiz\Core\Handlers\HandlerFactoryInterface;
use RZ\Roadiz\Core\Handlers\NodeTypeHandler;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Themes\Rozier\Forms\NodeTypeType;
use Themes\Rozier\RozierApp;
use Themes\Rozier\Utils\SessionListFilters;

/**
 * @package Themes\Rozier\Controllers\NodeTypes
 */
class NodeTypesController extends RozierApp
{
    private HandlerFactoryInterface $handlerFactory;

    /**
     * @param HandlerFactoryInterface $handlerFactory
     */
    public function __construct(HandlerFactoryInterface $handlerFactory)
    {
        $this->handlerFactory = $handlerFactory;
    }

    /**
     * List every node-types.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');
        /*
         * Manage get request to filter list
         */
        $listManager = $this->createEntityListManager(
            NodeType::class,
            [],
            ['name' => 'ASC']
        );
        $listManager->setDisplayingNotPublishedNodes(true);

        /*
         * Stored in session
         */
        $sessionListFilter = new SessionListFilters('node_types_item_per_page');
        $sessionListFilter->handleItemPerPage($request, $listManager);

        $listManager->handle();

        $this->assignation['filters'] = $listManager->getAssignation();
        $this->assignation['node_types'] = $listManager->getEntities();

        return $this->render('@RoadizRozier/node-types/list.html.twig', $this->assignation);
    }

    /**
     * Return an edition form for requested node-type.
     *
     * @param Request $request
     * @param int     $nodeTypeId
     *
     * @return Response
     */
    public function editAction(Request $request, int $nodeTypeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');

        /** @var NodeType|null $nodeType */
        $nodeType = $this->em()->find(NodeType::class, $nodeTypeId);

        if (!($nodeType instanceof NodeType)) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(NodeTypeType::class, $nodeType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->em()->flush();
                /** @var NodeTypeHandler $handler */
                $handler = $this->handlerFactory->getHandler($nodeType);
                $handler->updateSchema();

                $msg = $this->getTranslator()->trans('nodeType.%name%.updated', ['%name%' => $nodeType->getName()]);
                $this->publishConfirmMessage($request, $msg);
                /*
                 * Redirect to update schema page
                 */
                return $this->redirectToRoute('nodeTypesSchemaUpdate');
            } catch (EntityAlreadyExistsException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        $this->assignation['form'] = $form->createView();
        $this->assignation['nodeType'] = $nodeType;

        return $this->render('@RoadizRozier/node-types/edit.html.twig', $this->assignation);
    }

    /**
     * Return an creation form for requested node-type.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function addAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');

        $nodeType = new NodeType();

        $form = $this->createForm(NodeTypeType::class, $nodeType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->em()->persist($nodeType);
                $this->em()->flush();
                /** @var NodeTypeHandler $handler */
                $handler = $this->handlerFactory->getHandler($nodeType);
                $handler->updateSchema();

                $msg = $this->getTranslator()->trans('nodeType.%name%.created', ['%name%' => $nodeType->getName()]);
                $this->publishConfirmMessage($request, $msg);

                /*
                 * Redirect to update schema page
                 */
                return $this->redirectToRoute('nodeTypesSchemaUpdate');
            } catch (EntityAlreadyExistsException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        $this->assignation['form'] = $form->createView();
        $this->assignation['nodeType'] = $nodeType;

        return $this->render('@RoadizRozier/node-types/add.html.twig', $this->assignation);
    }

    /**
     * @param Request $request
     * @param int     $nodeTypeId
     *
     * @return Response
     */
    public function deleteAction(Request $request, int $nodeTypeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES_DELETE');

        /** @var NodeType $nodeType */
        $nodeType = $this->em()->find(NodeType::class, $nodeTypeId);

        if (!($nodeType instanceof NodeType)) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(FormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /*
             * Delete All node-type association and schema
             */
            /** @var NodeTypeHandler $handler */
            $handler = $this->handlerFactory->getHandler($nodeType);
            $handler->deleteWithAssociations();

            $msg = $this->getTranslator()->trans('nodeType.%name%.deleted', ['%name%' => $nodeType->getName()]);
            $this->publishConfirmMessage($request, $msg);
            /*
             * Redirect to update schema page
             */
            return $this->redirectToRoute('nodeTypesSchemaUpdate');
        }

        $this->assignation['form'] = $form->createView();
        $this->assignation['nodeType'] = $nodeType;

        return $this->render('@RoadizRozier/node-types/delete.html.twig', $this->assignation);
    }
}
