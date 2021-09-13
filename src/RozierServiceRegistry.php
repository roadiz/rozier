<?php
declare(strict_types=1);

namespace Themes\Rozier;

use Doctrine\Persistence\ManagerRegistry;
use RZ\Roadiz\Core\Authorization\Chroot\NodeChrootResolver;
use RZ\Roadiz\Core\Bags\Settings;
use RZ\Roadiz\Core\Entities\SettingGroup;
use RZ\Roadiz\Core\Models\DocumentInterface;
use Themes\Rozier\Widgets\FolderTreeWidget;
use Themes\Rozier\Widgets\NodeTreeWidget;
use Themes\Rozier\Widgets\TagTreeWidget;
use Themes\Rozier\Widgets\TreeWidgetFactory;

final class RozierServiceRegistry
{
    private Settings $settingsBag;
    private ManagerRegistry $managerRegistry;
    private TreeWidgetFactory $treeWidgetFactory;
    private NodeChrootResolver $chrootResolver;

    private ?array $settingGroups = null;
    private ?TagTreeWidget $tagTree = null;
    private ?FolderTreeWidget $folderTree = null;
    private ?NodeTreeWidget $nodeTree = null;

    /**
     * @param Settings $settingsBag
     * @param ManagerRegistry $managerRegistry
     * @param TreeWidgetFactory $treeWidgetFactory
     * @param NodeChrootResolver $chrootResolver
     */
    public function __construct(
        Settings $settingsBag,
        ManagerRegistry $managerRegistry,
        TreeWidgetFactory $treeWidgetFactory,
        NodeChrootResolver $chrootResolver
    ) {
        $this->settingsBag = $settingsBag;
        $this->managerRegistry = $managerRegistry;
        $this->treeWidgetFactory = $treeWidgetFactory;
        $this->chrootResolver = $chrootResolver;
    }

    /**
     * @param string $amount
     * @return int Always return value in Megas
     */
    private function parseSuffixedAmount(string $amount)
    {
        $intValue = intval(preg_replace('#([0-9]+)[s|k|m|g|t]#i', '$1', $amount));

        /*
         * If actual is in Gigas
         */
        if (preg_match('#([0-9]+)g#i', $amount) > 0) {
            return $intValue * 1024;
        } elseif (preg_match('#([0-9]+)t#i', $amount) > 0) {
            return $intValue * 1024 * 1024;
        } elseif (preg_match('#([0-9]+)k#i', $amount) > 0) {
            return $intValue / 1024;
        } else {
            return $intValue;
        }
    }

    /**
     * @return int
     */
    public function getMaxFileSize(): int
    {
        $post_max_size = $this->parseSuffixedAmount(ini_get('post_max_size') ?: '');
        $upload_max_filesize = $this->parseSuffixedAmount(ini_get('upload_max_filesize') ?: '');
        return min($post_max_size, $upload_max_filesize);
    }

    /**
     * @return DocumentInterface|null
     */
    public function getAdminImage(): ?DocumentInterface
    {
        return $this->settingsBag->getDocument('admin_image');
    }

    /**
     * @return array
     */
    public function getSettingGroups(): array
    {
        if (null === $this->settingGroups) {
            $this->settingGroups = $this->managerRegistry->getRepository(SettingGroup::class)
                ->findBy(
                    ['inMenu' => true],
                    ['name' => 'ASC']
                );
        }
        return $this->settingGroups;
    }

    public function getTagTree(): TagTreeWidget
    {
        if (null === $this->tagTree) {
            $this->tagTree = $this->treeWidgetFactory->createTagTree();
        }
        return $this->tagTree;
    }

    public function getFolderTree(): FolderTreeWidget
    {
        if (null === $this->folderTree) {
            $this->folderTree = $this->treeWidgetFactory->createFolderTree();
        }
        return $this->folderTree;
    }

    /**
     * @param mixed $user
     * @return NodeTreeWidget
     */
    public function getNodeTree($user): NodeTreeWidget
    {
        if (null === $this->nodeTree) {
            $this->nodeTree = $this->treeWidgetFactory->createNodeTree(
                $this->chrootResolver->getChroot($user)
            );
        }
        return $this->nodeTree;
    }
}
