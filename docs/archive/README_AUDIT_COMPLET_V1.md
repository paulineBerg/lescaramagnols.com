# Audit Complet Du Projet Les Caramagnols

Date d'audit : 2026-03-17

## Mise A Jour Au 2026-03-17

Statut verifie apres implementation :
- `backend/public/installsql.php` et `backend/public/assets/install.php` ont ete retires du webroot
- l'installation est maintenant documentee hors HTTP dans `backend/README_INSTALLATION_HORS_WEBROOT.md`
- la resolution de langue converge sur `LanguageResolver` et le chargement sur `Translator`, via `bootstrap_language_context()`
- `composer check-i18n` fonctionne et passe
- les valeurs de demonstration admin ne sont plus fournies par defaut dans la config ou `.env.example`
- le front-controller renvoie maintenant un vrai `404` HTTP pour les routes non resolues
- l'admin est maintenant gouverne par `backend/public/index.php`, `backend/src/Admin/*` et `backend/templates/admin/*`
- RSS passe maintenant par `/rss` via `backend/src/Feed/RssFeedService.php`
- les anciens fichiers publics `rss.php` et les wrappers admin legacy sont des shims de compatibilite
- la route admin de reference n'est plus couplee a un dossier committe et l'exemple `.env` utilise `ADMIN_LOGIN_PATH=admin`
- le manifest Vite est maintenant consomme via `backend/src/Assets/ViteAssetManager.php`
- le build frontend publie automatiquement vers `backend/public/` avec purge des anciens bundles hashes
- la CI verifie le build frontend publie
- la configuration PHPUnit est migree sur le schema courant sans deprecation runner
- la phase 3 contenu / templates a introduit un layout standard factorise et des pages JSON a regions semantiques
- la phase 4 a clarifie le blog comme MVP JSON `experimental` avec persistance active dans `backend/data/blog`
- l'ecriture blog passe maintenant par une API admin securisee sous gouvernance du front-controller
- la journalisation est maintenant branchee sur auth admin, sauvegarde menus et ecriture blog

Points restant ouverts :
- la couche `core/*` reste transitoire et encore dominante pour une partie du rendu
- les tests HTTP autour du front-controller et des alias legacy restent a etendre
- l'UI admin moderne de gestion des articles reste a construire si le besoin editorial se confirme

## Objet

Ce document synthétise un audit technique complet du depot :
- architecture generale
- backend PHP
- frontend Vite/TypeScript/SCSS
- donnees et contenu
- surface publique et securite
- qualite outillee
- dette technique et priorites

Le document est volontairement technique et orienté maintenance.

## Resume Executif

Le projet est exploitable et globalement sain sur son socle principal, mais il reste hybride :
- une partie moderne existe deja (Composer, FastRoute, PSR-4, Vite, TypeScript, Vitest, PHPStan, CI)
- une partie legacy reste tres presente (templates PHP rendus directement, assets servis en direct, endpoints publics historiques, admin dossierise)

Etat general :
- backend coeur : fonctionnel
- frontend : fonctionnel et outille
- CI / qualite : plutot bonne
- architecture : partiellement modernisee
- securite : base correcte, mieux fermee depuis la suppression des installateurs publics, mais surface legacy encore heterogene
- exploitabilite : bonne pour le build, plus fragile pour le mode dev et certains scripts legacy

## Etat De Sante Rapide

- `composer test` : OK
  - 42 tests, 109 assertions, 0 deprecation PHPUnit
- `composer phpstan` : OK
- `composer phpcs` : OK
- `npm run test:run` : OK
  - 3 fichiers de test, 8 tests
- `npm run lint` : OK
- `composer check-i18n` : OK
- verification HTTP :
  - `/` : `200 OK`
  - `/installsql.php` : `404 Not Found`
  - `/assets/install.php` : `404 Not Found`

Conclusion :
- la qualite automatique est globalement en bon etat
- l'outillage critique annonce dans la doc est a nouveau coherent

## Cartographie Technique

## Stack

- Backend : PHP 8.1+, Composer, FastRoute, Monolog, Symfony Mailer
- Frontend : Vite 7, TypeScript, SCSS, Vitest, ESLint, Stylelint
- Tests : PHPUnit 10, Vitest
- CI : GitHub Actions

## Structure Reelle

- `backend/public/`
  - point d'entree HTTP
  - admin
  - endpoints legacy
  - manifest Vite et assets de rendu
- `backend/core/`
  - bootstrap legacy
  - routing legacy
  - i18n legacy
  - outils CLI
