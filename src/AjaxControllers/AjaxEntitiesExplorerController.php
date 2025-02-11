<?php

declare(strict_types=1);

namespace Themes\Rozier\AjaxControllers;

use Doctrine\ORM\EntityManager;
use RZ\Roadiz\Core\AbstractEntities\AbstractField;
use RZ\Roadiz\Core\AbstractEntities\PersistableInterface;
use RZ\Roadiz\CoreBundle\Configuration\JoinNodeTypeFieldConfiguration;
use RZ\Roadiz\CoreBundle\Entity\NodeTypeField;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Yaml\Yaml;

final class AjaxEntitiesExplorerController extends AbstractAjaxExplorerController
{
    protected function getFieldConfiguration(NodeTypeField $nodeTypeField): array
    {
        if (
            AbstractField::MANY_TO_MANY_T !== $nodeTypeField->getType()
            && AbstractField::MANY_TO_ONE_T !== $nodeTypeField->getType()
        ) {
            throw new BadRequestHttpException('nodeTypeField is not a valid entity join.');
        }

        $configs = [
            Yaml::parse($nodeTypeField->getDefaultValues() ?? ''),
        ];
        $processor = new Processor();
        $joinConfig = new JoinNodeTypeFieldConfiguration();

        return $processor->processConfiguration($joinConfig, $configs);
    }

    public function indexAction(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_BACKEND_USER');

        if (!$request->query->has('nodeTypeFieldId')) {
            throw new BadRequestHttpException('nodeTypeFieldId parameter is missing.');
        }

        /** @var NodeTypeField|null $nodeTypeField */
        $nodeTypeField = $this->em()->find(NodeTypeField::class, $request->query->get('nodeTypeFieldId'));

        if (null === $nodeTypeField) {
            throw new BadRequestHttpException('nodeTypeField does not exist.');
        }

        $configuration = $this->getFieldConfiguration($nodeTypeField);
        /** @var class-string<PersistableInterface> $className */
        $className = $configuration['classname'];

        $orderBy = [];
        foreach ($configuration['orderBy'] as $order) {
            $orderBy[$order['field']] = $order['direction'];
        }

        $criteria = [];
        foreach ($configuration['where'] as $where) {
            $criteria[$where['field']] = $where['value'];
        }

        /*
         * Manage get request to filter list
         */
        $listManager = $this->createEntityListManager(
            $className,
            $criteria,
            $orderBy
        );
        $listManager->setDisplayingNotPublishedNodes(true);
        $listManager->setItemPerPage(30);
        $listManager->handle();
        $entities = $listManager->getEntities();

        $entitiesArray = $this->normalizeEntities($entities, $configuration);

        return $this->createSerializedResponse([
            'status' => 'confirm',
            'statusCode' => 200,
            'entities' => $entitiesArray,
            'filters' => $listManager->getAssignation(),
        ]);
    }

    public function listAction(Request $request): JsonResponse
    {
        if (!$request->query->has('nodeTypeFieldId')) {
            throw new BadRequestHttpException('nodeTypeFieldId parameter is missing.');
        }

        if (!$request->query->has('ids')) {
            throw new BadRequestHttpException('Ids should be provided within an array');
        }

        $this->denyAccessUnlessGranted('ROLE_BACKEND_USER');

        /** @var EntityManager $em */
        $em = $this->em();

        /** @var NodeTypeField|null $nodeTypeField */
        $nodeTypeField = $this->em()->find(NodeTypeField::class, $request->query->get('nodeTypeFieldId'));

        if (null === $nodeTypeField) {
            throw new BadRequestHttpException('nodeTypeField does not exist.');
        }

        $configuration = $this->getFieldConfiguration($nodeTypeField);
        /** @var class-string<PersistableInterface> $className */
        $className = $configuration['classname'];

        $cleanNodeIds = array_filter($request->query->filter('ids', [], \FILTER_DEFAULT, [
            'flags' => \FILTER_FORCE_ARRAY,
        ]));
        $entitiesArray = [];

        if (count($cleanNodeIds)) {
            $entities = $em->getRepository($className)->findBy([
                'id' => $cleanNodeIds,
            ]);

            // Sort array by ids given in request
            $entities = $this->sortIsh($entities, $cleanNodeIds);
            $entitiesArray = $this->normalizeEntities($entities, $configuration);
        }

        return $this->createSerializedResponse([
            'status' => 'confirm',
            'statusCode' => 200,
            'items' => $entitiesArray,
        ]);
    }

    /**
     * Normalize response Node list result.
     *
     * @param iterable<PersistableInterface> $entities
     *
     * @return array<array>
     */
    private function normalizeEntities(iterable $entities, array $configuration): array
    {
        $entitiesArray = [];

        foreach ($entities as $entity) {
            $explorerItem = $this->explorerItemFactory->createForEntity(
                $entity,
                $configuration
            );
            $entitiesArray[] = $explorerItem->toArray();
        }

        return $entitiesArray;
    }
}
