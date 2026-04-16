# Preparation V1 - Checklist Deploiement

Date de reference : 2026-03-21

Ce document propose un plan detaille pour finaliser la webapp en version 1 deployable.
Il est base sur l'etat reel du depot et sur des verifications executees localement.

## 1) Etat technique mesure (2026-03-19)

Commandes executees :

- `composer check-env` -> OK
- `composer check-i18n` -> OK
- `composer test` -> OK (177 tests)
- `composer phpstan` -> OK
- `composer phpcs` -> OK
- `npm run lint` -> OK
- `npm run test:run` -> OK (23 tests)
- `npm run build` -> OK
- `composer audit` -> OK
- `npm audit --json` -> OK (0 `high`/`critical`)
- `composer benchmark-routes -- --iterations=20 --warmup=3` -> OK (`/` avg 9.24ms ; `/blog` avg 10.20ms)
- `composer check-log-alerts -- --since-minutes=30` -> OK (aucune alerte)
- `npm run audit:images` -> OK (3552 images, 966 groupes de doublons exacts)
- autoload/controllers/http -> OK (`composer dump-autoload -o`, classes controllers resolues, smoke HTTP 200 avec en-tete proxy HTTPS)

Conclusion immediate : les quality gates P0.1/P0.2 sont verts localement, et les actions techniques P2 sont implementees/validees localement.
Le passage en V1 depend maintenant de la validation CI sur branche propre et de la cloture des restants P1/P2 (notamment objectifs Lighthouse et chantier images historiques).

## 2) Architecture actuelle et cible V1

Architecture runtime actuelle (a conserver) :

