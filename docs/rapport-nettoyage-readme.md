# Rapport nettoyage README et documentation Markdown

Date: 2026-05-06
Perimetre: `/home/surfacepro8/www/caramagnols`

## Objectif

Rationaliser la documentation en limitant la proliferation des fichiers `.md`, en regroupant par fonctions utiles, et en archivant les notes datees.

## Regle ajoutee

La gouvernance a ete renforcee dans `AGENTS.md`:
- ne pas multiplier les fichiers `.md` sans valeur distincte,
- preferer un `README.md` par dossier fonctionnel,
- fusionner avant de creer un nouveau document,
- archiver les notes ponctuelles/historiques sous `docs/archive/`.

## Structure documentaire cible obtenue

```text
/
├── README.md
├── backend/README.md
├── frontend/README.md
└── docs/
    ├── README.md
    ├── architecture.md
    ├── installation.md
    ├── seo.md
    ├── codex.md
    ├── admin/README.md
    ├── backend/README.md
    ├── blog/README.md
    ├── deployment/README.md
    ├── private/README.md
    ├── roadmap/README.md
    ├── security/README.md
    └── archive/README.md
```

## Fichiers conserves (actifs)

- `README.md`
- `AGENTS.md`
- `backend/README.md`
- `frontend/README.md`
- `docs/README.md`
- `docs/architecture.md`
- `docs/installation.md`
- `docs/seo.md`
- `docs/codex.md`
- `docs/refonte-lot-c.md`
- `docs/consolidation-lot-d.md`
- `docs/rapport-style-editorial-2026-04-16.md`
- dossiers fonctionnels `docs/admin`, `docs/backend`, `docs/blog`, `docs/deployment`, `docs/private`, `docs/roadmap`, `docs/security`

## Fusions realisees

1. Fusion SEO:
   - `docs/seo-json-ld.md` + `docs/seo-social-sharing-images.md` -> `docs/seo.md`
2. Fusion sources images libres datees:
   - `docs/austin-images-libres-2026-04-19.md`
   - `docs/citroen-2cv-images-libres-2026-04-25.md`
   - `docs/golfe-saint-tropez-images-libres-2026-04-20.md`
   - `docs/golfe-saint-tropez-villages-images-libres-2026-04-24.md`
   - `docs/golfe-saint-tropez-animations-images-libres-2026-04-25.md`
   -> `docs/archive/editorial-images-libres-2026-04.md`

## Fichiers supprimes

- `README_RENDER_ARTEFACTS_V1.md` (redondant et contradictoire avec la doc pipeline frontend)
- `docs/seo-json-ld.md` (fusion)
- `docs/seo-social-sharing-images.md` (fusion)
- les 5 anciens fichiers `images-libres-*` (fusionnes dans un archive unique)

## Fichiers deplaces / transformes en README

- `docs/index.md` -> `docs/README.md`
- `docs/archive/index.md` -> `docs/archive/README.md`
- `docs/admin/editorial-navigation-v1.md` -> `docs/admin/README.md`
- `docs/blog/blog-v1.md` -> `docs/blog/README.md`
- `docs/security/admin-hardening-v1.md` -> `docs/security/README.md`
- `docs/private/portail-famille-v1.md` -> `docs/private/README.md`
- `docs/deployment/v1-preparation.md` -> `docs/deployment/README.md`
- `docs/roadmap/modernisation-v1.md` -> `docs/roadmap/README.md`
- `docs/ADMIN_MENU_PRESENTATION_HELP.md` -> `docs/admin/menu-presentation-help.md`
- `docs/pages-dynamiques.md` -> `docs/backend/pages-dynamiques.md`
- `docs/instagram-feed-setup.md` -> `docs/deployment/instagram-feed-setup.md`
- `docs/v1-go-live-runbook.md` -> `docs/deployment/runbook-v1-go-live.md`
- `docs/audit-nettoyage-priorise-depot-local-2026-04-16.md` -> `docs/archive/audit-nettoyage-priorise-depot-local-2026-04-16.md`
- `docs/etat-prod-ovh-2026-04-16.md` -> `docs/archive/ops/etat-prod-ovh-2026-04-16.md`
- `docs/rapport-prod-suppression-image-suggeree-2026-04-16.md` -> `docs/archive/ops/rapport-prod-suppression-image-suggeree-2026-04-16.md`

## Raisons des decisions

- Reduire la dispersion documentaire et accelerer la recherche d'information.
- Eviter les doublons et contradictions entre documents actifs.
- Separer clairement documentation active vs historique.
- Utiliser `README.md` comme point d'entree des dossiers fonctionnels.

## Liens internes corriges

Tous les chemins mentionnes vers les anciens noms ont ete remappes vers les nouveaux emplacements.

Verification effectuee:
- `cd frontend && npm run hygiene:docs` -> OK (aucun lien casse)

## Points a surveiller

1. Avant de creer un nouveau `.md`, verifier si une section peut etre ajoutee dans un doc existant.
2. Maintenir `docs/README.md` et `docs/archive/README.md` a chaque ajout/suppression.
3. Archiver les rapports ponctuels directement sous `docs/archive/` au lieu de les laisser en racine `docs/`.
