<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers\Documents;

use RZ\Roadiz\Core\AbstractEntities\PersistableInterface;
use RZ\Roadiz\Core\AbstractEntities\TranslationInterface;
use RZ\Roadiz\CoreBundle\Entity\Document;
use RZ\Roadiz\CoreBundle\Entity\DocumentTranslation;
use RZ\Roadiz\CoreBundle\Entity\Translation;
use RZ\Roadiz\CoreBundle\Event\Document\DocumentTranslationUpdatedEvent;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Themes\Rozier\Forms\DocumentTranslationType;
use Themes\Rozier\RozierApp;
use Themes\Rozier\Traits\VersionedControllerTrait;
use Twig\Error\RuntimeError;

class DocumentTranslationsController extends RozierApp
{
    use VersionedControllerTrait;

    /**
     * @throws RuntimeError
     */
    public function editAction(Request $request, int $documentId, ?int $translationId = null): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS');

        if (null === $translationId) {
            $translation = $this->em()->getRepository(Translation::class)->findDefault();
            if ($translation instanceof PersistableInterface) {
                $translationId = $translation->getId();
            }
        } else {
            $translation = $this->em()->find(Translation::class, $translationId);
        }

        $this->assignation['available_translations'] = $this->em()
             ->getRepository(Translation::class)
             ->findAll();

        /** @var Document $document */
        $document = $this->em()
                         ->find(Document::class, $documentId);
        $documentTr = $this->em()
                           ->getRepository(DocumentTranslation::class)
                           ->findOneBy(['document' => $documentId, 'translation' => $translationId]);

        if (null === $documentTr && null !== $document && null !== $translation) {
            $documentTr = $this->createDocumentTranslation($document, $translation);
        }

        if (null === $documentTr || null === $document) {
            throw new ResourceNotFoundException();
        }

        $this->assignation['document'] = $document;
        $this->assignation['translation'] = $translation;
        $this->assignation['documentTr'] = $documentTr;

        /*
         * Versioning
         */
        if ($this->isGranted('ROLE_ACCESS_VERSIONS')) {
            if (null !== $response = $this->handleVersions($request, $documentTr)) {
                return $response;
            }
        }

        /*
         * Handle main form
         */
        $form = $this->createForm(DocumentTranslationType::class, $documentTr, [
            'referer' => $this->getRequest()->get('referer'),
            'disabled' => $this->isReadOnly,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->onPostUpdate($documentTr, $request);

            $routeParams = [
                'documentId' => $document->getId(),
                'translationId' => $translationId,
            ];

            if ($form->get('referer')->getData()) {
                $routeParams = array_merge($routeParams, [
                    'referer' => $form->get('referer')->getData(),
                ]);
            }

            /*
             * Force redirect to avoid resending form when refreshing page
             */
            return $this->redirectToRoute(
                'documentsMetaPage',
                $routeParams
            );
        }

        $this->assignation['form'] = $form->createView();
        $this->assignation['readOnly'] = $this->isReadOnly;

        return $this->render('@RoadizRozier/document-translations/edit.html.twig', $this->assignation);
    }

    protected function createDocumentTranslation(
        Document $document,
        TranslationInterface $translation,
    ): DocumentTranslation {
        $dt = new DocumentTranslation();
        $dt->setDocument($document);
        $dt->setTranslation($translation);

        $this->em()->persist($dt);

        return $dt;
    }

    /**
     * Return an deletion form for requested document.
     *
     * @throws RuntimeError
     */
    public function deleteAction(Request $request, int $documentId, int $translationId): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCUMENTS_DELETE');

        $documentTr = $this->em()
                           ->getRepository(DocumentTranslation::class)
                           ->findOneBy(['document' => $documentId, 'translation' => $translationId]);
        $document = $this->em()
                         ->find(Document::class, $documentId);

        if (
            null !== $documentTr
            && null !== $document
        ) {
            $this->assignation['documentTr'] = $documentTr;
            $this->assignation['document'] = $document;
            $form = $this->buildDeleteForm($documentTr);
            $form->handleRequest($request);

            if (
                $form->isSubmitted()
                && $form->isValid()
                && $form->getData()['documentId'] == $documentTr->getId()
            ) {
                try {
                    $this->em()->remove($documentTr);
                    $this->em()->flush();

                    $msg = $this->getTranslator()->trans(
                        'document.translation.%name%.deleted',
                        ['%name%' => (string) $document]
                    );
                    $this->publishConfirmMessage($request, $msg, $document);
                } catch (\Exception $e) {
                    $msg = $this->getTranslator()->trans(
                        'document.translation.%name%.cannot_delete',
                        ['%name%' => (string) $document]
                    );
                    $this->publishErrorMessage($request, $msg, $document);
                }

                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->redirectToRoute(
                    'documentsEditPage',
                    ['documentId' => $document->getId()]
                );
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('@RoadizRozier/document-translations/delete.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    private function buildDeleteForm(DocumentTranslation $doc): FormInterface
    {
        $defaults = [
            'documentTranslationId' => $doc->getId(),
        ];
        $builder = $this->createFormBuilder($defaults)
                        ->add('documentTranslationId', HiddenType::class, [
                            'data' => $doc->getId(),
                            'constraints' => [
                                new NotNull(),
                                new NotBlank(),
                            ],
                        ]);

        return $builder->getForm();
    }

    protected function onPostUpdate(PersistableInterface $entity, Request $request): void
    {
        /*
         * Dispatch pre-flush event
         */
        if ($entity instanceof DocumentTranslation) {
            $this->dispatchEvent(
                new DocumentTranslationUpdatedEvent($entity->getDocument(), $entity)
            );
            $this->em()->flush();
            $msg = $this->getTranslator()->trans('document.translation.%name%.updated', [
                '%name%' => (string) $entity->getDocument(),
            ]);
            $this->publishConfirmMessage($request, $msg, $entity);
        }
    }

    protected function getPostUpdateRedirection(PersistableInterface $entity): ?Response
    {
        if (
            $entity instanceof DocumentTranslation
            && $entity->getDocument() instanceof Document
            && $entity->getTranslation() instanceof Translation
        ) {
            $routeParams = [
                'documentId' => $entity->getDocument()->getId(),
                'translationId' => $entity->getTranslation()->getId(),
            ];

            /*
             * Force redirect to avoid resending form when refreshing page
             */
            return $this->redirectToRoute(
                'documentsMetaPage',
                $routeParams
            );
        }

        return null;
    }
}
