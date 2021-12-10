<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use Doctrine\ORM\Mapping\MappingException;
use Exception;
use RZ\Roadiz\Console\RoadizApplication;
use RZ\Roadiz\Core\Kernel;
use RZ\Roadiz\Utils\Doctrine\SchemaUpdater;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
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
    private KernelInterface $kernel;

    /**
     * @param SchemaUpdater $schemaUpdater
     * @param KernelInterface $kernel
     */
    public function __construct(SchemaUpdater $schemaUpdater, KernelInterface $kernel)
    {
        $this->schemaUpdater = $schemaUpdater;
        $this->kernel = $kernel;
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

    /**
     * @return Response
     * @throws Exception
     */
    public function clearThemeCacheAction()
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_THEMES');

        /*
         * Very important, when using standard-edition,
         * Kernel class is AppKernel or DevAppKernel.
         */
        if ($this->kernel instanceof Kernel) {
            /** @var class-string<Kernel> $kernelClass */
            $kernelClass = get_class($this->kernel);
            $application = new RoadizApplication(new $kernelClass('prod', false));
            $application->setAutoExit(false);

            $input = new ArrayInput([
                'command' => 'cache:clear'
            ]);
            // You can use NullOutput() if you don't need the output
            $output = new BufferedOutput();
            $application->run($input, $output);

            $inputFpm = new ArrayInput([
                'command' => 'cache:clear-fpm'
            ]);
            // You can use NullOutput() if you don't need the output
            $outputFpm = new BufferedOutput();
            $application->run($inputFpm, $outputFpm);
        }

        return new JsonResponse(['status' => true], JsonResponse::HTTP_PARTIAL_CONTENT);
    }
}
