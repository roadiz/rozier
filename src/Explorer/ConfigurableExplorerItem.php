<?php

declare(strict_types=1);

namespace Themes\Rozier\Explorer;

use Doctrine\Common\Collections\Collection;
use RZ\Roadiz\Core\AbstractEntities\PersistableInterface;
use RZ\Roadiz\CoreBundle\Explorer\AbstractExplorerItem;
use RZ\Roadiz\Documents\MediaFinders\EmbedFinderFactory;
use RZ\Roadiz\Documents\Models\DocumentInterface;
use RZ\Roadiz\Documents\Renderer\RendererInterface;
use RZ\Roadiz\Documents\UrlGenerators\DocumentUrlGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\UnicodeString;
use Themes\Rozier\Models\DocumentModel;

final class ConfigurableExplorerItem extends AbstractExplorerItem
{
    private PersistableInterface $entity;
    private array $configuration;
    private RendererInterface $renderer;
    private DocumentUrlGeneratorInterface $documentUrlGenerator;
    private UrlGeneratorInterface $urlGenerator;
    private ?EmbedFinderFactory $embedFinderFactory;

    public function __construct(
        PersistableInterface $entity,
        array &$configuration,
        RendererInterface $renderer,
        DocumentUrlGeneratorInterface $documentUrlGenerator,
        UrlGeneratorInterface $urlGenerator,
        ?EmbedFinderFactory $embedFinderFactory = null
    ) {
        $this->entity = $entity;
        $this->configuration = $configuration;
        $this->renderer = $renderer;
        $this->documentUrlGenerator = $documentUrlGenerator;
        $this->urlGenerator = $urlGenerator;
        $this->embedFinderFactory = $embedFinderFactory;
    }

    /**
     * @inheritDoc
     */
    public function getId(): int|string
    {
        return $this->entity->getId() ?? throw new \RuntimeException('Entity must have an ID');
    }

    /**
     * @inheritDoc
     */
    public function getAlternativeDisplayable(): ?string
    {
        $alt = $this->configuration['classname'];
        if (!empty($this->configuration['alt_displayable'])) {
            $alt = call_user_func([$this->entity, $this->configuration['alt_displayable']]);
            if ($alt instanceof \DateTimeInterface) {
                $alt = $alt->format('c');
            }
        }
        return (new UnicodeString($alt ?? ''))->truncate(30, '…')->toString();
    }

    /**
     * @inheritDoc
     */
    public function getDisplayable(): string
    {
        $displayable = call_user_func([$this->entity, $this->configuration['displayable']]);
        if ($displayable instanceof \DateTimeInterface) {
            $displayable = $displayable->format('c');
        }
        return (new UnicodeString($displayable ?? ''))->truncate(30, '…')->toString();
    }

    /**
     * @inheritDoc
     */
    public function getOriginal(): PersistableInterface
    {
        return $this->entity;
    }

    protected function getThumbnail(): ?array
    {
        /** @var DocumentInterface|null $thumbnail */
        $thumbnail = null;
        if (!empty($this->configuration['thumbnail'])) {
            $thumbnail = call_user_func([$this->entity, $this->configuration['thumbnail']]);
            if ($thumbnail instanceof Collection && $thumbnail->count() > 0 && $thumbnail->first() instanceof DocumentInterface) {
                $thumbnail = $thumbnail->first();
            } elseif (is_array($thumbnail) && count($thumbnail) > 0 && $thumbnail[0] instanceof DocumentInterface) {
                $thumbnail = $thumbnail[0];
            }
        }

        if ($thumbnail instanceof DocumentInterface) {
            $thumbnailModel = new DocumentModel(
                $thumbnail,
                $this->renderer,
                $this->documentUrlGenerator,
                $this->urlGenerator,
                $this->embedFinderFactory
            );
            $thumbnail = $thumbnailModel->toArray();
        } else {
            $thumbnail = null;
        }

        return $thumbnail;
    }

    protected function getEditItemPath(): ?string
    {
        return null;
    }
}
