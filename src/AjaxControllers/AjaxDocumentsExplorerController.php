<?php
declare(strict_types=1);

namespace Themes\Rozier\AjaxControllers;

use Doctrine\ORM\EntityManager;
use RZ\Roadiz\Core\Entities\Document;
use RZ\Roadiz\Core\Entities\Folder;
use RZ\Roadiz\Document\Renderer\RendererInterface;
use RZ\Roadiz\Utils\UrlGenerators\DocumentUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Themes\Rozier\Models\DocumentModel;

/**
 * @package Themes\Rozier\AjaxControllers
 */
class AjaxDocumentsExplorerController extends AbstractAjaxController
{
    public static array $thumbnailArray = [
        "fit" => "40x40",
        "quality" => 50,
        "inline" => false,
    ];
    /**
     * @param Request $request
     *
     * @return Response JSON response
     */
    public function indexAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS');

        /*
         * Prevent raw document to show in explorer.
         */
        $arrayFilter = [
            'raw' => false,
        ];

        if ($request->query->has('folderId') && $request->get('folderId') > 0) {
            $folder = $this->em()
                        ->find(
                            Folder::class,
                            $request->get('folderId')
                        );

            $arrayFilter['folders'] = [$folder];
        }
        /*
         * Manage get request to filter list
         */
        $listManager = $this->createEntityListManager(
            Document::class,
            $arrayFilter,
            [
                'createdAt' => 'DESC'
            ]
        );
        $listManager->setDisplayingNotPublishedNodes(true);
        $listManager->setItemPerPage(30);
        $listManager->handle();

        $documents = $listManager->getEntities();
        $documentsArray = $this->normalizeDocuments($documents);

        $responseArray = [
            'status' => 'confirm',
            'statusCode' => 200,
            'documents' => $documentsArray,
            'documentsCount' => count($documents),
            'filters' => $listManager->getAssignation(),
            'trans' => $this->getTrans(),
        ];

        if ($request->query->has('folderId') && $request->get('folderId') > 0) {
            $responseArray['filters'] = array_merge($responseArray['filters'], [
                'folderId' => $request->get('folderId')
            ]);
        }

        return new JsonResponse(
            $responseArray
        );
    }

    /**
     * Get a Document list from an array of id.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS');

        if (!$request->query->has('ids') || !is_array($request->query->get('ids'))) {
            throw new InvalidParameterException('Ids should be provided within an array');
        }

        $cleanDocumentIds = array_filter($request->query->get('ids'));

        /** @var EntityManager $em */
        $em = $this->em();
        $documents = $em->getRepository(Document::class)->findBy([
            'id' => $cleanDocumentIds,
            'raw' => false,
        ]);

        // Sort array by ids given in request
        $documents = $this->sortIsh($documents, $cleanDocumentIds);
        $documentsArray = $this->normalizeDocuments($documents);

        $responseArray = [
            'status' => 'confirm',
            'statusCode' => 200,
            'documents' => $documentsArray,
            'trans' => $this->getTrans()
        ];

        return new JsonResponse(
            $responseArray
        );
    }

    /**
     * Normalize response Document list result.
     *
     * @param array|\Traversable $documents
     * @return array
     */
    private function normalizeDocuments($documents)
    {
        $documentsArray = [];

        /** @var Document $doc */
        foreach ($documents as $doc) {
            $documentModel = new DocumentModel(
                $doc,
                $this->get(RendererInterface::class),
                $this->get(DocumentUrlGeneratorInterface::class),
                $this->get('router')
            );
            $documentsArray[] = $documentModel->toArray();
        }

        return $documentsArray;
    }

    /**
     * Get an array of translations.
     *
     * @return array
     */
    private function getTrans()
    {
        return [
            'editDocument' => $this->getTranslator()->trans('edit.document'),
            'unlinkDocument' => $this->getTranslator()->trans('unlink.document'),
            'linkDocument' => $this->getTranslator()->trans('link.document'),
            'moreItems' => $this->getTranslator()->trans('more.documents')
        ];
    }
}
