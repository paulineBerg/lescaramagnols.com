# Installation

## Prerequis

- PHP `8.1+`
- Composer
- Node `>=20.19.0 <25` (recommande: `22.22.1`)
- npm

## Installation locale

```bash
git submodule sync --recursive
git submodule update --init --recursive

cd backend
composer install

cd ../frontend
npm install
```

Les sous-modules privés sont épinglés sur des commits validés par le dépôt principal. Ne pas utiliser `git submodule update --remote` pour une installation normale : `git submodule update --init --recursive` restitue exactement les versions attendues.

Pour cloner le projet en une seule commande :

```bash
git clone --recurse-submodules git@github.com:paulineBerg/lescaramagnols.com.git
```

## Lancement local

Terminal backend:

```bash
cd backend
php -S 127.0.0.1:8000 -t public public/dev-router.php
```

Le routeur `public/dev-router.php` doit etre conserve en developpement local: sans lui, le serveur interne PHP cherche un fichier physique et renvoie un `404` sur les routes dynamiques comme `/auto-retro/.../*.php`.

Terminal frontend:

```bash
cd frontend
npm run dev
```

## Build / publication assets

```bash
cd frontend
npm run build
```

## Installation securisee hors webroot

Procedure detaillee:
- `docs/backend/installation-hors-webroot.md`

## Verifications recommandees

```bash
cd backend && ./vendor/bin/phpunit --version
cd frontend && npm run hygiene:repo
```
