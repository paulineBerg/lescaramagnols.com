# Les Caramagnols

## Objectif du projet

Ce depot contient le site Les Caramagnols avec rendu serveur PHP, pipeline frontend Vite, gestion editoriale multilingue (`fr`, `en`, `de`) et publication d'assets vers `backend/public/`.

## Structure générale

```text
/
├── README.md
├── AGENTS.md
├── backend/
│   ├── src/
│   ├── templates/
│   ├── tests/
│   └── README.md
├── frontend/
│   ├── src/
│   ├── tools/
│   └── README.md
└── docs/
    ├── README.md
    ├── architecture.md
    ├── installation.md
    ├── seo.md
    ├── codex.md
    ├── admin/
    ├── backend/
    ├── blog/
    ├── deployment/
    ├── private/
    ├── roadmap/
    ├── security/
    └── archive/
```

## Installation

Pre-requis:
- PHP `8.1+`
- Composer
- Node `>=20.19.0 <25` (recommande: `22.22.1` via `.nvmrc`)
- npm

Commandes:

```bash
cd backend && composer install
cd ../frontend && npm install
```

Procedure d'installation securisee (hors webroot): `docs/backend/installation-hors-webroot.md`.

## Développement local

Terminal 1:

```bash
cd backend/public
php -S 127.0.0.1:8099
```

Terminal 2:

```bash
cd frontend
npm run dev
```

Build publication:

```bash
cd frontend
npm run build
```

## Fonctionnalités principales

- Rendu serveur PHP avec front-controller unique
- Admin editorial (pages, navigation, blog)
- Blog SQL maitre avec contraintes de taxonomie et maillage interne
- Internationalisation `fr/en/de`
- SEO technique (canonical, JSON-LD, Open Graph/Twitter)
- Pipeline frontend outille (build, hygiene docs/assets, budgets)

## Documentation disponible

Point d'entree: `docs/README.md`.

Guides principaux:
- Architecture: `docs/architecture.md`
- Installation: `docs/installation.md`
- SEO: `docs/seo.md`
- Backend: `backend/README.md` et `docs/backend/`
- Frontend/build: `frontend/README.md`
- Blog: `docs/blog/`
- Securite admin: `docs/security/README.md`
- Modernisation/deploiement: `docs/roadmap/` et `docs/deployment/`
- Archives: `docs/archive/`

## Règles Codex / contribution

- Les regles de travail, d'architecture, de securite et de verification sont definies dans `AGENTS.md`.
- Toute modification non triviale doit respecter les documents de reference listes dans `AGENTS.md`.
- Ne pas multiplier les `.md`: fusionner par fonction et archiver les notes datees.
- Avant validation: executer les tests/lints pertinents et verifier les liens docs.

## Points sensibles à ne pas casser

- Routage public/admin autour de `backend/public/index.php` et `backend/src/Http/`
- Contrat i18n (`CURRENT_LANG`, `DEFAULT_LANG`, traducteurs backend/frontend)
- Source canonique editoriale SQL et coherence `fr/en/de`
- Publication assets frontend -> `backend/public/` sans reintroduire d'artefacts versionnes
- Regles SEO: canonical sans fragment `#`, JSON-LD centralise, images sociales par page
- Durcissement securite admin (session, re-auth, 2FA, anti brute-force)
