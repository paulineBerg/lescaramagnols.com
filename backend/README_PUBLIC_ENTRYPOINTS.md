# Gouvernance Des Entrees Publiques

Date : 2026-03-21

Ce document décrit la gouvernance HTTP publique de reference apres les passes 2 a 4 de modernisation.
La section E ajoute une couche de testabilite sur ce meme point d'entree sans changer le rendu front-office.

## Objectif

Faire converger les points d'entrée publics sensibles vers une seule gouvernance :
- bootstrap commun
- sécurité commune
- configuration commune
- routage commun

Le front-office PHP reste inchangé dans son rendu. La modernisation porte sur la structure HTTP, pas sur un basculement SPA.

## Route Canonique

Les entrées publiques de référence sont désormais :
- `/`
  - front-office via `backend/public/index.php`
- `/core/api/lang.php`
  - projection JSON des traductions
- `/core/blog/save_article.php`
  - alias POST legacy vers l'écriture blog gouvernée
- `/core/blog/submit_discussion.php`
  - soumission publique d'une discussion sous article (modération manuelle)
- `/rss`
  - flux RSS rendu par `backend/src/Feed/RssFeedService.php`
- `/sitemap.xml`
  - sitemap XML généré dynamiquement depuis les pages/articles publiés
  - un fichier statique `backend/public/sitemap.xml` peut être généré en déploiement via `core/tools/generate_sitemap.php`
  - la route reste servie par `index.php` en fallback si le fichier statique est absent
- `/robots.txt`
  - directives robots et annonce du sitemap canonique
- `/blog`
  - liste publique des articles publies (inclut les articles `scheduled` dont la date planifiee est atteinte ; fallback `DEFAULT_LANG` si la langue demandee est vide)
- `/blog/article/{slug}`
  - detail public d'un article publie (inclut les articles `scheduled` dont la date planifiee est atteinte ; fallback `DEFAULT_LANG` si slug absent dans la langue demandee)
- `/blog/proposer`
  - page explicite de non-ouverture de la contribution publique
- `/<base_path>/<ADMIN_LOGIN_PATH>`
  - login admin
- `/<base_path>/<ADMIN_LOGIN_PATH>/dashboard`
  - tableau de bord admin (pilotage des elements cles, focus discussions en attente)
- `/<base_path>/<ADMIN_LOGIN_PATH>/pages`
  - liste et filtres du registre editorial, avec suppression directe d'une page (suppression de toutes les traductions associees, avec CSRF + confirmation explicite Oui/Annuler cote UI, bind JS compatible CSP via nonce)
- `/<base_path>/<ADMIN_LOGIN_PATH>/pages/new`
  - creation d'une page structuree
- `/<base_path>/<ADMIN_LOGIN_PATH>/pages/{slug}`
  - edition multi-langue, workflow et SEO de la page
  - support V1 image SEO par langue (`meta.image`) avec upload admin vers `/uploads/editorial/page/...`
  - support V1 galerie medias partages (hors traductions) stockee en `meta.shared_media`, upload multiple avec resize auto + conversion WebP vers `/uploads/editorial/media/...`
- `/<base_path>/<ADMIN_LOGIN_PATH>/menus`
  - builder visuel des menus, des cartes laterales et des zones systeme
- `/<base_path>/<ADMIN_LOGIN_PATH>/discussions`
  - moderation des discussions blog (`pending`, `approved`, `rejected`)
- `/<base_path>/<ADMIN_LOGIN_PATH>/articles/save`
  - API admin canonique d'ecriture blog (mode `json|dual-write|sql`)
  - image de couverture article compatible upload admin (`/uploads/editorial/article/...`)
- `/<base_path>/<ADMIN_LOGIN_PATH>/logout`
  - déconnexion admin
- `/<base_path>/<ADMIN_LOGIN_PATH>/session/ping`
  - keepalive AJAX (POST + CSRF) pour prolonger une session admin active apres avertissement d'inactivite

Par défaut, l'exemple `.env` utilise `ADMIN_LOGIN_PATH=admin` et `base_path=/`, donc la route canonique est `/admin`.

## Couche De Reference

