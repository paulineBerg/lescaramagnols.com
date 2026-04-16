# Runbook V1 Go-Live / Post Go-Live

Date de reference : 2026-03-21

Ce runbook complete la checklist V1 avec des commandes executables.
Il sert de guide de verification avant mise en ligne puis en J+1/J+7.

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
