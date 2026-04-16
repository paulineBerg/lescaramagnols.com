# Pipeline De Build Frontend

Date : 2026-03-19

Ce document décrit la troisième passe de modernisation du pipeline Vite vers le backend PHP.

## Objectif

Garantir un seul contrat de build entre le frontend et le backend :
- build Vite dans `frontend/dist`
- publication contrôlée vers `backend/public/`
- purge automatique des anciens bundles hashés
- lecture du manifest unifiée côté PHP

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
- `npm run postbuild`
  - republie un `dist/` déjà généré vers le backend
- `npm run publish:backend`
  - alias explicite de `postbuild`
- `npm run audit:images`
  - audite les images historiques dans `backend/public/assets/images`
  - remonte les doublons exacts, noms non normalises et manques de variantes modernes
- `npm run hygiene:docs`
  - verifie les liens Markdown locaux des docs projet (`README*` + `docs/**`)
- `npm run hygiene:assets`
  - verifie le nommage des assets sous `backend/public/assets/images`
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
- copie `frontend/dist/assets/**` vers `backend/public/assets/**`
- copie le manifest vers `backend/public/.vite/manifest.json`
- supprime les anciens bundles hashés obsolètes à la racine de `backend/public/assets/`

Le nettoyage est volontairement limité :
- il touche uniquement les fichiers hashés à la racine de `backend/public/assets/`
- il ne touche pas `backend/public/assets/images/**`
- il ne touche pas `backend/public/assets/index.php`
- il ne touche pas `backend/public/assets/rss.php`

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
- tests et lint backend/frontend

## Politique Artefacts

- versionnes :
  - `backend/public/assets/images/**` (assets metier historiques)
  - `backend/public/assets/index.php`
  - `backend/public/assets/rss.php`
- non versionnes :
  - `frontend/dist/**`
  - `backend/public/.vite/**`
  - `backend/public/tarteaucitron/**` (copie publiee)
  - fichiers generes a la racine de `backend/public/assets` (bundles/hash de publication)

## TODO

- industrialiser un plan de remediation progressif des images historiques (dedoublonnage + variants modernes), avec objectif chiffre par lot
