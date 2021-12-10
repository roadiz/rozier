<?php

declare(strict_types=1);

namespace Themes\Rozier\Models;

use RZ\Roadiz\Core\AbstractEntities\AbstractEntity;
use RZ\Roadiz\Core\Entities\Document;
use RZ\Roadiz\Core\Models\DocumentInterface;
use RZ\Roadiz\Core\Models\HasThumbnailInterface;
use RZ\Roadiz\Document\Renderer\RendererInterface;
use RZ\Roadiz\Utils\UrlGenerators\DocumentUrlGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @package Themes\Rozier\Models
 */
final class DocumentModel implements ModelInterface
{
    public static array $thumbnailArray = [
        "fit" => "40x40",
        "quality" => 50,
        "sharpen" => 5,
        "inline" => false,
    ];
    public static array $thumbnail80Array = [
        "fit" => "80x80",
        "quality" => 50,
        "sharpen" => 5,
        "inline" => false,
    ];
    public static array $previewArray = [
        "width" => 1440,
        "quality" => 80,
        "inline" => false,
        "embed" => true,
    ];
    public static array $largeArray = [
        "noProcess" => true,
    ];

    private DocumentInterface $document;
    private RendererInterface $renderer;
    private DocumentUrlGeneratorInterface $documentUrlGenerator;
    private UrlGeneratorInterface $urlGenerator;

    /**
     * @param DocumentInterface $document
     * @param RendererInterface $renderer
     * @param DocumentUrlGeneratorInterface $documentUrlGenerator
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(
        DocumentInterface $document,
        RendererInterface $renderer,
        DocumentUrlGeneratorInterface $documentUrlGenerator,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->document = $document;
        $this->renderer = $renderer;
        $this->documentUrlGenerator = $documentUrlGenerator;
        $this->urlGenerator = $urlGenerator;
    }

    public function toArray()
    {
        $name = (string) $this->document;
        $thumbnail80Url = null;
        $previewUrl = null;

        if (
            $this->document instanceof Document &&
            $this->document->getDocumentTranslations()->first() &&
            $this->document->getDocumentTranslations()->first()->getName()
        ) {
            $name = $this->document->getDocumentTranslations()->first()->getName();
        }

        $this->documentUrlGenerator->setDocument($this->document);
        $hasThumbnail = false;

        if (
            $this->document instanceof HasThumbnailInterface &&
            $this->document->needsThumbnail() &&
            $this->document->hasThumbnails()
        ) {
            $this->documentUrlGenerator->setDocument($this->document->getThumbnails()->first());
            $hasThumbnail = true;
        }

        if (!empty($this->document->getRelativePath())) {
            $this->documentUrlGenerator->setOptions(DocumentModel::$thumbnail80Array);
            $thumbnail80Url = $this->documentUrlGenerator->getUrl();
            $this->documentUrlGenerator->setOptions(DocumentModel::$previewArray);
            $previewUrl = $this->documentUrlGenerator->getUrl();
        }

        if ($this->document instanceof AbstractEntity) {
            $id = $this->document->getId();
            $editUrl = $this->urlGenerator
                ->generate('documentsEditPage', [
                    'documentId' => $this->document->getId()
                ]);
        } else {
            $id = null;
            $editUrl = null;
        }

        return [
            'id' => $id,
            'filename' => (string) $this->document,
            'name' => $name,
            'hasThumbnail' => $hasThumbnail,
            'isImage' => $this->document->isImage(),
            'isWebp' => $this->document->getMimeType() === 'image/webp',
            'isVideo' => $this->document->isVideo(),
            'isSvg' => $this->document->isSvg(),
            'isEmbed' => $this->document->isEmbed(),
            'isPdf' => $this->document->isPdf(),
            'isPrivate' => $this->document->isPrivate(),
            'shortType' => $this->document->getShortType(),
            'editUrl' => $editUrl,
            'preview' => $previewUrl,
            'preview_html' => $this->renderer->render($this->document, DocumentModel::$previewArray),
            'embedPlatform' => $this->document->getEmbedPlatform(),
            'shortMimeType' => $this->document->getShortMimeType(),
            'thumbnail_80' => $thumbnail80Url,
        ];
    }
}
