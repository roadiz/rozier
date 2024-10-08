<?php

declare(strict_types=1);

namespace Themes\Rozier\AjaxControllers;

use RZ\Roadiz\Core\AbstractEntities\AbstractField;
use RZ\Roadiz\Core\Handlers\HandlerFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @package Themes\Rozier\AjaxControllers
 */
abstract class AjaxAbstractFieldsController extends AbstractAjaxController
{
    private HandlerFactoryInterface $handlerFactory;

    /**
     * @param HandlerFactoryInterface $handlerFactory
     */
    public function __construct(HandlerFactoryInterface $handlerFactory)
    {
        $this->handlerFactory = $handlerFactory;
    }

    protected function findEntity(int|string $entityId): ?AbstractField
    {
        return $this->em()->find($this->getEntityClass(), (int) $entityId);
    }

    /**
     * Handle actions for any abstract fields.
     *
     * @param Request       $request
     * @param AbstractField|null $field
     *
     * @return null|Response
     */
    protected function handleFieldActions(Request $request, AbstractField $field = null)
    {
        /*
         * Validate
         */
        $this->validateRequest($request);

        if ($field !== null) {
            $responseArray = null;

            /*
             * Get the right update method against "_action" parameter
             */
            switch ($request->get('_action')) {
                case 'updatePosition':
                    $responseArray = $this->updatePosition($request->request->all(), $field);
                    break;
            }

            if ($responseArray === null) {
                $responseArray = [
                    'statusCode' => '200',
                    'status' => 'success',
                    'responseText' => $this->getTranslator()->trans('field.%name%.updated', [
                        '%name%' => $field->getName(),
                    ]),
                ];
            }

            return new JsonResponse(
                $responseArray,
                Response::HTTP_PARTIAL_CONTENT
            );
        }

        return null;
    }

    /**
     * @param array $parameters
     * @param AbstractField|null $field
     *
     * @return array
     */
    protected function updatePosition(array $parameters, AbstractField $field = null): array
    {
        if (!empty($parameters['afterFieldId']) && is_numeric($parameters['afterFieldId'])) {
            $afterField = $this->findEntity((int) $parameters['afterFieldId']);
            if (null === $afterField) {
                throw new BadRequestHttpException('afterFieldId does not exist');
            }
            $field->setPosition($afterField->getPosition() + 0.5);
            // Apply position update before cleaning
            $this->em()->flush();
            $handler = $this->handlerFactory->getHandler($field);
            $handler->cleanPositions();
            $this->em()->flush();
            return [
                'statusCode' => '200',
                'status' => 'success',
                'responseText' => $this->getTranslator()->trans('field.%name%.updated', [
                    '%name%' => $field->getName(),
                ]),
            ];
        }
        if (!empty($parameters['beforeFieldId']) && is_numeric($parameters['beforeFieldId'])) {
            $beforeField = $this->findEntity((int) $parameters['beforeFieldId']);
            if (null === $beforeField) {
                throw new BadRequestHttpException('beforeFieldId does not exist');
            }
            $field->setPosition($beforeField->getPosition() - 0.5);
            // Apply position update before cleaning
            $this->em()->flush();
            $handler = $this->handlerFactory->getHandler($field);
            $handler->cleanPositions();
            $this->em()->flush();
            return [
                'statusCode' => '200',
                'status' => 'success',
                'responseText' => $this->getTranslator()->trans('field.%name%.updated', [
                    '%name%' => $field->getName(),
                ]),
            ];
        }

        throw new BadRequestHttpException('Cannot update position for Field. Missing parameters.');
    }

    /**
     * @return class-string<AbstractField>
     */
    abstract protected function getEntityClass(): string;
}
