# Archive - Migration stockage runtime 2026-07

Cette archive conserve les documents historiques lies a la migration du stockage
runtime prive executee le 2026-07-18, puis finalisee cote code le 2026-08-06.

## Contenu

- `RUNBOOK_STORAGE_MIGRATION.md` : runbook de migration execute, conserve pour
  historique, rollback et checklists de securite.
- `COMPTE_RENDU_AUDIT_STORAGE_2026-07-18.md` : audit complet du stockage.
- `prompt-audit-stockage-runtime-deploiement-production-maitre.md` : prompt
  source de l'audit et de la migration.

## Finalisation 2026-08-06

Le fallback applicatif legacy a ete retire des services de stockage prives. En
production, une configuration absente, inexistante ou situee sous `ROOT_PATH`
doit echouer explicitement au lieu de recreer `backend/private/storage/**`.

## Document actif

La politique active est `backend/docs/STORAGE_RUNTIME_POLICY.md`.

## Regles

- Ne pas supprimer ni deplacer les donnees de production
  `caramagnols-runtime/private-storage/**` depuis le depot local.
- Ne pas utiliser cette archive comme autorisation d'operation distante.
- Toute action production reste soumise aux regles de `AGENTS.md`, backup,
  verification prealable et demande explicite.
