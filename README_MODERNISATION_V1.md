# Plan De Modernisation Du Projet

Date de mise a jour : 2026-04-23

Ce document fixe les decisions d architecture durables pour moderniser `caramagnols` sans casser le rendu public ni transformer le site en SPA.

References :
- `README.md`
- `README_ADMIN_EDITORIAL_NAV_V1.md`
- `README_BLOG.md`
- `backend/README_BOOTSTRAP_I18N.md`
- `backend/README_PUBLIC_ENTRYPOINTS.md`
- `frontend/README_BUILD_PIPELINE.md`
- `docs/README_REFONTE_LOT_C.md` et `docs/README_CONSOLIDATION_LOT_D.md` uniquement pour relire les audits ponctuels du `2026-04-16`

## Objectif

Moderniser le projet sans casser :
- le rendu HTML cote serveur
- les URLs publiques et le SEO
- la logique i18n existante
- le pipeline Vite comme couche d assets
- la compatibilite transitoire du legacy utile

## Etat Courant A Retenir

Les decisions suivantes sont deja en place et ne doivent plus etre remises en cause sans raison forte :
- rendu public conserve cote PHP
- point d entree public unique : `backend/public/index.php`
- gouvernance HTTP appliquee par `backend/src/Http/FrontController.php`
- nouvelles logiques backend attendues dans `backend/src/*`
- `backend/core/*` traite comme zone transitoire ou wrapper de compatibilite
- i18n convergee sur `backend/src/I18n/*` et `backend/lang/*.php`
- build frontend publie vers `backend/public/` via Vite
- lecture du manifest centralisee dans `backend/src/Assets/ViteAssetManager.php`
- pages editoriales publiques unifiees autour de `structured_page`
- blog maintenu comme module dedie avec stockages `json`, `dual-write` ou `sql`
- logging applicatif, verifications et quality gates deja industrialises

## Principes Non Negociables

### 1. Rendu Serveur D Abord

Le site reste un site PHP rendu serveur.
Le frontend apporte des comportements et des assets, pas une couche applicative concurrente.

### 2. Pas De Big Bang Rewrite

La modernisation se fait par convergence progressive :
- extraire depuis `core/*` vers `src/*`
- garder les shims legacy strictement necessaires
- retirer le legacy seulement quand un remplacant testable est deja en place

### 3. Une Seule Gouvernance HTTP

Toute nouvelle route significative doit passer par :
- `backend/public/index.php`
- `backend/src/Http/FrontController.php`
- les resolvers ou controlleurs dedies dans `backend/src/*`

### 4. Une Seule Source De Verite Par Domaine

- i18n interface : `backend/lang/*.php`
- build frontend : `frontend/src/*` puis `frontend/README_BUILD_PIPELINE.md`
- pages et navigation : repositories editoriaux et documents de domaine
- blog : `README_BLOG.md`

### 5. Compatibilite Oui, Duplication Non

Les wrappers legacy sont toleres pour la compatibilite HTTP ou runtime.
Ils ne doivent plus redevenir des sources de verite metier.

## Architecture Cible

### Backend

- `backend/public/index.php` : point d entree public
- `backend/src/*` : couche applicative moderne
- `backend/core/*` : wrappers transitoires, bootstrap commun, outillage historique maitrise
- `backend/templates/*` : rendu HTML serveur
- `backend/data/*` : donnees editoriales ou derivees selon le mode de stockage

### Frontend

- `frontend/src/js/*` : comportements UI et modules applicatifs
- `frontend/src/scss/*` : styles
- `frontend/src/assets/*` : sources d assets publics
- Vite : build, dev server et publication vers `backend/public/`

### Contrat Front / Back

- HTML : rendu par PHP
- JS / CSS : servis via Vite en dev et manifest en production
- images editoriales publiques : source canonique dans `frontend/src/assets/images/**`
- uploads editoriaux runtime : `backend/public/uploads/editorial/**`
- i18n frontend : projection du socle PHP, pas systeme parallele

## Choix Par Sujet

### Rendu

- conserver PHP comme moteur de rendu
- ne pas migrer vers React, Vue, Next, Nuxt ou equivalent pour le site principal

### Assets

- conserver Vite comme pipeline d assets
- ne pas lui deleguer le rendu applicatif

### Images

- garder des chemins publics stables pour le contenu editorial
- ne pas forcer un hash Vite sur tout l historique media

### I18n

- garder `backend/lang/*.php` comme source de verite
- reutiliser cette source cote frontend quand une projection JSON est necessaire

### Admin

- poursuivre l extraction vers `backend/src/Admin/*`
- garder l admin en rendu serveur avec interactivite legere

### Stockage Editorial

- accepter `json`, `dual-write` et `sql` tant que la transition n est pas close
- ne basculer un domaine en `sql` qu apres import, verification et runbook clairs

### Qualite

- toute evolution doit rester compatible avec PHPUnit, PHPStan, PHPCS, ESLint, Stylelint et Vitest
- la CI et les scripts de verification de `README.md` restent la reference pratique

## Chantiers Encore Utiles

Les sujets encore ouverts a traiter progressivement sont :
- reduction continue de `backend/core/*`
- stabilisation de la bascule SQL pour l editorial quand le besoin d exploitation le justifie
- retrait progressif des shims legacy inutiles une fois la compatibilite verifiee
- poursuite de la couverture de tests autour du front-controller, de l admin et de la navigation
- hygiene media et simplification documentaire au fil des chantiers reels

## Ce Qu Il Ne Faut Pas Faire

- introduire une SPA pour les parcours structurants
- remettre de la logique metier dans `backend/public/*`
- multiplier les sources de verite pour les memes donnees
- reouvrir des endpoints publics isoles hors gouvernance du front-controller
- traiter un audit date comme source de verite courante quand le README de domaine existe deja

## Definition De Reussite

La modernisation est sur la bonne trajectoire si :
- les nouvelles features backend vont dans `backend/src/*`
- le rendu public reste stable et SEO-safe
- le legacy est reduit sans rupture de compatibilite inutile
- les domaines pages, navigation, blog, logging et build ont chacun une doc de reference claire
- les quality gates restent des preconditions de livraison, pas une etape optionnelle
