<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use RZ\Roadiz\CoreBundle\Entity\Document;
use RZ\Roadiz\CoreBundle\Entity\Folder;
use RZ\Roadiz\CoreBundle\Entity\FolderTranslation;
use RZ\Roadiz\CoreBundle\Entity\Translation;
use RZ\Roadiz\CoreBundle\Event\Folder\FolderCreatedEvent;
use RZ\Roadiz\CoreBundle\Event\Folder\FolderDeletedEvent;
use RZ\Roadiz\CoreBundle\Event\Folder\FolderUpdatedEvent;
use RZ\Roadiz\CoreBundle\Repository\TranslationRepository;
use RZ\Roadiz\Utils\Asset\Packages;
use RZ\Roadiz\Utils\StringHandler;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Themes\Rozier\Forms\FolderTranslationType;
use Themes\Rozier\Forms\FolderType;
use Themes\Rozier\RozierApp;

/**
 * @package Themes\Rozier\Controllers
 */
class FoldersController extends RozierApp
{
    private Packages $packages;

    /**
     * @param Packages $packages
     */
    public function __construct(Packages $packages)
    {
        $this->packages = $packages;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS');

        $listManager = $this->createEntityListManager(
            Folder::class
        );
        $listManager->setDisplayingNotPublishedNodes(true);
        $listManager->handle();

        $this->assignation['filters'] = $listManager->getAssignation();
        $this->assignation['folders'] = $listManager->getEntities();

        return $this->render('@RoadizRozier/folders/list.html.twig', $this->assignation);
    }

    /**
     * Return an creation form for requested folder.
     *
     * @param Request $request
     * @param int|null $parentFolderId
     *
     * @return Response
     */
    public function addAction(Request $request, ?int $parentFolderId = null)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS');

        $folder = new Folder();

        if (null !== $parentFolderId) {
            $parentFolder = $this->em()->find(Folder::class, $parentFolderId);
            if (null !== $parentFolder) {
                $folder->setParent($parentFolder);
            }
        }
        $form = $this->createForm(FolderType::class, $folder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @var Translation $translation */
                $translation = $this->em()->getRepository(Translation::class)->findDefault();
                $folderTranslation = new FolderTranslation($folder, $translation);
                $this->em()->persist($folder);
                $this->em()->persist($folderTranslation);

                $this->em()->flush();

                $msg = $this->getTranslator()->trans(
                    'folder.%name%.created',
                    ['%name%' => $folder->getFolderName()]
                );
                $this->publishConfirmMessage($request, $msg);

                /*
                 * Dispatch event
                 */
                $this->dispatchEvent(
                    new FolderCreatedEvent($folder)
                );
            } catch (\RuntimeException $e) {
                $this->publishErrorMessage($request, $e->getMessage());
            }

