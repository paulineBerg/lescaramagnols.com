# Les Caramagnols

Site `https://www.lescaramagnols.com/` : rendu serveur PHP avec front-controller unique, pipeline frontend Vite, gestion editoriale multilingue (`fr`, `en`, `de`) et publication d'assets vers `backend/public/`.

La production est la reference fonctionnelle et editoriale du projet (voir `AGENTS.md`).

## Stack technique

| Domaine | Technologies |
|---|---|
| Backend | PHP `8.2+` (les dependances verrouillees exigent `>=8.2`), FastRoute (routage), Monolog 3 (logs), Symfony Mailer 7 (emails), PDO/MySQL |
| Frontend | Vite 7, TypeScript, SCSS, tarteaucitron (consentement cookies) |
| Qualite backend | PHPUnit 10, PHPStan (niveau 5), PHP_CodeSniffer (PSR-12) |
| Qualite frontend | Vitest, ESLint 9, Stylelint 16, Prettier, budgets d'assets |
| Runtime | Node `>=20.19.0 <25` (recommande: `22.22.1` via `.nvmrc`), Composer |
| Hebergement | OVH (prod + preprod), deploiement rsync/ssh via `backend/tools/` |

Pas de framework applicatif : socle maison PSR-4 (`Caramagnols\` => `backend/src/`) sur FastRoute.

## Structure générale

```text
/
├── README.md
├── AGENTS.md               # gouvernance, securite, conventions (PRIORITAIRE)
├── Makefile                # install, tests, build, hooks git
├── dev.sh                  # orchestrateur dev local (PHP + Vite + proxy HTTPS)
├── backend/
│   ├── public/             # webroot unique (index.php, assets publies, .htaccess durci)
│   ├── src/                # code moderne PSR-4, organise par domaine
│   ├── core/               # bootstrap, helpers et wrappers legacy, outils CLI
│   ├── config/             # config centrale (secrets via .env, jamais en dur)
│   ├── templates/          # rendu serveur (pages, partials, admin, private)
│   ├── data/               # sources editoriales JSON (pages, menus, blog)
│   ├── sql/                # schemas et migrations (editorial/ et private/)
│   ├── tools/              # scripts shell d'exploitation et deploiement OVH
│   ├── var/                # runtime (cache, logs, rate-limits) — non versionne
│   ├── lang/               # dictionnaires i18n fr/en/de
│   └── tests/              # PHPUnit (~126 fichiers de tests)
├── frontend/
│   ├── src/                # TS/JS, SCSS, assets sources
│   └── tools/              # scripts de build, hygiene docs/assets, budgets
└── docs/                   # documentation par domaine (index: docs/README.md)
```

## Architecture backend

### Flux d'une requête

```text
backend/public/index.php
  └─ core/bootstrap.php            # env, config, i18n, securite, sessions
      └─ src/Http/FrontController  # dispatcher FastRoute (API, blog, RSS/sitemap, admin, prive)
          └─ fallback core/router.php (routes legacy -> templates/pages/*.php)
              └─ templates/ (layout + partials)
```

### Domaines principaux de `backend/src/`

- Site public et editorial : `Content` (pages structurees, tuiles), `Navigation` (menus/mega-menu), `Blog`, `Editorial`, `Seo`, `Feed`, `I18n`, `Assets`
- Transverse : `Http`, `Database`, `Security` (CSRF, cookies, CSP), `Logging`, `Mailer`, `Support`, `Cron`, `Backup`, `Social`
- Admin : `Admin` (controleur + services de gestion editoriale)
- Espace prive :
  - `PrivatePortal/` — **socle uniquement** : HTTP, routes, securite (auth, MFA, sessions), utilisateurs, permissions, operations transverses
  - `PrivateApps/` — **modules metier** : `RealEstateRental` (gestion locative + import agence), `TaxDeclarationHelper`, `FamilyDiscussion` (messagerie chiffree), `BlocNote`, `Documents`

Regle d'architecture (voir `AGENTS.md`) : toute nouvelle logique metier privee va dans `PrivateApps/`, jamais dans `PrivatePortal/`.

### Données

- Stockage editorial configurable via `EDITORIAL_STORAGE` : `json` | `sql` | `dual-write`
- Sources JSON : `backend/data/` (`pages.json`, `menus.json`, articles de blog multilingues)
- Schemas SQL : `backend/sql/install.sql` + migrations `sql/editorial/` et `sql/private/`
- Acces base : PDO exclusivement, requetes preparees, `ATTR_EMULATE_PREPARES => false` (`src/Database/PdoConnectionFactory.php`)
- Stockage runtime privé production : `/home/lescaramgl-ssh/caramagnols-runtime/private-storage/`, séparé du code backend depuis le 2026-07-18. Voir `backend/docs/STORAGE_RUNTIME_POLICY.md` et `backend/docs/RUNBOOK_STORAGE_MIGRATION.md`.

## Installation

Pre-requis: PHP `8.2+`, Composer, Node `>=20.19.0 <25`, npm.

```bash
make install-backend    # ou: cd backend && composer install
make install-frontend   # ou: cd frontend && npm ci
make install-git-hooks  # hooks pre-commit (.githooks)
```

Configuration : copier `backend/.env.example` vers `backend/.env` et renseigner les valeurs locales. Aucun secret ne doit etre versionne.

Procedure d'installation securisee (hors webroot): `docs/backend/installation-hors-webroot.md`.

## Développement local

Orchestrateur tout-en-un :

```bash
./dev.sh   # PHP 127.0.0.1:8000 + Vite localhost:5173 (+ proxy HTTPS optionnel)
```

Ou manuellement — Terminal 1:

```bash
cd backend
php -S 127.0.0.1:8000 -t public public/dev-router.php
```

Le routeur `public/dev-router.php` est necessaire en local pour servir les routes dynamiques du front-controller, par exemple les anciennes URL publiques en `.php`.

Terminal 2:

```bash
cd frontend
npm run dev
```

Build publication:

```bash
cd frontend
npm run build   # inclut check-budgets + publication vers backend/public/
```

## Qualité et tests

```bash
# Backend (depuis backend/)
composer test          # PHPUnit
composer phpstan       # analyse statique (niveau 5, src/)
composer phpcs         # PSR-12 (src/)
composer lint          # php -l
composer check-env     # coherence configuration
composer check-i18n    # coherence cles de traduction

# Frontend (depuis frontend/)
npm run test:run       # Vitest
npm run lint           # ESLint + Stylelint
npm run hygiene:docs   # verification des liens documentation

# Racine
make test-backend test-frontend
```

## Déploiement (OVH)

Cible unique : **prod** (`DEPLOY_TARGET=prod`). La preprod est abandonnee depuis le 2026-07-17.

Acces SSH OVH depuis ce poste :

```bash
ssh ovh-boutique
```

`ovh-boutique` est un alias SSH local gere hors depot dans `~/.ssh/config`. Ne jamais versionner de cle privee, mot de passe, host complet non public ou secret d'acces. Le backend production est dans `/home/lescaramgl-ssh/caramagnols/backend`.

`backend/private/` est synchronise en mode **additif** (rsync sans `--delete`) : les fichiers runtime prives de production ne sont jamais supprimes par un deploy.

Par defaut, les scripts de deploiement synchronisent aussi le schema SQL attendu par le code deploye :
- migrations editoriales versionnees `backend/sql/editorial/*.sql` ;
- creation idempotente des tables privees manquantes declarees dans `backend/sql/private/*.sql`.

Utiliser `--no-schema-sync` uniquement pour un deploiement de fichiers qui ne doit pas toucher au schema SQL.

```bash
backend/tools/deploy-release.sh   # deploiement complet (garde anti-fichiers non trackes)
backend/tools/deploy-fast.sh      # deploiement rapide
```

References : `docs/deployment/README.md` (checklist V1) et `docs/deployment/runbook-v1-go-live.md`.

## Sécurité

- Webroot limite a `backend/public/` ; `private/`, `var/`, `data/`, `.env` hors exposition HTTP
- `.htaccess` durci : HTTPS force, CSP, HSTS, X-Frame-Options, Permissions-Policy, blocage fichiers sensibles
- Admin : session securisee, CSRF, rate-limit, allowlist IP, timeout d'inactivite, re-authentification, 2FA TOTP
- Espace prive : MFA TOTP + codes de secours, politique de mot de passe (min. 14 caracteres), verrouillage de compte, chiffrement AES-256-GCM des pieces jointes de discussion
- Les routes canoniques admin/prive ne sont pas documentees ici (voir `AGENTS.md`)

Reference : `docs/security/README.md`.

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
- **Plan d'optimisation (phases + checklist): `docs/roadmap/optimisation-2026-07.md`**
- Archives: `docs/archive/`

## Règles de contribution

- Les regles de travail, d'architecture, de securite et de verification sont definies dans `AGENTS.md` (prioritaire).
- Toute modification non triviale doit respecter les documents de reference listes dans `AGENTS.md`.
- Ne pas multiplier les `.md`: fusionner par fonction et archiver les notes datees.
- Avant validation: executer les tests/lints pertinents et verifier les liens docs (`npm run hygiene:docs`).
- Aucun secret dans le depot : utiliser `backend/.env` (local) et `backend/.env.example` (reference).

## Points sensibles à ne pas casser

- Routage public/admin autour de `backend/public/index.php` et `backend/src/Http/`
- Contrat i18n (`CURRENT_LANG`, `DEFAULT_LANG`, traducteurs backend/frontend)
- Source canonique editoriale SQL et coherence `fr/en/de`
- Publication assets frontend -> `backend/public/` sans reintroduire d'artefacts versionnes
- Regles SEO: canonical sans fragment `#`, JSON-LD centralise, images sociales par page
- Durcissement securite admin (session, re-auth, 2FA, anti brute-force)
- Separation `PrivatePortal` (socle) / `PrivateApps` (metier) et non-exposition publique de `/private`
- Separation code/runtime privé : ne pas supprimer, synchroniser ou versionner `caramagnols-runtime/private-storage`
