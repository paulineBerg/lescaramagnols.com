# Runbook V1 Go-Live / Post Go-Live

Date de reference : 2026-03-21

Ce runbook complete la checklist V1 avec des commandes executables.
Il sert de guide de verification avant mise en ligne puis en J+1/J+7.

## 0) Discipline local / preprod

La preprod valide le comportement reel, mais le depot local reste la source de verite applicative.
Tout changement durable teste en preprod doit exister localement avant le prochain deploiement.

Ce qui doit rester aligne local -> preprod:
- code PHP, templates, CSS/JS sources, migrations, outils CLI et documentation utile
- assets sources sous `frontend/src/**` et assets publies regeneres par le build
- schema SQL ou payload editorial explicitement choisi pour la recette

Ce qui ne doit pas etre rendu identique:
- `.env`, `backend/config/*.override.php`, secrets, tokens et mots de passe
- logs, caches, backups, sessions, permissions serveur, crons et donnees runtime propres a l'environnement
- fixtures de recette preprod declarees jetables

Avant deploy preprod:

```bash
git status --short
```

Si le changement touche le frontend ou les images publiques:

```bash
cd frontend && npm run build
```

Puis controler les assets publies et les references editoriales avant l'envoi:

```bash
php backend/core/tools/check_vite_assets.php --public-root=backend/public
php backend/core/tools/check_editorial_media.php --check-published-assets
```

Deploiement preprod depuis le local, en preferant le perimetre indexe Git pour eviter d'envoyer des changements non lies:

```bash
git add <fichiers-backend-a-deployer>
DEPLOY_TARGET=preprod \
REMOTE_HOST=ovh-boutique \
REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend \
SITEMAP_BASE_URL=https://preprod.lescaramagnols.com \
bash backend/tools/deploy-fast.sh
```

Pour une recette complete de l'espace prive sur `caramagnols-preprod`, utiliser un deploy release preprod. Cette cible inclut `backend/private/`, mais continue d'exclure `.env`, `backend/config/*.override.php`, logs, caches, uploads runtime et fichiers de developpement:

```bash
DEPLOY_TARGET=preprod \
REMOTE_HOST=ovh-boutique \
REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend \
SITEMAP_BASE_URL=https://preprod.lescaramagnols.com \
bash backend/tools/deploy-release.sh
```

En production OVH `caramagnols`, garder la cible `prod` explicite. Cette cible exclut toujours `backend/private/` du deploy:

```bash
DEPLOY_TARGET=prod \
REMOTE_HOST=ovh-boutique \
REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols/backend \
SITEMAP_BASE_URL=https://www.lescaramagnols.com \
bash backend/tools/deploy-release.sh
```

Utiliser `--all-changes` seulement si le diff local est volontairement isole et relu.
Si le deploy ne concerne que le frontend publie, `deploy-fast.sh` peut sortir sans action faute de fichier `backend/` stage; dans ce cas, apres `npm run build`, synchroniser explicitement l'arbre publie:

```bash
REMOTE_HOST=ovh-boutique \
REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend \
bash backend/tools/sync-published-frontend-tree.sh
```

Apres deploy, rejouer les controles du domaine touche puis un smoke HTTP sur les URLs modifiees.

Si une correction a ete faite directement en preprod:
- identifier le ou les fichiers ou donnees touches
- reconstruire la correction en local ou rapatrier seulement les fichiers cibles hors secrets/runtime
- verifier le diff local avec `git diff`
- relancer les validations locales adaptees
- redeployer depuis le local pour que preprod redevienne le reflet applicatif du depot

Pour du contenu SQL/editorial cree ou corrige en preprod via l'admin, ne pas le recopier a la main dans deux environnements.
Il faut choisir explicitement: fixture jetable de recette, export a garder, ou synchronisation vers l'environnement source de verite.
Dans tous les cas, noter la decision dans la preuve de recette si le contenu peut influencer un futur deploy.

## 1) Go-Live (avant bascule)

1. Verifier la configuration production (hors Git) :
   - `composer check-env --working-dir=backend -- --env=production --strict-prod-security`
2. Verifier les headers securite sur la cible preprod :
   - `composer check-security-headers --working-dir=backend -- --url=https://preprod.votredomaine.tld`
