<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers\Nodes;

use RZ\Roadiz\CoreBundle\Entity\NodesSources;
use RZ\Roadiz\CoreBundle\Entity\Translation;
use RZ\Roadiz\CoreBundle\Event\Node\NodeTaggedEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Themes\Rozier\Forms\NodeTagsType;
use Themes\Rozier\RozierApp;
use Themes\Rozier\Traits\NodesTrait;

/**
 * @package Themes\Rozier\Controllers\Nodes
 */
class NodesTagsController extends RozierApp
{
    use NodesTrait;

    /**
     * Return tags form for requested node.
     *
     * @param Request $request
     * @param int     $nodeId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editTagsAction(Request $request, int $nodeId)
    {
        $this->validateNodeAccessForRole('ROLE_ACCESS_NODES', $nodeId);

        /** @var NodesSources|null $source */
        $source = $this->em()
                       ->getRepository(NodesSources::class)
                       ->setDisplayingAllNodesStatuses(true)
                       ->setDisplayingNotPublishedNodes(true)
                       ->findOneBy([
                           'node.id' => $nodeId,
                           'translation' => $this->em()->getRepository(Translation::class)->findDefault()
                       ]);
        if (null === $source) {
            /** @var NodesSources|null $source */
            $source = $this->em()
                ->getRepository(NodesSources::class)
                ->setDisplayingAllNodesStatuses(true)
                ->setDisplayingNotPublishedNodes(true)
                ->findOneBy([
                    'node.id' => $nodeId,
                ]);
        }

        if (null !== $source) {
            $node = $source->getNode();
            $form = $this->createForm(NodeTagsType::class, $node);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                /*
                 * Dispatch event
                 */
                $this->dispatchEvent(new NodeTaggedEvent($node));
                $this->em()->flush();

                $msg = $this->getTranslator()->trans('node.%node%.linked.tags', [
                    '%node%' => $node->getNodeName(),
                ]);
                $this->publishConfirmMessage($request, $msg, $source);

                return $this->redirectToRoute(
                    'nodesEditTagsPage',
                    ['nodeId' => $node->getId()]
                );
            }

            $this->assignation['translation'] = $source->getTranslation();
            $this->assignation['node'] = $node;
            $this->assignation['source'] = $source;
            $this->assignation['form'] = $form->createView();

            return $this->render('@RoadizRozier/nodes/editTags.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }
}
