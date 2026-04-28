# Les Caramagnols – documentation projet

Site associatif mêlant un backend PHP procédural (routage, templates, i18n serveur, sécurité) et un frontend Vite (JS/SCSS) pour l’interactivité et le responsive.  
Ce document décrit l’architecture, les langages, les dépendances, les commandes utiles et les points clés de mise en production.

## Emplacement WSL du depot
- Controle local du `2026-04-11` : depot verifie sous `/home/surfacepro8/www/caramagnols` sur le systeme de fichiers Linux WSL (`ext4`).
- Aucun deplacement n'a ete necessaire : le depot n'est pas stocke sous `/mnt/c`, `/mnt/d` ni un autre montage Windows.
- Regle de maintenance : garder le depot Git dans `/home/...` pour proteger les performances de Git, Composer, Node/Vite et des watchers locaux.

## Note 2026-03-18
- Le service éditorial de pages est désormais unique : registre [`backend/data/pages.json`](./backend/data/pages.json) + rendu [`backend/templates/pages/dynamic.php`](./backend/templates/pages/dynamic.php).
- Le registre ne contient plus de `legacy_template` ni de champ `template`.
- Les formulaires admin lourds (`pages`, `menus`) sérialisent maintenant leur état dans un champ JSON caché avant soumission pour éviter les troncatures PHP liées à `max_input_vars`.
- Les routes publiques préfixées `/*` ont été supprimées ; les routes canoniques sont désormais sans ce préfixe.
- Les mentions résiduelles de `legacy_template` dans certains documents de plan servent uniquement d’historique de conception tant qu’ils n’ont pas été entièrement réécrits.

## Note 2026-04-22
- Le mega menu desktop limite désormais chaque section à `5` liens consécutifs maximum.
- Au-delà, la suite est reprojetée automatiquement dans une colonne adjacente du meme bloc, sous un titre unique de section, sans imposer un faux sous-groupe dans l'admin.
- Le mobile conserve l'arborescence complète et ne réutilise pas cette découpe de confort desktop.
- Nouveau module admin `Tuiles` : groupes de tuiles Windows 10 réutilisables, rattachables aux pages en `after_body`. L écran `Tuiles` porte l édition complète des groupes ; l écran `Pages` ne gère plus que l affectation, l ordre, la visibilité locale et, si besoin, une page cible locale.
- Le catalogue `Tuiles` permet maintenant de dupliquer un groupe existant en un clic, avec ouverture directe de la copie dans l écran d édition.
- Les tuiles `after_body` supportent maintenant les formats `small`, `medium`, `large` et `rectangle`, avec une grille dense type Windows 10, hover léger uniquement et empilement vertical sur mobile.
- Le format éditorial par défaut du module `Tuiles` est désormais `rectangle`, avec image visible dans la tuile et fond W10 servi depuis `boutonrectangle/*`.
- Migration legacy des tuiles HTML vers SQL outillée par `php backend/core/tools/migrate_legacy_page_tiles.php` (`--apply` après backup) ; les groupes auto-retro y sont normalisés à `1` tuile par marque en ordre alphabétique, avec ajout de `Citroën`.
- Gabarit W10 des images de tuiles : source canonique sous `frontend/src/assets/images/structure/menu/**`, fond couleur servi depuis `boutonpetit/*`, `boutonmoyen/*`, `boutongrand/*` ou `boutonrectangle/*` selon la taille, avec fallback serveur si une couleur n existe pas pour un format donne.

## Docs de référence
- Index de la documentation : `README_DOCUMENTATION_INDEX.md`
- Plan V1 de deploiement : `README_V1_PREPARATION_DEPLOIEMENT.md`
- Portail prive famille (vision securisee) : `README_PRIVATE_FAMILLE_V1.md`
- Plan d’action (historique archive) : `docs/archive/README_AUDIT_PLAN_ACTION_V1.md`
- Stratégie de modernisation : `README_MODERNISATION_V1.md`
- Admin éditorial et navigation : `README_ADMIN_EDITORIAL_NAV_V1.md`
- Blog JSON MVP : `README_BLOG.md`
- Bootstrap backend / i18n : `backend/README_BOOTSTRAP_I18N.md`
- Conventions pages dynamiques / contenu : `docs/pages-dynamiques.md`
- Isolation de la refonte heritee (Lot C) : `docs/README_REFONTE_LOT_C.md`
- Consolidation du nouveau code (Lot D) : `docs/README_CONSOLIDATION_LOT_D.md`
- Entrées publiques : `backend/README_PUBLIC_ENTRYPOINTS.md`
- Installation hors webroot : `backend/README_INSTALLATION_HORS_WEBROOT.md`
- Logging backend : `backend/README_LOGGING.md`
- Pipeline de build frontend : `frontend/README_BUILD_PIPELINE.md`
- Runbook go-live V1 : `docs/v1-go-live-runbook.md`

## Quick start (2 min)
- Prérequis : PHP 8.1+, Composer, Node.js `20.19+` ou `22.12+`, npm
- Version recommandée pour ce dépôt : Node `22.22.1` via `nvm`
- Si `nvm` est installé : `nvm install && nvm use`
- Installer backend : `composer install --working-dir=backend`
- Installer frontend : `cd frontend && npm install`
- Installer les hooks Git locaux : `make install-git-hooks`


- deployer :

cd /home/surfacepro8/www/caramagnols && export REMOTE_HOST="lescaramgl-ssh@ssh.cluster103.hosting.ovh.net" REMOTE_BACKEND="/home/lescaramgl-ssh/caramagnols/backend" SITEMAP_BASE_URL="https://www.lescaramagnols.com" && cd frontend && npm run build && cd /home/surfacepro8/www/caramagnols && bash backend/tools/deploy-release.sh && bash backend/tools/push-local-sql-to-ovh.sh --live && ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php -r 'require \"core/bootstrap.php\"; if (function_exists(\"app_runtime_cache_clear\")) { app_runtime_cache_clear([\"pages\",\"navigation\",\"translations\",\"tiles\"]); } echo \"cache_cleared_final\n\";'"



- copier le SQL editorial local vers OVH : cd /home/surfacepro8/www/caramagnols
bash backend/tools/push-local-sql-to-ovh.sh --live
- copier bdd ovh sur bdd locale : cd /home/surfacepro8/www/caramagnols
bash .ops-sync/bin/pull-caramagnols-db.sh --live

- Lancer en dev :
  - Recommandé : `./dev.sh`
  - Terminal 1 : `  cd backend && php -S 127.0.0.1:8000 -t public public/dev-router.php  `
  - Terminal 2 : `  cd frontend && npm run dev  `
