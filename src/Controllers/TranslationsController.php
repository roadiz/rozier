<?php
declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\Events\Translation\TranslationCreatedEvent;
use RZ\Roadiz\Core\Events\Translation\TranslationDeletedEvent;
use RZ\Roadiz\Core\Events\Translation\TranslationUpdatedEvent;
use RZ\Roadiz\Core\Handlers\HandlerFactoryInterface;
use RZ\Roadiz\Core\Handlers\TranslationHandler;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Themes\Rozier\Forms\TranslationType;
use Themes\Rozier\RozierApp;

class TranslationsController extends RozierApp
{
    const ITEM_PER_PAGE = 5;

    private HandlerFactoryInterface $handlerFactory;

    /**
     * @param HandlerFactoryInterface $handlerFactory
     */
    public function __construct(HandlerFactoryInterface $handlerFactory)
    {
        $this->handlerFactory = $handlerFactory;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TRANSLATIONS');

        $this->assignation['translations'] = [];

        $listManager = $this->createEntityListManager(
            Translation::class
        );
        $listManager->setDisplayingNotPublishedNodes(true);
        $listManager->handle();

        $this->assignation['filters'] = $listManager->getAssignation();
        $translations = $listManager->getEntities();

        /** @var Translation $translation */
        foreach ($translations as $translation) {
            // Make default forms
            $form = $this->createNamedFormBuilder('default_trans_' . $translation->getId(), $translation)->getForm();
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                /** @var TranslationHandler $handler */
                $handler = $this->handlerFactory->getHandler($translation);
                $handler->makeDefault();
                $msg = $this->getTranslator()->trans('translation.%name%.made_default', ['%name%' => $translation->getName()]);
                $this->publishConfirmMessage($request, $msg);
                $this->dispatchEvent(new TranslationUpdatedEvent($translation));
                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->redirectToRoute(
                    'translationsHomePage'
                );
            }

            $this->assignation['translations'][] = [
                'translation' => $translation,
                'defaultForm' => $form->createView(),
            ];
        }

        return $this->render('translations/list.html.twig', $this->assignation);
    }

    /**
     * @param Request $request
     * @param int $translationId
     *
     * @return Response
     */
    public function editAction(Request $request, int $translationId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TRANSLATIONS');

        /** @var Translation|null $translation */
        $translation = $this->em()->find(Translation::class, $translationId);

        if ($translation !== null) {
            $this->assignation['translation'] = $translation;

            $form = $this->createForm(TranslationType::class, $translation);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->em()->flush();
                $msg = $this->getTranslator()->trans('translation.%name%.updated', ['%name%' => $translation->getName()]);
                $this->publishConfirmMessage($request, $msg);

                $this->dispatchEvent(new TranslationUpdatedEvent($translation));
                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->redirectToRoute(
                    'translationsEditPage',
                    ['translationId' => $translation->getId()]
                );
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('translations/edit.html.twig', $this->assignation);
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
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TRANSLATIONS');

        $translation = new Translation();
        $this->assignation['translation'] = $translation;

        $form = $this->createForm(TranslationType::class, $translation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em()->persist($translation);
            $this->em()->flush();

            $msg = $this->getTranslator()->trans('translation.%name%.created', ['%name%' => $translation->getName()]);
            $this->publishConfirmMessage($request, $msg);

            $this->dispatchEvent(new TranslationCreatedEvent($translation));
            /*
             * Force redirect to avoid resending form when refreshing page
             */
            return $this->redirectToRoute('translationsHomePage');
        }

        $this->assignation['form'] = $form->createView();

        return $this->render('translations/add.html.twig', $this->assignation);
    }

    /**
     * @param Request $request
     * @param int $translationId
     *
     * @return Response
     */
    public function deleteAction(Request $request, int $translationId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_TRANSLATIONS');

        /** @var Translation|null $translation */
        $translation = $this->em()->find(Translation::class, $translationId);

        if (null !== $translation) {
            $this->assignation['translation'] = $translation;
            $form = $this->createForm(FormType::class);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                if (false === $translation->isDefaultTranslation()) {
                    $this->em()->remove($translation);
                    $this->em()->flush();
                    $msg = $this->getTranslator()->trans('translation.%name%.deleted', ['%name%' => $translation->getName()]);
                    $this->publishConfirmMessage($request, $msg);
                    $this->dispatchEvent(new TranslationDeletedEvent($translation));

                    return $this->redirectToRoute('translationsHomePage');
                }
                $form->addError(new FormError($this->getTranslator()->trans(
                    'translation.%name%.cannot_delete_default_translation',
                    ['%name%' => $translation->getName()]
                )));
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('translations/delete.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }
}
