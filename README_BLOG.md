# Blog V1 (JSON, SQL, Dual-Write)

Date de mise à jour : 2026-03-21

Ce document fixe la gouvernance technique du module blog V1, avec persistance pilotable par mode.

Reference complementaire :
- `docs/README_CONSOLIDATION_LOT_D.md`

## Statut

Le blog reste en mode produit `experimental`, mais la persistance est désormais alignée sur la stratégie éditoriale globale :
- `json` : lecture/écriture fichiers (source historique)
- `dual-write` : lecture JSON, écriture SQL puis JSON
- `sql` : lecture/écriture SQL

Mise a jour 2026-04-16 :
- dans le cadre du Lot D, le blog/discussions/persistance SQL forme un domaine de consolidation autonome (`backend/src/Blog/*`, `backend/sql/editorial/005_blog.sql`, tests `backend/tests/Blog*` et endpoints associes)
- la note de consolidation recommande de sortir ce bloc en commit dedie, distinct du socle HTTP/admin et distinct de l'observabilite

Configuration :
- `BLOG_STORAGE=json|dual-write|sql`
- fallback automatique sur `EDITORIAL_STORAGE` si `BLOG_STORAGE` absent

## Périmètre fonctionnel

Actif :
- sauvegarde d’article admin
- workflow de statut admin `draft` / `scheduled` / `published`
- image de couverture article (URL ou upload admin) avec métadonnées (`alt`, `title`, `caption`, `width`, `height`)
- lecture publique (`/blog`, `/blog/article/{slug}`)
- flux RSS et sitemap basés sur les articles publiés
- discussions publiques modérées (`pending`, `approved`, `rejected`)
- modération admin (`Discussions`)

Hors périmètre :
- pas de création d’article publique
- pas de compte front-office obligatoire

## Couche de référence

- contrats : `backend/src/Blog/BlogRepositoryInterface.php`, `backend/src/Blog/BlogDiscussionRepositoryInterface.php`
- impl JSON : `JsonBlogRepository`, `JsonBlogDiscussionRepository`
- impl SQL : `SqlBlogRepository`, `SqlBlogDiscussionRepository`
- bridge dual-write : `DualWriteBlogRepository`, `DualWriteBlogDiscussionRepository`
- câblage runtime : `backend/core/helpers.php` (`blog_storage_mode`, `blog_repository`, `blog_discussion_repository`)

Tables SQL :
- `{{prefix}}blog_articles`
- `{{prefix}}blog_discussions` (FK avec suppression en cascade sur article)

Migration :
- `backend/sql/editorial/005_blog.sql`

## Publication planifiée automatique

La planification ne dépend pas d’un cron :
- statut `scheduled` côté admin + date planifiée (champ "Publication programmée"),
- publication automatique à la lecture publique dès que la date est atteinte,
- comportement appliqué de manière homogène sur les repositories JSON et SQL.

Règles :
- `published` : visible immédiatement en front, RSS et sitemap.
- `scheduled` avec date future : non visible publiquement.
- `scheduled` avec date atteinte : visible comme un article publié (front, RSS, sitemap).
- `draft` : non visible publiquement.

## Images article (V1)

La V1 ajoute une image de couverture optionnelle par article :
- saisie manuelle via chemin public (`/assets/images/...` ou `/uploads/editorial/...`) ou URL `https://...`
- upload admin sécurisé (JPG/PNG/WebP/GIF/AVIF) vers `backend/public/uploads/editorial/article/YYYY/MM`
- dimensions explicitables pour limiter les CLS
- rendu front sur :
  - liste blog (`/blog`)
  - détail article (`/blog/article/{slug}`)
  - chroniques attachées aux pages dynamiques
- SEO : `og:image` + `twitter:image` injectés quand une image de couverture est définie.

Contrainte déploiement :
- le dossier `backend/public/uploads/editorial/**` doit être préservé (pas de suppression en release).

## Import / exploitation

Commandes d’exploitation :
- `composer blog-import-sql` : import JSON -> SQL (articles + discussions)
- `composer blog-import-sql -- --articles-only`
- `composer blog-import-sql -- --discussions-only`
- `composer blog-import-sql -- --no-prune`

Backup/restore éditorial (pages/navigation/blog/discussions) :
- `php backend/core/tools/editorial_backup_restore.php backup [--storage=json|sql|dual-write]`
- `php backend/core/tools/editorial_backup_restore.php restore <backup.json> --force [--storage=json|sql|dual-write]`

### Rollout preprod recommande

1. `composer blog-import-sql`  
2. `.env` preprod -> `BLOG_STORAGE=dual-write`  
3. recette front/admin + checks securite  
4. `.env` preprod -> `BLOG_STORAGE=sql`  
5. clear cache runtime + re-validation

Preuves :
- archiver sorties CLI et captures dans `docs/private/recette-preprod-v1-YYYY-MM-DD/`

## Sécurité et intégrité

- suppression article -> suppression des discussions associées :
  - SQL : FK `blog_discussions(article_slug, article_lang)` -> `blog_articles(slug, lang)` en `ON DELETE CASCADE`
  - JSON : suppression explicite du thread dans `AdminBlogService::delete()`
- soumission discussion publique durcie : CSRF, nonce de formulaire, honeypot, rate limiting, reCAPTCHA optionnel

## Tests couverts

- `backend/tests/Blog/JsonBlogRepositoryTest.php`
- `backend/tests/Blog/JsonBlogDiscussionRepositoryTest.php`
- `backend/tests/Blog/SqlBlogRepositoryTest.php`
- `backend/tests/Blog/SqlBlogDiscussionRepositoryTest.php`
- `backend/tests/Blog/DualWriteBlogRepositoryTest.php`
- `backend/tests/BlogDiscussionApiControllerTest.php`
- `backend/tests/RssFeedServiceTest.php`
- `backend/tests/SitemapServiceTest.php`