- Non/Ouvrir : `https://127.0.0.1:18443?lang=fr` (mode `./dev.sh`, certificat local auto-signé)  
-  OUVRIR/ Fallback HTTP direct : `  http://127.0.0.1:8000?lang=fr  `
- Ne pas ouvrir `http://localhost:5173/` directement : le site reste rendu par PHP sur `8000`, Vite sert seulement les assets en dev.
- Si Vite tourne, PHP charge automatiquement `@vite/client` et `src/js/main.ts` depuis `VITE_DEV_SERVER_URL` (par défaut `http://localhost:5173`).
- Si Vite ne tourne pas, PHP retombe sur `backend/public/.vite/manifest.json` publié par `npm run build`.

---

## Hygiene du depot local
- Audit de nettoyage courant : `docs/audit-nettoyage-priorise-depot-local-2026-04-16.md`
- Avant tout nettoyage large, figer l etat courant dans une branche locale de sauvegarde.
- Convention recommandee pour cette branche : `backup/local-snapshot-YYYYMMDD-HHMMSS`
- Une branche de sauvegarde Git capture l etat `versionnable` du depot. Les fichiers ignores par Git n y entrent pas automatiquement.

### Procedure recommandee avant nettoyage
```bash
current_branch="$(git rev-parse --abbrev-ref HEAD)"
timestamp="$(date +%Y%m%d-%H%M%S)"
backup_branch="backup/local-snapshot-${timestamp}"
stash_label="temp-backup-snapshot-${timestamp}"

git stash push --include-untracked -m "${stash_label}"
git switch -c "${backup_branch}"
git stash apply "stash@{0}"
git add -A
git commit --no-verify -m "backup: local snapshot before cleanup ${timestamp}"
git switch "${current_branch}"
git stash pop "stash@{0}"
```

### Regles de nettoyage sans risque produit
- `frontend/node_modules` ne doit jamais rester suivi dans Git. Si des fichiers y sont historiques, les sortir de l index avec `git rm -r --cached frontend/node_modules`.
- Les artefacts et outils purement locaux doivent rester hors commits produit, sauf decision explicite de standardisation : `backups/`, `.ops-sync/`, `.nvmrc`, `dev.sh`, `php`.
- Ne pas lancer `git clean -fdx` ni `git reset --hard` tant que l audit n a pas separe le vrai travail produit, les artefacts generes et les suppressions de migration.

---

## Architecture générale
- **Entrée HTTP** : `backend/public/index.php` charge `core/bootstrap.php`, applique les en-têtes de sécurité, initialise la langue via `bootstrap_language_context()`, résout la route via le wrapper `core/router.php` (délégué à `backend/src/Http/LegacyRouteResolver.php`, avec fallback blog vers `DEFAULT_LANG` si contenu absent dans la langue demandée) puis rend la page avec le layout PHP (`templates/partials/layout.php`).
- **Templating** : pages PHP dans `backend/templates/pages/**` structurées autour des blocs `EditRegion*`; menus/header/footer dans `backend/templates/partials/*`. Le layout standard des zones est maintenant centralisé dans `backend/src/Content/StandardPageLayout.php`.
- **Navigation haute** : le mega menu desktop est projeté par `backend/src/Navigation/NavigationViewModelBuilder.php` puis rendu par `backend/templates/partials/menus_header.php`; une même section y est découpée automatiquement après `5` liens par colonne, mais conserve un titre unique avec ses colonnes adjacentes, sans modifier l'arbre éditorial saisi en admin.
- **Internationalisation (serveur)** : résolution via `backend/src/I18n/LanguageResolver.php`, orchestration via `backend/core/lang_bootstrap.php`, chargement/sanitisation via `backend/src/I18n/Translator.php` exposé par `backend/core/i18n.php`.
- **Internationalisation (client)** : module `frontend/src/js/i18n.ts` (fetch `core/api/lang.php`, cache in-memory + localStorage, application sur `data-i18n`).
- **Frontend build** : Vite 7 (ESM) + SCSS. Entrées `src/js/main.ts` et `src/scss/style.scss`; manifest publié dans `backend/public/.vite/manifest.json` puis injecté côté PHP via `backend/src/Assets/ViteAssetManager.php` et `vite_tags()`. Images copiées via `vite-plugin-static-copy`. Le build applique aussi un gate de budget via `frontend/tools/check-budgets.mjs`.
- **Assets runtime** : en production, `npm run build` publie le build dans `backend/public/assets` et `backend/public/.vite`, avec purge automatique des anciens bundles hashés à la racine de `backend/public/assets`. La politique V1 impose de ne pas versionner `frontend/dist/**`, `backend/public/.vite/**`, `backend/public/tarteaucitron/**` et les bundles generes a la racine `backend/public/assets`. En dev, Vite sert les assets, le proxy `/core/*` cible `http://127.0.0.1:8000`.
- **Uploads éditoriaux runtime** : les images uploadées depuis l’admin (pages/articles) sont stockées dans `backend/public/uploads/editorial/**` (chemins publics stables `/uploads/editorial/...`) et doivent être conservées en déploiement (pas de `--delete` sur ce dossier). Les medias partages pages sont mutualises dans `backend/public/uploads/editorial/media/YYYY/MM` avec resize auto + conversion WebP.
- **Sécurité** : en-têtes CSP/anti-clickjacking (`core/security.php`), session renommée (`caramagnols_session`) en `SameSite=Strict`, cookie `lang` en `HttpOnly`/`SameSite=Lax`, tokens CSRF (`csrf_token()` / `csrf_validate()`), rate limiting session (`core/rate_limiter.php`), timeout d'inactivite admin (120 min) avec warning de prolongation (fenetre 120s) et keepalive CSRF (`POST /<base_path>/<ADMIN_LOGIN_PATH>/session/ping`), sanitisation centralisée (`core/validation.php`) et filtrage strict des `iframe` editoriaux (YouTube uniquement, normalise `youtube-nocookie`).
- **Admin Blog (MVP JSON/SQL)** : espace protégé sous `/<base_path>/<ADMIN_LOGIN_PATH>` (exemple par défaut : `/admin` quand `base_path=/`) avec login email/mot de passe (`core/auth/admin.php`). Le rendu passe par `backend/src/Admin/AdminController.php`, l’écriture blog par `backend/src/Blog/BlogApiController.php`, et la persistance par `backend/src/Blog/JsonBlogRepository.php` / `SqlBlogRepository`. Workflow statuts disponible : `draft`, `scheduled` (auto-publication a date atteinte), `published`. Aucun identifiant admin par défaut n’est fourni par le dépôt.
- **Dashboard admin** : la page `/<base_path>/<ADMIN_LOGIN_PATH>/dashboard` synthétise les éléments clés de pilotage et met en priorité la modération des discussions (`pending`).
- **Entrées publiques harmonisées** : RSS, sitemap (`/sitemap.xml`), robots (`/robots.txt`), admin et API blog passent par `backend/public/index.php`. Le sitemap est servi dynamiquement (avec possibilité de générer aussi `backend/public/sitemap.xml` en déploiement). Les anciens fichiers publics `rss.php` et les anciens wrappers admin ne sont plus que des shims de compatibilité.
- **Données & recherche** : scripts CLI dans `backend/core/tools/` (génération d’index de recherche JSON à partir des templates). Dossier `backend/data/` pour les fichiers dérivés (index, articles blog JSON, logs applicatifs) et pour les pages dynamiques JSON.
- **Exploitation/perf** : scripts CLI `composer benchmark-routes` (temps de rendu routes critiques, option `--storage=json|sql|dual-write`), `composer cron-center` (coordination des jobs planifies SQL pour le cron OVH), `composer check-log-alerts` (seuils login failure/rate-limit/403/429 + notification webhook/email), `composer backup-production` (archive backend + dump SQL), `composer check-instagram-feed` (probe bloc Instagram accueil). Un pack systemd est fourni pour l'ordonnancement en preprod/prod (`backend/tools/systemd/*` + `backend/tools/check-log-alerts-runner.sh`). Le mode `notify_on` des alertes logs est pilotable depuis `Admin > Parametres > Observabilite ops` (sans exposer les secrets webhook/email), et `Admin > Parametres > Cron Center` expose le point d'entree OVH, les jobs, le test manuel par job et l'historique.
- **Base de données (optionnelle pour l’instant)** : schema MySQL legacy (`backend/sql/install.sql`) + schema editorial (`backend/sql/editorial/*.sql`), initialisables via `composer init-db-admin --working-dir=backend -- ...`. Le fichier `config/database.override.php` permet de surcharger les parametres issus de `.env`.

