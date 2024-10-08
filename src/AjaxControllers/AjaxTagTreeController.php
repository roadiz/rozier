<?php

declare(strict_types=1);

namespace Themes\Rozier\AjaxControllers;

use RZ\Roadiz\CoreBundle\Entity\Tag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Themes\Rozier\Widgets\TagTreeWidget;
use Themes\Rozier\Widgets\TreeWidgetFactory;

/**
 * @package Themes\Rozier\AjaxControllers
 */
class AjaxTagTreeController extends AbstractAjaxController
{
    public function __construct(private TreeWidgetFactory $treeWidgetFactory)
    {
    }

    public function getTreeAction(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TAGS');
        $translation = $this->getTranslation($request);

        /** @var TagTreeWidget|null $tagTree */
        $tagTree = null;

        switch ($request->get("_action")) {
            /*
             * Inner tag edit for tagTree
             */
            case 'requestTagTree':
                if ($request->get('parentTagId') > 0) {
                    $tag = $this->em()
                                ->find(
                                    Tag::class,
                                    (int) $request->get('parentTagId')
                                );
                } else {
                    $tag = null;
                }

                $tagTree = $this->treeWidgetFactory->createTagTree($tag, $translation);

                $this->assignation['mainTagTree'] = false;

                break;
            /*
             * Main panel tree tagTree
             */
            case 'requestMainTagTree':
                $parent = null;
                $tagTree = $this->treeWidgetFactory->createTagTree($parent, $translation);
                $this->assignation['mainTagTree'] = true;
                break;
        }

        $this->assignation['tagTree'] = $tagTree;

        $responseArray = [
            'statusCode' => '200',
            'status' => 'success',
            'tagTree' => $this->getTwig()->render('@RoadizRozier/widgets/tagTree/tagTree.html.twig', $this->assignation),
        ];

        return new JsonResponse(
            $responseArray
        );
    }
}
