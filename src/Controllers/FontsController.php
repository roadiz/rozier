<?php

declare(strict_types=1);

namespace Themes\Rozier\Controllers;

use JMS\Serializer\SerializerInterface;
use RZ\Roadiz\Core\AbstractEntities\PersistableInterface;
use RZ\Roadiz\CoreBundle\Entity\Font;
use RZ\Roadiz\CoreBundle\Event\Font\PreUpdatedFontEvent;
use RZ\Roadiz\Utils\Asset\Packages;
use RZ\Roadiz\Utils\StringHandler;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Themes\Rozier\Forms\FontType;

/**
 * @package Themes\Rozier\Controllers
 */
class FontsController extends AbstractAdminController
{
    private Packages $packages;

    /**
     * @param Packages $packages
     * @param SerializerInterface $serializer
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(
        Packages $packages,
        SerializerInterface $serializer,
        UrlGeneratorInterface $urlGenerator
    ) {
        parent::__construct($serializer, $urlGenerator);
        $this->packages = $packages;
    }

    /**
     * @inheritDoc
     */
    protected function supports(PersistableInterface $item): bool
    {
        return $item instanceof Font;
    }

    /**
     * @inheritDoc
     */
    protected function getNamespace(): string
    {
        return 'font';
    }

    /**
     * @inheritDoc
     */
    protected function createEmptyItem(Request $request): PersistableInterface
    {
        return new Font();
    }

    /**
     * @inheritDoc
     */
    protected function getTemplateFolder(): string
    {
        return '@RoadizRozier/fonts';
    }

    /**
     * @inheritDoc
     */
    protected function getRequiredRole(): string
    {
        return 'ROLE_ACCESS_FONTS';
    }

    /**
     * @inheritDoc
     */
    protected function getEntityClass(): string
    {
        return Font::class;
    }

    /**
     * @inheritDoc
     */
    protected function getFormType(): string
    {
        return FontType::class;
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultOrder(Request $request): array
    {
        return ['name' => 'ASC'];
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultRouteName(): string
    {
        return 'fontsHomePage';
    }

    /**
     * @inheritDoc
     */
    protected function getEditRouteName(): string
    {
        return 'fontsEditPage';
    }

    /**
     * @inheritDoc
     */
    protected function createUpdateEvent(PersistableInterface $item): ?Event
    {
        if ($item instanceof Font) {
            return new PreUpdatedFontEvent($item);
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    protected function getEntityName(PersistableInterface $item): string
    {
        if ($item instanceof Font) {
            return $item->getName();
        }
        throw new \InvalidArgumentException('Item should be instance of ' . $this->getEntityClass());
    }

    /**
     * Return a ZipArchive of requested font.
     *
     * @param Request $request
     * @param int $id
     *
     * @return Response
     */
    public function downloadAction(Request $request, int $id)
    {
        $this->denyAccessUnlessGranted($this->getRequiredRole());

        /** @var Font|null $font */
        $font = $this->em()->find(Font::class, $id);

        if ($font !== null) {
            // Prepare File
            $file = tempnam(sys_get_temp_dir(), "font_" . $font->getId());
            $zip = new \ZipArchive();
            $zip->open($file, \ZipArchive::CREATE);

            if ("" != $font->getEOTFilename()) {
                $zip->addFile($this->packages->getFontsPath($font->getEOTRelativeUrl()), $font->getEOTFilename());
            }
            if ("" != $font->getSVGFilename()) {
                $zip->addFile($this->packages->getFontsPath($font->getSVGRelativeUrl()), $font->getSVGFilename());
            }
            if ("" != $font->getWOFFFilename()) {
                $zip->addFile($this->packages->getFontsPath($font->getWOFFRelativeUrl()), $font->getWOFFFilename());
            }
            if ("" != $font->getWOFF2Filename()) {
                $zip->addFile($this->packages->getFontsPath($font->getWOFF2RelativeUrl()), $font->getWOFF2Filename());
            }
            if ("" != $font->getOTFFilename()) {
                $zip->addFile($this->packages->getFontsPath($font->getOTFRelativeUrl()), $font->getOTFFilename());
            }
            // Close and send to users
            $zip->close();
            $filename = StringHandler::slugify($font->getName() . ' ' . $font->getReadableVariant()) . '.zip';

            return new BinaryFileResponse($file, Response::HTTP_OK, [
                'content-type' => 'application/zip',
                'content-disposition' => 'attachment; filename=' . $filename,
            ], false);
        }

        throw new ResourceNotFoundException();
    }
}
