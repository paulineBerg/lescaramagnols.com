# Lot C - Isoler La Refonte

Date : 2026-04-16

Reference :
- `docs/audit-nettoyage-priorise-depot-local-2026-04-16.md`
- `README_MODERNISATION_V1.md`
- `README_ADMIN_EDITORIAL_NAV_V1.md`
- `backend/README_PUBLIC_ENTRYPOINTS.md`
- `backend/README_INSTALLATION_HORS_WEBROOT.md`

## Objectif

Ce lot ne correspond pas a un nettoyage de depot.
Il regroupe des suppressions suivies qui materialisent une refonte deja engagee : front-controller unique, admin canonique, pages editoriales structurees, navigation gouvernee par repository et sitemap unifie.

La regle a retenir :
- ce bloc doit etre relu, stage et committe a part
- il ne doit pas etre noye avec les artefacts locaux, les backups ou l'hygiene assets
- chaque suppression doit etre rattachee a son remplacant canonique

## Perimetre A Isoler

Suppressions suivies relevees par l'audit :
- `backend/templates/pages/site/**`
- `backend/public/adminFtyhik5642sZ/**`
- `backend/public/site/adminFtyhik5642sZ/**`
- `backend/public/installsql.php`
- `backend/config/menu_data.php`
- `backend/templates/partials/sitemap.php`
- `README_BLOG_PLAN.md`

## Cartographie Remplace / Conserve / Abandonne

### 1. Pages publiques legacy `backend/templates/pages/site/**`

Statut :
- abandonne comme source canonique des pages editoriales publiques

Remplace par :
- `backend/data/pages.json` comme registre editorial public
- `backend/templates/pages/dynamic.php` comme template public unique
- `backend/src/Content/PageRepository.php` et `backend/src/Content/StructuredPageRenderer.php`
- `backend/src/Http/LegacyRouteResolver.php` pour la resolution HTTP vers la page dynamique

Conserve :
- le rendu serveur PHP via `backend/templates/partials/layout.php`
- les wrappers legacy strictement necessaires cote routage tant que la transition n'est pas totalement purgee

### 2. Entrees admin obfusquees `backend/public/adminFtyhik5642sZ/**` et `backend/public/site/adminFtyhik5642sZ/**`

Statut :
- abandonne comme surface publique de reference

Remplace par :
- la route canonique `/<base_path>/<ADMIN_LOGIN_PATH>`
- `backend/src/Admin/AdminRouteResolver.php`
- `backend/src/Admin/AdminController.php`
- `backend/public/index.php` puis `backend/src/Http/FrontController.php`

Conserve :
- uniquement les shims/wrappers explicitement documentes pour compatibilite transitoire ; ils ne doivent plus porter de logique metier

### 3. Installateur web `backend/public/installsql.php`

Statut :
- abandonne pour raisons de securite et de gouvernance d'installation

Remplace par :
- `composer init-db-admin --working-dir=backend -- ...`
- `backend/README_INSTALLATION_HORS_WEBROOT.md`
- les scripts SQL `backend/sql/install.sql` et `backend/sql/editorial/*.sql` executes hors HTTP

Conserve :
- l'installation via shell et configuration hors webroot

### 4. Configuration legacy des menus `backend/config/menu_data.php`

Statut :
- abandonne comme source canonique du workflow navigation

Remplace par :
- `backend/src/Navigation/NavigationRepository.php`
- `backend/data/menus.json` comme source fichier canonique en mode `json`
- les stockages `sql` et `dual-write` selon `EDITORIAL_STORAGE`
- `backend/src/Navigation/LegacyMenuRuntime.php` pour la projection de compatibilite vers le front legacy

Conserve :
- `backend/core/menu_loader.php` comme wrapper de compatibilite, sans redevenir la source de verite

### 5. Partial `backend/templates/partials/sitemap.php`

Statut :
- abandonne comme mecanisme de rendu sitemap

Remplace par :
- la route canonique `/sitemap.xml`
- `backend/src/Feed/SitemapService.php`
- `backend/core/tools/generate_sitemap.php` pour la generation optionnelle de `backend/public/sitemap.xml` en deploiement

Conserve :
- le front-controller peut servir le sitemap dynamiquement si le fichier statique n'est pas genere

### 6. Documentation `README_BLOG_PLAN.md`

Statut :
- abandonne comme document actif

Remplace par :
- `docs/archive/README_BLOG_PLAN_V1.md` pour la trace historique
- `README_MODERNISATION_V1.md` pour la trajectoire de modernisation
- `README_V1_PREPARATION_DEPLOIEMENT.md` pour l'etat deployable et les gates

Conserve :
- l'archive si elle reste utile a la lecture historique, sans redevenir une source de pilotage produit

## Ce Que Lot C Ne Couvre Pas

Lot C n'est pas :
- le nettoyage d'artefacts locaux (`node_modules`, backups, caches, snapshots)
- la normalisation/publication des assets images
- le commit de nouvelles fonctionnalites encore non suivies

Ces sujets doivent rester dans des lots separes.

## Regle De Livraison

Si ce perimetre est commite :
- le faire dans un commit ou une branche dediee a la refonte
- decrire explicitement le mapping de remplacement dans le message de commit ou la PR
- verifier que les README actifs cites plus haut sont alignes avant livraison
