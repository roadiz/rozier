<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers\Nodes;

use RZ\Roadiz\CoreBundle\Entity\Log;
use RZ\Roadiz\CoreBundle\Entity\Node;
use RZ\Roadiz\CoreBundle\Entity\Translation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Themes\Rozier\RozierApp;
use Themes\Rozier\Utils\SessionListFilters;
use Twig\Error\RuntimeError;

/**
 * @package Themes\Rozier\Controllers\Nodes
 */
class HistoryController extends RozierApp
{
    /**
     * @param Request $request
     * @param int $nodeId
     * @return Response
     * @throws RuntimeError
     */
    public function historyAction(Request $request, int $nodeId): Response
    {
        $this->denyAccessUnlessGranted(['ROLE_ACCESS_NODES', 'ROLE_ACCESS_LOGS']);
        /** @var Node|null $node */
        $node = $this->em()->find(Node::class, $nodeId);

        if (null === $node) {
            throw new ResourceNotFoundException();
        }

        $listManager = $this->createEntityListManager(
            Log::class,
            [
                'nodeSource' => $node->getNodeSources()->toArray(),
            ],
            ['datetime' => 'DESC']
        );
        $listManager->setDisplayingNotPublishedNodes(true);
        $listManager->setDisplayingAllNodesStatuses(true);
        /*
         * Stored in session
         */
        $sessionListFilter = new SessionListFilters('user_history_item_per_page');
        $sessionListFilter->handleItemPerPage($request, $listManager);
        $listManager->handle();

        $this->assignation['node'] = $node;
        $this->assignation['translation'] = $this->em()->getRepository(Translation::class)->findDefault();
        $this->assignation['entries'] = $listManager->getEntities();
        $this->assignation['filters'] = $listManager->getAssignation();

        return $this->render('@RoadizRozier/nodes/history.html.twig', $this->assignation);
    }
}
