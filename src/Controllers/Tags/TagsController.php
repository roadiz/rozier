<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers\Tags;

use RZ\Roadiz\Core\AbstractEntities\PersistableInterface;
use RZ\Roadiz\Core\Handlers\HandlerFactoryInterface;
use RZ\Roadiz\CoreBundle\Entity\Node;
use RZ\Roadiz\CoreBundle\Entity\Tag;
use RZ\Roadiz\CoreBundle\Entity\TagTranslation;
use RZ\Roadiz\CoreBundle\Entity\Translation;
use RZ\Roadiz\CoreBundle\EntityHandler\TagHandler;
use RZ\Roadiz\CoreBundle\Event\Tag\TagCreatedEvent;
use RZ\Roadiz\CoreBundle\Event\Tag\TagDeletedEvent;
use RZ\Roadiz\CoreBundle\Event\Tag\TagUpdatedEvent;
use RZ\Roadiz\CoreBundle\Exception\EntityAlreadyExistsException;
use RZ\Roadiz\CoreBundle\Repository\TranslationRepository;
use RZ\Roadiz\Utils\StringHandler;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Themes\Rozier\Forms\TagTranslationType;
use Themes\Rozier\Forms\TagType;
use Themes\Rozier\RozierApp;
use Themes\Rozier\Traits\VersionedControllerTrait;
use Themes\Rozier\Widgets\TreeWidgetFactory;
use Twig\Error\RuntimeError;

/**
 * @package Themes\Rozier\Controllers\Tags
 */
class TagsController extends RozierApp
{
    use VersionedControllerTrait;

    private HandlerFactoryInterface $handlerFactory;
    private FormFactoryInterface $formFactory;
    private TreeWidgetFactory $treeWidgetFactory;

    /**
     * @param FormFactoryInterface $formFactory
     * @param HandlerFactoryInterface $handlerFactory
     * @param TreeWidgetFactory $treeWidgetFactory
     */
    public function __construct(
        FormFactoryInterface $formFactory,
        HandlerFactoryInterface $handlerFactory,
        TreeWidgetFactory $treeWidgetFactory
    ) {
        $this->handlerFactory = $handlerFactory;
        $this->formFactory = $formFactory;
        $this->treeWidgetFactory = $treeWidgetFactory;
    }

    /**
     * List every tags.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TAGS');

        /*
         * Manage get request to filter list
         */
        $listManager = $this->createEntityListManager(
            Tag::class
        );
        $listManager->setDisplayingNotPublishedNodes(true);
        $listManager->handle();

        $this->assignation['filters'] = $listManager->getAssignation();
        $this->assignation['tags'] = $listManager->getEntities();

        if ($this->isGranted('ROLE_ACCESS_TAGS_DELETE')) {
            /*
             * Handle bulk delete form
             */
            $deleteTagsForm = $this->buildBulkDeleteForm($request->getRequestUri());
            $this->assignation['deleteTagsForm'] = $deleteTagsForm->createView();
        }

