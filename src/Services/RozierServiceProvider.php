<?php
declare(strict_types=1);

namespace Themes\Rozier\Services;

use Doctrine\Persistence\ManagerRegistry;
use JMS\Serializer\SerializerInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Psr\Log\LoggerInterface;
use RZ\Roadiz\Attribute\Importer\AttributeImporter;
use RZ\Roadiz\CMS\Importers\GroupsImporter;
use RZ\Roadiz\CMS\Importers\NodeTypesImporter;
use RZ\Roadiz\CMS\Importers\RolesImporter;
use RZ\Roadiz\CMS\Importers\SettingsImporter;
use RZ\Roadiz\Core\Authorization\Chroot\NodeChrootResolver;
use RZ\Roadiz\Core\Bags\NodeTypes;
use RZ\Roadiz\Core\Entities\Role;
use RZ\Roadiz\Core\Handlers\HandlerFactoryInterface;
use RZ\Roadiz\Core\Kernel;
use RZ\Roadiz\Core\Serializers\NodeSourceXlsxSerializer;
use RZ\Roadiz\Document\Renderer\RendererInterface;
use RZ\Roadiz\OpenId\OAuth2LinkGenerator;
use RZ\Roadiz\Preview\PreviewResolverInterface;
use RZ\Roadiz\Utils\Asset\Packages;
use RZ\Roadiz\Utils\CustomForm\CustomFormAnswerSerializer;
use RZ\Roadiz\Utils\Doctrine\SchemaUpdater;
use RZ\Roadiz\Utils\Document\DocumentFactory;
use RZ\Roadiz\Utils\MediaFinders\RandomImageFinder;
use RZ\Roadiz\Utils\Node\NodeMover;
use RZ\Roadiz\Utils\Node\NodeNamePolicyInterface;
use RZ\Roadiz\Utils\Node\NodeTranstyper;
use RZ\Roadiz\Utils\Node\UniqueNodeGenerator;
use RZ\Roadiz\Utils\Security\FirewallEntry;
use RZ\Roadiz\Utils\Tag\TagFactory;
use RZ\Roadiz\Utils\UrlGenerators\DocumentUrlGeneratorInterface;
use RZ\Roadiz\Webhook\WebhookDispatcher;
use Symfony\Component\Asset\Context\RequestStackContext;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\AccessMap;
use Symfony\Component\Security\Http\FirewallMap;
use Symfony\Component\Workflow\Registry;
use Themes\Rozier\AjaxControllers\AjaxAbstractFieldsController;
use Themes\Rozier\AjaxControllers\AjaxAttributeValuesController;
use Themes\Rozier\AjaxControllers\AjaxCustomFormFieldsController;
use Themes\Rozier\AjaxControllers\AjaxCustomFormsExplorerController;
use Themes\Rozier\AjaxControllers\AjaxDocumentsExplorerController;
use Themes\Rozier\AjaxControllers\AjaxEntitiesExplorerController;
use Themes\Rozier\AjaxControllers\AjaxExplorerProviderController;
use Themes\Rozier\AjaxControllers\AjaxFoldersController;
use Themes\Rozier\AjaxControllers\AjaxFoldersExplorerController;
use Themes\Rozier\AjaxControllers\AjaxFolderTreeController;
use Themes\Rozier\AjaxControllers\AjaxNodesController;
use Themes\Rozier\AjaxControllers\AjaxNodeTreeController;
use Themes\Rozier\AjaxControllers\AjaxNodeTypeFieldsController;
use Themes\Rozier\AjaxControllers\AjaxNodeTypesController;
use Themes\Rozier\AjaxControllers\AjaxSearchNodesSourcesController;
use Themes\Rozier\AjaxControllers\AjaxSessionMessages;
use Themes\Rozier\AjaxControllers\AjaxTagsController;
use Themes\Rozier\AjaxControllers\AjaxTagTreeController;
use Themes\Rozier\Controllers\Attributes\AttributeController;
use Themes\Rozier\Controllers\Attributes\AttributeGroupController;
use Themes\Rozier\Controllers\CacheController;
use Themes\Rozier\Controllers\CustomForms\CustomFormsController;
use Themes\Rozier\Controllers\CustomForms\CustomFormsUtilsController;
use Themes\Rozier\Controllers\Documents\DocumentsController;
use Themes\Rozier\Controllers\FoldersController;
use Themes\Rozier\Controllers\FontsController;
use Themes\Rozier\Controllers\GroupsController;
use Themes\Rozier\Controllers\GroupsUtilsController;
use Themes\Rozier\Controllers\LoginController;
use Themes\Rozier\Controllers\LoginRequestController;
use Themes\Rozier\Controllers\Nodes\ExportController;
use Themes\Rozier\Controllers\Nodes\NodesAttributesController;
use Themes\Rozier\Controllers\Nodes\NodesController;
use Themes\Rozier\Controllers\Nodes\NodesTreesController;
use Themes\Rozier\Controllers\Nodes\NodesUtilsController;
use Themes\Rozier\Controllers\Nodes\TranstypeController;
use Themes\Rozier\Controllers\Nodes\UrlAliasesController;
use Themes\Rozier\Controllers\NodeTypeFieldsController;
use Themes\Rozier\Controllers\NodeTypes\NodeTypesController;
use Themes\Rozier\Controllers\NodeTypes\NodeTypesUtilsController;
use Themes\Rozier\Controllers\RedirectionsController;
use Themes\Rozier\Controllers\RolesController;
use Themes\Rozier\Controllers\RolesUtilsController;
use Themes\Rozier\Controllers\SchemaController;
use Themes\Rozier\Controllers\SettingGroupsController;
use Themes\Rozier\Controllers\SettingsController;
use Themes\Rozier\Controllers\SettingsUtilsController;
use Themes\Rozier\Controllers\Tags\TagMultiCreationController;
use Themes\Rozier\Controllers\Tags\TagsController;
use Themes\Rozier\Controllers\Tags\TagsUtilsController;
use Themes\Rozier\Controllers\TranslationsController;
use Themes\Rozier\Controllers\WebhookController;
use Themes\Rozier\Events\NodeDuplicationSubscriber;
use Themes\Rozier\Events\NodeRedirectionSubscriber;
use Themes\Rozier\Events\NodesSourcesUniversalSubscriber;
use Themes\Rozier\Events\NodesSourcesUrlSubscriber;
use Themes\Rozier\Events\TranslationSubscriber;
use Themes\Rozier\Forms\FolderCollectionType;
use Themes\Rozier\Forms\LoginType;
use Themes\Rozier\Forms\Node\AddNodeType;
use Themes\Rozier\Forms\Node\TranslateNodeType;
use Themes\Rozier\Forms\NodeSource\NodeSourceCustomFormType;
use Themes\Rozier\Forms\NodeSource\NodeSourceDocumentType;
use Themes\Rozier\Forms\NodeSource\NodeSourceJoinType;
use Themes\Rozier\Forms\NodeSource\NodeSourceNodeType;
use Themes\Rozier\Forms\NodeSource\NodeSourceProviderType;
use Themes\Rozier\Forms\NodeSource\NodeSourceType;
use Themes\Rozier\Forms\NodeTagsType;
use Themes\Rozier\Forms\NodeTreeType;
use Themes\Rozier\Forms\NodeType;
use Themes\Rozier\Forms\NodeTypeFieldType;
use Themes\Rozier\Forms\TranstypeType;
use Themes\Rozier\RozierServiceRegistry;
use Themes\Rozier\Serialization\DocumentThumbnailSerializeSubscriber;
use Themes\Rozier\Widgets\TreeWidgetFactory;
use Twig\Loader\FilesystemLoader;

