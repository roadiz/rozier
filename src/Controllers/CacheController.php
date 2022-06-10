<?php
declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use Psr\Log\LoggerInterface;
use RZ\Roadiz\Core\Events\Cache\CachePurgeAssetsRequestEvent;
use RZ\Roadiz\Core\Events\Cache\CachePurgeRequestEvent;
use RZ\Roadiz\Core\Kernel;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Themes\Rozier\RozierApp;

/**
 * @package Themes\Rozier\Controllers
 */
class CacheController extends RozierApp
{
    private KernelInterface $kernel;
    private LoggerInterface $logger;

    /**
     * @param KernelInterface $kernel
     * @param LoggerInterface $logger
     */
    public function __construct(
        KernelInterface $kernel,
        LoggerInterface $logger
    ) {
        $this->kernel = $kernel;
        $this->logger = $logger;
    }


    public function deleteDoctrineCache(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCTRINE_CACHE_DELETE');

        $form = $this->buildDeleteDoctrineForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = new CachePurgeRequestEvent($this->kernel);
            $this->dispatchEvent($event);

            // Clear cache for prod preview
            $kernelClass = get_class($this->kernel);
            /** @var Kernel $prodPreviewKernel */
            $prodPreviewKernel = new $kernelClass('prod', false, true);
            $prodPreviewKernel->boot();
            $prodPreviewEvent = new CachePurgeRequestEvent($prodPreviewKernel);
            $this->dispatchEvent($prodPreviewEvent);

            $msg = $this->getTranslator()->trans('cache.deleted');
            $this->publishConfirmMessage($request, $msg);

            foreach ($event->getMessages() as $message) {
                $this->logger->info(sprintf('Cache cleared: %s', $message['description']));
            }
            foreach ($event->getErrors() as $message) {
                $this->publishErrorMessage($request, sprintf('Could not clear cache: %s', $message['description']));
            }
            foreach ($prodPreviewEvent->getMessages() as $message) {
                $this->logger->info(sprintf('Preview cache cleared: %s', $message['description']));
            }
            foreach ($prodPreviewEvent->getErrors() as $message) {
                $this->publishErrorMessage($request, sprintf('Could not clear creview cache: %s', $message['description']));
            }

            /*
             * Force redirect to avoid resending form when refreshing page
             */
            return $this->redirectToRoute('adminHomePage');
        }

        $this->assignation['form'] = $form->createView();

        $this->assignation['cachesInfo'] = [
            'resultCache' => $this->em()->getConfiguration()->getResultCacheImpl(),
            'hydratationCache' => $this->em()->getConfiguration()->getHydrationCacheImpl(),
            'queryCache' => $this->em()->getConfiguration()->getQueryCacheImpl(),
            'metadataCache' => $this->em()->getConfiguration()->getMetadataCacheImpl(),
        ];

        foreach ($this->assignation['cachesInfo'] as $key => $value) {
            if (null !== $value) {
                $this->assignation['cachesInfo'][$key] = get_class($value);
            } else {
                $this->assignation['cachesInfo'][$key] = false;
            }
        }

        return $this->render('@RoadizRozier/cache/deleteDoctrine.html.twig', $this->assignation);
    }

    /**
     * @return FormInterface
     */
    private function buildDeleteDoctrineForm()
    {
        $builder = $this->createFormBuilder();

        return $builder->getForm();
    }

    /**
     * @param Request $request
     *
     * @return Response
     * @throws \Twig\Error\RuntimeError
     */
    public function deleteAssetsCache(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_DOCTRINE_CACHE_DELETE');

        $form = $this->buildDeleteAssetsForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = $this->dispatchEvent(new CachePurgeAssetsRequestEvent($this->kernel));
            $msg = $this->getTranslator()->trans('cache.deleted');
            $this->publishConfirmMessage($request, $msg);
            foreach ($event->getMessages() as $message) {
                $this->logger->info(sprintf('Cache cleared: %s', $message['description']));
            }
            foreach ($event->getErrors() as $message) {
                $this->publishErrorMessage($request, sprintf('Could not clear cache: %s', $message['description']));
            }

            /*
             * Force redirect to avoid resending form when refreshing page
             */
            return $this->redirectToRoute('adminHomePage');
        }

        $this->assignation['form'] = $form->createView();

        return $this->render('@RoadizRozier/cache/deleteAssets.html.twig', $this->assignation);
    }

    /**
     * @return FormInterface
     */
    private function buildDeleteAssetsForm()
    {
        $builder = $this->createFormBuilder();

        return $builder->getForm();
    }
}