---

## Périmètre technique
- **Langages** : PHP 8.1+, JavaScript ES Modules, SCSS (Sass).
- **Outils** : Composer, Vite 7, Vitest, ESLint, Stylelint, Sharp (conversion WebP), PHPUnit 10.
- **Cibles d’exécution** : hébergement PHP (Apache/Nginx) sans serveur Node en production ; Node utilisé uniquement pour builder les assets.

---

## Structure des dossiers (racine)
```
backend/
  public/            # point d’entrée HTTP, manifest + assets buildés
  core/              # bootstrap, sécurité, i18n, router, API lang, outils CLI
  templates/         # layouts et pages (blocs EditRegion*)
  config/            # config app + overrides DB (hors webroot)
  lang/              # traductions PHP (fr/en/de)
  data/              # index de recherche, données dérivées
  sql/               # install.sql (schéma MySQL)
frontend/
  src/               # JS, SCSS, images sources
  dist/              # sortie Vite (générée)
```

## Schéma : flux de rendu d’une page
```
public/index.php
  -> core/bootstrap.php (env, config, sécurité)
  -> bootstrap_language_context() (langue + traductions)
  -> router (FastRoute puis fallback fichiers)
  -> template page (templates/pages/…)
  -> partials/layout.php
       -> scripts_head.php (charge Vite via ViteAssetManager)
```

## Contrat PHP <-> Vite
- **Dev** : le site s'ouvre sur `http://127.0.0.1:8000`. Si le serveur Vite tourne sur `VITE_DEV_SERVER_URL`, PHP injecte `@vite/client` et `src/js/main.ts` directement depuis Vite. Le proxy `/core/*` de Vite pointe vers `http://127.0.0.1:8000`.
- **Prod** : `backend/public/assets/**` et `backend/public/.vite/manifest.json` doivent exister (publiés par `npm run build`).
- Symptômes + solutions :
  - `http://localhost:5173/` renvoie 404 : normal, Vite ne sert pas de page HTML autonome dans ce projet.
  - Page sur `8000` sans CSS/JS en dev : vérifier que `npm run dev` tourne bien sur `VITE_DEV_SERVER_URL`.
  - Page sur `8000` sans CSS/JS hors dev : manifest manquant ou `/assets/...` en 404 → relancer `npm run build`.
  - 404 sur `/core/...` en dev : vérifier que le proxy Vite cible bien `127.0.0.1:8000`.

## Mises à jour récentes
- Suppression du menu principal « COMMUNIQUER » et de ses sous-entrées (desktop et mobile).
- Modernisation progressive (févr. 2026) :
  - Autoload PSR-4 (`Caramagnols\\`) et FastRoute ajouté en front-controller avec fallback legacy.
  - CSP avec nonce (fin de `unsafe-inline`), support Vite dev, frame-ancestors 'none'.
  - API langue : ETag/Cache-Control → 304 si inchangé.
  - Mailer basculé sur Symfony Mailer + logs Monolog (`backend/data/logs`).
  - Script `composer check-i18n` pour aligner les clés fr/en/de.
  - Frontend : entrée `main.ts`, modules i18n/menus/logger en TypeScript, autoprefixer+browserslist.
  - CI GitHub Actions : phpunit, phpstan, phpcs, lint JS/CSS, vitest.
  - Sécurité repo public : `.env`, `config/db.php`, `config/database.override.php`, logs et `public/tmp_config` sont ignorés (`.gitignore`).
- Phase socle (17 mars 2026) :
  - unification du bootstrap i18n autour de `LanguageResolver` + `Translator`
  - suppression des installateurs publics `backend/public/installsql.php` et `backend/public/assets/install.php`
  - documentation d’installation déplacée vers `backend/README_INSTALLATION_HORS_WEBROOT.md`
  - suppression des valeurs de démonstration pour l’auth admin dans le code et `.env.example`
- Deuxième passe (17 mars 2026) :
  - route admin canonique découplée du dossier legacy et alignée sur `ADMIN_LOGIN_PATH`
  - création de `backend/src/Admin/AdminRouteResolver.php` et `backend/src/Admin/AdminController.php`
  - création de `backend/src/Feed/RssFeedService.php` pour un RSS gouverné par le front-controller
  - transformation des anciens fichiers publics admin/RSS en shims de compatibilité
- Troisième passe (17 mars 2026) :
  - création de `backend/src/Assets/ViteAssetManager.php` comme source de vérité Vite côté PHP
  - wrappers `vite_*` conservés mais désormais branchés sur cette classe
  - `frontend/tools/publish-build.mjs` remplace la copie brute et purge les anciens bundles hashés
  - la CI vérifie maintenant le build frontend publié
- Phase 3 contenu/templates (17 mars 2026) :
  - `backend/src/Content/StandardPageLayout.php` centralise le layout standard des régions de contenu
  - `backend/src/Content/StructuredPageRenderer.php` introduit un schéma de pages JSON à régions sémantiques
  - `backend/templates/partials/contenu.php` n’embarque plus la structure en dur et passe par une factorisation dédiée
  - `backend/data/pages.json` supporte désormais le schéma `regions` pour les nouveaux contenus
