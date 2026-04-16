# Politique Artefacts De Rendu

Date : 2026-03-21

Ce document decrit la gouvernance des artefacts de build/publication.
Il remplace les listes statiques de fichiers orphelins par une regle outillee et verifiable.

## Objectif

Garantir qu'un `npm run build` ne pollue pas le repo versionne.

## Source De Verite

- build frontend : `frontend/vite.config.mjs`
- publication backend : `frontend/tools/publish-build.mjs`
- controle artefacts versionnes : `frontend/tools/check-repo-artifacts.mjs`
- controle liens docs : `frontend/tools/check-doc-links.mjs`
- controle nommage assets : `frontend/tools/check-asset-naming.mjs`

## Politique De Versionning

Versionnes :
- `backend/public/assets/images/**`
- `backend/public/assets/index.php`
- `backend/public/assets/rss.php`

Non versionnes :
- `frontend/dist/**`
- `backend/public/.vite/**`
- `backend/public/tarteaucitron/**`
- fichiers generes a la racine de `backend/public/assets` (bundles/hash Vite et assets de publication)

## Nettoyage Et Verification

Commandes de reference :
- `cd frontend && npm run build`
- `cd frontend && npm run hygiene:repo`

Resultat attendu :
- aucun artefact build suivi par Git hors politique
- aucun lien Markdown casse dans la documentation active
- aucun nom de fichier invalide dans `backend/public/assets/images`

## Migration Git (branche de travail)

Si des artefacts historiques restent suivis :
1. `git rm -r --cached frontend/dist backend/public/.vite`
2. de-tracker les fichiers racine de `backend/public/assets` sauf `index.php` et `rss.php`
3. relancer `npm run hygiene:repo`

## TODO

- poursuivre la reduction des doublons d'images historiques et la convergence vers un nommage editorial plus homogene (snake_case cible)
- lot suivant cible : dedoublonner en priorite les triplets `racine` / `dossier-theme` / `autoretro/dossier-theme` quand les references front sont deja migrees vers le chemin canonique.

## Avancement 2026-03-21

- artefacts parasites Windows retires :
  - `backend/public/assets/images/mercedes_SLK250_AMG_cdi.jpg:Zone.Identifier`
  - `backend/public/assets/images/mercedes/mercedes_SLK250_AMG_cdi.jpg:Zone.Identifier`
  - `backend/public/assets/images/autoretro/mercedes/mercedes_SLK250_AMG_cdi.jpg:Zone.Identifier`
- audit image rejoue pour suivre la dette restante :
  - `npm run audit:images` : `3639` images, `995` groupes de doublons exacts, `124` noms non normalises.
- preuve archivee : `docs/private/recette-preprod-v1-2026-03-21/131-images-audit-maintenance.txt`
