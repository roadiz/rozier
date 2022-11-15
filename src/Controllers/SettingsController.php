<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use Doctrine\Common\Cache\CacheProvider;
use RZ\Roadiz\CoreBundle\Entity\NodeTypeField;
use RZ\Roadiz\CoreBundle\Entity\Setting;
use RZ\Roadiz\CoreBundle\Entity\SettingGroup;
use RZ\Roadiz\CoreBundle\Exception\EntityAlreadyExistsException;
use RZ\Roadiz\CoreBundle\Form\SettingType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Themes\Rozier\RozierApp;
use Themes\Rozier\Utils\SessionListFilters;

class SettingsController extends RozierApp
{
    private FormFactoryInterface $formFactory;

    /**
     * @param FormFactoryInterface $formFactory
     */
    public function __construct(FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;
    }

    /**
     * List every settings.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_SETTINGS');

        if (null !== $response = $this->commonSettingList($request)) {
            return $response->send();
        }

        return $this->render('@RoadizRozier/settings/list.html.twig', $this->assignation);
    }

    /**
     * @param Request $request
     * @param int     $settingGroupId
     *
     * @return Response
     */
    public function byGroupAction(Request $request, int $settingGroupId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_SETTINGS');

        /** @var SettingGroup|null $settingGroup */
        $settingGroup = $this->em()->find(SettingGroup::class, $settingGroupId);

        if ($settingGroup !== null) {
            $this->assignation['settingGroup'] = $settingGroup;

            if (null !== $response = $this->commonSettingList($request, $settingGroup)) {
                return $response->send();
            }

            return $this->render('@RoadizRozier/settings/list.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }

    /**
     * @param Request $request
     * @param SettingGroup|null $settingGroup
     *
     * @return Response|null
     */
    protected function commonSettingList(Request $request, SettingGroup $settingGroup = null)
    {
        $criteria = [];
        if (null !== $settingGroup) {
            $criteria = ['settingGroup' => $settingGroup];
        }
        /*
         * Manage get request to filter list
         */
        $listManager = $this->createEntityListManager(
            Setting::class,
            $criteria,
            ['name' => 'ASC']
        );
        $listManager->setDisplayingNotPublishedNodes(true);

        /*
         * Stored in session
         */
        $sessionListFilter = new SessionListFilters('settings_item_per_page');
        $sessionListFilter->handleItemPerPage($request, $listManager);

        $listManager->handle();

        $this->assignation['filters'] = $listManager->getAssignation();
        $settings = $listManager->getEntities();
        $this->assignation['settings'] = [];

        /** @var Setting $setting */
        foreach ($settings as $setting) {
            $form = $this->formFactory->createNamed($setting->getName(), SettingType::class, $setting, [
                'shortEdit' => true,
            ]);
            $form->handleRequest($request);
            if ($form->isSubmitted()) {
                if ($form->isValid()) {
                    try {
                        $this->resetSettingsCache();
                        $this->em()->flush();
                        $msg = $this->getTranslator()->trans(
                            'setting.%name%.updated',
                            ['%name%' => $setting->getName()]
                        );
                        $this->publishConfirmMessage($request, $msg);

                        if ($request->isXmlHttpRequest() || $request->getRequestFormat('html') === 'json') {
                            return new JsonResponse([
                                'status' => 'success',
                                'message' => $msg,
                            ], JsonResponse::HTTP_ACCEPTED);
                        }

                        if (null !== $settingGroup) {
                            return $this->redirectToRoute(
                                'settingGroupsSettingsPage',
                                ['settingGroupId' => $settingGroup->getId()]
                            );
                        } else {
                            return $this->redirectToRoute(
                                'settingsHomePage'
                            );
                        }
                    } catch (EntityAlreadyExistsException $e) {
                        $form->addError(new FormError($e->getMessage()));
                    }
                } else {
                    foreach ($this->getErrorsAsArray($form) as $error) {
                        $this->publishErrorMessage($request, $error);
                    }

                    if ($request->isXmlHttpRequest() || $request->getRequestFormat('html') === 'json') {
                        return new JsonResponse([
                            'status' => 'failed',
                            'errors' => $this->getErrorsAsArray($form),
                        ], JsonResponse::HTTP_BAD_REQUEST);
                    }
                }
            }

            $document = null;
            if ($setting->getType() == NodeTypeField::DOCUMENTS_T) {
                $document = $this->getSettingsBag()->getDocument($setting->getName());
            }

            $this->assignation['settings'][] = [
                'setting' => $setting,
                'form' => $form->createView(),
                'document' => $document,
            ];
        }

        return null;
    }

    /**
     * Return an edition form for requested setting.
     *
     * @param Request $request
     * @param int     $settingId
     *
     * @return Response
     */
    public function editAction(Request $request, int $settingId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_SETTINGS');
        /** @var Setting|null $setting */
        $setting = $this->em()->find(Setting::class, $settingId);

        if ($setting !== null) {
            $this->assignation['setting'] = $setting;

            $form = $this->createForm(SettingType::class, $setting, [
                'shortEdit' => false
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $this->resetSettingsCache();
                    $this->em()->flush();
                    $msg = $this->getTranslator()->trans('setting.%name%.updated', ['%name%' => $setting->getName()]);
                    $this->publishConfirmMessage($request, $msg);
                    /*
                     * Force redirect to avoid resending form when refreshing page
                     */
                    return $this->redirectToRoute(
                        'settingsEditPage',
                        ['settingId' => $setting->getId()]
                    );
                } catch (EntityAlreadyExistsException $e) {
                    $form->addError(new FormError($e->getMessage()));
                }
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('@RoadizRozier/settings/edit.html.twig', $this->assignation);
        }

        throw $this->createNotFoundException();
    }

    protected function resetSettingsCache(): void
    {
        $this->getSettingsBag()->reset();
        /** @var CacheProvider $cacheDriver */
        $cacheDriver = $this->em()->getConfiguration()->getResultCacheImpl();
        if ($cacheDriver !== null) {
            $cacheDriver->deleteAll();
        }
    }

    /**
     * Return an creation form for requested setting.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function addAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_SETTINGS');

        $setting = new Setting();
        $setting->setSettingGroup(null);

        $this->assignation['setting'] = $setting;
        $form = $this->createForm(SettingType::class, $setting, [
            'shortEdit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->resetSettingsCache();
                $this->em()->persist($setting);
                $this->em()->flush();
                $msg = $this->getTranslator()->trans('setting.%name%.created', ['%name%' => $setting->getName()]);
                $this->publishConfirmMessage($request, $msg);

                return $this->redirectToRoute('settingsHomePage');
            } catch (EntityAlreadyExistsException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        $this->assignation['form'] = $form->createView();

        return $this->render('@RoadizRozier/settings/add.html.twig', $this->assignation);
    }

    /**
     * Return an deletion form for requested setting.
     *
     * @param Request $request
     * @param int     $settingId
     *
     * @return Response
     */
    public function deleteAction(Request $request, int $settingId)
    {
        $this->denyAccessUnlessGranted('ROLE_ACCESS_SETTINGS');

        /** @var Setting|null $setting */
        $setting = $this->em()->find(Setting::class, $settingId);

        if (null !== $setting) {
            $this->assignation['setting'] = $setting;

            $form = $this->createForm(FormType::class, $setting);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->resetSettingsCache();
                $this->em()->remove($setting);
                $this->em()->flush();

                $msg = $this->getTranslator()->trans('setting.%name%.deleted', ['%name%' => $setting->getName()]);
                $this->publishConfirmMessage($request, $msg);

                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                return $this->redirectToRoute('settingsHomePage');
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('@RoadizRozier/settings/delete.html.twig', $this->assignation);
        }

        throw new ResourceNotFoundException();
    }
}