            return $this->redirectToRoute('foldersHomePage');
        }

        $this->assignation['form'] = $form->createView();

        return $this->render('@RoadizRozier/folders/add.html.twig', $this->assignation);
    }

    /**
     * Return a deletion form for requested folder.
     *
     * @param Request $request
     * @param int     $folderId
     *
     * @return Response
     */
    public function deleteAction(Request $request, int $folderId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS');

        /** @var Folder|null $folder */
        $folder = $this->em()->find(Folder::class, $folderId);

        if (null !== $folder) {
            $form = $this->createForm(FormType::class, $folder);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $this->em()->remove($folder);
                    $this->em()->flush();
                    $msg = $this->getTranslator()->trans(
                        'folder.%name%.deleted',
                        ['%name%' => $folder->getFolderName()]
                    );
                    $this->publishConfirmMessage($request, $msg);

                    /*
                     * Dispatch event
                     */
                    $this->dispatchEvent(
                        new FolderDeletedEvent($folder)
                    );
                } catch (\RuntimeException $e) {
                    $this->publishErrorMessage($request, $e->getMessage());
                }

                return $this->redirectToRoute('foldersHomePage');
            }

            $this->assignation['form'] = $form->createView();
            $this->assignation['folder'] = $folder;

            return $this->render('@RoadizRozier/folders/delete.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * Return an edition form for requested folder.
     *
     * @param Request $request
     * @param int     $folderId
     *
     * @return Response
     */
    public function editAction(Request $request, int $folderId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS');

        /** @var Folder|null $folder */
        $folder = $this->em()->find(Folder::class, $folderId);

        /** @var Translation $translation */
        $translation = $this->em()
            ->getRepository(Translation::class)
            ->findDefault();

        if ($folder !== null) {
            $form = $this->createForm(FolderType::class, $folder);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $this->em()->flush();
                    $msg = $this->getTranslator()->trans(
                        'folder.%name%.updated',
                        ['%name%' => $folder->getFolderName()]
                    );
                    $this->publishConfirmMessage($request, $msg);
                    /*
                     * Dispatch event
                     */
                    $this->dispatchEvent(
                        new FolderUpdatedEvent($folder)
                    );
                } catch (\RuntimeException $e) {
                    $this->publishErrorMessage($request, $e->getMessage());
                }

                return $this->redirectToRoute('foldersEditPage', ['folderId' => $folderId]);
            }

            $this->assignation['folder'] = $folder;
            $this->assignation['form'] = $form->createView();
            $this->assignation['translation'] = $translation;

            return $this->render('@RoadizRozier/folders/edit.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param Request $request
     * @param int     $folderId
     * @param int     $translationId
     *
     * @return Response
     */
    public function editTranslationAction(Request $request, int $folderId, int $translationId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS');

        /** @var TranslationRepository $translationRepository */
        $translationRepository = $this->em()->getRepository(Translation::class);

        /** @var Folder|null $folder */
        $folder = $this->em()->find(Folder::class, $folderId);

        /** @var Translation|null $translation */
        $translation = $this->em()->find(Translation::class, $translationId);

        /** @var FolderTranslation|null $folderTranslation */
        $folderTranslation = $this->em()
            ->getRepository(FolderTranslation::class)
            ->findOneBy([
                'folder' => $folder,
                'translation' => $translation,
            ]);

        if (null === $folderTranslation) {
            $folderTranslation = new FolderTranslation($folder, $translation);
            $this->em()->persist($folderTranslation);
        }

        if (null !== $folder && null !== $translation) {
            $form = $this->createForm(FolderTranslationType::class, $folderTranslation);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $this->em()->flush();
                    $msg = $this->getTranslator()->trans(
                        'folder.%name%.updated',
                        ['%name%' => $folder->getFolderName()]
                    );
                    $this->publishConfirmMessage($request, $msg);
                    /*
                     * Dispatch event
                     */
                    $this->dispatchEvent(
                        new FolderUpdatedEvent($folder)
                    );
                } catch (\RuntimeException $e) {
                    $this->publishErrorMessage($request, $e->getMessage());
                }

                return $this->redirectToRoute('foldersEditTranslationPage', [
                    'folderId' => $folderId,
                    'translationId' => $translationId,
                ]);
            }

            $this->assignation['folder'] = $folder;
            $this->assignation['translation'] = $translation;
            $this->assignation['form'] = $form->createView();
            $this->assignation['available_translations'] = $translationRepository->findAll();
            $this->assignation['translations'] = $translationRepository->findAvailableTranslationsForFolder($folder);

            return $this->render('@RoadizRozier/folders/edit.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * Return a ZipArchive of requested folder.
     *
     * @param Request $request
     * @param int     $folderId
     *
     * @return Response
     */
    public function downloadAction(Request $request, int $folderId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS');

        /** @var Folder|null $folder */
        $folder = $this->em()->find(Folder::class, $folderId);

        if ($folder !== null) {
            // Prepare File
            $file = tempnam(sys_get_temp_dir(), "folder_" . $folder->getId());
            $zip = new \ZipArchive();
            $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            $documents = $this->em()
                              ->getRepository(Document::class)
                              ->findBy([
                                  'folders' => [$folder],
                              ]);

            /** @var Document $document */
            foreach ($documents as $document) {
                if ($document->isLocal()) {
                    $zip->addFile(
                        $this->packages->getDocumentFilePath($document),
                        $document->getFolder() . DIRECTORY_SEPARATOR . $document->getFilename()
                    );
                }
            }

            // Close and send to users
            $zip->close();

            $filename = StringHandler::slugify($folder->getFolderName()) . '.zip';

            $response = new Response(
                file_get_contents($file),
                Response::HTTP_OK,
                [
                    'content-type' => 'application/zip',
                    'content-length' => filesize($file),
                    'content-disposition' => 'attachment; filename=' . $filename,
                ]
            );
            unlink($file);

            return $response;
        }

        throw new ResourceNotFoundException();
    }
}
