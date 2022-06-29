<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use Doctrine\ORM\Mapping\MappingException;
use Exception;
use RZ\Roadiz\Utils\Doctrine\SchemaUpdater;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Themes\Rozier\RozierApp;

/**
 * Redirection controller use to update database schema.
 * THIS CONTROLLER MUST NOT PREPARE ANY DATA.
 */
class SchemaController extends RozierApp
{
    private SchemaUpdater $schemaUpdater;

    /**
     * @param SchemaUpdater $schemaUpdater
     */
    public function __construct(SchemaUpdater $schemaUpdater)
    {
        $this->schemaUpdater = $schemaUpdater;
    }

    /**
     * No preparation for this blind controller.
     *
     * @return $this
     */
    public function prepareBaseAssignation()
    {
        return $this;
    }

    /**
     * @param Request $request
     *
     * @return Response
     * @throws Exception
     */
    public function updateNodeTypesSchemaAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');
        $this->schemaUpdater->clearMetadata();
        $this->schemaUpdater->updateNodeTypesSchema();

        return $this->redirectToRoute(
            'nodeTypesHomePage'
        );
    }

    /**
     * @param Request $request
     * @param int $nodeTypeId
     *
     * @return Response
     * @throws Exception
     */
    public function updateNodeTypeFieldsSchemaAction(Request $request, int $nodeTypeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODETYPES');
        $this->schemaUpdater->clearMetadata();
        $this->schemaUpdater->updateNodeTypesSchema();

        return $this->redirectToRoute(
            'nodeTypeFieldsListPage',
            [
                'nodeTypeId' => $nodeTypeId,
            ]
        );
    }

    /**
     * @return Response
     * @throws Exception
     */
    public function updateThemeSchemaAction()
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_THEMES');

        try {
            $this->schemaUpdater->clearMetadata();
            $this->schemaUpdater->updateNodeTypesSchema();
            return new JsonResponse(['status' => true], JsonResponse::HTTP_PARTIAL_CONTENT);
        } catch (MappingException $e) {
            return new JsonResponse([
                'status' => false,
                'error' => $e->getMessage(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
