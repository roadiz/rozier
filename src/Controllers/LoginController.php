<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use RZ\Roadiz\CoreBundle\Bag\Settings;
use RZ\Roadiz\CoreBundle\Entity\Document;
use RZ\Roadiz\Documents\MediaFinders\RandomImageFinder;
use RZ\Roadiz\Documents\UrlGenerators\DocumentUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Themes\Rozier\RozierApp;

class LoginController extends RozierApp
{
    public function __construct(
        private DocumentUrlGeneratorInterface $documentUrlGenerator,
        private RandomImageFinder $randomImageFinder,
        private Settings $settingsBag
    ) {
    }

    public function imageAction(Request $request): Response
    {
        $response = new JsonResponse();
        $response->setPublic();
        $response->setMaxAge(600);

        if (null !== $document = $this->settingsBag->getDocument('login_image')) {
            if (
                $document instanceof Document &&
                !$document->isPrivate() &&
                $document->isProcessable()
            ) {
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
                return $response;
            }
        }

        $feed = $this->randomImageFinder->getRandomBySearch('road');
        $url = null;

        if (null !== $feed) {
            $url = $feed['url'] ?? $feed['urls']['regular'] ?? $feed['urls']['full'] ?? $feed['urls']['raw'] ?? null;
        }
        $response->setData([
            'url' => '/themes/Rozier/static/assets/img/default_login.jpg'
        ]);
        return $response;
    }
}