- Phase 4 blog/outillage (17 mars 2026) :
  - le blog est clarifié comme MVP JSON `experimental` avec persistance unique dans `backend/data/blog`
  - l’écriture blog canonique passe par `POST /<base_path>/<ADMIN_LOGIN_PATH>/articles/save` avec alias legacy `/core/blog/save_article.php`
  - des routes publiques minimales existent maintenant pour `/blog` et `/blog/article/{slug}`
  - le logging est uniformisé sur auth admin, sauvegarde menus et écriture blog
- Audit admin/navigation (17 mars 2026) :
   - `README_ADMIN_EDITORIAL_NAV_V1.md` cadre la prochaine modernisation éditoriale et la refonte du header
   - `backend/core/menu_loader.php` normalise désormais les clés legacy `menu_droit` / `menu_gauche` vers `menuDroit` / `menuGauche`
- Section F editorial SQL (17 mars 2026) :
   - couche PDO MariaDB/MySQL commune via `backend/src/Database/*`
   - repositories editoriaux pages/navigation maintenant storage-aware (`json`, `dual-write`, `sql`)
   - commande `composer editorial-import-sql` pour importer `pages.json` et la navigation vers SQL
   - lecture SQL smoke-testee localement, tout en gardant `json` comme mode par defaut
- Simplification pages (18 mars 2026) :
  - suppression du contrat legacy de pages `legacy_template` / `template`
  - toutes les pages du registre éditorial public sont maintenant des `structured_page`
  - l’admin pages ne maintient plus de pseudo multi-type en parallèle
- Discussions blog modérées (18 mars 2026) :
  - soumission publique de messages sous article via `POST /core/blog/submit_discussion.php`
  - file de moderation admin via `/<base_path>/<ADMIN_LOGIN_PATH>/discussions`
  - section de configuration anti-bot/reCAPTCHA dans `settings`
  - support explicite des deux modes de clés Google : `v2 checkbox` et `v3 score`
  - stratégie sécurité retenue : contribution publique sans compte client obligatoire + modération + anti-bot multicouche
- Stabilisation architecture (19 mars 2026) :
  - `backend/core/router.php` et `backend/core/menu_loader.php` sont désormais des wrappers de compatibilité
  - logique legacy routage/menu migree vers `backend/src/Http/LegacyRouteResolver.php` et `backend/src/Navigation/LegacyMenuRuntime.php`
  - extraction de composants admin (`AdminSerializedFormNormalizer`, parseurs navigation, manager des overrides de traductions)
  - ajout du script de backup/restore editorial `backend/core/tools/editorial_backup_restore.php`
- Performance/exploitation (19 mars 2026) :
  - budgets frontend rendus bloquants dans `npm run build` via `frontend/tools/check-budgets.mjs`
  - audit images historiques outille via `npm run audit:images`
  - lazy loading + dimensions explicites ajoutes sur medias non critiques des templates
  - cache HTTP durci pour assets fingerprintes (`immutable`)
  - cache runtime consolide pages/navigation/traductions avec invalidation explicite cote admin
  - benchmark routes critiques disponible via `composer benchmark-routes`
  - observabilite renforcee: `X-Request-Id`, correlation log, rotation/retention locale, `composer check-log-alerts`
- Editorial images admin (21 mars 2026) :
  - creation/edition articles: image de couverture avec upload admin securise, metadonnees (alt/titre/legende/dimensions) et rendu front (liste/detail/chroniques attachees)
  - creation/edition pages: image SEO par langue (`meta.image`) avec upload admin
  - creation/edition pages: galerie de medias partages (hors traductions) `meta.shared_media` en tete de page, reutilisable inter-pages/inter-articles
  - upload galerie partages: redimensionnement automatique (max 2048px) + conversion WebP cote serveur vers `/uploads/editorial/media/YYYY/MM`
  - SEO image: emission `og:image` + `twitter:image` dans le head quand disponible
  - scripts de deploiement mis a jour pour preserver `backend/public/uploads/editorial/**`
- Observabilite ops admin (21 mars 2026) :
  - ajout d'une section `Observabilite ops` dans les parametres admin pour choisir le mode d'envoi des alertes logs (`alerts`/`always`)
  - persistance dans `backend/config/site.override.php` et prise en compte par `check_log_alerts.php`
  - secrets webhook/email conserves dans la configuration systeme (`/etc/caramagnols/check-log-alerts.env`)
- Revue post go-live J+1/J+7 (21 mars 2026) :
  - runbook execute et archive dans `docs/private/recette-preprod-v1-2026-03-21/` (`122` a `128`)
  - logs securite J+1/J+7 : aucune alerte declenchee
  - benchmark routes archive + synthese anomalies (aucune anomalie bloquante)

## Mise en œuvre des optimisations (plan d’action)

| Axe | Tâches | Priorité | Statut |
| --- | --- | --- | --- |
| Sécurité | CSP par nonce, frame-ancestors, cookies lang SameSite=Lax | Haute | En place (à valider en prod) |
| Performance | ETag/Cache-Control sur API langue | Haute | En place |
| Qualité code | PSR-4, FastRoute, tests router/i18n/CSRF/API | Haute | En place, à étendre |
| Frontend | Passage TypeScript modules clés, autoprefixer, tests menus | Moyenne | En place |
| Observabilité | Monolog (logs app), Symfony Mailer | Moyenne | En place |
| CI | Workflow GitHub Actions (lint+tests) | Haute | En place |
| Dette restante | UI admin pages/menus/traductions, refonte du header, réduction progressive de `backend/core/*` | Moyenne | À faire |

### Backlog priorisé
1) **Tests & lint** : augmenter la couverture (pages, router avancé, i18n).  
2) **TS progressif** : migrer le reste des modules JS et typer les menus complexes.  
3) **Nettoyage assets** : retirer les assets non référencés, automatiser la purge des hash anciens en postbuild.  
4) **Middlewares** : étendre la pile (CSRF POST, rate-limit par endpoint).  
5) **Search index** : tester/garder à jour la génération multi-langues en CI.

### Checklist rapide
- [ ] `composer install` (backend) et `npm install` (frontend, Node 20+).
- [ ] `composer lint && npm run lint`.
- [ ] `composer test && npm run test:run`.
- [ ] `composer check-i18n`.
- [ ] `npm run build` (puis vérifier `backend/public/.vite/manifest.json` et `/assets`).
- [ ] `composer benchmark-routes` et `composer check-log-alerts` pour le smoke exploitation.
- [ ] `composer check-instagram-feed -- --strict` si bloc Instagram activé en cible.
- [ ] `npm run audit:images` pour suivre la dette image historique.
- [ ] `npm run hygiene:repo` pour valider policy artefacts/docs/nommage assets.
- [ ] Démarrer PHP (`php -S 127.0.0.1:8000 -t public public/dev-router.php`) + Vite (`npm run dev`) pour valider le rendu.
- [ ] Vérifier CSP dans DevTools (aucun blocage), API langue renvoie 304 avec ETag.

