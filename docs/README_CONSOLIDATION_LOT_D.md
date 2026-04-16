# Lot D - Consolider Le Nouveau Code

Date : 2026-04-16

References :
- `docs/audit-nettoyage-priorise-depot-local-2026-04-16.md`
- `README_MODERNISATION_V1.md`
- `README_ADMIN_EDITORIAL_NAV_V1.md`
- `README_BLOG.md`
- `backend/README_BOOTSTRAP_I18N.md`
- `backend/README_LOGGING.md`
- `frontend/README_BUILD_PIPELINE.md`

## Objectif

Le Lot D ne correspond ni a un nettoyage ni a une suppression heritage.
Il sert a consolider le vrai code produit deja present dans le worktree et a le sortir en lots courts, lisibles et verifiables.

Perimetre central releve par l'audit :
- `backend/src/**` (`70` fichiers)
- `backend/tests/**` (`57` fichiers)
- `backend/templates/admin/**` (`12` fichiers)
- `backend/sql/editorial/**` (`6` fichiers)
- `frontend/tools/**` (`8` fichiers)

## Regle De Base

Le code neuf ne doit pas etre commite en un seul bloc fourre-tout.
La bonne granularite pour ce depot est :
- un commit par domaine fonctionnel
- les tests associes dans le meme lot
- les README de domaine mis a jour au meme moment

## Ce Que Lot D Ne Couvre Pas

Lot D n'inclut pas :
- les suppressions heritage du Lot C
- l'hygiene assets du Lot B
- les artefacts locaux (`backups`, `snapshots`, `uploads`, caches, `node_modules`)
- les documents de recette ou de preuves qui ne changent pas le runtime

## Decoupage Recommande

### D1. Socle HTTP / bootstrap / i18n / assets

Code principal :
- `backend/src/Http/*`
- `backend/src/I18n/*`
- `backend/src/Assets/ViteAssetManager.php`
- `backend/src/Feed/SitemapService.php`
- `backend/src/Feed/RssFeedService.php`

Fichiers tracked a embarquer avec ce lot si necessaire :
- `backend/core/bootstrap.php`
- `backend/core/lang_bootstrap.php`
- `backend/core/i18n.php`
- `backend/core/helpers.php`
- `backend/public/index.php`
- `backend/templates/partials/scripts_head.php`

Tests a associer :
- `backend/tests/FrontControllerHttpTest.php`
- `backend/tests/ApiLangTest.php`
- `backend/tests/BootstrapLanguageContextTest.php`
- `backend/tests/RoutePathHelperTest.php`
- `backend/tests/RssFeedServiceTest.php`
- `backend/tests/SitemapServiceTest.php`
- `backend/tests/ViteAssetManagerTest.php`
- `backend/tests/SecurityHttpsTest.php`

README de domaine :
- `backend/README_BOOTSTRAP_I18N.md`
- `backend/README_PUBLIC_ENTRYPOINTS.md`
- `README.md`

### D2. Admin editorial / pages / navigation

Code principal :
- `backend/src/Admin/*`
- `backend/src/Admin/Navigation/*`
- `backend/src/Admin/Settings/*`
- `backend/src/Content/*`
- `backend/src/Navigation/*`
- `backend/templates/admin/*`

Migrations SQL liees :
- `backend/sql/editorial/001_editorial.sql`
- `backend/sql/editorial/002_navigation_mega_menu.sql`
- `backend/sql/editorial/004_drop_page_template.sql`
- `backend/sql/editorial/006_navigation_item_label_i18n.sql`

Tests a associer :
- `backend/tests/AdminControllerTest.php`
- `backend/tests/AdminPageServiceTest.php`
- `backend/tests/AdminNavigationServiceTest.php`
- `backend/tests/AdminRouteResolverTest.php`
- `backend/tests/AdminSerializedFormNormalizerTest.php`
- `backend/tests/AdminTranslationSettingsManagerTest.php`
- `backend/tests/AdminLogAlertsSettingsManagerTest.php`
- `backend/tests/Content/PageRepositoryTest.php`
- `backend/tests/NavigationRepositoryTest.php`
- `backend/tests/NavigationViewModelBuilderTest.php`
- `backend/tests/NavigationBuilderActionParserTest.php`
- `backend/tests/NavigationItemPathCodecTest.php`
- `backend/tests/NavigationItemLabelManagerTest.php`
- `backend/tests/MenusHeaderPartialTest.php`
- `backend/tests/MenusFixesPartialTest.php`
- `backend/tests/MenuLoaderTest.php`