        return $this->render('@RoadizRozier/tags/list.html.twig', $this->assignation);
    }

    /**
     * Return an edition form for current translated tag.
     *
     * @param Request $request
     * @param int $tagId
     * @param int|null $translationId
     *
     * @return Response
     * @throws RuntimeError
     */
    public function editTranslatedAction(Request $request, int $tagId, ?int $translationId = null)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TAGS');

        if (null === $translationId) {
            /** @var Translation|null $translation */
            $translation = $this->em()->getRepository(Translation::class)->findDefault();
        } else {
            /** @var Translation|null $translation */
            $translation = $this->em()->find(Translation::class, $translationId);
        }

        if (null === $translation) {
            throw new ResourceNotFoundException();
        }
        /*
         * Here we need to directly select tagTranslation
         * if not doctrine will grab a cache tag because of TagTreeWidget
         * that is initialized before calling route method.
         */
        /** @var Tag|null $tag */
        $tag = $this->em()->find(Tag::class, $tagId);

        /** @var TagTranslation|null $tagTranslation */
        $tagTranslation = $this->em()->getRepository(TagTranslation::class)
            ->findOneBy(['translation' => $translation, 'tag' => $tag]);

        if (null === $tag) {
            throw new ResourceNotFoundException();
        }

        if (null === $tagTranslation) {
            /*
             * If translation does not exist, we created it.
             */
            $this->em()->refresh($tag);
            $baseTranslation = $tag->getTranslatedTags()->first();
            $tagTranslation = new TagTranslation($tag, $translation);
            if (false !== $baseTranslation) {
                $tagTranslation->setName($baseTranslation->getName());
            } else {
                $tagTranslation->setName('tag_' . $tag->getId());
            }
            $this->em()->persist($tagTranslation);
            $this->em()->flush();
        }

        /**
         * Versioning
         */
        if ($this->isGranted('ROLE_ACCESS_VERSIONS')) {
            if (null !== $response = $this->handleVersions($request, $tagTranslation)) {
                return $response;
            }
        }

        $form = $this->createForm(TagTranslationType::class, $tagTranslation, [
            'tagName' => $tag->getTagName(),
            'disabled' => $this->isReadOnly,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                /*
                 * Update tag slug if not locked
                 * only from default translation.
                 */
                $newTagName = StringHandler::slugify($tagTranslation->getName());
                if ($tag->getTagName() !== $newTagName) {
                    if (
                        !$tag->isLocked() &&
                        $translation->isDefaultTranslation() &&
                        !$this->tagNameExists($newTagName)
                    ) {
                        $tag->setTagName($tagTranslation->getName());
                    }
                }
                $this->em()->flush();
                /*
                 * Dispatch event
                 */
                $this->dispatchEvent(
                    new TagUpdatedEvent($tag)
                );

                $msg = $this->getTranslator()->trans('tag.%name%.updated', [
                    '%name%' => $tagTranslation->getName(),
                ]);
                $this->publishConfirmMessage($request, $msg);

                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->getPostUpdateRedirection($tagTranslation);
            }

            /*
             * Handle errors when Ajax POST requests
             */
            if ($request->isXmlHttpRequest()) {
                $errors = $this->getErrorsAsArray($form);
                return new JsonResponse([
                    'status' => 'fail',
                    'errors' => $errors,
                    'message' => $this->getTranslator()->trans('form_has_errors.check_you_fields'),
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
        }
        /** @var TranslationRepository $translationRepository */
        $translationRepository = $this->em()->getRepository(Translation::class);

        $this->assignation['tag'] = $tag;
        $this->assignation['translation'] = $translation;
        $this->assignation['translatedTag'] = $tagTranslation;
        $this->assignation['available_translations'] = $translationRepository->findAll();
        $this->assignation['translations'] = $translationRepository->findAvailableTranslationsForTag($tag);
        $this->assignation['form'] = $form->createView();
        $this->assignation['readOnly'] = $this->isReadOnly;

        return $this->render('@RoadizRozier/tags/edit.html.twig', $this->assignation);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    protected function tagNameExists(string $name): bool
    {
        $entity = $this->em()->getRepository(Tag::class)->findOneByTagName($name);

        return (null !== $entity);
    }

    /**
     * @param Request $request
     *
     * @return Response
     * @throws RuntimeError
     */
    public function bulkDeleteAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TAGS_DELETE');

        if (!empty($request->get('deleteForm')['tagsIds'])) {
            $tagsIds = trim($request->get('deleteForm')['tagsIds']);
            $tagsIds = explode(',', $tagsIds);
            array_filter($tagsIds);

            $tags = $this->em()
                ->getRepository(Tag::class)
                ->findBy([
                    'id' => $tagsIds,
                ]);

            if (count($tags) > 0) {
                $form = $this->buildBulkDeleteForm(
                    $request->get('deleteForm')['referer'],
                    $tagsIds
                );
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    $msg = $this->bulkDeleteTags($form->getData());

                    $this->publishConfirmMessage($request, $msg);

                    if (!empty($form->getData()['referer'])) {
                        return $this->redirect($form->getData()['referer']);
                    } else {
                        return $this->redirectToRoute('tagsHomePage');
                    }
                }

                $this->assignation['tags'] = $tags;
                $this->assignation['form'] = $form->createView();

                if (!empty($request->get('deleteForm')['referer'])) {
                    $this->assignation['referer'] = $request->get('deleteForm')['referer'];
                }

                return $this->render('@RoadizRozier/tags/bulkDelete.html.twig', $this->assignation);
            }
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function addAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TAGS');

        $tag = new Tag();
        $translation = $this->em()->getRepository(Translation::class)->findDefault();

        if ($translation !== null) {
            $this->assignation['tag'] = $tag;
            $form = $this->createForm(TagType::class, $tag);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                /*
                 * Get latest position to add tags after.
                 */
                $latestPosition = $this->em()
                    ->getRepository(Tag::class)
                    ->findLatestPositionInParent();
                $tag->setPosition($latestPosition + 1);

                $this->em()->persist($tag);
                $this->em()->flush();

                $translatedTag = new TagTranslation($tag, $translation);
                $this->em()->persist($translatedTag);
                $this->em()->flush();

                /*
                 * Dispatch event
                 */
                $this->dispatchEvent(new TagCreatedEvent($tag));

                $msg = $this->getTranslator()->trans('tag.%name%.created', ['%name%' => $tag->getTagName()]);
                $this->publishConfirmMessage($request, $msg);
                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->redirectToRoute('tagsHomePage');
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('@RoadizRozier/tags/add.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param Request $request
     * @param int $tagId
     *
     * @return Response
     */
    public function editSettingsAction(Request $request, int $tagId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TAGS');

        $translation = $this->em()->getRepository(Translation::class)->findDefault();

        /** @var Tag|null $tag */
        $tag = $this->em()->find(Tag::class, $tagId);

        if ($tag === null) {
            throw new ResourceNotFoundException();
        }

        $form = $this->createForm(TagType::class, $tag, [
            'tagName' => $tag->getTagName(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $this->em()->flush();
                /*
                 * Dispatch event
                 */
                $this->dispatchEvent(new TagUpdatedEvent($tag));

                $msg = $this->getTranslator()->trans('tag.%name%.updated', ['%name%' => $tag->getTagName()]);
                $this->publishConfirmMessage($request, $msg);

                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->redirectToRoute(
                    'tagsSettingsPage',
                    ['tagId' => $tag->getId()]
                );
            }
            /*
             * Handle errors when Ajax POST requests
             */
            if ($request->isXmlHttpRequest()) {
                $errors = $this->getErrorsAsArray($form);
                return new JsonResponse([
                    'status' => 'fail',
                    'errors' => $errors,
                    'message' => $this->getTranslator()->trans('form_has_errors.check_you_fields'),
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
        }

        $this->assignation['form'] = $form->createView();
        $this->assignation['tag'] = $tag;
        $this->assignation['translation'] = $translation;

        return $this->render('@RoadizRozier/tags/settings.html.twig', $this->assignation);
    }

    /**
     * @param Request $request
     * @param int $tagId
     * @param int|null $translationId
     *
     * @return Response
     */
    public function treeAction(Request $request, int $tagId, ?int $translationId = null)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TAGS');

        $tag = $this->em()
            ->find(Tag::class, $tagId);
        $this->em()->refresh($tag);

        if (null !== $translationId) {
            $translation = $this->em()
                ->getRepository(Translation::class)
                ->findOneBy(['id' => $translationId]);
        } else {
            $translation = $this->em()->getRepository(Translation::class)->findDefault();
        }

        if (null !== $tag) {
            $widget = $this->treeWidgetFactory->createTagTree($tag, $translation);
            $this->assignation['tag'] = $tag;
            $this->assignation['translation'] = $translation;
            $this->assignation['specificTagTree'] = $widget;
        }

        return $this->render('@RoadizRozier/tags/tree.html.twig', $this->assignation);
    }

    /**
     * Return a deletion form for requested tag.
     *
     * @param Request $request
     * @param int     $tagId
     *
     * @return Response
     */
    public function deleteAction(Request $request, int $tagId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TAGS_DELETE');

        /** @var Tag $tag */
        $tag = $this->em()->find(Tag::class, $tagId);

        if (
            $tag !== null &&
            !$tag->isLocked()
        ) {
            $this->assignation['tag'] = $tag;

            $form = $this->buildDeleteForm($tag);
            $form->handleRequest($request);

            if (
                $form->isSubmitted() &&
                $form->isValid() &&
                $form->getData()['tagId'] == $tag->getId()
            ) {
                /*
                 * Dispatch event
                 */
                $this->dispatchEvent(new TagDeletedEvent($tag));

                $this->em()->remove($tag);
                $this->em()->flush();

                $msg = $this->getTranslator()->trans('tag.%name%.deleted', ['%name%' => $tag->getTranslatedTags()->first()->getName()]);
                $this->publishConfirmMessage($request, $msg);

                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->redirectToRoute('tagsHomePage');
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('@RoadizRozier/tags/delete.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * Handle tag creation pages.
     *
     * @param Request $request
     * @param int $tagId
     * @param int|null $translationId
     *
     * @return Response
     */
    public function addChildAction(Request $request, int $tagId, ?int $translationId = null)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TAGS');

        $translation = $this->em()->getRepository(Translation::class)->findDefault();

        if ($translationId !== null) {
            $translation = $this->em()->find(Translation::class, $translationId);
        }
        $parentTag = $this->em()->find(Tag::class, $tagId);
        $tag = new Tag();
        $tag->setParent($parentTag);

        if (
            $translation !== null &&
            $parentTag !== null
        ) {
            $form = $this->createForm(TagType::class, $tag);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    /*
                     * Get latest position to add tags after.
                     */
                    $latestPosition = $this->em()
                        ->getRepository(Tag::class)
                        ->findLatestPositionInParent($parentTag);
                    $tag->setPosition($latestPosition + 1);

                    $this->em()->persist($tag);
                    $this->em()->flush();

                    $translatedTag = new TagTranslation($tag, $translation);
                    $this->em()->persist($translatedTag);
                    $this->em()->flush();
                    /*
                     * Dispatch event
                     */
                    $this->dispatchEvent(new TagCreatedEvent($tag));

                    $msg = $this->getTranslator()->trans('child.tag.%name%.created', ['%name%' => $tag->getTagName()]);
                    $this->publishConfirmMessage($request, $msg);

                    return $this->redirectToRoute(
                        'tagsEditPage',
                        ['tagId' => $tag->getId()]
                    );
                } catch (EntityAlreadyExistsException $e) {
                    $form->addError(new FormError($e->getMessage()));
                }
            }

            $this->assignation['translation'] = $translation;
            $this->assignation['form'] = $form->createView();
            $this->assignation['parentTag'] = $parentTag;

            return $this->render('@RoadizRozier/tags/add.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * Handle tag nodes page.
     *
     * @param Request $request
     * @param int $tagId
     *
     * @return Response
     * @throws RuntimeError
     */
    public function editNodesAction(Request $request, int $tagId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TAGS');

        $tag = $this->em()->find(Tag::class, $tagId);

        if (null !== $tag) {
            $translation = $this->em()->getRepository(Translation::class)->findDefault();

            $this->assignation['tag'] = $tag;

            /*
             * Manage get request to filter list
             */
            $listManager = $this->createEntityListManager(
                Node::class,
                [
                    'tags' => $tag,
                ]
            );
            $listManager->setDisplayingNotPublishedNodes(true);
            $listManager->handle();

            $this->assignation['filters'] = $listManager->getAssignation();
            $this->assignation['nodes'] = $listManager->getEntities();
            $this->assignation['translation'] = $translation;

            return $this->render('@RoadizRozier/tags/nodes.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param Tag $tag
     *
     * @return FormInterface
     */
    private function buildDeleteForm(Tag $tag)
    {
        $builder = $this->createFormBuilder()
            ->add('tagId', HiddenType::class, [
                'data' => $tag->getId(),
                'constraints' => [
                    new NotNull(),
                    new NotBlank(),
                ],
            ]);

        return $builder->getForm();
    }

    /**
     * @param false|string $referer
     * @param array $tagsIds
     *
     * @return FormInterface
     */
    private function buildBulkDeleteForm(
        $referer = false,
        array $tagsIds = []
    ) {
        $builder = $this->formFactory
            ->createNamedBuilder('deleteForm')
            ->add('tagsIds', HiddenType::class, [
                'data' => implode(',', $tagsIds),
                'attr' => ['class' => 'tags-id-bulk-tags'],
                'constraints' => [
                    new NotNull(),
                    new NotBlank(),
                ],
            ]);

        if (false !== $referer && (new UnicodeString($referer))->startsWith('/')) {
            $builder->add('referer', HiddenType::class, [
                'data' => $referer,
            ]);
        }

        return $builder->getForm();
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private function bulkDeleteTags(array $data)
    {
        if (!empty($data['tagsIds'])) {
            $tagsIds = trim($data['tagsIds']);
            $tagsIds = explode(',', $tagsIds);
            array_filter($tagsIds);

            $tags = $this->em()
                ->getRepository(Tag::class)
                ->findBy([
                    'id' => $tagsIds,
                    // Removed locked tags from bulk deletion
                    'locked' => false,
                ]);

            /** @var Tag $tag */
            foreach ($tags as $tag) {
                /** @var TagHandler $handler */
                $handler = $this->handlerFactory->getHandler($tag);
                $handler->removeWithChildrenAndAssociations();
            }

            $this->em()->flush();

            return $this->getTranslator()->trans('tags.bulk.deleted');
        }

        return $this->getTranslator()->trans('wrong.request');
    }

    protected function onPostUpdate(PersistableInterface $entity, Request $request): void
    {
        if ($entity instanceof TagTranslation) {
            $this->em()->flush();
            /*
             * Dispatch event
             */
            $this->dispatchEvent(
                new TagUpdatedEvent($entity->getTag())
            );

            $msg = $this->getTranslator()->trans('tag.%name%.updated', [
                '%name%' => $entity->getName(),
            ]);
            $this->publishConfirmMessage($request, $msg);
        }
    }

    protected function getPostUpdateRedirection(PersistableInterface $entity): ?Response
    {
        if ($entity instanceof TagTranslation) {
            /** @var Translation $translation */
            $translation = $entity->getTranslation();
            return $this->redirectToRoute(
                'tagsEditTranslatedPage',
                [
                    'tagId' => $entity->getTag()->getId(),
                    'translationId' => $translation->getId()
                ]
            );
        }
        return null;
    }
}