## Conventions i18n
- Clés en notation pointée : `menu.home`, `hero.title`.
- Chaque clé doit exister en `fr`, `en`, `de`.
- Script de vérification : `composer check-i18n` ou `php backend/core/tools/check_i18n_keys.php` (échoue si une clé manque).

## Admin (sécurité)
- En production :
  - Changer `ADMIN_LOGIN_PATH`, `ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH`, `ADMIN_SESSION_KEY`.
  - Ne jamais laisser de valeurs de démo.
  - `.env` doit rester hors `public/` et en permissions 600/640.

---

## Backend (PHP)
- **Bootstrap** : `core/bootstrap.php` charge `.env`, config (`config/config.php`), sécurité, i18n, router. Vérification des variables critiques en prod (DB & SMTP). Référence détaillée : `backend/README_BOOTSTRAP_I18N.md`.
- **Routage** : `core/router.php` mappe l’URI vers un fichier de page sous `templates/pages/`, avec prise en compte du préfixe langue (`/fr`, `/en`, `/de`).
- **Contenu dynamique** : `backend/data/pages.json` est le registre unique des pages éditoriales publiques.
  - état courant : uniquement des `structured_page`
  - écriture admin courante : `regions` sémantiques selon `docs/pages-dynamiques.md`
  - compatibilité de lecture : le moteur tolère encore `blocks` s’ils existent dans une donnée legacy importée, sans en faire un second service
- **Persistance editoriale** :
  - mode par defaut : `EDITORIAL_STORAGE=json`
  - transition disponible : `EDITORIAL_STORAGE=dual-write`
  - bascule SQL : `EDITORIAL_STORAGE=sql`
  - import initial : `composer editorial-import-sql`
  - import blog JSON -> SQL : `composer blog-import-sql`
  - backup/restore operationnel : `php backend/core/tools/editorial_backup_restore.php backup|restore` (+ `--storage=json|sql|dual-write` pour forcer un mode de verification)
- **API** : `core/api/lang.php` sert les traductions JSON au frontend (`?lang=fr|en|de`), avec fallback `DEFAULT_LANG`, ETag et le même chargeur de traductions que le rendu PHP.
- **Validation & sécurité** :
  - `core/validation.php` : sanitation texte, email, tags, commentaires, traductions (whitelist de balises).
  - `core/security.php` : en-têtes, session sécurisée, CSRF tokens.
  - `core/rate_limiter.php` : limiteur de requêtes basé session (utilisé pour l’API blog et les flux sensibles).
- **Admin** :
  - Authentification email+password (`core/auth/admin.php`), clés dans `.env` (`ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH`, `ADMIN_SESSION_KEY`).
  - Route canonique : `/<base_path>/<ADMIN_LOGIN_PATH>` puis `/dashboard`, `/menus`, `/logout`.
  - Contrôleur : `backend/src/Admin/AdminController.php`, résolution d’URL via `backend/src/Admin/AdminRouteResolver.php`, rendu via `backend/templates/admin/*.php`.
  - Les anciens scripts admin publics legacy sont des shims de compatibilité.
  - Sans configuration explicite (`ADMIN_EMAIL` + `ADMIN_PASSWORD_HASH`), la connexion admin reste désactivée par conception.
- **Blog (JSON/SQL pilotable)** :
  - lecture publique : `/blog`, `/blog/article/{slug}`
  - soumission discussion publique : `POST /core/blog/submit_discussion.php` (statut initial `pending`)
  - moderation admin : `/<base_path>/<ADMIN_LOGIN_PATH>/discussions`
  - écriture admin canonique : `/<base_path>/<ADMIN_LOGIN_PATH>/articles/save`
  - alias legacy : `POST /core/blog/save_article.php`
  - mode de stockage : `BLOG_STORAGE=json|dual-write|sql` (fallback `EDITORIAL_STORAGE`)
  - workflow statut : `draft` / `scheduled` / `published`
  - publication planifiee : un article `scheduled` devient visible automatiquement (front, RSS, sitemap) des que sa date est atteinte, sans cron
  - stockage JSON : `backend/data/blog/{slug}.{lang}.json` et `backend/data/blog-discussions/{slug}.{lang}.json`
  - stockage SQL : tables `blog_articles` / `blog_discussions` (migration `backend/sql/editorial/005_blog.sql`)
  - statut produit : `BLOG_MODE=experimental`
- **Installation** : procédure shell documentée dans `backend/README_INSTALLATION_HORS_WEBROOT.md`.

---

## Frontend (Vite + SCSS)
- **Entrée** : `src/js/main.ts` importe `menus.ts`, `i18n.ts` et `src/scss/style.scss`.
- **Menus & UI** : `src/js/menus.ts` gère le menu desktop (survol souris + clic/tap tactile), le mobile (hamburger), et le bouton “remonter”. Les sous-menus desktop imbriqués s’ouvrent désormais sous leur parent pour limiter les pertes d’ouverture liées au pointage.
- **Fusion mobile groupe/lien** : si un groupe mobile et son premier enfant partagent le même libellé, le lien enfant est fusionné au niveau groupe pour supprimer le doublon d’affichage.
- **i18n client** : `src/js/i18n.ts` (cache `Map`, persistance localStorage, `changeLanguage`, `applyTranslations` sur `data-i18n`).
- **Logger** : `src/js/logger.ts` centralise `console` en dev.
- **Budgets** :
  - `npm run build` applique un gate de poids JS/CSS/image (`frontend/tools/check-budgets.mjs`) avant publication backend.
- **Styles** :
  - SCSS modulaires : `_variables.scss`, `_utilities.scss`, `_layout.scss`, `_menus.scss`, `_responsive.scss`, `_components.scss`.
  - Conventions : classes nouvelles en `kebab-case`, utilitaires préfixés `.u-`, hooks JS préfixés `js-`, placeholders `%` pour factoriser.
- **Images** :
  - Imports Vite (`@/assets/...`) pour hashing.
- Script `npm run build:webp` (`frontend/tools/convert-webp.js`) génère WebP + variantes `@400w/@700w` avec cache simple.
- Pour les images diffusees dans le texte editorial, viser `400 px` par defaut et ne monter a `700 px` que si un detail le justifie vraiment.
- Dans la section `Sources` des pages publiques, pour une image, ne garder que le lien vers la source ou le fichier. Ne pas afficher `Photo`, `Auteur`, `Licence`, ni des mentions internes comme `Ajout local`, `Chemin du site`, `Added locally` ou `Site path`.
- Script `npm run audit:images` pour inventorier doublons, noms non normalisés et manques de variantes modernes.

---

## Pré-requis
- PHP 8.1+ avec extensions standard.
- Composer.
- Node.js `20.19+` ou `22.12+` / npm.
- Recommandé : `nvm` + `nvm use` à la racine du dépôt (`.nvmrc` fourni : `22.22.1`).
- MySQL 5.7+/MariaDB 10+ requis si vous activez le stockage SQL (`EDITORIAL_STORAGE` ou `BLOG_STORAGE` en `dual-write|sql`) ou si vous lancez `composer editorial-import-sql` / `composer blog-import-sql`.
- Secrets : rester dans `.env` hors `public/`, non versionné (voir `.gitignore`).