README de domaine :
- `README_ADMIN_EDITORIAL_NAV_V1.md`
- `README_MODERNISATION_V1.md`
- `docs/pages-dynamiques.md`

### D3. Blog / discussions / persistance SQL

Code principal :
- `backend/src/Blog/*`
- `backend/src/Database/*`
- `backend/src/Editorial/EditorialImportService.php`
- `backend/src/Social/InstagramFeedService.php`

Migration SQL liee :
- `backend/sql/editorial/005_blog.sql`

Tests a associer :
- `backend/tests/Blog/*`
- `backend/tests/BlogApiControllerTest.php`
- `backend/tests/BlogDiscussionApiControllerTest.php`
- `backend/tests/BlogRouteTest.php`
- `backend/tests/AdminBlogServiceTest.php`
- `backend/tests/EditorialImportServiceTest.php`
- `backend/tests/EditorialStorageModeTest.php`
- `backend/tests/InstagramFeedServiceTest.php`
- `backend/tests/SqlPageStoreTest.php`
- `backend/tests/SqlNavigationStoreTest.php`

README de domaine :
- `README_BLOG.md`
- `README_V1_PREPARATION_DEPLOIEMENT.md`

### D4. Logging / observabilite / exploitation

Code principal :
- `backend/src/Logging/*`

Migration SQL liee :
- `backend/sql/editorial/003_log_entries.sql`

Tests a associer :
- `backend/tests/LoggerFactoryTest.php`
- `backend/tests/SqlLogStoreTest.php`
- `backend/tests/Logging/LogAlertsNotifierTest.php`

README de domaine :
- `backend/README_LOGGING.md`
- `README_SECURITE_ADMIN_V1.md`
- `README_V1_PREPARATION_DEPLOIEMENT.md`

### D5. Outillage frontend / hygiene / publication

Code principal :
- `frontend/tools/publish-build.mjs`
- `frontend/tools/check-budgets.mjs`
- `frontend/tools/check-doc-links.mjs`
- `frontend/tools/check-repo-artifacts.mjs`
- `frontend/tools/check-asset-naming.mjs`
- `frontend/tools/audit-images.mjs`
- `frontend/tools/dev-https-proxy.mjs`
- `frontend/tools/convert-webp.js`

README de domaine :
- `frontend/README_BUILD_PIPELINE.md`
- `README_RENDER_ARTEFACTS_V1.md`

## Point Important Sur Les Commits

Le perimetre audite dans `backend/src/**` et `backend/tests/**` ne suffit pas a lui seul a faire des commits propres.
Dans ce depot, il faut assumer des fichiers tracked d'accompagnement, selon le domaine :
- bootstrap et wrappers legacy pour D1
- partials/layouts et `backend/data/pages.json` pour D2 et D3
- `backend/composer.json`, `backend/composer.lock`, CI et scripts CLI quand un domaine introduit une dependance ou une commande

L'objectif n'est pas de viser la purete mathematique, mais d'eviter le commit monolithique illisible.

## Ordre Recommande

1. D1 socle HTTP / bootstrap / i18n / assets
2. D2 admin editorial / pages / navigation
3. D3 blog / discussions / persistance SQL
4. D4 logging / observabilite
5. D5 outillage frontend

## Verification Minimale Avant Commit

Selon le domaine touche :
- `composer test --working-dir=backend`
- `cd frontend && npm run test:run`
- `cd frontend && npm run hygiene:docs`
- `cd frontend && npm run build`

Le minimum acceptable est de lier chaque commit a ses tests et a son README de domaine.
