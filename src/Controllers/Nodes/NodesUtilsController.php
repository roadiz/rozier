<?php
declare(strict_types=1);

namespace Themes\Rozier\Controllers\Nodes;

use RZ\Roadiz\Core\Entities\Node;
use RZ\Roadiz\Core\Events\Node\NodeCreatedEvent;
use RZ\Roadiz\Core\Events\Node\NodeDuplicatedEvent;
use RZ\Roadiz\Core\Serializers\NodeJsonSerializer;
use RZ\Roadiz\Utils\Node\NodeDuplicator;
use RZ\Roadiz\Utils\Node\NodeNamePolicyInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Themes\Rozier\RozierApp;

/**
 * @package Themes\Rozier\Controllers\Nodes
 */
class NodesUtilsController extends RozierApp
{
    private NodeNamePolicyInterface $nodeNamePolicy;

    /**
     * @param NodeNamePolicyInterface $nodeNamePolicy
     */
    public function __construct(NodeNamePolicyInterface $nodeNamePolicy)
    {
        $this->nodeNamePolicy = $nodeNamePolicy;
    }

    /**
     * Export a Node in a Json file (.json).
     *
     * @param Request $request
     * @param int     $nodeId
     *
     * @return Response
     */
    public function exportAction(Request $request, int $nodeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');

        /** @var Node $existingNode */
        $existingNode = $this->em()->find(Node::class, $nodeId);
        $this->em()->refresh($existingNode);

        $serializer = new NodeJsonSerializer($this->em());
        $node = $serializer->serialize([$existingNode]);

        $response = new Response(
            $node,
            Response::HTTP_OK,
            []
        );

        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'node-' . $existingNode->getNodeName() . '-' . date("YmdHis") . '.json'
            )
        );

        $response->prepare($request);

        return $response;
    }

    /**
     * Export all Node in a Json file (.rzn).
     *
     * @param Request $request
     *
     * @return Response
     */
    public function exportAllAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');

        /** @var Node[] $existingNodes */
        $existingNodes = $this->em()
            ->getRepository(Node::class)
            ->setDisplayingNotPublishedNodes(true)
            ->findBy(["parent" => null]);

        foreach ($existingNodes as $existingNode) {
            $this->em()->refresh($existingNode);
        }

        $serializer = new NodeJsonSerializer($this->em());
        $node = $serializer->serialize($existingNodes);

        $response = new Response(
            $node,
            Response::HTTP_OK,
            []
        );

        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'node-all-' . date("YmdHis") . '.json'
            )
        );

        $response->prepare($request);

        return $response;
    }

    /**
     * Duplicate node by ID
     *
     * @param Request $request
     * @param int     $nodeId
     *
     * @return Response
     */
    public function duplicateAction(Request $request, int $nodeId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_NODES');

        /** @var Node $existingNode */
        $existingNode = $this->em()->find(Node::class, $nodeId);

        try {
            $duplicator = new NodeDuplicator(
                $existingNode,
                $this->em(),
                $this->nodeNamePolicy
            );
            $newNode = $duplicator->duplicate();

            /*
             * Dispatch event
             */
            $this->dispatchEvent(new NodeCreatedEvent($newNode));
            $this->dispatchEvent(new NodeDuplicatedEvent($newNode));

            $msg = $this->getTranslator()->trans("duplicated.node.%name%", [
                '%name%' => $existingNode->getNodeName(),
            ]);

            $this->publishConfirmMessage($request, $msg, $newNode->getNodeSources()->first());

            return $this->redirectToRoute(
                'nodesEditPage',
                ["nodeId" => $newNode->getId()]
            );
        } catch (\Exception $e) {
            $this->publishErrorMessage(
                $request,
                $this->getTranslator()->trans("impossible.duplicate.node.%name%", [
                    '%name%' => $existingNode->getNodeName(),
                ])
            );

            return $this->redirectToRoute(
                'nodesEditPage',
                ["nodeId" => $existingNode->getId()]
            );
        }
    }
}