- front-controller : `backend/public/index.php`
- application HTTP testable : `backend/src/Http/FrontController.php`
- resolueur legacy de pages publiques : `backend/src/Http/LegacyRouteResolver.php` (appele via wrapper `core/router.php`, langue derivee de l'URL + fallback blog vers `DEFAULT_LANG`)
- résolution des routes admin : `backend/src/Admin/AdminRouteResolver.php`
- contrôleur admin : `backend/src/Admin/AdminController.php`
- service pages admin : `backend/src/Admin/AdminPageService.php`
- service navigation admin : `backend/src/Admin/AdminNavigationService.php`
- projection legacy des menus : `backend/src/Navigation/LegacyMenuRuntime.php` (appele via wrapper `core/menu_loader.php`)
- contrôleur API blog : `backend/src/Blog/BlogApiController.php`
- service blog : `backend/src/Blog/BlogSaveService.php`
- persistance blog : interfaces `BlogRepositoryInterface`/`BlogDiscussionRepositoryInterface` + impl `Json*`, `Sql*`, `DualWrite*`
- rendu admin : `backend/templates/admin/*.php`
- flux RSS : `backend/src/Feed/RssFeedService.php`
- sitemap XML : `backend/src/Feed/SitemapService.php`
- journalisation : `backend/src/Logging/AppEventLogger.php`
- stockage editorial pages/navigation : facades `PageRepository` et `NavigationRepository` avec implementations `json`, `sql` et `dual-write`

Le webroot ne doit plus contenir la logique métier de l'admin, du RSS ni de l'ecriture blog.
`backend/public/index.php` ne doit plus porter la logique de routage lui-meme ; il doit deleguer a `backend/src/Http/FrontController.php`.

Verification securite associee :
- `backend/public/.htaccess` force HTTPS et bloque l'acces direct a des fichiers techniques/sensibles.
- `composer check-security-headers -- --url=https://preprod.exemple.tld` valide les headers de securite en preprod.
- verifier en production que `BASE_URL` (ou `site.url.domain`/`site.url.ssl_domain`) pointe sur le domaine public pour eviter des URLs loopback dans `sitemap.xml` et `robots.txt`.

## Stockage Editorial

Les points d'entree publics et admin lisent maintenant le registre editorial via une configuration unique :
- `EDITORIAL_STORAGE=json`
  - lecture/ecriture fichier
- `EDITORIAL_STORAGE=dual-write`
  - lecture JSON, ecriture SQL puis JSON
- `EDITORIAL_STORAGE=sql`
  - lecture/ecriture SQL
  - resilience front-office : si un emplacement de navigation manque encore en SQL (`footer`, `sideLeft`, `sideRight`, etc.), le chargeur retombe sur `backend/data/menus.json` pour cet emplacement tant que l'import SQL n'est pas complet

La commande de migration initiale est :
- `composer editorial-import-sql`

Le choix par defaut reste `json` pour ne pas bloquer le front-office pendant la transition.

Pour le blog, la meme logique est appliquee avec `BLOG_STORAGE=json|dual-write|sql` (fallback `EDITORIAL_STORAGE`).
Migration blog JSON -> SQL :
- `composer blog-import-sql`
- options : `--articles-only`, `--discussions-only`, `--no-prune`

Workflow blog admin :
- `status=draft` : non visible publiquement
- `status=scheduled` + date atteinte : visible publiquement sans cron (auto-publication a la lecture)
- `status=published` : visible publiquement immediatement

Backup/restore avant operation destructive :
- backup : `php core/tools/editorial_backup_restore.php backup [--output=/chemin/backup.json]`
- restore : `php core/tools/editorial_backup_restore.php restore /chemin/backup.json --force`

Option de mode pour verification ciblee :
- `--storage=json|sql|dual-write`

Le backup inclut pages, navigation, articles blog et fils de discussions.
La restauration est volontairement protegee par `--force`.

Integrite suppression article -> discussions :
- mode SQL legacy (`backend/sql/install.sql`) : `car_comments.article_id` est en `ON DELETE CASCADE`.
- mode JSON blog/discussions : `AdminBlogService::delete()` supprime explicitement le fil de discussions associe.

Les formulaires admin `pages` et `menus` transportent maintenant leur etat complet via un champ JSON cache (`page_state_json`, `builder_state_json`) avant POST.
Objectif :
- eviter les troncatures silencieuses dues a `max_input_vars`
- garder un rendu serveur classique et un fallback fonctionnel si JavaScript est indisponible

Images editoriales runtime :
- les uploads admin pages/articles sont stockes dans `backend/public/uploads/editorial/**`
- ce dossier doit etre conserve en deploiement (scripts release/fast exclus des suppressions sur ce scope)
- les medias partages pages utilisent le sous-dossier mutualise `backend/public/uploads/editorial/media/YYYY/MM` pour permettre la reutilisation inter-pages/inter-articles

Images publiques versionnees / derivees :
- la source canonique des images publiques front-office vit dans `frontend/src/assets/images/**`
- la publication HTTP correspondante est copiee dans `backend/public/assets/images/**` au build (`npm run build` / `npm run postbuild`)
- `backend/public/assets/images/**` ne doit pas etre edite a la main ni versionne dans Git
- si une image publique manque ou doit etre corrigee, faire la modification dans `frontend/src/assets/images/**` puis republier

## Shims Legacy Conserves

Des fichiers publics restent présents uniquement pour compatibilité d'URL :
- `backend/public/rss.php`
- `backend/public/assets/rss.php`
- anciens wrappers admin publics legacy

Leur rôle est volontairement minimal :
- ils délèguent au front-controller
- ils ne doivent pas rendre eux-mêmes de HTML métier
- ils ne doivent pas porter de logique d'authentification

## Regles D Evolution

- toute nouvelle entrée publique doit être déclarée dans `backend/public/index.php`
- toute nouvelle logique HTTP applicative doit vivre dans `backend/src/`
- les templates HTML admin doivent rester hors `backend/public/`
- les chemins legacy ne doivent servir qu'à la compatibilité, jamais comme source de vérité
- les routes `/*` ne sont plus exposees
- les anciens templates `backend/templates/pages/admin/*` sont consideres comme archives et ne doivent plus etre routables
- `/<base_path>/<ADMIN_LOGIN_PATH>/menus` doit rester un workflow visuel serveur ; le JSON canonique n'est plus qu'un mode expert secondaire
- un basculement `EDITORIAL_STORAGE=sql` ne doit etre active qu'apres import SQL reussi et verification HTTP locale

## TODO

- retirer a terme les repertoires physiques legacy admin du webroot quand la couche serveur ne dependra plus de ces shims

## Mise A Jour 2026-03-21

Couverture HTTP admin etendue dans `backend/tests/FrontControllerHttpTest.php` :
- POST `/<base_path>/<ADMIN_LOGIN_PATH>/menus` :
  - sauvegarde builder visuel
  - changement d'onglet par tabs d'emplacements (`switch_location@*`)
- POST `/<base_path>/<ADMIN_LOGIN_PATH>/pages/new` :
  - rejet CSRF invalide
  - workflow `draft` et `published` valide
- POST blog :
  - route canonique `/<base_path>/<ADMIN_LOGIN_PATH>/articles/save`
  - alias legacy `/core/blog/save_article.php`