- `backend/src/`
  - couche moderne en cours d'introduction
  - Request/Response, i18n, mailer, logging, securite
- `backend/templates/`
  - 69 fichiers
  - 58 pages
  - 11 partials
- `backend/data/`
  - pages dynamiques JSON
  - index de recherche
  - donnees blog derivees
- `frontend/src/`
  - code JS/TS/SCSS
  - enorme volume d'assets images
- `docs/`
  - documentation libre
- `README_RENDER_ARTEFACTS_V1.md`
  - audit specifique des artefacts de rendu deja mene

## Architecture Fonctionnelle

## Backend

Chemin principal d'une requete HTML :

1. `backend/public/index.php`
2. `backend/core/bootstrap.php`
3. `backend/core/lang_bootstrap.php`
4. `backend/core/router.php`
5. inclusion d'un template `backend/templates/pages/...`
6. rendu du layout `backend/templates/partials/layout.php`
7. chargement du manifest Vite via `backend/templates/partials/scripts_head.php`

Le projet utilise donc encore un rendu serveur majoritairement procedural, meme si une couche moderne `backend/src/` commence a apparaitre.

## Frontend

Le frontend ne pilote pas l'application : il prepare et injecte des assets.

Pipeline actuel :

1. `frontend/src/js/main.ts`
2. `vite build`
3. generation de `frontend/dist`
4. copie manuelle vers :
   - `backend/public/assets`
   - `backend/public/.vite`

Le frontend est donc principalement une chaine de build d'assets pour le backend PHP.

## Donnees

- contenu statique : templates PHP
- contenu dynamique : `backend/data/pages.json`
- recherche : generation via `backend/core/tools/generate_search_index.php`
- blog : persistance JSON active, rendu public minimal et API admin securisee

## Forces Actuelles

## Points Positifs

- structure generale lisible a la racine
- separation backend / frontend / data deja nette
- baseline de securite correcte :
  - cookies HttpOnly
  - `SameSite=Strict`
  - CSRF
  - CSP
  - rate limiter session
- CI existante et utile
- tests backend et frontend deja en place
- modernisation reelle cote frontend :
  - TypeScript
  - lint
  - tests
- modernisation reelle cote backend :
  - autoload Composer
  - FastRoute
  - classes PSR-4
  - PHPStan
- outil de recherche et outillage de contenu deja presents

## Ce Qui Marche Bien

- le coeur de rendu PHP fonctionne
- le build frontend est reproductible
- les tests existants passent
- l'analyse statique backend est propre
- le code est relativement navigable pour un projet hybride

## Constats Detailes Par Zone

## 1. Backend PHP

### 1.1 Bootstrap et architecture

Le bootstrap charge encore beaucoup de logique legacy globale :
- environnement
- validation
- headers de securite
- config
- DB optionnelle
- i18n
- routing

En parallele, `backend/src/` introduit une architecture plus propre, mais elle n'est pas encore la couche dominante.

Constat :
- coexistence de deux styles de code
- bonne direction de modernisation
- migration incomplete

### 1.2 Routing

Le routing est partage entre :
- FastRoute pour quelques cas modernes
- `resolve_route()` pour la logique legacy
- fallback vers les fichiers PHP

Points positifs :
- mecanisme simple
- support du slug dynamique via `pages.json`

Points faibles :
- routage encore tres lie a l'arborescence de templates
- peu de routes modernes reelles
- le "front controller moderne" reste surtout un adaptateur vers le legacy

### 1.3 I18n

Deux couches coexistent :
- `backend/core/lang_bootstrap.php`
- `backend/src/I18n/LanguageResolver.php` et `Translator.php`

Constat :
- duplication de responsabilite
- comportement potentiellement divergent a terme
- dette de convergence evidente

### 1.4 Reponse HTTP

La classe `Response` existe, mais le rendu HTML principal est encore echo directement par `layout.php`.

Consequence :
- la couche `Response` n'est pas encore une vraie abstraction de sortie
- le code moderne n'est pas la source de verite du cycle de reponse HTML

### 1.5 Auth admin

L'auth admin est simple et propre dans son principe :
- hash de mot de passe
- regeneration de session
- CSRF

Mais la protection reste fragile operationnellement :
- chemin admin cache mais commite
- valeurs fallback presentes dans la config
- documentation et `.env.example` exposent le chemin attendu

### 1.6 Blog

Le point d'entree `backend/core/blog/save_article.php` :
- est maintenant un shim legacy
- delegue a `backend/public/index.php`
- conserve une compatibilite d'URL sans porter la logique metier

