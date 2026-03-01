# Les Caramagnols – documentation projet

Site associatif mêlant un backend PHP procédural (routage, templates, i18n serveur, sécurité) et un frontend Vite (JS/SCSS) pour l’interactivité et le responsive.  
Ce document décrit l’architecture, les langages, les dépendances, les commandes utiles et les points clés de mise en production.

## Quick start (2 min)
- Prérequis : PHP 8.1+, Composer, Node.js 20+, npm
- Installer backend : `composer install --working-dir=backend`
- Installer frontend : `cd frontend && npm install`
- Lancer en dev :
  - Terminal 1 : `cd backend && php -S 127.0.0.1:8000 -t public public/dev-router.php`
  - Terminal 2 : `cd frontend && npm run dev`
- Ouvrir : http://127.0.0.1:8000?lang=fr (proxy Vite sur /core)
- Pas de CSS/JS ? Vérifier `backend/public/.vite/manifest.json` (généré par `npm run build && npm run postbuild`) ou que Vite affiche “ready in …” dans le terminal.

---

## Architecture générale
- **Entrée HTTP** : `backend/public/index.php` charge `core/bootstrap.php`, applique les en-têtes de sécurité, détecte la langue, résout la route via le mini-routeur (`core/router.php`) puis rend la page avec le layout PHP (`templates/partials/layout.php`).
- **Templating** : pages PHP dans `backend/templates/pages/**` structurées autour des blocs `EditRegion*`; menus/header/footer dans `backend/templates/partials/*`.
- **Internationalisation (serveur)** : détection langue (`core/lang_bootstrap.php`), chargement et sanitisation des traductions (`core/i18n.php`, fichiers `backend/lang/{fr,en,de}.php`), cookie `lang` sécurisé.
- **Internationalisation (client)** : module `frontend/src/js/i18n.js` (fetch `core/api/lang.php`, cache in-memory + localStorage, application sur `data-i18n`).
- **Frontend build** : Vite 7 (ESM) + SCSS. Entrées `src/js/main.js` et `src/scss/style.scss`; manifest généré dans `backend/public/.vite/manifest.json` puis injecté côté PHP (`partials/scripts_head.php`). Images copiées via `vite-plugin-static-copy`.
- **Assets runtime** : en production, les bundles sont copiés dans `backend/public/assets` et `backend/public/.vite` par `npm run postbuild`. En dev, Vite sert les assets, le proxy `/core/*` cible `http://127.0.0.1:8000`.
- **Sécurité** : en-têtes CSP/anti-clickjacking (`core/security.php`), cookies HttpOnly + `SameSite=Strict`, session renommée (`caramagnols_session`), tokens CSRF (`csrf_token()` / `csrf_validate()`), rate limiting session (`core/rate_limiter.php`), sanitisation centralisée (`core/validation.php`).
- **Admin Blog (MVP)** : espace protégé sous `/site/<ADMIN_LOGIN_PATH>/` (défaut `adminFtyhik5642sZ`) avec login email/mot de passe (`core/auth/admin.php`). Layout admin : `backend/public/site/adminFtyhik5642sZ/layout.php`, pages dashboard & login.
- **Données & recherche** : scripts CLI dans `backend/core/tools/` (génération d’index de recherche JSON à partir des templates). Dossier `backend/data/` pour les fichiers dérivés (index, brouillons Blog…).
- **Base de données (optionnelle pour l’instant)** : schéma MySQL minimal dans `backend/sql/install.sql` avec préfixe `car_`. Fichier `config/database.override.php` permet de surcharger les paramètres issus de `.env`.

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
  -> lang_bootstrap (détection langue)
  -> router (FastRoute puis fallback fichiers)
  -> template page (templates/pages/…)
  -> partials/layout.php
       -> scripts_head.php (charge manifest Vite)
