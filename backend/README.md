# Backend

## Rôle

Le backend porte le rendu serveur, le routage HTTP public/admin, la logique metier et les templates PHP.

## Dossiers principaux

- `src/`: logique applicative moderne
- `core/`: bootstrap commun, wrappers legacy, outillage
- `templates/`: rendu serveur
- `tests/`: tests PHPUnit
- `sql/`: schemas/migrations

## Stockage runtime privé

En production OVH, le code et les données runtime privées sont séparés depuis le 2026-07-18 :

```text
# Code déployé
/home/lescaramgl-ssh/caramagnols/backend/

# Données runtime privées, hors déploiement
/home/lescaramgl-ssh/caramagnols-runtime/private-storage/
```

Le dossier `caramagnols-runtime/` est volontaire. Il contient les documents et fichiers générés par l'espace privé (`uploads/`, `document-hub/`, `family-discussion/`, `backups/`, `exports/`) et évite qu'un déploiement backend ou une opération Git touche des données utilisateurs.

Configuration production attendue :

```bash
PRIVATE_STORAGE_ROOT=/home/lescaramgl-ssh/caramagnols-runtime/private-storage
PUSH_LOCAL_SQL_BLOCKED=1
SYNC_EDITORIAL_UPLOADS_BLOCKED=1
```

L'ancien chemin `backend/private/storage/` peut rester présent comme transition/rollback, mais il n'est plus la destination d'écriture attendue en production. Ne pas supprimer ni archiver l'ancien stockage sans suivre `docs/archive/2026-07-storage/RUNBOOK_STORAGE_MIGRATION.md`.

Références :

- `docs/STORAGE_RUNTIME_POLICY.md`
- `docs/archive/2026-07-storage/RUNBOOK_STORAGE_MIGRATION.md`
- `docs/photo-geo-renamer-pbgestion.md`

## Documentation technique

- Bootstrap et i18n: `../docs/backend/bootstrap-i18n.md`
- Gouvernance des entrees publiques: `../docs/backend/public-entrypoints.md`
- Logging applicatif: `../docs/backend/logging.md`
- Installation hors webroot: `../docs/backend/installation-hors-webroot.md`
- Photo Geo Renamer via PbGestion: `docs/photo-geo-renamer-pbgestion.md`

## Regles critiques

- ne pas ajouter de logique metier dans `public/`
- garder le front-controller et le bootstrap centralises
- appliquer les regles i18n et SEO definies dans `AGENTS.md`
