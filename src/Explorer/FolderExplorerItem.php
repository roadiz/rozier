<?php

declare(strict_types=1);

namespace Themes\Rozier\Explorer;

use RZ\Roadiz\CoreBundle\Entity\Folder;
use RZ\Roadiz\CoreBundle\Explorer\AbstractExplorerItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class FolderExplorerItem extends AbstractExplorerItem
{
    private Folder $folder;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(Folder $folder, UrlGeneratorInterface $urlGenerator)
    {
        $this->folder = $folder;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        return $this->folder->getId();
    }

    /**
     * @inheritDoc
     */
    public function getAlternativeDisplayable(): ?string
    {
        /** @var Folder|null $parent */
        $parent = $this->folder->getParent();
        if (null !== $parent) {
            return $parent->getTranslatedFolders()->first()->getName();
        }
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getDisplayable(): string
    {
        return $this->folder->getTranslatedFolders()->first()->getName();
    }

    /**
     * @inheritDoc
     */
    public function getOriginal()
    {
        return $this->folder;
    }

    protected function getEditItemPath(): ?string
    {
        return $this->urlGenerator->generate('foldersEditPage', [
            'folderId' => $this->folder->getId()
        ]);
    }
}