---

## Installation & scripts
```bash
# Dépendances backend
composer install --working-dir=backend

# Version Node recommandée
nvm install
nvm use

# Dépendances frontend
cd frontend
npm install
```

### Développement local
```bash
# Mode recommandé : lance PHP + Vite, vérifie les ports et stoppe proprement les deux processus
./dev.sh

# Terminal 1 : serveur PHP
cd backend
php -S 127.0.0.1:8000 -t public public/dev-router.php

# Terminal 2 : Vite + proxy /core
cd frontend
npm run dev   # http://127.0.0.1:5173
```
Visiter `https://127.0.0.1:18443` (langue forçable avec `?lang=en`) en mode `./dev.sh`.  
Le proxy HTTPS local relaie vers PHP `http://127.0.0.1:8000`, et Vite relaie `/core/*` vers le serveur PHP.

Variables optionnelles pour `./dev.sh` :
- `DEV_LANG` pour la langue d'ouverture affichée (`fr` par défaut)
- `PHP_HOST` / `PHP_PORT` pour le serveur backend
- `VITE_HOST` / `VITE_PORT` pour le serveur Vite
- `HTTPS_ENABLED=0` pour désactiver le proxy HTTPS local
- `HTTPS_HOST` / `HTTPS_PORT` pour l'URL HTTPS locale (par défaut `https://127.0.0.1:18443`)
- `REUSE_EXISTING_SERVICES=1` (défaut) pour réutiliser un PHP/Vite déjà lancé au lieu d'échouer sur port occupé

Exemple :
```bash
PHP_PORT=8080 VITE_PORT=5174 DEV_LANG=en ./dev.sh
```

### Build & copie des assets
```bash
cd frontend
npm run build
```

Notes :
- `npm run build` publie automatiquement le résultat vers `backend/public/` via le script `postbuild`
- `npm run postbuild` ou `npm run publish:backend` servent uniquement à republier un `dist/` déjà généré

### Initialisation DB + admin (CLI)
```bash
composer init-db-admin --working-dir=backend -- \
  --db-host=127.0.0.1 \
  --db-port=3306 \
  --db-name=caramagnols \
  --db-user=root \
  --db-password='motdepasse_sql' \
  --admin-email=admin@exemple.tld \
  --admin-password='motdepasse-admin-fort' \
  --dry-run
```

Puis relancer la meme commande sans `--dry-run` pour appliquer.

### Tests
- **Frontend** : `cd frontend && npm run test:run` (Vitest, jsdom).
- **Backend** : `composer test` ou `vendor/bin/phpunit` (tests sous `backend/tests/`).
- Couverture Vitest : `frontend/coverage/`.

### Outils CLI utiles (backend/core/tools)
- `php backend/core/tools/check_env.php [--env=production|... --json --require=KEY1,KEY2 --strict-prod-security]` : valide la présence/permissions des variables d’env et des clés critiques.
- `php backend/core/tools/generate_search_index.php` : construit `backend/data/search_index*.json` à partir des templates.
- `php backend/core/tools/generate_favicon.php` : régénère les favicons depuis `frontend/src/assets/images/structure/logo.*`.
- `php backend/core/tools/editorial_backup_restore.php backup [--output=...] [--storage=json|sql|dual-write]` : exporte un backup complet pages/navigation/blog/discussions.
- `php backend/core/tools/editorial_backup_restore.php restore <backup.json> --force [--storage=json|sql|dual-write]` : restaure ce backup (commande destructive).
- `php backend/core/tools/backup_production.php [--scope=all|files|sql] [--dry-run] [--json] [--quiet]` : crée un backup production hors webroot avec archive du dossier backend (`.tar.gz`) et dump SQL (`.sql.gz`), puis applique la rétention.

### Conversion images
```bash
cd frontend
npm run build:webp   # Sharp → WebP + tailles responsive
```

Pour les images inserees dans le corps des pages editoriales :
- `400 px` est la largeur cible par defaut
- `700 px` est un maximum reserve aux visuels qui apportent un detail utile a la lecture
- eviter de servir plus large quand le gain editorial est nul
- dans `Sources`, ne pas exposer de traces internes de workflow comme `Ajout local`, `Chemin du site`, `Added locally` ou `Site path`

## Licence
- Code sous licence MIT (voir fichier `LICENSE`).
- Les assets (images, logos) doivent être utilisés uniquement si vous en détenez les droits ou l’autorisation explicite ; remplacer ou attribuer si nécessaire.
- Rappel : à chaque modification significative (fonctionnalité, build, dépendance), mettre à jour ce README si nécessaire.

---

## Configuration (.env)
Copier `backend/.env.example` vers `backend/.env`, puis ajuster :
- `APP_ENV` (`development`/`production`…), `BASE_URL`, `DEFAULT_LANG`.
- `VITE_DEV_SERVER_URL` si Vite n'est pas expose sur `http://localhost:5173`.
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_TABLE_PREFIX`.
- SMTP : `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT`, `MAIL_SMTP_USER`, `MAIL_SMTP_PASSWORD`, `MAIL_SMTP_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.
- Admin : `ADMIN_LOGIN_PATH`, `ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH`, `ADMIN_SESSION_KEY`, `ADMIN_LANGUAGE` (`fr`, `en` ou `de`; défaut `DEFAULT_LANG`).
- HTTPS/proxy : `FORCE_HTTPS`, `FORCE_HTTPS_ON_LOCALHOST`, `TRUST_PROXY_HEADERS`.
- Backups production : `PRODUCTION_BACKUP_ROOT`, `PRODUCTION_BACKUP_RETENTION_DAYS`, `PRODUCTION_BACKUP_TAR_BINARY`, `PRODUCTION_BACKUP_MYSQLDUMP_BINARY`, `PHP_CLI_BINARY`. Ces valeurs, ainsi que la connexion SQL utilisee par le dump, peuvent aussi etre ajustees depuis `Admin > Parametres > Sauvegardes`; le mot de passe SQL n'est jamais reaffiche et les chemins de sortie restent refuses s'ils pointent dans le backend ou le webroot.
- Exemple par défaut : `ADMIN_LOGIN_PATH=admin`.
- `ADMIN_PASSWORD_HASH` est volontairement vide dans `.env.example` tant qu’aucun compte admin n’est créé.
- Vérifier les permissions du `.env` (600 ou 640, hors `public/`).  
Commande de contrôle : `composer check-env --working-dir=backend` (option `--env=production` pour forcer les clés prod, et `--strict-prod-security` pour rendre bloquants les points sécurité admin critiques).

---

