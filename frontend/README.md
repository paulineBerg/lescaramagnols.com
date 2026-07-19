# Pipeline De Build Frontend

Date : 2026-03-19

Ce document décrit la troisième passe de modernisation du pipeline Vite vers le backend PHP.

Reference complementaire :
- `../docs/consolidation-lot-d.md`

## Objectif

Garantir un seul contrat de build entre le frontend et le backend :
- build Vite dans `frontend/dist`
- publication contrôlée vers `backend/public/`
- purge automatique des anciens bundles hashés
- lecture du manifest unifiée côté PHP

Mise a jour 2026-04-16 :
- le Lot D isole `frontend/tools/**` comme domaine de consolidation specifique ; ce README reste la source de verite pour ce sous-lot (publication, hygiene docs/assets/repo, budgets, proxy dev)

## Source De Verite

- build frontend : `frontend/vite.config.mjs`
- publication backend : `frontend/tools/publish-build.mjs`
- lecture du manifest côté PHP : `backend/src/Assets/ViteAssetManager.php`
- wrappers legacy compatibles : `backend/core/helpers.php`

## Scripts De Reference

- `./dev.sh`
  - depuis la racine du depot
  - lance le serveur PHP et le dev server Vite
  - verifie que les ports `8000` et `5173` sont libres
  - stoppe proprement les deux processus au `Ctrl+C`
- `npm run build`
  - lance `vite build`
  - controle ensuite les budgets frontend via `tools/check-budgets.mjs`
  - déclenche ensuite automatiquement `postbuild`
  - publie donc le build dans `backend/public/`
  - constitue aussi le prerequis attendu par `php ../backend/core/tools/check_editorial_media.php --check-published-assets`
- `npm run postbuild`
  - republie un `dist/` déjà généré vers le backend
- `npm run publish:backend`
  - alias explicite de `postbuild`
- `npm run audit:images`
  - audite les images source dans `frontend/src/assets/images`
  - remonte les doublons exacts, noms non normalises et manques de variantes modernes
- `npm run build:webp`
  - produit les variantes WebP de travail
  - cible `@400w` par defaut et `@700w` pour les usages editoriaux qui demandent plus de detail
  - ne change pas le format redactionnel de la section `Sources` : sur les pages publiques, garder seulement source, fichier, auteur et licence, sans mentions internes comme `Ajout local`, `Chemin du site`, `Added locally` ou `Site path`
- `npm run hygiene:docs`
  - verifie les liens Markdown locaux des docs projet (`README*` + `docs/**`)
- `npm run hygiene:assets`
  - verifie le nommage des assets sous `frontend/src/assets/images`
  - interdit espaces et caracteres speciaux hors `A-Z a-z 0-9 . _ - @ /`
- `npm run hygiene:repo`
  - verifie la politique de versionning des artefacts build (`frontend/dist`, `backend/public/.vite`, racine `backend/public/assets`)
  - enchaine aussi `hygiene:docs` et `hygiene:assets`

## Budgets De Performance (P2.1)

Le script `frontend/tools/check-budgets.mjs` est un gate bloquant du build.

Seuils courants :
- JS entry max : `70 KiB`
- CSS entry max : `110 KiB`
- JS+CSS initial max : `220 KiB`
- plus grosse image referencee max : `220 KiB`

Exemple de sortie attendue :
- `[budget] Budgets respectés.` -> build valide
- `[budget] Build refusé` -> au moins un seuil depasse (exit non-zero)

## Ce Que Fait La Publication

Le script `frontend/tools/publish-build.mjs` :
- lit `frontend/dist/.vite/manifest.json`
- resynchronise d'abord `frontend/dist/assets/images/**` depuis `frontend/src/assets/images/**`
- vérifie que `frontend/dist/assets/images/**` est un miroir exact de `frontend/src/assets/images/**`
- remplace `backend/public/assets/images/**` par la copie issue de ce miroir
- copie le reste de `frontend/dist/assets/**` vers `backend/public/assets/**`
- copie le manifest vers `backend/public/.vite/manifest.json`
- supprime les anciens bundles hashés obsolètes à la racine de `backend/public/assets/`
- vérifie ensuite que `backend/public/assets/images/**` reste un miroir exact de `frontend/src/assets/images/**`

Avant deploy ou push editorial, le controle recommande est :
- `php ../backend/core/tools/check_editorial_media.php --check-published-assets`

Ce gate bloque si :
- une reference `/assets/images/...` n'existe pas dans `frontend/src/assets/images/**`
- ou si le miroir publie manque dans `backend/public/assets/images/**`
- ou si un upload runtime `/uploads/editorial/...` reference n'existe pas sous `backend/public/uploads/editorial/**`