final class RozierServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritDoc
     */
    public function register(Container $container)
    {
        /*
         * Overrideable form types
         */
        $container['rozier.form_type.add_node'] = AddNodeType::class;
        $container['rozier.form_type.node'] = NodeType::class;


        $container[RozierServiceRegistry::class] = function (Container $c) {
            return new RozierServiceRegistry(
                $c['settingsBag'],
                $c[ManagerRegistry::class],
                $c[TreeWidgetFactory::class],
                $c[NodeChrootResolver::class],
                $c['backoffice.entries']
            );
        };

        $container[TreeWidgetFactory::class] = function (Container $c) {
            return new TreeWidgetFactory($c['request_stack'], $c[ManagerRegistry::class]);
        };

        $container[NodeTreeType::class] = function (Container $c) {
            return new NodeTreeType(
                $c['securityAuthorizationChecker'],
                $c['request_stack'],
                $c[ManagerRegistry::class],
                $c[TreeWidgetFactory::class]
            );
        };

        $container[LoginType::class] = function (Container $c) {
            return new LoginType(
                $c['router'],
                $c['request_stack'],
            );
        };

        $container[NodeTypeFieldType::class] = function (Container $c) {
            return new NodeTypeFieldType($c['config']['inheritance']['type']);
        };

        $container[AddNodeType::class] = function (Container $c) {
            return new AddNodeType($c[ManagerRegistry::class]);
        };

        $container[TranslateNodeType::class] = function (Container $c) {
            return new TranslateNodeType($c[ManagerRegistry::class]);
        };

        $container[FolderCollectionType::class] = function (Container $c) {
            return new FolderCollectionType($c[ManagerRegistry::class]);
        };

        $container[NodeTagsType::class] = function (Container $c) {
            return new NodeTagsType($c[ManagerRegistry::class]);
        };

        $container[TranstypeType::class] = function (Container $c) {
            return new TranstypeType($c[ManagerRegistry::class]);
        };

        $container[NodeSourceCustomFormType::class] = function (Container $c) {
            return new NodeSourceCustomFormType($c[ManagerRegistry::class], $c['node.handler']);
        };

        $container[NodeSourceNodeType::class] = function (Container $c) {
            return new NodeSourceNodeType($c[ManagerRegistry::class], $c['node.handler']);
        };

        $container[NodeSourceDocumentType::class] = function (Container $c) {
            return new NodeSourceDocumentType($c[ManagerRegistry::class], $c['nodes_sources.handler']);
        };

        $container[NodeSourceJoinType::class] = function (Container $c) {
            return new NodeSourceJoinType($c[ManagerRegistry::class]);
        };

        $container[NodeSourceProviderType::class] = function (Container $c) {
            return new NodeSourceProviderType($c[ManagerRegistry::class], new \Pimple\Psr11\Container($c));
        };

        $container[NodeSourceType::class] = function (Container $c) {
            return new NodeSourceType($c[ManagerRegistry::class]);
        };

        $container->extend('serializer.subscribers', function (array $subscribers, $c) {
            $subscribers[] = new DocumentThumbnailSerializeSubscriber($c['document.url_generator']);
            return $subscribers;
        });

        $container->extend('backoffice.entries', function (array $entries, $c) {
            /** @var UrlGenerator $urlGenerator */
            $urlGenerator = $c['urlGenerator'];
            $entries['dashboard'] = [
                'name' => 'dashboard',
                'path' => $urlGenerator->generate('adminHomePage'),
                'icon' => 'uk-icon-rz-dashboard',
                'roles' => null,
                'subentries' => null,
            ];
            $entries['nodes'] = [
                'name' => 'nodes',
                'path' => null,
                'icon' => 'uk-icon-rz-global-nodes',
                'roles' => ['ROLE_ACCESS_NODES'],
                'subentries' => [
                    'all.nodes' => [
                        'name' => 'all.nodes',
                        'path' => $urlGenerator->generate('nodesHomePage'),
                        'icon' => 'uk-icon-rz-all-nodes',
                        'roles' => null,
                    ],
                    'draft.nodes' => [
                        'name' => 'draft.nodes',
                        'path' => $urlGenerator->generate('nodesHomeDraftPage'),
                        'icon' => 'uk-icon-rz-draft-nodes',
                        'roles' => null,
                    ],
                    'pending.nodes' => [
                        'name' => 'pending.nodes',
                        'path' => $urlGenerator->generate('nodesHomePendingPage'),
                        'icon' => 'uk-icon-rz-pending-nodes',
                        'roles' => null,
                    ],
                    'archived.nodes' => [
                        'name' => 'archived.nodes',
                        'path' => $urlGenerator->generate('nodesHomeArchivedPage'),
                        'icon' => 'uk-icon-rz-archives-nodes',
                        'roles' => null,
                    ],
                    'deleted.nodes' => [
                        'name' => 'deleted.nodes',
                        'path' => $urlGenerator->generate('nodesHomeDeletedPage'),
                        'icon' => 'uk-icon-rz-deleted-nodes',
                        'roles' => null,
                    ],
                    'search.nodes' => [
                        'name' => 'search.nodes',
                        'path' => $urlGenerator->generate('searchNodePage'),
                        'icon' => 'uk-icon-search',
                        'roles' => null,
                    ],
                ],
            ];
            $entries['manage.documents'] = [
                'name' => 'manage.documents',
                'path' => $urlGenerator->generate('documentsHomePage'),
                'icon' => 'uk-icon-rz-documents',
                'roles' => ['ROLE_ACCESS_DOCUMENTS'],
                'subentries' => null,
            ];
            $entries['manage.tags'] = [
                'name' => 'manage.tags',
                'path' => $urlGenerator->generate('tagsHomePage'),
                'icon' => 'uk-icon-rz-tags',
                'roles' => ['ROLE_ACCESS_TAGS'],
                'subentries' => null,
            ];
            $entries['construction'] = [
                'name' => 'construction',
                'path' => null,
                'icon' => 'uk-icon-rz-construction',
                'roles' => [
                    'ROLE_ACCESS_NODETYPES',
                    'ROLE_ACCESS_ATTRIBUTES',
                    'ROLE_ACCESS_TRANSLATIONS',
                    'ROLE_ACCESS_THEMES',
                    'ROLE_ACCESS_FONTS',
                    'ROLE_ACCESS_REDIRECTIONS',
                    'ROLE_ACCESS_WEBHOOKS',
                ],
                'subentries' => [
                    'manage.nodeTypes' => [
                        'name' => 'manage.nodeTypes',
                        'path' => $urlGenerator->generate('nodeTypesHomePage'),
                        'icon' => 'uk-icon-rz-manage-nodes',
                        'roles' => ['ROLE_ACCESS_NODETYPES'],
                    ],
                    'manage.attributes' => [
                        'name' => 'manage.attributes',
                        'path' => $urlGenerator->generate('attributesHomePage'),
                        'icon' => 'uk-icon-server',
                        'roles' => ['ROLE_ACCESS_ATTRIBUTES'],
                    ],
                    'manage.translations' => [
                        'name' => 'manage.translations',
                        'path' => $urlGenerator->generate('translationsHomePage'),
                        'icon' => 'uk-icon-rz-translate',
                        'roles' => ['ROLE_ACCESS_TRANSLATIONS'],
                    ],
                    'manage.fonts' => [
                        'name' => 'manage.fonts',
                        'path' => $urlGenerator->generate('fontsHomePage'),
                        'icon' => 'uk-icon-rz-fontes',
                        'roles' => ['ROLE_ACCESS_FONTS'],
                    ],
                    'manage.redirections' => [
                        'name' => 'manage.redirections',
                        'path' => $urlGenerator->generate('redirectionsHomePage'),
                        'icon' => 'uk-icon-compass',
                        'roles' => ['ROLE_ACCESS_REDIRECTIONS'],
                    ],
                    'manage.webhooks' => [
                        'name' => 'manage.webhooks',
                        'path' => $urlGenerator->generate('webhooksHomePage'),
                        'icon' => 'uk-icon-space-shuttle',
                        'roles' => ['ROLE_ACCESS_WEBHOOKS'],
                    ]
                ],
            ];

            $entries['user.system'] = [
                'name' => 'user.system',
                'path' => null,
                'icon' => 'uk-icon-rz-users',
                'roles' => ['ROLE_ACCESS_USERS', 'ROLE_ACCESS_ROLES', 'ROLE_ACCESS_GROUPS'],
                'subentries' => [
                    'manage.users' => [
                        'name' => 'manage.users',
                        'path' => $urlGenerator->generate('usersHomePage'),
                        'icon' => 'uk-icon-rz-user',
                        'roles' => ['ROLE_ACCESS_USERS'],
                    ],
                    'manage.roles' => [
                        'name' => 'manage.roles',
                        'path' => $urlGenerator->generate('rolesHomePage'),
                        'icon' => 'uk-icon-rz-roles',
                        'roles' => ['ROLE_ACCESS_ROLES'],
                    ],
                    'manage.groups' => [
                        'name' => 'manage.groups',
                        'path' => $urlGenerator->generate('groupsHomePage'),
                        'icon' => 'uk-icon-rz-groups',
                        'roles' => ['ROLE_ACCESS_GROUPS'],
                    ],
                ],
            ];

            $entries['interactions'] = [
                'name' => 'interactions',
                'path' => null,
                'icon' => 'uk-icon-rz-interactions',
                'roles' => [
                    'ROLE_ACCESS_CUSTOMFORMS',
                    'ROLE_ACCESS_MANAGE_SUBSCRIBERS',
                    'ROLE_ACCESS_COMMENTS',
                ],
                'subentries' => [
                    'manage.customForms' => [
                        'name' => 'manage.customForms',
                        'path' => $urlGenerator->generate('customFormsHomePage'),
                        'icon' => 'uk-icon-rz-surveys',
                        'roles' => ['ROLE_ACCESS_CUSTOMFORMS'],
                    ],
                ],
            ];

            $entries['settings'] = [
                'name' => 'settings',
                'path' => null,
                'icon' => 'uk-icon-rz-settings',
                'roles' => ['ROLE_ACCESS_SETTINGS'],
                'subentries' => [
                    'all.settings' => [
                        'name' => 'all.settings',
                        'path' => $urlGenerator->generate('settingsHomePage'),
                        'icon' => 'uk-icon-rz-settings-general',
                        'roles' => null,
                    ],
                    /*
                     * This entry is dynamic
                     */
                    'setting.groups.dynamic' => [
                        'name' => 'setting.groups.dynamic',
                        'path' => 'settingGroupsSettingsPage',
                        'icon' => 'uk-icon-rz-settings-group',
                        'roles' => null,
                    ],
                    'setting.groups' => [
                        'name' => 'setting.groups',
                        'path' => $urlGenerator->generate('settingGroupsHomePage'),
                        'icon' => 'uk-icon-rz-settings-groups',
                        'roles' => null,
                    ],
                ],
            ];

            return $entries;
        });

        $container->extend('twig.loaderFileSystem', function (FilesystemLoader $loader) {
            $loader->prependPath(dirname(__DIR__) . '/Resources/views', 'Rozier');
            $loader->prependPath(dirname(__DIR__) . '/Resources/views');
            return $loader;
        });

        $container->extend('assetPackages', function (Packages $packages, Container $c) {
            $packages->addPackage('Rozier', new PathPackage(
                'themes/Rozier/static',
                $c['versionStrategy'],
                new RequestStackContext($c['requestStack'])
            ));
            return $packages;
        });

        /*
         * Force login pages (connection, logout and reset) to be public
         * before rz-admin base pattern to be restricted
         */
        $container->extend('accessMap', function (AccessMap $accessMap, Container $c) {
            $accessMap->add(
                new RequestMatcher('^/rz-admin/login'),
                ['IS_AUTHENTICATED_ANONYMOUSLY']
            );
            $accessMap->add(
                new RequestMatcher('^/rz-admin/logout'),
                ['IS_AUTHENTICATED_ANONYMOUSLY']
            );
            return $accessMap;
        });

        $container->extend('firewallMap', function (FirewallMap $firewallMap, Container $c) {
            /*
            * Add default backend firewall entry.
            */
            $firewallBasePattern = '^/rz-admin';
            $firewallBasePath = '/rz-admin';
            $firewallLogin = $firewallBasePath . '/login';
            $firewallLogout = $firewallBasePath . '/logout';
            $firewallLoginCheck = $firewallBasePath . '/login_check';
            $firewallBaseRole = Role::ROLE_BACKEND_USER;

            $firewallEntry = new FirewallEntry(
                $c,
                $firewallBasePattern,
                $firewallBasePath,
                $firewallLogin,
                $firewallLogout,
                $firewallLoginCheck,
                $firewallBaseRole
            );
            $firewallEntry->withSwitchUserListener()
                ->withAnonymousAuthenticationListener()
                ->withOAuth2AuthenticationListener()
                ->withReferer();

            $firewallMap->add(
                $firewallEntry->getRequestMatcher(),
                $firewallEntry->getListeners(),
                $firewallEntry->getExceptionListener(true)
            );

            return $firewallMap;
        });

        /*
         * Controllers as services
         */
        $container[AjaxAttributeValuesController::class] = function (Container $c) {
            return new AjaxAttributeValuesController($c['csrfTokenManager']);
        };
        $container[AjaxCustomFormFieldsController::class] = function (Container $c) {
            return new AjaxCustomFormFieldsController($c['factory.handler'], $c['csrfTokenManager']);
        };
        $container[AjaxCustomFormsExplorerController::class] = function (Container $c) {
            return new AjaxCustomFormsExplorerController($c['csrfTokenManager']);
        };
        $container[AjaxDocumentsExplorerController::class] = function (Container $c) {
            return new AjaxDocumentsExplorerController($c['csrfTokenManager']);
        };
        $container[AjaxEntitiesExplorerController::class] = function (Container $c) {
            return new AjaxEntitiesExplorerController($c['csrfTokenManager']);
        };
        $container[AjaxExplorerProviderController::class] = function (Container $c) {
            return new AjaxExplorerProviderController(new \Pimple\Psr11\Container($c), $c['csrfTokenManager']);
        };
        $container[AjaxFoldersController::class] = function (Container $c) {
            return new AjaxFoldersController($c['factory.handler'], $c['csrfTokenManager']);
        };
        $container[AjaxFoldersExplorerController::class] = function (Container $c) {
            return new AjaxFoldersExplorerController($c['csrfTokenManager']);
        };
        $container[AjaxFolderTreeController::class] = function (Container $c) {
            return new AjaxFolderTreeController($c[TreeWidgetFactory::class], $c['csrfTokenManager']);
        };
        $container[AjaxNodesController::class] = function (Container $c) {
            return new AjaxNodesController(
                $c[NodeNamePolicyInterface::class],
                $c['logger'],
                $c[NodeMover::class],
                $c[NodeChrootResolver::class],
                $c['workflow.registry'],
                $c['utils.uniqueNodeGenerator'],
                $c['csrfTokenManager']
            );
        };
        $container[AjaxNodeTreeController::class] = function (Container $c) {
            return new AjaxNodeTreeController(
                $c[NodeChrootResolver::class],
                $c[TreeWidgetFactory::class],
                $c['nodeTypesBag'],
                $c['csrfTokenManager']
            );
        };
        $container[AjaxNodeTypeFieldsController::class] = function (Container $c) {
            return new AjaxNodeTypeFieldsController($c['factory.handler'], $c['csrfTokenManager']);
        };
        $container[AjaxNodeTypesController::class] = function (Container $c) {
            return new AjaxNodeTypesController($c['factory.handler'], $c['csrfTokenManager']);
        };
        $container[AjaxSearchNodesSourcesController::class] = function (Container $c) {
            return new AjaxSearchNodesSourcesController($c['document.url_generator'], $c['csrfTokenManager']);
        };
        $container[AjaxSessionMessages::class] = function (Container $c) {
            return new AjaxSessionMessages($c['csrfTokenManager']);
        };
        $container[AjaxTagsController::class] = function (Container $c) {
            return new AjaxTagsController($c['factory.handler'], $c['csrfTokenManager']);
        };
        $container[AjaxTagTreeController::class] = function (Container $c) {
            return new AjaxTagTreeController($c[TreeWidgetFactory::class], $c['csrfTokenManager']);
        };

        /*
         *
         */
        $container[AttributeController::class] = function (Container $c) {
            return new AttributeController($c[AttributeImporter::class], $c['serializer'], $c['router']);
        };
        $container[AttributeGroupController::class] = function (Container $c) {
            return new AttributeGroupController($c['serializer'], $c['router']);
        };
        $container[CustomFormsController::class] = function (Container $c) {
            return new CustomFormsController($c['serializer'], $c['router']);
        };
        $container[CustomFormsUtilsController::class] = function (Container $c) {
            return new CustomFormsUtilsController($c[CustomFormAnswerSerializer::class]);
        };
        $container[DocumentsController::class] = function (Container $c) {
            return new DocumentsController(
                $c['document.platforms'],
                $c['assetPackages'],
                $c['factory.handler'],
                $c['logger'],
                $c[RandomImageFinder::class],
                $c['document.factory'],
                $c[RendererInterface::class],
                $c['document.url_generator'],
                $c['router'],
                $c['interventionRequestSupportsWebP']
            );
        };
        $container[ExportController::class] = function (Container $c) {
            return new ExportController($c[NodeSourceXlsxSerializer::class]);
        };
        $container[NodesAttributesController::class] = function (Container $c) {
            return new NodesAttributesController($c['formFactory']);
        };
        $container[NodesController::class] = function (Container $c) {
            return new NodesController(
                $c[NodeChrootResolver::class],
                $c[NodeMover::class],
                $c['securityAuthorizationChecker'],
                $c['workflow.registry'],
                $c['factory.handler'],
                $c['utils.uniqueNodeGenerator'],
                $c['rozier.form_type.node'],
                $c['rozier.form_type.add_node']
            );
        };
        $container[NodesTreesController::class] = function (Container $c) {
            return new NodesTreesController(
                $c[NodeChrootResolver::class],
                $c[TreeWidgetFactory::class],
                $c['formFactory'],
                $c['factory.handler'],
                $c['workflow.registry']
            );
        };
        $container[NodesUtilsController::class] = function (Container $c) {
            return new NodesUtilsController($c[NodeNamePolicyInterface::class]);
        };
        $container[TranstypeController::class] = function (Container $c) {
            return new TranstypeController($c[NodeTranstyper::class]);
        };
        $container[UrlAliasesController::class] = function (Container $c) {
            return new UrlAliasesController($c['formFactory']);
        };
        $container[NodeTypesController::class] = function (Container $c) {
            return new NodeTypesController($c['factory.handler']);
        };
        $container[NodeTypesUtilsController::class] = function (Container $c) {
            return new NodeTypesUtilsController(
                $c['serializer'],
                $c['nodeTypesBag'],
                $c[NodeTypesImporter::class]
            );
        };
        $container[TagMultiCreationController::class] = function (Container $c) {
            return new TagMultiCreationController($c[TagFactory::class]);
        };
        $container[TagsController::class] = function (Container $c) {
            return new TagsController(
                $c['formFactory'],
                $c['factory.handler'],
                $c[TreeWidgetFactory::class]
            );
        };
        $container[TagsUtilsController::class] = function (Container $c) {
            return new TagsUtilsController($c['serializer']);
        };
        $container[CacheController::class] = function (Container $c) {
            return new CacheController(
                $c['kernel'],
                $c['logger'],
                $c['nodesSourcesUrlCacheProvider']
            );
        };
        $container[FoldersController::class] = function (Container $c) {
            return new FoldersController($c['assetPackages']);
        };
        $container[FontsController::class] = function (Container $c) {
            return new FontsController(
                $c['assetPackages'],
                $c['serializer'],
                $c['router']
            );
        };
        $container[GroupsController::class] = function (Container $c) {
            return new GroupsController($c['serializer'], $c['router']);
        };
        $container[GroupsUtilsController::class] = function (Container $c) {
            return new GroupsUtilsController($c['serializer'], $c[GroupsImporter::class]);
        };
        $container[LoginController::class] = function (Container $c) {
            return new LoginController(
                $c['securityAuthenticationUtils'],
                $c[OAuth2LinkGenerator::class],
                $c['logger'],
                $c['document.url_generator'],
                $c['assetPackages'],
                $c[RandomImageFinder::class]
            );
        };
        $container[LoginRequestController::class] = function (Container $c) {
            return new LoginRequestController($c['logger'], $c['router']);
        };
        $container[NodeTypeFieldsController::class] = function (Container $c) {
            return new NodeTypeFieldsController($c['factory.handler']);
        };
        $container[RedirectionsController::class] = function (Container $c) {
            return new RedirectionsController($c['serializer'], $c['router']);
        };
        $container[RolesController::class] = function (Container $c) {
            return new RolesController($c['serializer'], $c['router']);
        };
        $container[RolesUtilsController::class] = function (Container $c) {
            return new RolesUtilsController($c['serializer'], $c[RolesImporter::class]);
        };
        $container[SchemaController::class] = function (Container $c) {
            return new SchemaController($c[SchemaUpdater::class], $c['kernel']);
        };
        $container[SettingGroupsController::class] = function (Container $c) {
            return new SettingGroupsController($c['serializer'], $c['router']);
        };
        $container[SettingsController::class] = function (Container $c) {
            return new SettingsController($c['formFactory']);
        };
        $container[SettingsUtilsController::class] = function (Container $c) {
            return new SettingsUtilsController($c['serializer'], $c[SettingsImporter::class]);
        };
        $container[TranslationsController::class] = function (Container $c) {
            return new TranslationsController($c['factory.handler']);
        };
        $container[WebhookController::class] = function (Container $c) {
            return new WebhookController(
                $c[WebhookDispatcher::class],
                $c['serializer'],
                $c['router']
            );
        };
    }
}