## Base de données
- Schéma de base dans `backend/sql/install.sql` (tables `car_users`, `car_articles`, `car_comments` avec contraintes FK).
- Préfixe configurable via `DB_TABLE_PREFIX` (défaut `car_`) et helper `db_table()` en PHP.
- Surcharge des paramètres de connexion via `backend/config/database.override.php` (généré par le module admin, ignoré par Git).
- Commande canonique d'initialisation : `composer init-db-admin --working-dir=backend -- ...` (base, schemas SQL, compte admin, overrides).
- Installation shell / hors webroot : `backend/README_INSTALLATION_HORS_WEBROOT.md`.

---

## Internationalisation
- **Serveur** : résolution URL (`/fr/`), paramètre `?lang`, cookie `lang`, header `HTTP_ACCEPT_LANGUAGE`; fallback `DEFAULT_LANG`. Le bootstrap commun fixe `CURRENT_LANG` puis charge `backend/lang/{code}.php` via `Translator`.
- **Client** : sélection ou persistance de la langue via `i18n.ts` ; attributs `data-i18n` et `data-i18n-attr` dans le HTML pour hydrater les textes/attrs ; fallback sur les contenus existants.
- **Navigation + consentement** : labels marque/langues du header et fallback titre YouTube (cookie consent) sont fournis par les clés i18n backend (`TXT_SITE_BRAND`, `TXT_LANGUAGE_*_LABEL`, `TXT_YOUTUBE_VIDEO_FALLBACK_TITLE`), donc pilotables depuis les dictionnaires et overrides admin.
- **Tarteaucitron (services externes)** : l’admin permet de définir la liste `services` et un objet JSON `Variables JS services` injecté dans `tarteaucitron.user` (exemple GTM : `{"googletagmanagerId":"GTM-XXXXXXX"}`).
  L’objet vide doit rester saisi en `{}` (et non `[]`) ; la validation refuse toujours les listes JSON explicites pour éviter une configuration invalide.
  Les drapeaux booléens sont normalisés côté runtime/admin (compatibilité legacy avec des valeurs texte comme `false`/`off`/`0`) pour éviter un recochage involontaire après sauvegarde.
  Clés fréquentes : `googletagmanagerId` (GTM), `googleadsId`, `matomoHost` + `matomoId`, `facebookpixelId`, `hotjarId`.

---

## Sécurité & conformité
- En-têtes : CSP restrictive, `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`; HSTS si HTTPS détecté.
- CSP: ouverture conditionnelle des domaines GTM/Google Analytics quand le service tarteaucitron `googletagmanager` est activé.
- Sessions : cookie `caramagnols_session`, `HttpOnly`, `SameSite=Strict`, `Secure` en HTTPS.
- CSRF : helpers génériques (`csrf_token`, `csrf_validate`) + variantes admin.
- Proxy headers : `X-Forwarded-*` pris en compte uniquement si `TRUST_PROXY_HEADERS=true`.
- Rate limiting : `SessionRateLimiter` (clé + capacité + fenêtre).
- Sanitisation : textes, emails, tags, commentaires, traductions (HTML autorisé limité) dans `core/validation.php`.
- Audit config : `core/tools/check_env.php` pour vérifier présence des secrets et permissions.
- Audit headers preprod : `composer check-security-headers --working-dir=backend -- --url=https://preprod.exemple.tld`.

---

## Accès admin
- URL canonique : `/<base_path>/<ADMIN_LOGIN_PATH>` (exemple : `/admin` si `base_path=/`, `/catalogue/admin` si `base_path=/catalogue`).
- Route keepalive admin : `POST /<base_path>/<ADMIN_LOGIN_PATH>/session/ping` (prolongation de session apres warning d'inactivite).
- Les anciens chemins `rss.php`, `assets/rss.php` et les anciens alias admin restent compatibles mais ne sont plus la voie de référence.
- Aucun identifiant par défaut n’est fourni. Renseigner explicitement `ADMIN_EMAIL` et `ADMIN_PASSWORD_HASH` dans `.env`.

---

## Convention de code & qualité
- **PHP** : style procédural, helpers dédiés (`env()`, `app_config()`, `t()`). Tests PHPUnit requis pour nouvelles fonctions critiques.
- **JS/SCSS** : lint via `npm run lint` (`lint:js` + `lint:css`). Préférer les imports Vite pour les assets (`@/assets/...`). Pas de `console` hors logger centralisé.
- **CSS** : nouvelles classes en `kebab-case`; utilitaires `.u-` depuis `_utilities.scss`; hooks JS préfixés `js-`; placeholders `%` pour factoriser. Anciennes classes PascalCase conservées pour compatibilité mais à éviter.

---

## Roadmap (extrait)
- M3 : Moteur de recherche front complet sur `backend/data/search_index.json`, filtres et UX.
- M3 : Dashboard admin enrichi (logs, gestion BDD : création/écrasement contrôlé, vérification tables).
- M4 : Module blog complet (CRUD multi-langue, workflow brouillon→publication, modération commentaires, anti-spam), analytics/SEO.

---

## Déploiement (rappel)
1) Builder le front : `npm run build` (publication dans `backend/public`).  
cd ~/www/caramagnols/frontend
npm run build

2) Déployer `backend/public/` + `backend/data/` (index recherche) sur l’hébergement PHP.  
3) Mettre en place `.env` sécurisé hors `public/`, vérifier avec `composer check-env --env=production`.  
4) Configurer le serveur web pour pointer sur `backend/public/` comme document root et autoriser le cache long sur `assets/` et `.vite/`.  
5) Activer HTTPS pour bénéficier de HSTS et des cookies `Secure`.

### Déploiement rapide (petits correctifs)

Scripts :