Le nettoyage est volontairement limité :
- il touche uniquement les fichiers hashés à la racine de `backend/public/assets/`
- il resynchronise integralement `backend/public/assets/images/**` depuis la source frontend
- il ne depend pas d'un plugin de copie Vite pour les images publiques versionnees
- il ne touche pas `backend/public/assets/index.php`
- il ne touche pas `backend/public/assets/rss.php`

## Controle De Deploiement

Le script `backend/core/tools/check_vite_assets.php` verifie le contrat entre `backend/public/.vite/manifest.json` et les fichiers publies sous `backend/public/`.

Il controle notamment :
- le fichier principal `file` de chaque entree du manifest
- les feuilles `css`
- les fichiers listes dans `assets`

Les scripts `backend/tools/deploy-fast.sh` et `backend/tools/deploy-release.sh` l'executent :
- localement avant toute ecriture distante
- sur la cible OVH apres rsync et mise a jour des permissions

Synchronisation OVH du front publie :
- `backend/tools/deploy-fast.sh` synchronise maintenant le miroir publie complet `backend/public/.vite/**`, `backend/public/assets/**` et `backend/public/tarteaucitron/**`
- `backend/tools/push-local-sql-to-ovh.sh --live` pousse lui aussi ce miroir complet sans filtrage par extension, pour ne pas perdre une image, une police, un bundle ou une autre nouveaute publiee

Si un fichier hashe reference par le manifest manque, le deploiement s'arrete avec la liste des fichiers absents.

## Audit Images Historiques

Le script `frontend/tools/audit-images.mjs` produit un etat de dette image exploitable :
- volume total (`jpg/png/gif/webp/avif/svg`)
- ratio formats legacy vs modernes
- groupes de doublons exacts (hash fichier)
- noms non conformes (espaces/caracteres non normalises)
- images legacy sans variante moderne (`.webp` ou `.avif`)

Usage:
- `npm run audit:images`

Ce script n'est pas bloquant pour le front-office. Il sert de base pour le chantier de dedoublonnage et de normalisation.

Source de verite image :
- source versionnee : `frontend/src/assets/images/**`
- publication derivee : `backend/public/assets/images/**`
- ordre editorial obligatoire : quand un nouveau `/assets/images/**` est reference dans une page ou un article, publier d'abord le miroir vers `backend/public/assets/images/**`, puis seulement sauvegarder le contenu
- consequence pratique : ne jamais corriger une image directement dans `backend/public/assets/images`
- pour les images integrees dans le texte editorial : viser `400 px` par defaut et ne monter a `700 px` qu'en cas de besoin documentaire clair
- si un contenu editorial reference un upload admin, la copie vers OVH passe par `../backend/tools/sync-editorial-uploads.sh` et non par le pipeline Vite

## Contrat Cote PHP

Le rendu serveur consomme désormais Vite via `backend/src/Assets/ViteAssetManager.php`.

Le flux recommandé est :
1. `backend/templates/partials/scripts_head.php`
2. `vite_tags()`
3. `vite_asset_manager()`
4. `Caramagnols\\Assets\\ViteAssetManager`

Les helpers `vite_tags()`, `vite_asset()` et `vite_css()` restent disponibles pour compatibilité, mais la logique réelle vit dans la classe PSR-4.

## CI

La CI exécute maintenant :
- installation backend
- installation frontend
- `npm run build`
- gate budget inclus dans `npm run build`
- vérification de la présence de `backend/public/.vite/manifest.json`
- gate d'hygiene repo/documentation/assets via `npm run hygiene:repo`
- refus de tout dossier/fichier temporaire Lighthouse/PageSpeed (`lighthouse*`, `pagespeed*`, `psi*`) tant qu'il n'est pas supprimé
- tests et lint backend/frontend

## Politique Artefacts

- versionnes :
  - `frontend/src/assets/images/**` (source canonique des images publiques)
  - `backend/public/assets/index.php`
  - `backend/public/assets/rss.php`
- non versionnes :
  - `frontend/dist/**`
  - `backend/public/.vite/**`
  - `backend/public/tarteaucitron/**` (copie publiee)
  - `backend/public/assets/images/**` (copie publiee depuis la source frontend)
  - fichiers generes a la racine de `backend/public/assets` (bundles/hash de publication)

## TODO

- industrialiser un plan de remediation progressif des images historiques (dedoublonnage + variants modernes), avec objectif chiffre par lot