```

## Contrat PHP <-> Vite
- **Dev** : Vite sur http://127.0.0.1:5173, proxy `/core` vers PHP. Pas de manifest utilisé, assets en HMR.
- **Prod** : `backend/public/assets/**` et `backend/public/.vite/manifest.json` doivent exister (créés par `npm run build && npm run postbuild`).
- Symptômes + solutions :
  - Page sans CSS/JS : manifest manquant ou `/assets/...` en 404 → relancer `npm run build && npm run postbuild`.
  - 404 sur `/core/...` en dev : vérifier que `npm run dev` tourne et que le proxy cible bien 127.0.0.1:8000.

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

## Mise en œuvre des optimisations (plan d’action)

| Axe | Tâches | Priorité | Statut |
| --- | --- | --- | --- |
| Sécurité | CSP par nonce, frame-ancestors, cookies lang SameSite=Lax | Haute | En place (à valider en prod) |
| Performance | ETag/Cache-Control sur API langue | Haute | En place |
| Qualité code | PSR-4, FastRoute, tests router/i18n/CSRF/API | Haute | En place, à étendre |
| Frontend | Passage TypeScript modules clés, autoprefixer, tests menus | Moyenne | En place |
| Observabilité | Monolog (logs app), Symfony Mailer | Moyenne | En place |
| CI | Workflow GitHub Actions (lint+tests) | Haute | En place |
| Dette restante | Étendre la couverture tests, migration TS progressive, nettoyage assets obsolètes | Moyenne | À faire |

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
- [ ] `npm run build && npm run postbuild` (puis vérifier `backend/public/.vite/manifest.json` et `/assets`).
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
- **Bootstrap** : `core/bootstrap.php` charge `.env`, config (`config/config.php`), sécurité, i18n, router. Vérification des variables critiques en prod (DB & SMTP).
- **Routage** : `core/router.php` mappe l’URI vers un fichier de page sous `templates/pages/`, avec prise en compte du préfixe langue (`/fr`, `/en`, `/de`).
- **API** : `core/api/lang.php` sert les traductions JSON au frontend (`?lang=fr|en|de`), avec fallback `DEFAULT_LANG`.
- **Validation & sécurité** :
  - `core/validation.php` : sanitation texte, email, tags, commentaires, traductions (whitelist de balises).
  - `core/security.php` : en-têtes, session sécurisée, CSRF tokens.
  - `core/rate_limiter.php` : limiteur de requêtes basé session (utilisé pour `Blog/save_article.php`).
- **Admin** :
  - Authentification email+password (`core/auth/admin.php`), clés dans `.env` (`ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH`, `ADMIN_SESSION_KEY`).
  - Tableau de bord & connexion : `backend/public/site/<ADMIN_LOGIN_PATH>/{index,dashboard,logout}.php`.
- **Blog (MVP JSON)** : endpoint `core/blog/save_article.php` nettoie et retourne l’article/les commentaires ; la persistance BDD reste à brancher.
- **Installateur SQL (legacy)** : `backend/public/installsql.php` pour provisionner MySQL et déposer `config/db.php` si nécessaire.

---

## Frontend (Vite + SCSS)
- **Entrée** : `src/js/main.js` importe `menus.js`, `i18n.js` et `src/scss/style.scss`.
- **Menus & UI** : `src/js/menus.js` gère le menu desktop (hover) et mobile (hamburger), bouton “remonter”.
- **i18n client** : `src/js/i18n.js` (cache `Map`, persistance localStorage, `changeLanguage`, `applyTranslations` sur `data-i18n`).
- **Logger** : `src/js/logger.js` centralise `console` en dev.
- **Styles** :
  - SCSS modulaires : `_variables.scss`, `_utilities.scss`, `_layout.scss`, `_menus.scss`, `_responsive.scss`, `_components.scss`.
  - Conventions : classes nouvelles en `kebab-case`, utilitaires préfixés `.u-`, hooks JS préfixés `js-`, placeholders `%` pour factoriser.
- **Images** :
  - Imports Vite (`@/assets/...`) pour hashing.
  - Script `npm run build:webp` (`frontend/tools/convert-webp.js`) génère WebP + variantes `@480w/@960w` avec cache simple.

---

## Pré-requis
- PHP 8.1+ avec extensions standard.
- Composer.
- Node.js 20+ / npm.
- (Optionnel) MySQL 5.7+/MariaDB 10+ si vous activez le blog ou l’installateur SQL.
- Secrets : rester dans `.env` hors `public/`, non versionné (voir `.gitignore`).

---

## Installation & scripts
```bash
# Dépendances backend
composer install --working-dir=backend

# Dépendances frontend
cd frontend
npm install
```

### Développement local
```bash
# Terminal 1 : serveur PHP
cd backend
php -S 127.0.0.1:8000 -t public public/dev-router.php

# Terminal 2 : Vite + proxy /core
cd frontend
npm run dev   # http://127.0.0.1:5173
```
Visiter http://127.0.0.1:8000 (langue forçable avec `?lang=en`). Le proxy Vite relaie `/core/*` vers le serveur PHP.

### Build & copie des assets
```bash
cd frontend
npm run build
npm run postbuild   # copie dist/assets et dist/.vite vers backend/public
# ou enchaîné :
npm run build && npm run postbuild
```

### Tests
- **Frontend** : `cd frontend && npm run test:run` (Vitest, jsdom).
- **Backend** : `composer test` ou `vendor/bin/phpunit` (tests sous `backend/tests/`).
- Couverture Vitest : `frontend/coverage/`.

### Outils CLI utiles (backend/core/tools)
- `php backend/core/tools/check_env.php [--env=production|... --json --require=KEY1,KEY2]` : valide la présence/permissions des variables d’env et des clés critiques.
- `php backend/core/tools/generate_search_index.php` : construit `backend/data/search_index*.json` à partir des templates.
- `php backend/core/tools/generate_favicon.php` : régénère les favicons depuis `frontend/src/assets/images/structure/logo.*`.

### Conversion images
```bash
cd frontend
npm run build:webp   # Sharp → WebP + tailles responsive
```

## Licence
- Code sous licence MIT (voir fichier `LICENSE`).
- Les assets (images, logos) doivent être utilisés uniquement si vous en détenez les droits ou l’autorisation explicite ; remplacer ou attribuer si nécessaire.
- Rappel : à chaque modification significative (fonctionnalité, build, dépendance), mettre à jour ce README si nécessaire.

---

## Configuration (.env)
Copier `backend/.env.example` vers `backend/.env`, puis ajuster :
- `APP_ENV` (`development`/`production`…), `BASE_URL`, `DEFAULT_LANG`.
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_TABLE_PREFIX`.
- SMTP : `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT`, `MAIL_SMTP_USER`, `MAIL_SMTP_PASSWORD`, `MAIL_SMTP_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.
- Admin : `ADMIN_LOGIN_PATH`, `ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH`, `ADMIN_SESSION_KEY`.
- Vérifier les permissions du `.env` (600 ou 640, hors `public/`).  
Commande de contrôle : `composer check-env --working-dir=backend` (option `--env=production` pour forcer les clés prod).

---

## Base de données
- Schéma de base dans `backend/sql/install.sql` (tables `car_users`, `car_articles`, `car_comments` avec contraintes FK).
- Préfixe configurable via `DB_TABLE_PREFIX` (défaut `car_`) et helper `db_table()` en PHP.
- Surcharge des paramètres de connexion via `backend/config/database.override.php` (généré par le module admin, ignoré par Git).
- Script installateur web héritage : `backend/public/installsql.php` (création DB, import SQL, utilisateur admin, fallback `public/tmp_config` si `config/` n’est pas inscriptible).

---

## Internationalisation
- **Serveur** : détection URL (`/fr/`), paramètre `?lang`, cookie `lang`, header `HTTP_ACCEPT_LANGUAGE`; fallback `DEFAULT_LANG`. Chargement des fichiers `backend/lang/{code}.php`.
- **Client** : sélection ou persistance de la langue via `i18n.js` ; attributs `data-i18n` et `data-i18n-attr` dans le HTML pour hydrater les textes/attrs ; fallback sur les contenus existants.

---

## Sécurité & conformité
- En-têtes : CSP restrictive, `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`; HSTS si HTTPS détecté.
- Sessions : cookie `caramagnols_session`, `HttpOnly`, `SameSite=Strict`, `Secure` en HTTPS.
- CSRF : helpers génériques (`csrf_token`, `csrf_validate`) + variantes admin.
- Rate limiting : `SessionRateLimiter` (clé + capacité + fenêtre).
- Sanitisation : textes, emails, tags, commentaires, traductions (HTML autorisé limité) dans `core/validation.php`.
- Audit config : `core/tools/check_env.php` pour vérifier présence des secrets et permissions.

---

## Accès admin (démo)
- URL : `/site/<ADMIN_LOGIN_PATH>/` (défaut `/site/adminFtyhik5642sZ/`).
- Identifiants par défaut : email `pauline@lescaramagnols.com`, mot de passe correspondant au hash dans `.env.example` (`admin`). **À changer avant toute mise en ligne** via `ADMIN_PASSWORD_HASH`.

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
1) Builder le front : `npm run build && npm run postbuild` (copie dans `backend/public`).  
cd ~/www/caramagnols/frontend
npm run build && npm run postbuild

2) Déployer `backend/public/` + `backend/data/` (index recherche) sur l’hébergement PHP.  
3) Mettre en place `.env` sécurisé hors `public/`, vérifier avec `composer check-env --env=production`.  
4) Configurer le serveur web pour pointer sur `backend/public/` comme document root et autoriser le cache long sur `assets/` et `.vite/`.  
5) Activer HTTPS pour bénéficier de HSTS et des cookies `Secure`.

---

### Commandes rapides (mémo)
- Dev : `php -S 127.0.0.1:8000 -t backend/public backend/public/dev-router.php` + `npm run dev`.
cd ~/www/caramagnols
php -S 127.0.0.1:8000 -t backend/public backend/public/dev-router.php

- Tests : `composer test` ; `npm run test:run`.
- Lint : `npm run lint`.
- Build : `npm run build && npm run postbuild`.
- Env check : `composer check-env --working-dir=backend`.

---

> Dernière mise à jour : 28 février 2026. Merci d’ajouter vos modifications et tests exécutés dans vos PRs.

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
