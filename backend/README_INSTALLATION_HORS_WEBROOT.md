# Installation Hors Webroot

Date : 2026-03-21

Ce document remplace l'ancien usage des installateurs web publics.

## Objectif

Installer ou réinstaller le projet sans exposer d'outil d'installation dans `backend/public/`.

## Principe

- aucun installateur HTTP ne doit exister dans le webroot
- la configuration reste hors `public/`
- la base se prépare depuis le shell ou un outil d'administration non public
- le document root du vhost doit pointer vers `backend/public` (pas `backend/`)

## Procedure Recommandee

1. Installer les dependances backend :
   - `composer install --working-dir=backend`
2. Installer les dependances frontend :
   - `cd frontend && npm install`
3. Preparer l'environnement :
   - copier `backend/.env.example` vers `backend/.env`
   - renseigner `APP_ENV`, `BASE_URL`, `DEFAULT_LANG`
   - renseigner `DB_*`
   - personnaliser `ADMIN_LOGIN_PATH` si besoin
4. Initialiser la base et le compte admin avec la commande CLI dediee :
   - mode simulation (aucune ecriture) :
     - `composer init-db-admin --working-dir=backend -- --db-host=127.0.0.1 --db-port=3306 --db-name=caramagnols --db-user=root --db-password='motdepasse_sql' --admin-email=admin@exemple.tld --admin-password='motdepasse-admin-fort' --dry-run`
   - execution reelle :
     - `composer init-db-admin --working-dir=backend -- --db-host=127.0.0.1 --db-port=3306 --db-name=caramagnols --db-user=root --db-password='motdepasse_sql' --admin-email=admin@exemple.tld --admin-password='motdepasse-admin-fort'`
   - la commande :
     - cree la base si absente
     - applique `backend/sql/install.sql` (schema legacy)
     - applique `backend/sql/editorial/*.sql` (schema editorial SQL)
     - seed un compte admin dans `{prefix}users`
     - ecrit `config/database.override.php` et `config/admin.override.php` (sans ecrasement par defaut)
5. Construire les assets si besoin :
   - `cd frontend && npm run build`

## Option WSL (copier-coller simple)

Depuis WSL :

```bash
cd /home/surfacepro8/www/caramagnols

composer install --working-dir=backend
cd frontend && npm install && cd ..

cp backend/.env.example backend/.env

composer init-db-admin --working-dir=backend -- \
  --db-host=127.0.0.1 \
  --db-port=3306 \
  --db-name=caramagnols \
  --db-user=root \
  --db-password='motdepasse_sql' \
  --admin-email=admin@exemple.tld \
  --admin-password='motdepasse-admin-fort' \
  --dry-run

composer init-db-admin --working-dir=backend -- \
  --db-host=127.0.0.1 \
  --db-port=3306 \
  --db-name=caramagnols \
  --db-user=root \
  --db-password='motdepasse_sql' \
  --admin-email=admin@exemple.tld \
  --admin-password='motdepasse-admin-fort'
```

## Controles Apres Installation

- `composer check-env --working-dir=backend`
- `composer check-env --working-dir=backend -- --env=production --strict-prod-security`
- `composer check-i18n --working-dir=backend`
- `composer test --working-dir=backend`
- `cd frontend && npm run lint && npm run test:run`
- verifier l'URL admin canonique : `/<ADMIN_LOGIN_PATH>` (exemple `.env.example` : `/admin`)
- verifier les headers securite sur preprod : `composer check-security-headers --working-dir=backend -- --url=https://preprod.exemple.tld`

## Ce Qui A Change

- `backend/public/installsql.php` a été supprimé
- `backend/public/assets/install.php` a été supprimé
- la documentation projet ne doit plus orienter vers une installation via HTTP
- la route admin n'est plus couplée à un dossier public précis
- la commande CLI `composer init-db-admin` est la methode canonique d'initialisation DB + compte admin