- `backend/tools/deploy-fast.sh` : sync uniquement les fichiers backend modifies.
- `backend/tools/deploy-release.sh` : sync complet backend (release).
- `backend/tools/check-log-alerts-runner.sh` : runner exploitation pour `check_log_alerts.php` (utilisable en scheduler).
- `backend/tools/systemd/install-check-log-alerts-systemd.sh` : installation timer `systemd` preprod/prod.
- `backend/core/tools/run_cron_center.php` : point d'entree unique pour le cron OVH ; il charge les jobs actifs stockes en SQL, applique leurs expressions cron et journalise les executions. Le Cron Center admin peut aussi lancer un job manuellement pour test, avec les memes verrous et journaux. Les scripts executables restent limites a la liste autorisee `core/tools/*.php` (extension possible via `CRON_CENTER_ALLOWED_SCRIPTS`).
- `backend/core/tools/backup_production.php` : backup production du dossier backend et dump SQL compresse, appele directement ou via Cron Center.
- Les deux scripts preservent `.env` et `backend/config/*.override.php` (config admin runtime).
- Les deux scripts excluent et nettoient automatiquement les fichiers non-prod : `tests/`, `docs/`, `README*`, `phpunit.xml`, `phpstan*`, `phpcs.xml`, `package*.json`, `replace_image_paths.php`, `backend/public/dev-router.php`, `.env.example`, `.env.production`, `.env.bak.*`, `*.bak`, `*.old`, `*.orig`, `*.tmp`, `*~`, `.DS_Store`, `Thumbs.db`.
- `deploy-release.sh` exclut aussi les artefacts runtime/locaux non produits : `backend/var/**`, `backend/data/logs/**`, `backend/data/snapshots/**`.
- Les deux scripts regenerent `backend/public/sitemap.xml` sur la cible.
- Le sommaire public peut etre regenere dans la page editoriale avec le meme collecteur que le sitemap : `php backend/core/tools/generate_site_summary.php --storage=json` pour le registre JSON versionne, ou sans `--storage` pour le stockage editorial actif. Utiliser `--dry-run --stdout` pour controler le HTML sans ecrire.
- Les deux scripts executent `backend/core/tools/check_vite_assets.php` avant et apres synchronisation : si `backend/public/.vite/manifest.json` reference un fichier absent de `backend/public/assets/`, le deploiement echoue au lieu de laisser un front sans JavaScript ou CSS.
- Les deux scripts executent `backend/core/tools/check_prod_tree.php --clean` sur la cible : la release echoue si le backend prod conserve un residu de developpement, test, documentation, backup ou temporaire apres nettoyage.

Variables requises :

```bash
export REMOTE_HOST="lescaramgl-ssh@ssh.cluster103.hosting.ovh.net"
export REMOTE_BACKEND="/home/lescaramgl-ssh/caramagnols/backend"
# optionnel: force l'URL canonique du sitemap
export SITEMAP_BASE_URL="https://www.lescaramagnols.com"
```

Prévisualisation (sans ecriture distante) :

```bash
bash backend/tools/deploy-fast.sh --dry-run
bash backend/tools/deploy-release.sh --dry-run
```

Note `deploy-fast.sh` :

- mode par defaut = fichiers backend **stages** uniquement (safe deploy)
- pour inclure aussi les fichiers non stages : `--all-changes`

Execution :

```bash
# Correctif rapide
bash backend/tools/deploy-fast.sh

# Release complete (vendor sync par defaut)
bash backend/tools/deploy-release.sh
```

Cas `composer.lock` modifie localement :

- utiliser `deploy-release.sh` (ou `deploy-fast.sh --with-vendor`) pour pousser `vendor/` sur OVH si Composer n'est pas disponible en SSH.

Cas frontend modifie :

- executer d'abord `cd frontend && npm run build` pour regenerer et publier localement `backend/public/.vite/manifest.json` et les fichiers hashes sous `backend/public/assets/`
- lancer ensuite le deploiement ; le controle Vite bloque si un fichier reference par le manifest manque localement ou sur OVH apres sync

Rollback minimal :

1. Restaurer `.env` depuis une sauvegarde manuelle si necessaire.
2. Redeployer la derniere archive stable (zip ou release precedente).
3. Purger le cache runtime :

```bash
cd /home/lescaramgl-ssh/caramagnols/backend
php -r "require 'core/bootstrap.php'; app_runtime_cache_clear(['pages','navigation','translations']); echo 'cache_cleared'.PHP_EOL;"
```

---

### Commandes rapides (mémo)
- Dev : `./dev.sh` depuis la racine (HTTP + HTTPS local).
- Dev manuel : `php -S 127.0.0.1:8000 -t backend/public backend/public/dev-router.php` + `npm run dev`.

- Tests : `composer test` ; `npm run test:run`.
- Lint : `npm run lint`.
- Build : `npm run build`.
- Env check : `composer check-env --working-dir=backend`.

---

> Dernière mise à jour : 17 mars 2026. Merci d’ajouter vos modifications et tests exécutés dans vos PRs.

# Rapport d'optimisation Codex

Analyse réalisée (28 février 2026) --- aperçu rapide du projet et
leviers d'optimisation.

------------------------------------------------------------------------

## Constats

-   Backend PHP 8.1 procédural, fonctions globales et fichiers
    utilitaires centralisés (backend/core/\*.php), pas de PSR-4 ni
    d'injection de dépendances.
-   Routage minimal par fichier (backend/core/router.php), pas de notion
    d'HTTP verbs ni de middleware ; vues en templates PHP avec blocs
    \$blocks.
-   Frontend Vite 7 en ESM + SCSS, JS pur sans typage, pipeline
    d'images/WebP via Sharp, copie manuelle des assets vers
    backend/public (frontend/package.json).
-   Tests très partiels : quelques tests PHPUnit (backend/tests) et
    Vitest (i18n/menu), pas de couverture des routes ni des pages.
-   Sécurité de base (CSP, CSRF, rate limiter session) mais CSP tolère
    unsafe-inline et l'emailing repose sur mail()
    (backend/core/security.php, backend/core/mailer.php).

------------------------------------------------------------------------

## Optimisations Backend

-   Introduire un autoload PSR-4 + namespaces et regrouper le code par
    domaine (src/Http, src/I18n, src/Security, etc.) ; adapter
    composer.json.
-   Remplacer le routeur ad hoc par FastRoute/nikic + middleware
    (langue, sécurité, rate limit).
-   Externaliser la configuration .env via vlucas/phpdotenv ou
    symfony/dotenv.
-   Durcir le rate limiting via stockage Redis/filesystem.
-   Industrialiser l'emailing avec Symfony Mailer ou PHPMailer + logs
    d'envoi.
-   Isoler un module Domain/Blog avec interfaces repository
    (MySQL/JSON).

------------------------------------------------------------------------

## Optimisations Frontend

-   Migration progressive vers TypeScript.
-   Ajouter autoprefixer + browserslist + éventuellement
    @vitejs/plugin-legacy.
-   Nettoyer les assets hashés obsolètes en postbuild.
-   Exporter les tokens SCSS en CSS Custom Properties.
-   Étendre les tests Vitest (menus, i18n, interactions).
-   Envisager un mode static prerender (SSG).

------------------------------------------------------------------------

## Qualité & Langage

-   Ajouter PHPStan/Psalm + PHPCS.
-   Étendre PHPUnit (routeur, sécurité, API lang).
-   Script de vérification des clés i18n manquantes.
-   Centraliser le logging avec Monolog.
-   Mettre en place CI (GitHub Actions).

------------------------------------------------------------------------

## Sécurité & Performance

-   CSP sans unsafe-inline (nonces/hashes), ajouter frame-ancestors
    'none'.
-   Adapter cookie langue (SameSite=Lax + Secure).
-   Cache HTTP (ETag, Cache-Control) pour JSON.
-   Ajouter COOP/COEP si possible.
-   Optimiser build Vite (manualChunks, minification SVG, Brotli).

------------------------------------------------------------------------
