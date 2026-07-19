# Documentation projet

Mise a jour: 2026-05-06

## Regle d'organisation

- Un seul `README.md` a la racine du depot.
- Un `README.md` par dossier fonctionnel majeur.
- Ne pas multiplier les `.md` pour le meme sujet: fusionner par domaine.
- Les notes datees, preuves ponctuelles et historiques vont dans `docs/archive/`.

## Racine projet

- `README.md`: vue d'ensemble courte
- `AGENTS.md`: gouvernance, securite, workflow, conventions

## Dossiers fonctionnels actifs

- `docs/backend/`: bootstrap, routes publiques, logging, pages dynamiques, installation
- `docs/admin/`: modernisation admin/editorial/navigation
- `docs/blog/`: reference blog et guide de redaction
- `docs/security/`: hardening admin
- `docs/private/`: portail prive famille + backlog
- `docs/deployment/`: checklist V1, runbook go-live, setup Instagram
- `docs/roadmap/`: modernisation + transition

## Guides transverses actifs

- `docs/architecture.md`
- `docs/installation.md`
- `docs/seo.md`
- `docs/codex.md`
- `docs/refonte-lot-c.md`
- `docs/consolidation-lot-d.md`
- `docs/rapport-style-editorial-2026-04-16.md`

## Archives

- Point d'entree: `docs/archive/README.md`
- Contient audits historiques, rapports ponctuels prod, consolidations de sources datees.

## Verification rapide

```bash
rg --files -g '*.md' -g '!backend/vendor/**' -g '!frontend/node_modules/**'
rg --files -g 'README*.md' -g 'readme*.md'
cd frontend && npm run hygiene:docs
```