3. Valider la supervision logs applicative :
   - `composer check-log-alerts --working-dir=backend -- --since-minutes=60 --strict`
4. Valider Instagram accueil (si bloc active) :
   - `composer check-instagram-feed --working-dir=backend -- --strict`
5. Activer la surveillance periodique logs (systemd timer) :
   - `sudo bash backend/tools/systemd/install-check-log-alerts-systemd.sh --dry-run`
   - `sudo bash backend/tools/systemd/install-check-log-alerts-systemd.sh`
   - verifier le timer : `systemctl list-timers --all caramagnols-check-log-alerts.timer`

## 1.b) Migration blog SQL en preprod (ordre recommande)

1. Importer les donnees blog JSON vers SQL :
   - `composer blog-import-sql --working-dir=backend`
2. Basculer en stabilisation :
   - `BLOG_STORAGE=dual-write` dans `.env` preprod
3. Valider en preprod :
   - front : `/`, `/blog`, `/blog/article/{slug}` (desktop + mobile)
   - admin : login, articles, discussions, suppression article + discussions rattachees
4. Basculer en SQL source de verite :
   - `BLOG_STORAGE=sql` dans `.env` preprod
5. Rejouer les verifications outillees :
   - `composer check-env --working-dir=backend -- --env=production --strict-prod-security`
   - `composer check-security-headers --working-dir=backend -- --url=https://preprod.votredomaine.tld`
   - `composer check-log-alerts --working-dir=backend -- --since-minutes=60 --strict`
6. Purger les caches applicatifs apres bascule :
   - `php -r "require 'backend/core/bootstrap.php'; app_runtime_cache_clear(['pages','navigation','translations']);"`
7. Archiver les preuves :
   - dossier recommande : `docs/private/recette-preprod-v1-YYYY-MM-DD/`
   - inclure sorties CLI + captures front/admin + incidents/resolutions

## 2) Rotation secrets (avant prod)

Secrets a tourner avant go-live :
- `ADMIN_SESSION_KEY`
- `ADMIN_PASSWORD_HASH` (mot de passe admin)
- `ADMIN_TOTP_SECRET`
- cles reCAPTCHA discussions
- token Instagram (si bloc active)

Politique :
- ne jamais commiter les secrets dans Git
- injecter via variables d'environnement/deploiement
- tracer la date de rotation dans le ticket de release

## 3) J+1 (stabilisation)

1. Revue securite et erreurs :
   - `composer check-log-alerts --working-dir=backend -- --since-minutes=1440 --strict`
2. Revue perf routes critiques :
   - `composer benchmark-routes --working-dir=backend -- --iterations=20 --warmup=3 --storage=json`
3. Revue frontend :
   - verifier absence d'erreurs JS critiques dans la console navigateur sur `/`, `/blog`, `/blog/article/{slug}`
4. Revue Lighthouse mobile (cible officielle V1) :
   - cible :
     - Performance >= 80
     - Accessibilite >= 95
     - Best Practices >= 95
     - SEO >= 95
   - commandes :
     - `npx --yes lighthouse https://www.lescaramagnols.com --form-factor=mobile --screenEmulation.mobile=true --only-categories=performance,accessibility,best-practices,seo`
     - `npx --yes lighthouse https://www.lescaramagnols.com/blog --form-factor=mobile --screenEmulation.mobile=true --only-categories=performance,accessibility,best-practices,seo`

## 4) J+7 (suivi)

1. Rejouer la revue logs securite :
   - `composer check-log-alerts --working-dir=backend -- --since-minutes=10080 --strict`
2. Rejouer benchmark routes :
   - `composer benchmark-routes --working-dir=backend -- --iterations=30 --warmup=5 --storage=json`
3. Rejouer Lighthouse mobile sur les memes URLs :
   - `npx --yes lighthouse https://www.lescaramagnols.com --form-factor=mobile --screenEmulation.mobile=true --only-categories=performance,accessibility,best-practices,seo`
   - `npx --yes lighthouse https://www.lescaramagnols.com/blog --form-factor=mobile --screenEmulation.mobile=true --only-categories=performance,accessibility,best-practices,seo`
4. Planifier correctifs :
   - consigner anomalies et patchs dans le backlog V1.x

## TODO

- valider la reception effective des notifications sur le canal ops cible (webhook/email) apres activation timer.
