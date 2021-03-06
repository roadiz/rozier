<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use RZ\Roadiz\CoreBundle\Entity\Log;
use Symfony\Component\HttpFoundation\Request;
use Themes\Rozier\RozierApp;

/**
 * @package Themes\Rozier\Controllers
 */
class DashboardController extends RozierApp
{
    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response $response
     * @throws \Twig\Error\RuntimeError
     */
    public function indexAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_BACKEND_USER');

        $this->assignation['latestLogs'] = [];

        $this->assignation['latestLogs'] = $this->em()
             ->getRepository(Log::class)
             ->findLatestByNodesSources(8);


        return $this->render('@RoadizRozier/dashboard/index.html.twig', $this->assignation);
    }
}
