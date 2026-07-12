# Plan D Action Suite A L Audit

Date : 2026-03-17

Ce document transforme l'audit complet en plan d'action priorise.

Reference principale :
- `audit-complet-v1.md`
- `docs/roadmap/README.md`
- `docs/admin/README.md`

## Avancement Verifie Au 2026-03-17

Realise dans cette passe :
- P0.1 : installateurs publics retires du webroot et remplaces par `docs/backend/installation-hors-webroot.md`
- P0.2 : fallback admin de demonstration retires du code et de `.env.example`
- P0.3 : `composer check-i18n` corrige puis valide sur les vrais fichiers de langue
- P1.1 : bootstrap i18n convergent autour de `LanguageResolver`, `Translator` et `bootstrap_language_context()`
- P1.3 : RSS et admin convergent vers `backend/public/index.php` avec gouvernance commune documentee dans `docs/backend/public-entrypoints.md`
- P2.2 : la logique admin n'est plus dans le webroot et les shims legacy morts ont ete neutralises
- P4.2 : tests ajoutes sur la resolution des routes admin et le flux RSS
- P1.2 : lecture du manifest Vite centralisee dans `backend/src/Assets/ViteAssetManager.php`
- P3.3 : conventions contenu / templates documentees, layout standard factorise et premiere page JSON migree vers des regions semantiques
- P3.1 : purge automatisee des anciens bundles hashes via `frontend/tools/publish-build.mjs`
- P4.3 : CI et documentation alignees sur le vrai workflow `npm run build`
- P4.1 : configuration PHPUnit migree vers le schema courant, sans deprecation runner restante
- P2.1 : statut du blog clarifie avec persistance JSON unique, routes publiques minimales et API admin securisee
- P4.2 : tests etendus sur `AdminController`, API blog et routage blog public
- P4.2 complement : `backend/src/Http/FrontController.php` extrait la gouvernance HTTP testable avec couverture des routes admin et aliases legacy
- P4.2 complement : Vitest couvre maintenant la fermeture du header sur clic exterieur, `resize` et unicite des sous-menus
- P1.3 complement : ecriture blog rattachee elle aussi a la gouvernance commune du front-controller
- P4 complement : journalisation uniforme branchee sur auth admin, sauvegarde menus et ecriture blog
- P4.3 complement : la CI execute un smoke test HTTP sur le header public et les routes admin critiques
- correctif complementaire : routes inexistantes renvoient maintenant un vrai `404` HTTP

Le plan d'assainissement prioritaire est solde.

Reste ensuite, hors perimetre de ce premier assainissement :
- outiller une vraie administration editoriale pour pages, menus et traductions
- refondre le menu haut autour d'une navigation moderne et accessible
- continuer la reduction progressive de `backend/core/*`
- outiller une vraie UI admin de gestion des articles si le besoin editorial se confirme

## Objectif

Ramener le projet a un etat :
- plus sur
- plus coherent entre legacy et moderne
- plus simple a maintenir
- plus fiable pour le developpement local et la mise en production

## Priorites Par Horizon

## Priorite 0 - A Traiter En Premier

### P0.1 Supprimer la surface d'installation publique

Actions :
- retirer `backend/public/installsql.php` du webroot
- retirer `backend/public/assets/install.php` du webroot
- supprimer tout acces public a l'installation en production
- documenter un processus d'installation hors HTTP

Effet attendu :
- suppression du principal risque de securite du depot

### P0.2 Assainir l'administration

Actions :
- changer `ADMIN_LOGIN_PATH`
- supprimer toute valeur de demo restante des chemins et docs publiques
- s'assurer qu'aucun fallback exploitable ne reste dans la config

Effet attendu :
- diminution du risque operationnel sur l'admin

### P0.3 Corriger `check-i18n`

Action :
- corriger `backend/core/tools/check_i18n_keys.php` pour viser `backend/lang`

Effet attendu :
- restaurer un outil de verification annonce et attendu

## Priorite 1 - Coherence D Architecture

### P1.1 Unifier la resolution de langue

Etat actuel :
- `backend/core/lang_bootstrap.php`
- `backend/src/I18n/LanguageResolver.php`

Action :
- garder une seule source de verite
- faire converger bootstrap legacy et couche `src/`

Effet attendu :
- moins de duplication
- moins de risque de divergence fonctionnelle

### P1.2 Unifier le chargement d'assets Vite

Etat actuel :
- `scripts_head.php` lit le manifest directement
- `core/helpers.php` expose `vite_asset()` / `vite_css()` mais n'est pas utilise

Action :
- choisir un seul mecanisme
- centraliser la lecture du manifest
- ajouter un vrai mode dev si Vite doit etre supporte en HMR

Effet attendu :
- contrat front/back plus clair
- doc plus fiable

### P1.3 Harmoniser les points d'entree publics

Action :
- faire passer RSS, admin, endpoints utilitaires et front controller par une gouvernance commune
- minimum : bootstrap commun, securite commune, log commun