Le module blog :
- persiste maintenant en JSON dans `backend/data/blog`
- expose un endpoint admin canonique securise
- alimente le rendu public `/blog`, `/blog/article/{slug}` et le RSS
- reste volontairement limite a un MVP editorial

Conclusion :
- module clarifie
- techniquement coherent
- encore incomplet sur l'outillage d'edition

### 1.7 Mail et logging

Le mailer Symfony et Monolog sont bien choisis.

Etat mis a jour :
- logging transverse branche sur auth admin
- sauvegarde menus tracee
- ecriture blog tracee
- canaux separes `security.log` et `content.log`

Conclusion :
- bon socle
- adoption reelle mais encore extensible

## 2. Frontend

### 2.1 Role reel du frontend

Le frontend n'est pas une SPA ni une application front autonome.
Il sert principalement a :
- fournir JS comportemental
- fournir CSS
- produire des assets optimises

Ce point est important pour la maintenance :
- la verite du rendu reste en PHP
- Vite est un pipeline d'assets, pas la couche de presentation principale

### 2.2 Qualite du code front

Points positifs :
- TypeScript sur les modules clefs
- tests Vitest presents
- lint propre
- structure simple

Limites :
- logique concentree dans peu de modules globaux
- beaucoup d'assets dans `frontend/src`, ce qui gonfle le volume percu du frontend

### 2.3 Couplage avec le backend

Etat mis a jour :
- manifest lu cote PHP via `backend/src/Assets/ViteAssetManager.php`
- publication backend automatisee par `frontend/tools/publish-build.mjs`
- purge des bundles hashes automatisee a la publication
- mode dev et mode build documentes explicitement

Conclusion :
- le contrat PHP <-> Vite est maintenant nettement plus propre
- la maintenance reste hybride, mais n'est plus artisanale sur ce point

## 3. Donnees Et Contenu

### 3.1 Templates

Les templates PHP restent centraux.
Ils portent :
- structure des pages
- contenu HTML
- references directes aux images
- logique de blocs `EditRegion*`

Avantage :
- contenu tres maitrise par fichier

Inconvenient :
- difficile a factoriser
- difficile a tester exhaustivement
- fort couplage entre contenu et structure

### 3.2 Pages dynamiques

Le chargeur `pages_loader.php` est plutot propre :
- cache memoire
- validation du JSON
- fallback langue
- hooks de tests

C'est une des briques les plus propres du backend.

### 3.3 Assets

Les assets sont encore un sujet majeur :
- beaucoup d'images sources
- copies de rendu dans le backend
- historique d'artefacts buildes present dans le repo
- nettoyage manuel necessaire

Le document `README_RENDER_ARTEFACTS_V1.md` couvre deja ce sujet en detail.

## 4. Securite Et Surface Publique

## Forces

- headers de securite presents
- session durcie
- CSRF present
- rate limiting de base

## Faiblesses

### 4.1 Scripts d'installation exposes publiquement

Les fichiers suivants sont accessibles sous `backend/public/` :
- `backend/public/installsql.php`
- `backend/public/assets/install.php`

Ils :
- creent une base
- ecrivent des fichiers de config
- creent un compte admin

C'est le point de risque numero un du depot.

### 4.2 Endpoints publics legacy hors bootstrap moderne

Etat mis a jour :
- `backend/public/installsql.php` et `backend/public/assets/install.php` ont ete retires
- `backend/public/rss.php` et `backend/public/assets/rss.php` deleguent maintenant a `backend/public/index.php`
- les chemins admin legacy deleguent aussi au front-controller

Conclusion :
- la surface publique legacy a ete fortement reduite
- les reliquats restants sont des shims de compatibilite, plus des points d'entree applicatifs autonomes

### 4.3 Securite par obscurite pour l'admin

Le chemin admin reste configurable, mais :
- il ne doit plus etre considere comme une protection
- il n'est plus couple a un dossier public committe
- l'exemple `.env.example` utilise maintenant `admin`

Conclusion :
- ce mecanisme ne doit pas etre considere comme une protection

### 4.4 Valeurs de demo

La config fallback embarque :
- email admin
- hash de mot de passe de demo

Meme si la doc dit de changer ces valeurs, leur presence dans le code est un risque operationnel.

## 5. Qualite, Tests Et CI

## Ce qui est bon

- tests backend en place
- tests frontend en place
- PHPStan OK
- ESLint / Stylelint OK
- workflow CI present

## Ce qui a merite correction