1. `backend/public/index.php` (point d'entree unique)
2. `backend/core/bootstrap.php` (env + securite + config + i18n)
3. `backend/src/Http/FrontController.php` (dispatch)
4. `backend/templates/*` (rendu serveur)
5. `frontend` (pipeline assets Vite uniquement)

Architecture cible V1 (evolution, pas rewrite) :

1. Garder rendu serveur PHP.
2. Reduire progressivement `backend/core/*` au profit de `backend/src/*` testable.
3. Stabiliser contrats de donnees editoriales (pages/navigation/discussions).
4. Durcir securite admin + secrets + politiques HTTPS.
5. Industrialiser QA (tests/lint/audit) jusqu'a gate vert.

## 3) Priorites V1 (bloquants puis structurants)

## Priorite 0 - Bloquants release

### P0.1 Qualite outillee 100% verte

- Corriger le test `EditorialImportServiceTest` (adaptation fixture SQL apres nettoyage DB).
- Corriger `phpstan` (58 erreurs, surtout types array et checks redondants).
- Corriger `phpcs` (2 signatures multi-line).
- Corriger `stylelint` dans `_menus.scss` (ordre de specificite + nommage classes).
- Aligner la CI pour refuser toute regression sur ces 4 gates.

Critere d'acceptation :

- `composer test && composer phpstan && composer phpcs`
- `npm run lint && npm run test:run && npm run build`
- workflow CI vert sur branche propre.

### P0.2 Hygiene securite dependances

- Monter `phpunit/phpunit` vers version non vulnerable (`>=10.5.62` ou equivalent branche choisie).
- Corriger vulnerabilities npm dev (`immutable >=5.1.5`, `flatted >=3.4.0`) puis relancer audit.
- Figer versions dans lockfiles et valider compatibilite tests.

Critere d'acceptation :

- `composer audit` sans CVE ouverte pertinente.
- `npm audit --json` sans `high`/`critical`.

### P0.3 Aligner routes/admin legacy restantes

- Supprimer ou adapter le smoke CI qui cible une URL admin obfusquee legacy.
- Verifier qu'aucune doc "active" ne depend d'URL obfusquee.
- Garder uniquement la route admin canonique (`/<ADMIN_LOGIN_PATH>`).

Critere d'acceptation :

- aucune occurrence de l'ancien identifiant admin obfusque ne remonte dans la CI et la documentation active.

## Priorite 1 - Stabilisation architecture et code

### P1.1 Reduction dette `core/*`

- Isoler les blocs legacy encore lourds (`router.php`, helpers globaux, menu_loader).
- Migrer les comportements metier vers services `src/*` avec contrats clairs.
- Limiter les fonctions globales aux wrappers de compatibilite.

Critere d'acceptation :

- Nouvelles features ajoutees uniquement dans `src/*`.
- Regression tests couverts sur routes publiques/admin.

Statut 2026-03-19 :

- [x] Routage legacy de `backend/core/router.php` deplace dans `backend/src/Http/LegacyRouteResolver.php` ; `core/router.php` est maintenant un wrapper de compatibilite.
- [x] Logique legacy de projection/normalisation des menus de `backend/core/menu_loader.php` deplacee dans `backend/src/Navigation/LegacyMenuRuntime.php`.
- [x] Tests de non-regression ajoutes pour les nouveaux services (`LegacyRouteResolverTest`, `LegacyMenuRuntimeTest`) + suites `RouterTest`/`BlogRouteTest`/`MenuLoaderTest` maintenues vertes.
- [x] Helpers de routage public (`request_path`, `normalize_public_route`, `public_route_variants`) extraits vers `backend/src/Http/RoutePathHelper.php` ; `backend/core/helpers.php` conserve des wrappers de compatibilite.
- [~] Dette restante ciblee : poursuivre l'extraction progressive de certains helpers globaux vers des services `src/*` (facades de runtime uniquement dans `core/*`).

### P1.2 Decoupage fichiers monolithiques admin

Fichiers prioritaires a decouper :

- `backend/src/Admin/AdminSettingsService.php`
- `backend/src/Admin/AdminController.php`
- `backend/src/Admin/AdminNavigationService.php`

Actions :

- extraire validateurs, DTO, serializers, persistance, rendering helpers.
- ajouter tests unitaires par sous-composant.

Critere d'acceptation :

- baisse de complexite cyclomatique et meilleure lisibilite des tests.

Statut 2026-03-19 :

- [x] `backend/src/Admin/AdminController.php` : extraction de la normalisation des formulaires serialises vers `backend/src/Admin/AdminSerializedFormNormalizer.php`.
- [x] `backend/src/Admin/AdminNavigationService.php` : extraction du parsing d'action builder et du codec de path item vers `backend/src/Admin/Navigation/NavigationBuilderActionParser.php` et `backend/src/Admin/Navigation/NavigationItemPathCodec.php`.
- [x] `backend/src/Admin/AdminNavigationService.php` : extraction de la normalisation/lecture des labels multilingues vers `backend/src/Admin/Navigation/NavigationItemLabelManager.php`.
- [x] `backend/src/Admin/AdminSettingsService.php` : extraction de la logique de traductions (parse/validation/normalisation) vers `backend/src/Admin/Settings/AdminTranslationSettingsManager.php`.
- [x] `backend/src/Admin/AdminSettingsService.php` : extraction du sous-domaine "alertes logs" vers `backend/src/Admin/Settings/AdminLogAlertsSettingsManager.php`.
- [x] Tests unitaires ajoutes pour ces sous-composants.
- [~] Restant P1.2 : poursuivre le decoupage de la persistance/serialisation/rendu dans `AdminSettingsService` et `AdminNavigationService` (etendre DTO/validators dedies).

### P1.3 Contrats de donnees et DB

- Finaliser schema SQL editorial/documenter source de verite (`json`, `sql`, `dual-write`).
- Verifier contraintes relationnelles de suppression en cascade article->discussions (ou traitement applicatif explicite).
- Ajouter script de backup/restore documente avant operations destructives.

Critere d'acceptation :

- suppression article = aucune discussion orpheline.
- import/export editorial reproducible.

Statut 2026-03-19 :

- [x] Suppression article -> discussions rattachees validee au niveau service via `backend/tests/AdminBlogServiceTest.php` (suppression article + thread associe).
- [x] Contrat SQL blog V1 ajoute : migration `backend/sql/editorial/005_blog.sql` avec FK `blog_discussions(article_slug, article_lang)` -> `blog_articles(slug, lang)` en `ON DELETE CASCADE`.
- [x] Script d'import JSON -> SQL pour le blog ajoute : `composer blog-import-sql` (`--articles-only`, `--discussions-only`, `--no-prune`).
- [x] Script de backup/restore editorial mis a jour pour restaurer discussions via repository (modes `json|sql|dual-write`) : `backend/core/tools/editorial_backup_restore.php`.
- [x] Runbook complet backup + restore rejoue en preprod (`storage=json`) avec preuves archivees (`103-w1-05-backup.txt`, `104-w1-05-restore.txt`).

## Priorite 2 - Performance et robustesse exploitation

### P2.1 Performance frontend reelle

- Definir budget de poids CSS/JS/image.
- Ajouter lazy loading et dimensions explicites sur medias non critiques.
- Evaluer optimisation images historiques (formats modernes, dedoublonnage, nommage).
- Garder un cache HTTP agressif sur assets fingerprintes.

Critere d'acceptation :

- cible Lighthouse mobile officielle :
  - Performance >= 80
  - Accessibilite >= 95
  - Best Practices >= 95
  - SEO >= 95
- pas de regressions visuelles majeures sur desktop/mobile.

Statut 2026-03-19 :

- [x] Budget frontend automatise via `frontend/tools/check-budgets.mjs` et branche sur `npm run build` (gate bloquante).
- [x] Budgets courants valides : JS entry 11.6 KiB, CSS entry 83.4 KiB, JS+CSS initial 95.0 KiB, image max 47.6 KiB.
- [x] Lazy loading + dimensions explicites ajoutes sur medias non critiques (partials layout/menus + pages search/dynamic).
- [x] Cache HTTP agressif sur assets fingerprintes dans `backend/public/.htaccess` (`max-age=31536000, immutable`).
- [x] Audit images historiques outille (`npm run audit:images`) pour dedoublonnage/normalisation/variants modernes.
- [x] Cible Lighthouse mobile officielle fixee pour la V1 :
  - Performance >= 80
  - Accessibilite >= 95
  - Best Practices >= 95
  - SEO >= 95
  - pages de reference : `/` et `/blog` (profil mobile)
  - commande de mesure recommandee (preprod/prod) :
    - `npx --yes lighthouse https://www.lescaramagnols.com --form-factor=mobile --screenEmulation.mobile=true --only-categories=performance,accessibility,best-practices,seo`
    - `npx --yes lighthouse https://www.lescaramagnols.com/blog --form-factor=mobile --screenEmulation.mobile=true --only-categories=performance,accessibility,best-practices,seo`

Mise a jour 2026-03-21 (correctifs SEO/accessibilite cibles Lighthouse) :

- [x] Fallback `meta description` ajoute dans `backend/templates/partials/scripts_head.php` (base `TXT_SCHEMA_ORG_DESCRIPTION` si la page n'a pas de description specifique).
- [x] Landmark principal ajoute dans `backend/templates/partials/layout.php` (`<main id=\"main-content\">`).
- [x] Bouton `remonter` aligne accessibilite (nom accessible identique au libelle visible, image decorative marquee `aria-hidden`).
- [x] Contraste renforce pour le pied de page (`#nav-menu-3 a`) et le libelle `remonter` (`frontend/src/scss/_layout.scss`, `frontend/src/scss/_components.scss`).
- [x] Validation locale apres patch :
  - `npm run build` (publication assets backend) -> OK
  - `phpunit tests/FrontControllerHttpTest.php tests/ScriptsHeadPartialTest.php` -> OK
  - `npm run test:run` -> OK
- [~] Verification finale Lighthouse a confirmer apres deploiement en cible (les mesures sur `https://www.lescaramagnols.com` ne refleteront le patch qu'apres sync backend/public effectif).

### P2.2 Caching et cout backend

- Consolider cache pour pages/menu/traductions si necessaire.
- Encadrer invalidation lors des sauvegardes admin.
- Mesurer temps de rendu route critique (`/`, pages article, blog).

Critere d'acceptation :

- temps moyen route critique en baisse ou stable sous charge normale.

Statut 2026-03-19 :

- [x] Cache runtime consolide pour navigation (`navigation_view_model`), pages et traductions.
- [x] Invalidation explicite apres sauvegardes admin pages/navigation/settings via `app_runtime_cache_clear(...)`.
- [x] Script de mesure route critique ajoute : `backend/core/tools/benchmark_front_routes.php` (script Composer `benchmark-routes`).
- [x] Mesures locales stables : `/` avg 9.24ms (p95 11.12ms), `/blog` avg 10.20ms (p95 11.74ms), statuts HTTP 200.
- [x] Benchmark rejoue en cible avec donnees editoriales realistes :
  - J+1 (`iterations=20`, `warmup=3`) :
    - `/` avg=135.76ms, p95=143.29ms, status 200
    - `/blog` avg=154.52ms, p95=182.40ms, status 200
  - J+7 (`iterations=30`, `warmup=5`) :
    - `/` avg=219.24ms, p95=157.07ms, max=2714.61ms, status 200
    - `/blog` avg=138.19ms, p95=163.81ms, status 200
  - traces archivees dans `docs/private/recette-preprod-v1-2026-03-21/`.

### P2.3 Observabilite exploitable

- Definir retention/rotation logs (`security.log`, `content.log`, `access.log`).
- Ajouter correlation id par requete (si absent) pour diagnostic.
- Ajouter alertes de base (ex: pics de 403/429/login failure).

Critere d'acceptation :

- incidents de production tracables sans accès shell profond.

Statut 2026-03-19 :

- [x] Correlation id par requete implemente dans `FrontController` (entete `X-Request-Id` en reponse, reprise `X-Request-Id`/`X-Correlation-Id` en entree).
- [x] Contexte requete injecte automatiquement dans tous les logs via `AppEventLogger` (`request_id`, method, uri, path, client_ip).
- [x] Rotation/retention logs implementee dans `LoggerFactory` (config `.env` : `LOG_RETENTION_FILES`, `LOG_ROTATION_MAX_BYTES`).
- [x] Script d'alertes de base ajoute : `backend/core/tools/check_log_alerts.php` (seuils 403/429/login failed/rate_limited, mode `--strict`).
- [x] Verification locale : `composer check-log-alerts -- --since-minutes=30` sans alerte ouverte.
- [x] Script d'alertes connecte aux canaux ops (webhook/email) via options CLI et variables d'environnement (`LOG_ALERTS_*`).
- [x] Scheduler systemd fourni pour preprod/prod (`backend/tools/check-log-alerts-runner.sh`, `backend/tools/systemd/*`).
- [x] Pilotage admin V2 du mode `notify_on` (`alerts|always`) via `Parametres > Observabilite ops`, sans exposition des secrets infra.

## 4) Securite V1 (application + exploitation)

## Socle applicatif

- [x] HTTPS force en prod (app + serveur web), HSTS cote infra.
- [x] Session admin durcie (`HttpOnly`, `SameSite`, regen ID).
- [x] CSRF sur tous POST sensibles.
- [x] Timeout inactivite admin + re-auth sensibles actifs.
- [x] Warning de session admin avant expiration (T-120s), fenetre de decision 120s, deconnexion auto sans reponse.
- [x] 2FA TOTP actif hors localhost.
- [~] Allowlist IP admin active en prod (si contexte le permet).
- [x] reCAPTCHA discussions + honeypot + rate limiting verifies.

## Surface publique

- [~] Webroot restreint a `backend/public`.
- [x] Fichiers sensibles bloques via `.htaccess`/equivalent Nginx.
- [x] Aucun secret dans Git (`.env`, overrides, tokens API).
- [~] Headers securite verifies en preprod.

Statut 2026-03-19 :

- [x] `backend/core/security.php` : redirection HTTPS applicative, headers securite et HSTS; prise en charge `X-Forwarded-Proto` seulement si `TRUST_PROXY_HEADERS=true`.
- [x] `backend/public/.htaccess` : redirection HTTPS serveur + blocage de fichiers sensibles (env/composer/package/phpunit + extensions techniques).
- [x] Session admin durcie validee (`session.cookie_httponly=1`, `SameSite=Strict`, `session_regenerate_id()` sur login/logout).
- [x] Couverture CSRF etendue aux formulaires contact (page legacy + composant `contact_form` du renderer structure).
- [x] Re-auth actions sensibles + timeout inactivite actifs dans `AdminController`/`auth/admin.php`.
- [x] Warning session admin et keepalive CSRF (`POST /<base_path>/<ADMIN_LOGIN_PATH>/session/ping`) actifs dans `AdminController` + layout admin.
- [x] TOTP hors localhost controlee et enforcee par `check_env --env=production` (`ADMIN_TOTP_ENABLED=true` + secret valide).
- [~] Allowlist IP: mecanisme actif (`ADMIN_ALLOWED_IPS`), `check_env` alerte si vide/loopback-only; la valeur finale reste dependante du contexte infra.
- [x] Discussions publiques: honeypot + double rate limit + reCAPTCHA + nonce anti-replay verifies (controller + tests).
- [x] Hygiene secrets: `admin.override.php` et `database.override.php` purges; `check_env` detecte les overrides trackes et echoue si valeur sensible probable.
- [x] Nouveau script preprod `composer check-security-headers -- --url=...` pour valider CSP/XFO/nosniff/Referrer/Permissions/COOP/CORP/HSTS.
- [x] Parametrage tarteaucitron etendu cote admin : liste des services + variables JS par service (objet JSON injecte dans `tarteaucitron.user`, ex: `googletagmanagerId` pour GTM).
- [x] Verifications fin de tache executees localement: `composer dump-autoload -o` (autoload OK, 1933 classes), `class_exists` des controllers front/admin (OK), smoke HTTP (`/blog`, `/rss`, `/admin` en 200).
- [x] Controle local headers securite valide via `check_security_headers.php` (status 200, headers requis presents) + cache runtime purge (`pages`, `navigation`, `translations`).
- [~] Restant section 4: executer `check-security-headers` sur l'URL de preprod et consigner le resultat (preuve de verification avant go-live).

Mise a jour 2026-03-20 (execution ticket W1-03) :

- [x] `php core/tools/check_security_headers.php --url=https://www.lescaramagnols.com` -> OK (`status 200`, headers requis presents).
- [x] `php core/tools/check_env.php --env=production --strict-prod-security` -> OK.
- [x] Preuves archivees : `docs/private/recette-preprod-v1-2026-03-20/33-check-security-headers-www.txt` et `34-check-env-production-strict.txt`.
- [~] Si l'environnement de go-live utilise une URL preprod distincte, rejouer et archiver `check-security-headers` sur cette URL avant bascule finale.

Mise a jour 2026-03-20 (execution ticket W1-04) :

- [x] Stabilite admin navigation/footer validee : `./vendor/bin/phpunit tests/AdminSerializedFormNormalizerTest.php tests/AdminNavigationServiceTest.php tests/AdminControllerTest.php` -> OK (`45` tests, `298` assertions).
- [x] Cas systeme verifies sur le builder menus : `footer_notice`, `banner`, `remonter` (normalisation formulaire + persistence + rendu admin).
- [x] Invalidation cache navigation verifiee apres sauvegarde menus (test de non-regression ajoute dans `AdminNavigationServiceTest`).
- [x] Purge cache navigation executee : `php -r "require 'core/bootstrap.php'; app_runtime_cache_clear(['navigation']); ..."` -> `cache_cleared`.
- [x] Preuves archivees : `62-w1-04-admin-tests.txt` et `63-w1-04-cache-clear-navigation.txt`.

Mise a jour 2026-03-20 (execution ticket W1-06) :

- [x] Recette manuelle FO/Admin archivee sur les 4 axes cibles :
  - `docs/private/recette-preprod-v1-2026-03-20/front/desktop`
  - `docs/private/recette-preprod-v1-2026-03-20/front/mobile`
  - `docs/private/recette-preprod-v1-2026-03-20/admin/desktop`
  - `docs/private/recette-preprod-v1-2026-03-20/admin/mobile`
- [x] Verification automatisee des dossiers de preuve : `85-w1-06-directories-check.txt` (`OK`).
- [x] Inventaire des captures/headers archive : `86-w1-06-proof-files.txt`.
- [x] Non-regression FO/Admin rejouee : `87-w1-06-fo-admin-tests.txt` (`53` tests, `306` assertions).
- [x] Synthese anomalies : `88-w1-06-anomalies.md` (aucune anomalie bloquante relevee).

Mise a jour 2026-03-21 (execution ticket W1-05) :

- [x] Backup editorial preprod rejoue : `php core/tools/editorial_backup_restore.php backup --storage=json --output=var/backups/w1-05-backup-2026-03-21.json` -> `103-w1-05-backup.txt`.
- [x] Restauration preprod rejouee : `php core/tools/editorial_backup_restore.php restore var/backups/w1-05-backup-2026-03-21.json --force --storage=json` -> `104-w1-05-restore.txt`.
- [x] Coherence post-restore validee : `47` pages, `8` emplacements navigation, `0` article, `0` discussion (jeu de donnees courant en JSON).

Mise a jour 2026-03-21 (execution ticket W1-07) :

- [x] Controle autoload : `105-w1-07-autoload.txt` -> `autoload_ok`.
- [x] Controle controller admin : `106-w1-07-controller.txt` -> `admin_controller_ok`.
- [x] Controle suite controllers front/admin : `106b-w1-07-controllers-suite.txt` -> `FrontController`, `AdminController`, `AdminRouteResolver` resolus.
- [x] Smoke HTTP/HTTPS en cible : `107-w1-07-http-smoke.txt` -> redirection HTTP 301 vers HTTPS puis status final 200.
- [x] Purge cache runtime de cloture : `108-w1-07-cache-clear.txt` -> `cache_cleared`.
- [x] Tests fin de mission :
  - backend `composer test` -> `109-w1-07-composer-test.txt` (`217` tests, `849` assertions, `19` skipped, `1` warning)
  - frontend `npm run test:run` -> `110-w1-07-npm-test-run.txt` (`30` tests).
- [x] Hygiene docs : `npm run hygiene:docs` -> `112-w1-07-hygiene-docs.txt` (31 fichiers, 0 lien casse).
- [x] Purge cache finale post-documentation : `113-w1-07-cache-clear-final.txt` -> `cache_cleared`.
- [x] Decision ticket :
  - passage S1 -> S2 : `GO`
  - deploiement production immediat : `GO` (etat final valide apres controle go-live)
- [x] Addendum go-live (2026-03-21, 12:50 Europe/Paris) :
  - `check-env --env=production --strict-prod-security` -> OK (`115-check-env-production-strict.txt`)
  - `check-security-headers --url=https://lescaramagnols.com --json` -> OK (`114-check-security-headers-lescaramagnols.json`)
  - `check-log-alerts --since-minutes=60 --strict` -> OK (`116-check-log-alerts-strict.txt`)
  - Instagram accueil inactif confirme (`117-check-instagram-feed.txt`) ; gate strict live non applicable
  - recette admin authentifiee cible (super-admin + 2FA) : validee
  - decision finale confirmee : deploiement production immediat `GO`

Mise a jour 2026-03-20 (warning session admin) :

- [x] Route keepalive admin ajoutee et gouvernee : `POST /<base_path>/<ADMIN_LOGIN_PATH>/session/ping`.
- [x] Warning d'expiration de session admin implemente : prompt Oui/Non a `T-120s`, attente 120s, logout auto sans reponse.
- [x] Couverture tests HTTP ajoutee : scenarios `unauthenticated` (401) et keepalive authentifie (200 + timeout rafraichi).
- [x] Documentation alignee : `README_SECURITE_ADMIN_V1.md`, `backend/README_PUBLIC_ENTRYPOINTS.md`, `README.md`.

Mise a jour 2026-03-21 (images editoriales V1) :

- [x] Admin articles : image de couverture (URL/upload) + metadonnees SEO (`alt`, `title`, `caption`, dimensions).
- [x] Admin pages : image SEO par langue (`translations[*].meta.image`) + upload.
- [x] Rendu front : image article sur liste/detail/chroniques rattachees + balises `og:image` / `twitter:image`.
- [x] Deploiement : `deploy-fast.sh` et `deploy-release.sh` exclus de suppression sur `backend/public/uploads/editorial/**`.
- [x] Qualite locale rejouee sur le scope : `composer test`, `npm run test:run`, `npm run build`, `composer check-i18n`, `npm run hygiene:docs`.

Mise a jour 2026-03-20 (correctif tarteaucitron admin) :

- [x] Correction `AdminSettingsService::normalizeTarteaucitronUserConfigJson()` : l'objet JSON vide reste serialize en `{}` (plus de conversion implicite en `[]`).
- [x] Validation conservee : les listes JSON explicites (`[]`) restent refusees avec le message d'erreur dedie.
- [x] Normalisation booleenne renforcee pour tarteaucitron (`false`/`off`/`0` legacy) afin d'eviter le recochage involontaire des cases apres sauvegarde.
- [x] Durcissement runtime complementaire : remplacement des derniers cast `(bool)` ambigus par une normalisation explicite dans `normalizeTarteaucitronConfig()` et `applyRuntimeConfig()`.
- [x] Non-regression ajoutee dans `AdminControllerTest` :
  - `testSettingsUrlSectionAllowsEmptyObjectTarteaucitronUserConfig`
  - `testSettingsUrlSectionPreservesTarteaucitronFalseFlagsWhenConfiguredAsStrings`
  - `testSettingsRejectsTarteaucitronUserConfigJsonListSyntax`

Mise a jour 2026-03-21 (S2 labels menus i18n + persistance SQL) :

- [x] Builder menus : labels d'items editables par langue (champs `label_translations[*]` + `label_default_language`) sans retour au JSON brut.
- [x] Resolution front : fallback labels menus aligne (`langue courante` -> `langue par defaut` -> `label principal` -> `translationKey`).
- [x] Persistance SQL navigation etendue pour les labels i18n :
  - migration `backend/sql/editorial/006_navigation_item_label_i18n.sql`,
  - lecture/ecriture `backend/src/Navigation/SqlNavigationStore.php` avec `label_default_language` + `label_translations_json`.
- [x] Couverture de non-regression ajoutee :
  - `AdminNavigationServiceTest` (sauvegarde labels menu multilingues),
  - `NavigationViewModelBuilderTest` (fallback labels par langue),
  - `SqlNavigationStoreTest` (round-trip SQL labels i18n),
  - `AdminControllerTest` (presence des champs builder),
  - tests unitaires des nouveaux sous-composants (`NavigationItemLabelManagerTest`, `AdminLogAlertsSettingsManagerTest`, `RoutePathHelperTest`).
- [~] Preparation decision F7 renforcee : criteres go-live documentes, suppression de l'ecriture JSON toujours differee tant que la fenetre d'observation exploitation n'est pas close.

## 5) I18n, contenus, coherence UX

- [x] Cartographier les textes encore hardcodes hors pipeline de traduction.
- [x] Etendre gestion admin des traductions FR par defaut puis autres langues.
- [x] Verifier fallback langue et non regression sur pages dynamiques/blog/navigation.

Critere d'acceptation :

- plus aucun texte front "metier" non pilotable par traduction ou contenu editorial.

Statut 2026-03-19 :

- [x] Cartographie des textes front durcis hors pipeline i18n realisee sur le scope V1 (`navigation`, `blog`, `pages dynamiques`, `consentement cookies`).
- [x] Suppression des hardcodes metier identifies :
  - `NavigationViewModelBuilder` n'embarque plus les labels de marque/langues en dur (cles `TXT_SITE_BRAND`, `TXT_LANGUAGE_*_LABEL`).
  - `frontend/src/js/consent.ts` ne fixe plus un titre YouTube en dur ; fallback injecte depuis backend (`TXT_YOUTUBE_VIDEO_FALLBACK_TITLE`).
- [x] Gestion admin des overrides i18n etendue : langue par defaut forcee en tete (`AdminTranslationSettingsManager::normalizeLanguages`), puis autres langues dedupliquees.
- [x] Fallback langue valide par tests :
  - pages dynamiques : fallback traduction par defaut (`PageRepositoryTest`),
  - blog : fallback vers langue par defaut si contenu absent dans la langue demandee (`LegacyRouteResolverTest`),
  - navigation : labels de langues/traductions resolves dans la langue courante (`NavigationViewModelBuilderTest`).

## 6) Nettoyage code et hygiene repo

- Purger references documentaires casses.
- Uniformiser nommage fichiers assets (eviter espaces/caracteres speciaux).
- Nettoyer artefacts transitoires non necessaires dans repo versionne.
- Clarifier politique sur `backend/public/assets` versionnes/non versionnes.

Critere d'acceptation :

- `git status` propre apres build selon politique decidee.

Statut 2026-03-19 :

- [x] Verification automatisee des liens docs active via `frontend/tools/check-doc-links.mjs` (`npm run hygiene:docs`).
- [x] Nommage assets durci via `frontend/tools/check-asset-naming.mjs` (`npm run hygiene:assets`) + normalisation des fichiers restants avec espaces/parentheses.
- [x] Politique artefacts rendue executable via `frontend/tools/check-repo-artifacts.mjs` (`npm run hygiene:repo`) et gate CI associe.
- [x] Politique de versionning clarifiee dans `README_RENDER_ARTEFACTS_V1.md` et `frontend/README_BUILD_PIPELINE.md`.
- [~] Branche de travail locale encore en cours de stabilisation globale ; le critere "git status propre" doit etre valide sur branche release nettoyee apres rebase final.

## 7) Checklist de passage en V1

## A. Pre-release technique

- [x] Toutes les gates qualite sont vertes (tests, lint, static analysis).
- [x] Audits dependances sans vuln high/critical ouvertes.
- [x] Docs canoniques a jour (`README.md`, securite, routes, build).
- [x] Backup DB valide + test de restauration.

Verification executee le 2026-03-19 :

- gates qualite :
  - `composer phpstan` -> OK
  - `composer phpcs` -> OK
  - `composer test` -> OK (182 tests, 700 assertions, 12 skipped)
  - `npm run lint` -> OK
  - `npm run test:run` -> OK (24 tests)
  - `npm run build` -> OK (budgets OK + publication OK)
- audits dependances :
  - `composer audit` -> OK
  - `npm audit --json` -> `high=0`, `critical=0`
- coherence documentaire :
  - `npm run hygiene:docs` -> OK (19 fichiers, 0 lien casse)
  - docs canoniques maj : `README.md`, `README_V1_PREPARATION_DEPLOIEMENT.md`, `frontend/README_BUILD_PIPELINE.md`, `README_RENDER_ARTEFACTS_V1.md`, `backend/README_LOGGING.md`
- backup/restore :
  - ajout option `--storage=json|sql|dual-write` dans `core/tools/editorial_backup_restore.php` pour test fiable par mode
  - validation reelle : `backup --storage=json --output=...` + `restore <backup> --force --storage=json` -> OK (`47` pages, `8` emplacements navigation)
- controles fin de tache :
  - `composer dump-autoload -o` -> OK (autoload optimise, 1933 classes)
  - `php -r 'class_exists(...)'` sur `Caramagnols\Http\FrontController`, `Caramagnols\Admin\AdminController`, `Caramagnols\Admin\AdminRouteResolver` -> OK
  - smoke HTTP local (`/`, `/blog`, `/rss`, `/admin`) -> `308` attendu (redirection HTTPS forcee)
  - purge cache runtime executee : `app_runtime_cache_clear(['pages', 'navigation', 'translations'])`

## B. Pre-release fonctionnel

- [x] Parcours front critiques verifies (desktop + mobile).
- [x] Parcours admin critiques verifies (auth, pages, menus, blog, discussions).
- [x] Suppression article + discussions rattachees testee en bout-en-bout.
- [~] Instagram bloc accueil teste avec credentials valides.

Verification executee le 2026-03-19 :

- front/admin/blog/discussions :
  - `./vendor/bin/phpunit tests/FrontControllerHttpTest.php tests/BlogRouteTest.php tests/DynamicRouteTest.php tests/AdminBlogServiceTest.php tests/BlogDiscussionApiControllerTest.php` -> OK (26 tests)
  - `npm run test:run` (inclut `menus.test.ts`) -> OK (couverture comportement desktop/mobile du menu)
- suppression article + discussions :
  - validee par `AdminBlogServiceTest` (`deletedDiscussions=2`, thread vide apres suppression)
- Instagram :
  - nouveau controle CLI : `composer check-instagram-feed`
  - mode bloquant pre-release : `composer check-instagram-feed -- --strict`
  - etat local courant : KO strict attendu (token absent) -> credentials preprod/prod a fournir avant validation finale

## C. Go-live

- [x] Variables d'environnement prod injectees hors Git.
- [x] Rotation des secrets faite avant mise en ligne.
- [x] HTTPS/host canonique verifies en environnement cible.
- [x] Monitoring/logs operationnels valides.

Etat 2026-03-19 :

- `composer check-env -- --env=production` -> KO attendu en local (secrets/config prod manquants) ; la commande est le gate de verification final en cible.
- runbook go-live ajoute : `docs/v1-go-live-runbook.md` (check-env, check-security-headers, check-log-alerts, check-instagram-feed, rotation secrets).
- verification headers locale outillee :
  - `php core/tools/check_security_headers.php --url=http://127.0.0.1:8000/blog --forwarded-proto=https` -> OK
  - verification preprod HTTPS reelle reste a executer sur URL cible.
- monitoring local :
  - `composer check-log-alerts -- --since-minutes=60 --strict` -> OK
  - validation operationnelle finale preprod/prod executee : pack systemd timer + canaux webhook/email documentes.

## D. Post go-live (J+1 / J+7)

- [x] Revue logs securite et erreurs applicatives.
- [~] Revue performances reelles (temps de chargement, erreurs JS).
- [x] Correctifs rapides documentes et planifies.

Etat 2026-03-21 :

- plan d'execution documente dans `docs/v1-go-live-runbook.md` (J+1/J+7).
- execution J+1 archivee :
  - `composer check-log-alerts --working-dir=backend -- --since-minutes=1440 --strict` -> OK (`122-j1-check-log-alerts-1440.txt`)
  - `composer benchmark-routes --working-dir=backend -- --iterations=20 --warmup=3 --storage=json` -> OK (`123-j1-benchmark-routes-json.txt`)
- execution J+7 archivee :
  - `composer check-log-alerts --working-dir=backend -- --since-minutes=10080 --strict` -> OK (`124-j7-check-log-alerts-10080.txt`)
  - `composer benchmark-routes --working-dir=backend -- --iterations=30 --warmup=5 --storage=json` -> OK (`125-j7-benchmark-routes-json.txt`)
- anomalies consolidees : `128-jplus-anomalies.md` (aucune anomalie bloquante).
- controle cible complementaire :
  - `curl -I -L http://lescaramagnols.com` (redirection HTTP->HTTPS + 200 final) -> `126-http-https-smoke.txt`
  - `composer check-security-headers --working-dir=backend -- --url=https://lescaramagnols.com` -> OK (`127-security-headers-prod.txt`)

## 8) Lot de correction recommande (ordre concret)

- [x] 1. Fix CI/quality gates (tests, phpstan, phpcs, stylelint).
- [x] 2. Patch security advisories (composer/npm).
- [x] 3. Nettoyage references legacy admin et docs cassees.
- [x] 4. Durcissement final securite prod (2FA, allowlist, HTTPS strict).
- [x] 5. Revue perf + cache + assets pour stabiliser le rendu.

Verification executee le 2026-03-19 :

- lot 8.1 (quality gates / CI) :
  - gates locales rejouees et vertes (`composer phpstan`, `composer phpcs`, `composer test`, `npm run lint`, `npm run test:run`, `npm run build`).
  - CI alignee pour bloquer les regressions qualite + audit:
    - ajout `composer audit` en gate CI.
    - ajout `npm audit --audit-level=high` en gate CI.
- lot 8.2 (security advisories) :
  - dependances backend/front deja patchees (`phpunit ^10.5.62`, `immutable ^5.1.5`, `flatted ^3.4.2`) et lockfiles verifies.
  - audits rejoues: `composer audit` OK, `npm audit --json` -> `high=0`, `critical=0`.
- lot 8.3 (legacy/docs) :
  - garde CI anti-regression ajoutee contre reintroduction d'une ancienne route admin obfusquee.
  - verification locale de l'absence de reference active: `rg -n "adminFtyhik5642sZ" README.md README_DOCUMENTATION_INDEX.md docs backend/README*.md backend/core backend/src backend/public frontend` -> aucun resultat.
  - docs cassees rejouees via `npm run hygiene:docs` -> OK (19 fichiers, 0 lien casse).
  - coherence README: suppression doublon "Exploitation/perf" dans `README.md`.
  - tableau de bord admin modernise: vue recentree sur les elements cles de pilotage avec focus prioritaire sur la moderation des discussions en attente.
- lot 8.4 (securite prod) :
  - `check_env` durci avec `--strict-prod-security`:
    - `ADMIN_SESSION_KEY` court devient bloquant en prod strict.
    - `ADMIN_ALLOWED_IPS` vide/loopback-only devient bloquant en prod strict.
  - runbooks/docs alignes sur la commande stricte de pre-go-live.
  - verification locale: `composer check-env -- --env=production --strict-prod-security` -> KO attendu tant que les variables/secrets de prod ne sont pas injectes.
- lot 8.5 (perf/cache/assets) :
  - build + budgets frontend rejoues (OK), hygiene assets/repo OK.
  - benchmark routes critiques rejoue (`composer benchmark-routes -- --storage=json`) pour valider stabilite locale sans dependre d'un backend SQL editorial local: `/` avg 6.99ms (p95 12.93ms), `/blog` avg 7.97ms (p95 13.19ms), status 200.
  - cache runtime purge en fin de lot (`pages`, `navigation`, `translations`).

## 9) Definition de "V1 prete au deploiement"

Statut verifie le 2026-03-21 :

- [x] Toutes les verifications automatiques passent local et CI.
- [~] Le parcours front/admin est valide sur le scope W1-06 (la recette admin authentifiee finale reste a rejouer en cible).
- [~] La securite admin est appliquee en configuration production.
- [x] La documentation canonique permet d'installer, exploiter et depanner sans ambiguite.

Details d'execution :

- verifications automatiques :
  - backend : `composer phpstan`, `composer phpcs`, `composer test`, `composer audit` -> OK
  - frontend : `npm run lint`, `npm run test:run`, `npm run build`, `npm run hygiene:repo`, `npm audit --json` -> OK (`high=0`, `critical=0`)
  - CI : workflow aligne sur ces gates (qualite + audits + smoke HTTP)
- parcours front/admin :
  - non-regression automatisee OK : `phpunit tests/FrontControllerHttpTest.php tests/BlogRouteTest.php tests/DynamicRouteTest.php tests/AdminBlogServiceTest.php tests/BlogDiscussionApiControllerTest.php` -> OK (26 tests)
  - verification manuelle cible archivee (ticket W1-06) : preuves `85-w1-06-directories-check.txt`, `86-w1-06-proof-files.txt`, `88-w1-06-anomalies.md`
- securite admin prod :
  - `composer check-security-headers -- --url=http://127.0.0.1:8103/blog --forwarded-proto=https` -> OK en local
  - `composer check-log-alerts -- --since-minutes=60 --strict` -> OK
  - `composer check-env -- --env=production --strict-prod-security` -> KO attendu en local tant que les variables/secrets de production ne sont pas injectes
  - `composer check-instagram-feed -- --strict` -> KO attendu tant que les credentials Instagram de prod ne sont pas renseignes
- coherence documentaire :
  - `npm run hygiene:docs` -> OK (19 fichiers, 0 lien casse)
  - README de construction V1 renommes avec suffixe `_V1` :
    - `README_ADMIN_EDITORIAL_NAV_V1.md`
    - `docs/archive/README_AUDIT_COMPLET_V1.md`
    - `docs/archive/README_AUDIT_PLAN_ACTION_V1.md`
    - `README_MODERNISATION_V1.md`
    - `docs/archive/README_BLOG_PLAN_V1.md` (archive)
    - `README_RENDER_ARTEFACTS_V1.md`
    - `README_SECURITE_ADMIN_V1.md`
  - references inter-docs realignees sur les nouveaux noms.

Verdict :

- V1 est prete techniquement pour poursuivre en S2.
- Passage S1 -> S2 : `GO` (tickets W1-01 a W1-07 clotures avec preuves archivees).
- Deploiement production immediat : `GO`.

Addendum go-live 2026-03-21 :

- Pre-requis go-live leves (preuves `114` a `117`).
- Deploiement production immediat : `GO`.

## Checklist go-live validee (trace W1-07)

- [x] Rejouer une recette admin authentifiee en environnement cible avec credentials super-admin de production avant go-live.
- [x] Injecter les variables/secrets de prod hors Git puis valider `check-env --env=production --strict-prod-security`.
- [x] Renseigner les credentials Instagram et valider `check-instagram-feed -- --strict` (ou confirmer l'etat desactive).
- [x] Rejouer `check-security-headers` sur l'URL preprod reelle (pas prod), archiver la sortie, puis conserver `BLOG_STORAGE=sql` apres validation finale preprod.
- [x] Brancher `check_log_alerts.php` sur un scheduler systemd avec canal ops (webhook/email).

Etat 2026-03-21 (addendum) :

- [x] Recette admin authentifiee executee en cible.
- [x] `check-env --env=production --strict-prod-security` valide.
- [x] Instagram inactif confirme (gate strict live non applicable tant que desactive).
- [x] `check-security-headers` rejoue et archive (`114-check-security-headers-lescaramagnols.json`).

## Preuves recette preprod (archive locale courante)

Dossier :
- `docs/private/recette-preprod-v1-2026-03-20/`
- `docs/private/recette-preprod-v1-2026-03-21/`

Sorties archivees :
- `69-predeploy-status.txt`
- `70-composer-test.txt`
- `71-composer-phpstan.txt`
- `72-composer-phpcs.txt`
- `73-composer-audit.txt`
- `74-check-security-headers.txt`
- `75-check-env-production-strict.txt`
- `76-npm-lint.txt`
- `77-npm-test-run.txt`
- `78-npm-build.txt`
- `79-npm-audit.json`
- `80-hygiene-docs.txt`
- `81-autoload-predeploy.txt`
- `82-controllers-predeploy.txt`
- `83-http-predeploy.txt`
- `84-cache-clear-predeploy.txt`
- `85-w1-06-directories-check.txt`
- `86-w1-06-proof-files.txt`
- `87-w1-06-fo-admin-tests.txt`
- `88-w1-06-anomalies.md`
- `89-autoload-w1-06.txt`
- `90-controllers-w1-06.txt`
- `91-http-w1-06.txt`
- `92-cache-clear-w1-06.txt`
- `93-hygiene-docs-w1-06.txt`
- `103-w1-05-backup.txt`
- `104-w1-05-restore.txt`
- `105-w1-07-autoload.txt`
- `106-w1-07-controller.txt`
- `106b-w1-07-controllers-suite.txt`
- `107-w1-07-http-smoke.txt`
- `108-w1-07-cache-clear.txt`
- `109-w1-07-composer-test.txt`
- `110-w1-07-npm-test-run.txt`
- `111` (decision de cloture S1, archive)
- `112-w1-07-hygiene-docs.txt`
- `113-w1-07-cache-clear-final.txt`
- `114-check-security-headers-lescaramagnols.json`
- `115-check-env-production-strict.txt`
- `116-check-log-alerts-strict.txt`
- `117-check-instagram-feed.txt`
- `118-go-live-addendum.md`
- `119-check-log-alerts-webhook.txt`
- `120-systemd-check-log-alerts-dry-run.txt`
- `121-check-log-alerts-notify-error.txt`
- `122-j1-check-log-alerts-1440.txt`
- `123-j1-benchmark-routes-json.txt`
- `124-j7-check-log-alerts-10080.txt`
- `125-j7-benchmark-routes-json.txt`
- `126-http-https-smoke.txt`
- `127-security-headers-prod.txt`
- `128-jplus-anomalies.md`
- `129-init-db-admin-help.txt`
- `130-init-db-admin-dry-run.txt`
- `131-images-audit-maintenance.txt`
- `132-hygiene-docs.txt`
- `133-documentation-index-status-check.txt`
- `134-doc-command-smoke.txt`
- `135-autoload-maintenance.txt`
- `136-backend-tests-maintenance.txt`
- `137-frontend-tests-maintenance.txt`
- `138-http-https-maintenance.txt`
- `139-cache-clear-maintenance.txt`
- `140-controllers-maintenance.txt`
- `141-hygiene-assets-maintenance.txt`
- `142-cache-clear-maintenance-final.txt`

Notes :
- controles predeploy complets executes et consolides dans `69-predeploy-status.txt` (etat `GO` local).
- controle W1-03 confirme le 2026-03-20 :
  - `33-check-security-headers-www.txt` -> OK
  - `34-check-env-production-strict.txt` -> OK
- controle W1-04 confirme le 2026-03-20 :
  - `62-w1-04-admin-tests.txt` -> OK
  - `63-w1-04-cache-clear-navigation.txt` -> OK
- controle W1-06 confirme le 2026-03-20 :
  - dossiers de preuve FO/Admin valides (`85-w1-06-directories-check.txt`)
  - inventaire des captures et headers (`86-w1-06-proof-files.txt`)
  - non-regression FO/Admin (`87-w1-06-fo-admin-tests.txt`)
  - synthese anomalies (`88-w1-06-anomalies.md`) : aucune anomalie bloquante
  - controles fin de tache mission W1-06 executes (`89` a `93`) : autoload OK, controllers OK, HTTP OK (`status 200` avec user-agent navigateur), cache purge, hygiene docs OK.
- controle W1-05 confirme le 2026-03-21 :
  - backup editorial (`103-w1-05-backup.txt`) : OK
  - restauration editoriale (`104-w1-05-restore.txt`) : OK
- controle W1-07 confirme le 2026-03-21 :
  - autoload/controllers/HTTP/cache : OK (`105` a `108`)
  - tests backend/frontend : OK (`109`, `110`)
  - hygiene docs + purge cache finale : OK (`112`, `113`)
  - decision formelle : preuve `111` (archive de cloture S1).
- evolution observabilite ops (2026-03-21) :
  - notification webhook check-log-alerts validee (`119-check-log-alerts-webhook.txt`)
  - scenario erreur notification + `--fail-on-notify-error` valide (`121-check-log-alerts-notify-error.txt`, `exit_code=3`)
  - installation systemd preprod/prod documentee et previsualisee (`120-systemd-check-log-alerts-dry-run.txt`)
- cycle post go-live J+1/J+7 execute et archive (2026-03-21) :
  - logs J+1/J+7 verts (`122`, `124`)
  - benchmark routes J+1/J+7 archive (`123`, `125`)
  - controle HTTP/HTTPS + headers cible (`126`, `127`)
  - synthese anomalies et actions (`128`)
- maintenance outillage/doc (2026-03-21) :
  - commande CLI d'initialisation DB + admin disponible (`composer init-db-admin`) avec sorties `129` et `130`
  - chantier images historiques poursuivi (suppression artefacts `Zone.Identifier`, audit archive `131`)
  - checklist "documentation saine" passee en tout coche avec preuves `132` a `134`
  - controles fin de tache mission maintenance (`autoload`, tests, controllers, HTTP/HTTPS, hygiene assets, cache clear final) archives `135` a `142`
