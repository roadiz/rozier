<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use Exception;
use RZ\Roadiz\CoreBundle\Entity\NodeType;
use RZ\Roadiz\CoreBundle\Entity\NodeTypeField;
use RZ\Roadiz\CoreBundle\Message\UpdateNodeTypeSchemaMessage;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Themes\Rozier\Forms\NodeTypeFieldType;
use Themes\Rozier\RozierApp;
use Twig\Error\RuntimeError;

class NodeTypeFieldsController extends RozierApp
{
    private MessageBusInterface $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    /**
     * @param Request $request
     * @param int $nodeTypeId
     *
     * @return Response
     * @throws RuntimeError
     */
    public function listAction(Request $request, int $nodeTypeId): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');

        /** @var NodeType|null $nodeType */
        $nodeType = $this->em()->find(NodeType::class, $nodeTypeId);

        if ($nodeType === null) {
            throw new ResourceNotFoundException();
        }

        $fields = $nodeType->getFields();

        $this->assignation['nodeType'] = $nodeType;
        $this->assignation['fields'] = $fields;

        return $this->render('@RoadizRozier/node-type-fields/list.html.twig', $this->assignation);
    }

    /**
     * @param Request $request
     * @param int $nodeTypeFieldId
     *
     * @return Response
     * @throws RuntimeError
     */
    public function editAction(Request $request, int $nodeTypeFieldId): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');

        /** @var NodeTypeField|null $field */
        $field = $this->em()->find(NodeTypeField::class, $nodeTypeFieldId);

        if ($field === null) {
            throw new ResourceNotFoundException();
        }

        $this->assignation['nodeType'] = $field->getNodeType();
        $this->assignation['field'] = $field;

        $form = $this->createForm(NodeTypeFieldType::class, $field);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em()->flush();

            /** @var NodeType $nodeType */
            $nodeType = $field->getNodeType();
            $this->messageBus->dispatch(new Envelope(new UpdateNodeTypeSchemaMessage($nodeType->getId())));

            $msg = $this->getTranslator()->trans('nodeTypeField.%name%.updated', ['%name%' => $field->getName()]);
            $this->publishConfirmMessage($request, $msg);

            return $this->redirectToRoute(
                'nodeTypeFieldsEditPage',
                [
                    'nodeTypeFieldId' => $nodeTypeFieldId,
                ]
            );
        }

        $this->assignation['form'] = $form->createView();

        return $this->render('@RoadizRozier/node-type-fields/edit.html.twig', $this->assignation);
    }

    /**
     * @param Request $request
     * @param int $nodeTypeId
     *
     * @return Response
     * @throws RuntimeError
     */
    public function addAction(Request $request, int $nodeTypeId): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');

        $field = new NodeTypeField();
        /** @var NodeType|null $nodeType */
        $nodeType = $this->em()->find(NodeType::class, $nodeTypeId);

        if ($nodeType === null) {
            throw new ResourceNotFoundException();
        }

        $latestPosition = $this->em()
                               ->getRepository(NodeTypeField::class)
                               ->findLatestPositionInNodeType($nodeType);
        $field->setNodeType($nodeType);
        $field->setPosition($latestPosition + 1);
        $field->setType(NodeTypeField::STRING_T);

        $this->assignation['nodeType'] = $nodeType;
        $this->assignation['field'] = $field;

        $form = $this->createForm(NodeTypeFieldType::class, $field);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->em()->persist($field);
                $this->em()->flush();
                $this->em()->refresh($nodeType);

                $this->messageBus->dispatch(new Envelope(new UpdateNodeTypeSchemaMessage($nodeType->getId())));

                $msg = $this->getTranslator()->trans(
                    'nodeTypeField.%name%.created',
                    ['%name%' => $field->getName()]
                );
                $this->publishConfirmMessage($request, $msg);

                return $this->redirectToRoute(
                    'nodeTypeFieldsListPage',
                    [
                        'nodeTypeId' => $nodeTypeId,
                    ]
                );
            } catch (Exception $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        $this->assignation['form'] = $form->createView();

        return $this->render('@RoadizRozier/node-type-fields/add.html.twig', $this->assignation);
    }

    /**
     * @param Request $request
     * @param int $nodeTypeFieldId
     *
     * @return Response
     * @throws RuntimeError
     */
    public function deleteAction(Request $request, int $nodeTypeFieldId): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODEFIELDS_DELETE');

        /** @var NodeTypeField|null $field */
        $field = $this->em()->find(NodeTypeField::class, $nodeTypeFieldId);

        if ($field === null) {
            throw new ResourceNotFoundException();
        }

        $form = $this->createForm(FormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var NodeType $nodeType */
            $nodeType = $field->getNodeType();
            $nodeTypeId = $nodeType->getId();
            $this->em()->remove($field);
            $this->em()->flush();

            $this->messageBus->dispatch(new Envelope(new UpdateNodeTypeSchemaMessage($nodeTypeId)));

            $msg = $this->getTranslator()->trans(
                'nodeTypeField.%name%.deleted',
                ['%name%' => $field->getName()]
            );
            $this->publishConfirmMessage($request, $msg);

            return $this->redirectToRoute(
                'nodeTypeFieldsListPage',
                [
                    'nodeTypeId' => $nodeTypeId,
                ]
            );
        }

        $this->assignation['field'] = $field;
        $this->assignation['form'] = $form->createView();

        return $this->render('@RoadizRozier/node-type-fields/delete.html.twig', $this->assignation);
    }
}
