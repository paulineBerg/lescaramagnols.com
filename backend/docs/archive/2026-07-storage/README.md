# Archive - Migration stockage runtime 2026-07

Cette archive conserve les documents historiques lies a la migration du stockage
runtime prive executee le 2026-07-18.

## Contenu

- `RUNBOOK_STORAGE_MIGRATION.md` : runbook de migration execute, conserve pour
  historique, rollback et checklists de securite.

## Document actif

La politique active est `backend/docs/STORAGE_RUNTIME_POLICY.md`.

## Regles

- Ne pas supprimer ni deplacer les donnees de production
  `caramagnols-runtime/private-storage/**` depuis le depot local.
- Ne pas utiliser cette archive comme autorisation d'operation distante.
- Toute action production reste soumise aux regles de `AGENTS.md`, backup,
  verification prealable et demande explicite.