### 5.1 `check-i18n` a ete corrige

Le script pointait historiquement vers `backend/core/lang/*.php`.
Il vise maintenant correctement `backend/lang/*.php` et fait partie des verifications valides du projet.

### 5.2 La configuration PHPUnit a ete migree

Les tests passaient deja, mais l'execution remontait une deprecation du runner a cause d'un schema XML obsolete.
La configuration `backend/phpunit.xml` est maintenant migree vers le schema PHPUnit 10.5 courant.

### 5.3 Journalisation de bruit dans les tests

Pendant les tests backend, un cas de JSON invalide loggue un message dans `pages_loader`.
Ce n'est pas critique, mais c'est un signe de couplage entre test attendu et logging runtime.

## Findings Priorises

## Critique

### F1. Installateurs publics exposes

Fichiers :
- `backend/public/installsql.php`
- `backend/public/assets/install.php`

Risque :
- creation DB
- ecriture config
- creation compte admin

Action :
- retirer du webroot
- ou restreindre severement
- ou proteger par environnement et authentification forte

## Eleve

### F2. Surface publique legacy heterogene

Statut :
- corrige pour les endpoints encore actifs
- les installateurs ont ete retires
- RSS et admin passent maintenant par le front-controller

### F3. Mode dev documente mais pas vraiment implemente cote rendu

Statut :
- corrige
- le rendu HTML principal charge Vite dev via `vite_tags()` quand le serveur Vite est joignable
- hors dev, le meme point d'entree retombe sur le manifest publie

### F4. Chemin admin et fallback de credentials commites

Statut :
- hash demo retire
- route admin canonique decouplee du dossier public legacy
- les anciens chemins publics ne servent plus que de compatibilite

Impact :
- faux sentiment de securite
- risque d'oubli en production

## Moyen

### F5. Modernisation incomplete et logique dupliquee

Exemples :
- `lang_bootstrap.php` vs `LanguageResolver.php`
- ancienne duplication manifest/headers Vite desormais resolue via `backend/src/Assets/ViteAssetManager.php`
- `Response` presente mais non centrale pour le rendu HTML

Impact :
- dette de maintenance
- risque de divergence

### F6. Module blog encore limite

Statut :
- corrige en partie
- le blog persiste maintenant en JSON et dispose d'un rendu public minimal
- le point faible restant est l'absence d'UI admin moderne de gestion des articles

Impact :
- contrat technique clarifie
- dette surtout concentree sur l'ergonomie et le workflow

### F7. Outillage i18n casse

Le script `check_i18n_keys.php` pointe vers le mauvais dossier.

Impact :
- outil CI / local inutilisable
- doc techniquement fausse

### F8. Shim admin legacy incomplet

Statut :
- corrige
- les anciens wrappers admin publics deleguent maintenant au front-controller
- le chemin mort `database.php` ne porte plus de logique propre et retombe sur la gouvernance HTTP commune

## Faible

### F9. Artefacts et reliquats de tooling

Exemples :
- `backend/package-lock.json` vide
- historiques d'artefacts de rendu anciennement suivis
- doc racine plus ambitieuse que l'etat reel sur certains points

Impact :
- bruit
- comprehension plus lente

## Synthese Par Axe

## Robustesse

Bonne sur le coeur du site.
Encore inegale sur les points legacy publics.

## Maintenabilite

Moyenne a bonne.
La base est lisible mais la cohabitation legacy / moderne augmente le cout de comprehension.

## Securite

Base correcte.
Mais la surface publique legacy tire fortement la note vers le bas.

## Qualite outillee

Bonne.
Le projet dispose d'une base CI utile et de tests reels.

## Exploitabilite

Bonne en build.
Moins claire en developpement local et sur le perimetre admin/blog.

## Recommandations Structurantes

1. Retirer ou verrouiller totalement les scripts d'installation publics.
2. Unifier tous les points d'entree publics derriere le meme bootstrap.
3. Decider d'un contrat clair pour le mode dev Vite et l'implementer reellement.
4. Converger vers une seule couche de resolution langue / assets / reponse.
5. Soit terminer le module blog, soit le documenter comme experimental.
   Statut : fait, avec un choix `experimental + json`.
6. Corriger l'outillage casse (`check-i18n`) avant d'elargir les promesses de qualite.
7. Continuer le nettoyage des artefacts et des reliquats legacy documentes.

## Documents Lies

- `README.md`
- `README_RENDER_ARTEFACTS_V1.md`
- `README_AUDIT_PLAN_ACTION_V1.md`
