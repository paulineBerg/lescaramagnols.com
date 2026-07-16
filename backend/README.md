# Backend

## Rôle

Le backend porte le rendu serveur, le routage HTTP public/admin, la logique metier et les templates PHP.

## Dossiers principaux

- `src/`: logique applicative moderne
- `core/`: bootstrap commun, wrappers legacy, outillage
- `templates/`: rendu serveur
- `tests/`: tests PHPUnit
- `sql/`: schemas/migrations

## Documentation technique

- Bootstrap et i18n: `../docs/backend/bootstrap-i18n.md`
- Gouvernance des entrees publiques: `../docs/backend/public-entrypoints.md`
- Logging applicatif: `../docs/backend/logging.md`
- Installation hors webroot: `../docs/backend/installation-hors-webroot.md`

## Regles critiques

- ne pas ajouter de logique metier dans `public/`
- garder le front-controller et le bootstrap centralises
- appliquer les regles i18n et SEO definies dans `AGENTS.md`
