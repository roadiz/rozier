<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers\Nodes;

use RZ\Roadiz\CoreBundle\Entity\Node;
use RZ\Roadiz\CoreBundle\Entity\NodeType;
use RZ\Roadiz\CoreBundle\Event\Node\NodeUpdatedEvent;
use RZ\Roadiz\CoreBundle\Event\NodesSources\NodesSourcesUpdatedEvent;
use RZ\Roadiz\CoreBundle\Node\NodeTranstyper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Themes\Rozier\Forms\TranstypeType;
use Themes\Rozier\RozierApp;

/**
 * @package Themes\Rozier\Controllers\Nodes
 */
class TranstypeController extends RozierApp
{
    private NodeTranstyper $nodeTranstyper;

    /**
     * @param NodeTranstyper $nodeTranstyper
     */
    public function __construct(NodeTranstyper $nodeTranstyper)
    {
        $this->nodeTranstyper = $nodeTranstyper;
    }

    /**
     * @param Request $request
     * @param int $nodeId
     *
     * @return RedirectResponse|Response
     */
    public function transtypeAction(Request $request, int $nodeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');

        /** @var Node|null $node */
        $node = $this->em()->find(Node::class, $nodeId);
        $this->em()->refresh($node);

        if (null === $node) {
            throw new ResourceNotFoundException();
        }

        $form = $this->createForm(TranstypeType::class, null, [
            'currentType' => $node->getNodeType(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            /** @var NodeType $newNodeType */
            $newNodeType = $this->em()->find(NodeType::class, (int) $data['nodeTypeId']);
            $this->nodeTranstyper->transtype($node, $newNodeType);
            $this->em()->flush();
            $this->em()->refresh($node);
            /*
             * Dispatch event
             */
            $this->dispatchEvent(new NodeUpdatedEvent($node));

            foreach ($node->getNodeSources() as $nodeSource) {
                $this->dispatchEvent(new NodesSourcesUpdatedEvent($nodeSource));
            }

            $msg = $this->getTranslator()->trans('%node%.transtyped_to.%type%', [
                '%node%' => $node->getNodeName(),
                '%type%' => $newNodeType->getName(),
            ]);
            $this->publishConfirmMessage($request, $msg, $node->getNodeSources()->first());

            return $this->redirectToRoute(
                'nodesEditSourcePage',
                [
                    'nodeId' => $node->getId(),
                    'translationId' => $node->getNodeSources()->first()->getTranslation()->getId(),
                ]
            );
        }

        $this->assignation['form'] = $form->createView();
        $this->assignation['node'] = $node;
        $this->assignation['parentNode'] = $node->getParent();
        $this->assignation['type'] = $node->getNodeType();

        return $this->render('@RoadizRozier/nodes/transtype.html.twig', $this->assignation);
    }
}
