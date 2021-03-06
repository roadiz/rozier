<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use RZ\Roadiz\CoreBundle\Entity\NodeType;
use RZ\Roadiz\CoreBundle\Entity\NodeTypeField;
use RZ\Roadiz\Core\Handlers\HandlerFactoryInterface;
use RZ\Roadiz\CoreBundle\EntityHandler\NodeTypeHandler;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Themes\Rozier\Forms\NodeTypeFieldType;
use Themes\Rozier\RozierApp;

/**
 * @package Themes\Rozier\Controllers
 */
class NodeTypeFieldsController extends RozierApp
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
     * @param Request $request
     * @param int $nodeTypeId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction(Request $request, int $nodeTypeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');

        /** @var NodeType|null $nodeType */
        $nodeType = $this->em()->find(NodeType::class, $nodeTypeId);

        if ($nodeType !== null) {
            $fields = $nodeType->getFields();

            $this->assignation['nodeType'] = $nodeType;
            $this->assignation['fields'] = $fields;

            return $this->render('@RoadizRozier/node-type-fields/list.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param Request $request
     * @param int     $nodeTypeFieldId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, int $nodeTypeFieldId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');

        /** @var NodeTypeField|null $field */
        $field = $this->em()->find(NodeTypeField::class, $nodeTypeFieldId);

        if ($field !== null) {
            $this->assignation['nodeType'] = $field->getNodeType();
            $this->assignation['field'] = $field;

            $form = $this->createForm(NodeTypeFieldType::class, $field);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->em()->flush();

                /** @var NodeType $nodeType */
                $nodeType = $field->getNodeType();
                /** @var NodeTypeHandler $handler */
                $handler = $this->handlerFactory->getHandler($nodeType);
                $handler->updateSchema();

                $msg = $this->getTranslator()->trans('nodeTypeField.%name%.updated', ['%name%' => $field->getName()]);
                $this->publishConfirmMessage($request, $msg);

                /*
                 * Redirect to update schema page
                 */
                return $this->redirectToRoute(
                    'nodeTypesFieldSchemaUpdate',
                    [
                        'nodeTypeId' => $nodeType->getId(),
                    ]
                );
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('@RoadizRozier/node-type-fields/edit.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param Request $request
     * @param int     $nodeTypeId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addAction(Request $request, int $nodeTypeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');

        $field = new NodeTypeField();
        /** @var NodeType|null $nodeType */
        $nodeType = $this->em()->find(NodeType::class, $nodeTypeId);

        if ($nodeType !== null) {
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

                    /** @var NodeTypeHandler $handler */
                    $handler = $this->handlerFactory->getHandler($nodeType);
                    $handler->updateSchema();

                    $msg = $this->getTranslator()->trans(
                        'nodeTypeField.%name%.created',
                        ['%name%' => $field->getName()]
                    );
                    $this->publishConfirmMessage($request, $msg);

                    /*
                     * Redirect to update schema page
                     */
                    return $this->redirectToRoute(
                        'nodeTypesFieldSchemaUpdate',
                        [
                            'nodeTypeId' => $nodeTypeId,
                        ]
                    );
                } catch (\Exception $e) {
                    $msg = $e->getMessage();
                    $this->publishErrorMessage($request, $msg);
                    /*
                     * Redirect to add page
                     */
                    return $this->redirectToRoute(
                        'nodeTypeFieldsAddPage',
                        ['nodeTypeId' => $nodeTypeId]
                    );
                }
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('@RoadizRozier/node-type-fields/add.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param Request $request
     * @param int $nodeTypeFieldId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Twig\Error\RuntimeError
     */
    public function deleteAction(Request $request, int $nodeTypeFieldId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODEFIELDS_DELETE');

        /** @var NodeTypeField|null $field */
        $field = $this->em()->find(NodeTypeField::class, $nodeTypeFieldId);

        if ($field !== null) {
            $form = $this->createForm(FormType::class);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                /** @var NodeType $nodeType */
                $nodeType = $field->getNodeType();
                $nodeTypeId = $nodeType->getId();
                $this->em()->remove($field);
                $this->em()->flush();

                /*
                 * Update Database
                 */
                /** @var NodeType|null $nodeType */
                $nodeType = $this->em()->find(NodeType::class, (int) $nodeTypeId);

                /** @var NodeTypeHandler $handler */
                $handler = $this->handlerFactory->getHandler($nodeType);
                $handler->updateSchema();

                $msg = $this->getTranslator()->trans(
                    'nodeTypeField.%name%.deleted',
                    ['%name%' => $field->getName()]
                );
                $this->publishConfirmMessage($request, $msg);

                /*
                 * Redirect to update schema page
                 */
                return $this->redirectToRoute(
                    'nodeTypesFieldSchemaUpdate',
                    [
                        'nodeTypeId' => $nodeTypeId,
                    ]
                );
            }

            $this->assignation['field'] = $field;
            $this->assignation['form'] = $form->createView();

            return $this->render('@RoadizRozier/node-type-fields/delete.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param NodeTypeField $field
     *
     * @return FormInterface
     */
    private function buildDeleteForm(NodeTypeField $field)
    {
        $builder = $this->createFormBuilder()
                        ->add('nodeTypeFieldId', HiddenType::class, [
                            'data' => $field->getId(),
                            'constraints' => [
                                new NotNull(),
                                new NotBlank(),
                            ],
                        ]);

        return $builder->getForm();
    }
}
