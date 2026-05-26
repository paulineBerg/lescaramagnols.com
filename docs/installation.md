# Installation

## Prerequis

- PHP `8.1+`
- Composer
- Node `>=20.19.0 <25` (recommande: `22.22.1`)
- npm

## Installation locale

```bash
cd backend
composer install

cd ../frontend
npm install
```

## Lancement local

Terminal backend:

```bash
cd backend/public
php -S 127.0.0.1:8099
```

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
