<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use Psr\Log\LoggerInterface;
use RZ\Roadiz\CMS\Forms\LoginRequestForm;
use RZ\Roadiz\CMS\Traits\LoginRequestTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Themes\Rozier\RozierApp;

class LoginRequestController extends RozierApp
{
    use LoginRequestTrait;

    private LoggerInterface $logger;
    private UrlGeneratorInterface $urlGenerator;

    /**
     * @param LoggerInterface $logger
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(LoggerInterface $logger, UrlGeneratorInterface $urlGenerator)
    {
        $this->logger = $logger;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @param Request $request
     *
     * @return Response
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function indexAction(Request $request)
    {
        $form = $this->createForm(LoginRequestForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $this->sendConfirmationEmail(
                    $form,
                    $this->em(),
                    $this->logger,
                    $this->urlGenerator
                );
            }
            /*
             * Always go to confirm even if email is not valid
             * for avoiding database sniffing.
             */
            return $this->redirectToRoute(
                'loginRequestConfirmPage'
            );
        }

        $this->assignation['form'] = $form->createView();

        return $this->render('login/request.html.twig', $this->assignation);
    }

    /**
     * @return Response
     */
    public function confirmAction()
    {
        return $this->render('login/requestConfirm.html.twig', $this->assignation);
    }
}