Effet attendu :
- surface publique plus coherente
- auditabilite amelioree

## Priorite 2 - Clarifier Le Produit Technique

### P2.1 Statuer sur le blog

Etat actuel :
- `save_article.php` nettoie et retourne un JSON
- aucune persistance finale

Action :
- soit brancher une persistence reelle
- soit retirer les promesses de fonctionnalite
- soit documenter explicitement le module comme experimental

Effet attendu :
- moins d'ambiguite produit / technique

### P2.2 Clarifier l'admin legacy

Action :
- supprimer les shims de redirection inutiles
- corriger ou retirer les chemins morts comme `database.php`

Effet attendu :
- moins de bruit dans `backend/public/`

## Priorite 3 - Maintenance Et Dette Technique

### P3.1 Continuer le nettoyage des artefacts

Reference :
- `frontend/README.md`

Action :
- automatiser la purge des anciens bundles en postbuild
- maintenir le backend public propre

### P3.2 Elaguer les reliquats de tooling

Action :
- supprimer `backend/package-lock.json` si inutile
- revoir les fichiers suivis qui sont des sorties generees
- stabiliser la politique `.gitignore`

### P3.3 Reduire le couplage contenu / templates

Action :
- factoriser progressivement les templates
- deplacer une partie du contenu structure vers des donnees
- limiter la logique inline dans les pages PHP

Effet attendu :
- maintenance editoriale et technique plus simple

## Priorite 4 - Qualite Continue

### P4.1 Nettoyer les warnings de test

Action :
- supprimer la deprecation PHPUnit
- maitriser les logs attendus en test

### P4.2 Elargir la couverture utile

Cibles prioritaires :
- endpoints publics legacy
- chargement du manifest / assets
- admin login et redirections
- scripts/outils CLI

Etat :
- couverture en place sur le front-controller, les routes admin canoniques, les aliases legacy et la navigation haute

### P4.3 Formaliser le contrat de dev local

Action :
- documenter le mode dev reel
- soit manifest local obligatoire
- soit integration Vite dev server complete

## Priorite 5 - Administration Editoriale Et Navigation

Reference detaillee :
- `docs/admin/README.md`

### P5.1 Introduire un registre de pages

Action :
- permettre a l'admin de manipuler des pages `structured_page` et `legacy_template`
- identifier les pages par `slug` metier stable
- relier routes, SEO et traduction a ce registre

Effet attendu :
- creation et edition de pages sans dependre du code pour chaque nouveau contenu

Etat :
- fondation en place avec `backend/src/Content/PageRepository.php` et le registre `backend/data/pages.json` en schema `v2`

### P5.2 Remplacer l'edition JSON des menus

Action :
- sortir du textarea JSON
- introduire un schema canonique de navigation
- lier un item a une page par `page_slug`, a une route ou a un lien externe

Effet attendu :
- menus plus fiables
- navigation plus facile a maintenir

Etat :
- fondation en place avec `backend/src/Navigation/NavigationRepository.php` et un schema navigation `v2` versionne au stockage

### P5.3 Clarifier le workflow de traduction editoriale

Action :
- garder `backend/lang/*.php` pour l'interface
- gerer en admin les traductions de pages et de menus
- visualiser les langues manquantes et les contenus non publies

Effet attendu :
- edition multilingue simplifiee
- moins de confusion entre traduction systeme et contenu

### P5.4 Refondre le menu haut

Action :
- unifier desktop et mobile sur un seul arbre de navigation
- remplacer le hover seul par une navigation accessible
- deplacer la logique actuelle vers des partials et modules dedies

Effet attendu :
- header plus moderne
- meilleur usage mobile et clavier

## Roadmap Suggeree

## - 1

- desactiver ou retirer les installateurs publics
- corriger `check-i18n`
- changer le chemin admin et verifier les valeurs de demo
- retirer les redirections mortes evidentes

## - 2 A 3

- unifier le chargement d'assets Vite
- documenter le vrai workflow dev
- nettoyer les points d'entree publics legacy

## - 4 A 5

- converger i18n legacy / moderne
- decider du futur du module blog
- refactorer progressivement la couche de reponse et d'assets

## Checklist Operationnelle

- [x] plus aucun installateur accessible publiquement
- [x] `composer check-i18n` fonctionne
- [x] un seul mecanisme de chargement des assets Vite
- [x] une seule logique de resolution langue
- [x] workflow dev documente et testable
- [x] admin sans fallback de demo
- [x] endpoints publics legacy passes en revue
- [x] dette d'artefacts suivie dans `frontend/README.md`

## Definition De "Projet Assaini"

Le projet pourra etre considere assaini quand :
- les scripts dangereux ne seront plus exposés
- les docs de dev correspondront au comportement reel
- les couches moderne et legacy auront une frontiere claire
- l'admin et le blog auront un statut technique non ambigu
- la qualite outillee annoncée sera effectivement executable de bout en bout
