<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use Psr\Log\LoggerInterface;
use RZ\Roadiz\Core\Entities\Document;
use RZ\Roadiz\Core\Entities\Role;
use RZ\Roadiz\OpenId\Exception\DiscoveryNotAvailableException;
use RZ\Roadiz\OpenId\OAuth2LinkGenerator;
use RZ\Roadiz\Utils\Asset\Packages;
use RZ\Roadiz\Utils\MediaFinders\RandomImageFinder;
use RZ\Roadiz\Utils\UrlGenerators\DocumentUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Themes\Rozier\Forms\LoginType;
use Themes\Rozier\RozierApp;

/**
 * @package Themes\Rozier\Controllers
 */
class LoginController extends RozierApp
{
    private AuthenticationUtils $authenticationUtils;
    private OAuth2LinkGenerator $oAuth2LinkGenerator;
    private LoggerInterface $logger;
    private DocumentUrlGeneratorInterface $documentUrlGenerator;
    private Packages $packages;
    private RandomImageFinder $randomImageFinder;

    /**
     * @param AuthenticationUtils $authenticationUtils
     * @param OAuth2LinkGenerator $oAuth2LinkGenerator
     * @param LoggerInterface $logger
     * @param DocumentUrlGeneratorInterface $documentUrlGenerator
     * @param Packages $packages
     * @param RandomImageFinder $randomImageFinder
     */
    public function __construct(
        AuthenticationUtils $authenticationUtils,
        OAuth2LinkGenerator $oAuth2LinkGenerator,
        LoggerInterface $logger,
        DocumentUrlGeneratorInterface $documentUrlGenerator,
        Packages $packages,
        RandomImageFinder $randomImageFinder
    ) {
        $this->authenticationUtils = $authenticationUtils;
        $this->oAuth2LinkGenerator = $oAuth2LinkGenerator;
        $this->logger = $logger;
        $this->documentUrlGenerator = $documentUrlGenerator;
        $this->packages = $packages;
        $this->randomImageFinder = $randomImageFinder;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        if ($this->isGranted(Role::ROLE_BACKEND_USER)) {
            return $this->redirectToRoute('adminHomePage');
        }

        $form = $this->createForm(LoginType::class);
        $this->assignation['form'] = $form->createView();

        $this->assignation['last_username'] = $this->authenticationUtils->getLastUsername();
        $this->assignation['error'] = $this->authenticationUtils->getLastAuthenticationError();

        try {
            if ($this->oAuth2LinkGenerator->isSupported($request)) {
                $this->assignation['openid_button_label'] = $this->getSettingsBag()->get('openid_button_label');
                $this->assignation['openid'] = $this->oAuth2LinkGenerator->generate(
                    $request,
                    $this->generateUrl('loginCheckPage', [], UrlGeneratorInterface::ABSOLUTE_URL)
                );
            }
        } catch (DiscoveryNotAvailableException $exception) {
            $this->logger->error($exception->getMessage());
        }

        return $this->render('@RoadizRozier/login/login.html.twig', $this->assignation);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function checkAction(Request $request)
    {
        return $this->render('@RoadizRozier/login/check.html.twig', $this->assignation);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function logoutAction(Request $request)
    {
        return $this->render('@RoadizRozier/login/check.html.twig', $this->assignation);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function imageAction(Request $request)
    {
        $response = new JsonResponse();
        if (null !== $document = $this->getSettingsBag()->getDocument('login_image')) {
            if ($document instanceof Document && $document->isProcessable()) {
                $this->documentUrlGenerator->setDocument($document);
                $this->documentUrlGenerator->setOptions([
                    'width' => 1920,
                    'height' => 1920,
                    'quality' => 80,
                    'sharpen' => 5,
                ]);
                $response->setData([
                    'url' => $this->documentUrlGenerator->getUrl()
                ]);
                return $this->makeResponseCachable($request, $response, 60, true);
            }
        }

        $feed = $this->randomImageFinder->getRandomBySearch('road');
        $url = null;

        if (null !== $feed) {
            $url = $feed['url'] ?? $feed['urls']['regular'] ?? $feed['urls']['full'] ?? $feed['urls']['raw'] ?? null;
        }
        $response->setData([
            'url' => $url ?? $this->packages->getUrl('themes/Rozier/static/assets/img/default_login.jpg')
        ]);
        return $this->makeResponseCachable($request, $response, 60, true);
    }
}
