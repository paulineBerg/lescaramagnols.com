<!-- .github/copilot-instructions.md - Guidance for AI coding agents working on LesCaramagnols -->

# Quick context
- Small PHP (procedural) backend + Vite frontend. Backend lives in `backend/`, public webroot is `backend/public/`. Frontend sources in `frontend/src/` and built assets are copied into `backend/public/` by `npm run postbuild`.
- Entrypoints: PHP requests hit `backend/public/index.php` which requires `backend/core/bootstrap.php`. Frontend dev server (Vite) runs from `frontend/`.

# What you should know (high-value facts)
- Configuration: `.env` lives at repository root inside `backend/` (use `backend/.env.example` as template). `backend/core/env.php` exposes `env()` and `load_env()`; `app_config()` values come from `backend/config/config.php` after `load_env()`.
- Language/i18n: server-side translations use `t('KEY')` and are loaded via `backend/core/lang_bootstrap.php` + `backend/core/i18n.php`. Client-side i18n is in progress (`frontend/src/js/i18n.js`) and reads `core/api/lang.php`.
- Routing: `backend/core/router.php` maps cleaned URIs to templates under `backend/templates/pages/`. Language prefix (eg `/en/...`) is stripped by the router.
- Templates: views are plain PHP files under `backend/templates/` and expect helper functions `t()`, `env()`, and `app_config()` to be available after bootstrap.
- Build/test scripts:
  - Frontend: `cd frontend && npm install` then `npm run dev` (Vite), `npm run build` and `npm run postbuild` to publish into `backend/public/`.
  - Backend tests: `composer install --working-dir=backend` then `composer test` or `vendor/bin/phpunit`.

# Conventions and patterns to follow (concrete)
- Keep changes minimal in `backend/templates/` — templates are simple PHP includes; prefer small, incremental edits.
- Use `t('KEY')` for any user-facing text in PHP templates. Add new keys in `backend/lang/<lang>.php` files (they return associative arrays).
- Environment variables: prefer `env('KEY', 'default')` where appropriate. The bootstrap enforces presence of critical keys when `APP_ENV=production`.
- Assets: frontend images should be imported via Vite (use JS/SCSS imports) instead of hard-coded absolute paths when adding new images—this preserves fingerprinting and avoids manual copy steps.
- Tests: backend unit tests live in `backend/tests/` (PHPUnit v10). Frontend tests use Vitest in `frontend/`.

# Typical tasks and how to run them (examples)
- Run the full local dev setup (frontend + backend):
  1. Backend dev server: `cd backend && php -S 127.0.0.1:8000 -t public public/dev-router.php`
  2. Frontend dev: `cd frontend && npm run dev` (Vite will proxy `/core/*` to the backend)
- Produce a production-ready frontend build and copy assets to backend:
  - `cd frontend && npm run build && npm run postbuild`
- Generate the search index (used by site search):
  - `php backend/core/tools/generate_search_index.php`
- Validate env for CI/CD before deploy:
  - `php backend/core/tools/check_env.php [--path=backend/.env] [--env=production]`

# Common pitfalls for contributors (from codebase)
- Don't commit `backend/.env`; use `backend/.env.example`. Production bootstrap will stop on missing critical keys.
- In dev, Vite doesn't push assets automatically into backend; run the `postbuild` copy step before testing PHP includes that rely on the Vite manifest.
- Templates sometimes reference images under `/assets/...` — when migrating to Vite imports, keep the original path mapping in `backend/public/assets/` until all templates are updated.
- The mini-router expects templates under `backend/templates/pages/` and performs limited freedom in URI matching — add pages by creating the correct file path rather than changing router logic unless necessary.

# Where to look for examples (key files)
- Bootstrap & env: `backend/core/bootstrap.php`, `backend/core/env.php`
- Router: `backend/core/router.php`
- i18n: `backend/core/i18n.php`, `backend/core/lang_bootstrap.php`, `backend/lang/*.php`
- Templates entry: `backend/public/index.php`, `backend/templates/partials/layout.php`
- Frontend build & scripts: `frontend/package.json`, `frontend/src/` (JS/SCSS)
- Tests: `backend/tests/`, `frontend/` (Vitest)

# Decision rules for code changes (brief)
- For UI/asset changes: prefer editing `frontend/src/` and using the build+postbuild flow. Do not edit `backend/public/assets/` manually unless fixing a production bug.
- For language keys: modify `backend/lang/*.php`. Keep keys stable; prefer adding new keys over changing existing ones to avoid regressions.
- For routing or bootstrap changes: these are high-impact — add tests in `backend/tests/` and update README if you change expected dev commands.

# When you need help
- If a file or key referenced in templates is missing, search for it under `backend/templates/`, `backend/lang/`, or `frontend/src/`.
- Ask for clarification when a change touches env or deployment-critical code (bootstrap, check_env, config). Leave a short PR note describing required environment variables.

---
If any specific sections are unclear or you want more examples (tests, common refactors), tell me which area to expand and I will iterate.

## Quick how-tos (concrete examples)

1) Add a new public page (server-side template)
  - Create the file under `backend/templates/pages/...` matching the route you want. Example: to serve `/site/about/team` create `backend/templates/pages/site/about/team.php`.
  - Keep page templates minimal: set `$pageTitle` if needed, and render content — layout is applied from `backend/templates/partials/layout.php` when `backend/public/index.php` includes the page.
  - Do not modify the router for simple pages; the mini-router maps URIs to files under `backend/templates/pages/` and will return 404 if the path is missing.

2) Add or update a translation key
  - Edit `backend/lang/fr.php` (and `backend/lang/en.php`, `backend/lang/de.php`) and add the key => value pair.
  - Use `t('NEW_KEY')` in templates or PHP code. Server loads translations via `backend/core/i18n.php` (cached per-request).
  - If you need to normalize paths in language files, `php backend/replace_image_paths.php` helps.

3) Add an image or frontend asset (Vite workflow)
  - Add sources in `frontend/src/assets/...` and import them from JS/SCSS to get hashed filenames.
  - Build and copy to backend: `cd frontend && npm run build && npm run postbuild` (copies `dist/assets` into `backend/public/assets` and `.vite` manifest into `backend/public/.vite`).
  - Temporary: if a template references `/assets/...` keep the file under `backend/public/assets/...` until you migrate the template to use Vite imports.

4) CI / PR checklist (minimal, runnable commands)
  - Backend lint & tests:
    ```bash
    composer install --working-dir=backend --no-interaction --prefer-dist
    composer test --working-dir=backend
    php -l backend/**/*.php || true
    ```
  - Frontend lint & tests + build:
    ```bash
    cd frontend
    npm ci
    npm run lint
    npm run test:run
    npm run build && npm run postbuild
    ```
  - Env check before deploy:
    ```bash
    php backend/core/tools/check_env.php --path=backend/.env --env=production
    ```

---
Tell me if you want these how-tos expanded into small automated scripts (Makefile or npm scripts) or turned into PR templates.

<!-- BEGIN MANAGED AI GOVERNANCE -->
Lire et appliquer `AGENTS.md` à la racine du dépôt avant toute suggestion,
revue ou génération de code. Ce fichier est uniquement le point d'entrée natif
de GitHub Copilot ; les règles ne sont pas dupliquées ici. Le bloc central de
`AGENTS.md` référence le routeur du socle.
<!-- END MANAGED AI GOVERNANCE -->
