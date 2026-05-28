# README Securite Admin

Date: 2026-03-20  
Statut : canonique

## Objectif

Ce document decrit le niveau de securite admin reellement en place, la configuration a maintenir, et la checklist de verification avant mise en production.

## Resume Executif

- Niveau global actuel: **bon socle applicatif** avec defenses multicouches.
- Le controle de securite ne repose pas sur une URL "complexe".
- Les protections critiques actives sont: authentification + CSRF + rate-limit + allowlist IP + timeout d'inactivite + re-auth actions sensibles + 2FA TOTP (hors localhost) + logs securite.

## Perimetre

- Ce README couvre l'acces admin et les actions sensibles cote backend.
- Il ne remplace pas les protections infra (WAF/CDN/fail2ban/EDR), qui restent recommandees.

## Protections Actives

- Auth admin par identifiant/mot de passe hash (`password_verify`).
- Regeneration d'ID de session a la connexion/deconnexion.
- Cookie de session durci (`HttpOnly`, `SameSite=Strict`, `use_strict_mode`).
- Protection CSRF sur formulaires admin.
- Rate-limit sur ecran de connexion admin (`ADMIN_LOGIN_RATE_LIMIT_*`).
- Filtrage IP admin (`ADMIN_ALLOWED_IPS`, IPv4/IPv6/CIDR).
- 2FA TOTP pour admin, avec bypass local optionnel (`ADMIN_TOTP_SKIP_LOCALHOST=true`).
- Timeout d'inactivite admin (defaut 7200s = 120 min).
- Avertissement d'expiration de session admin : modale "Voulez-vous prolonger la session ?" 120 secondes avant timeout, choix Oui/Non, fermeture automatique si aucune reponse sous 120 secondes.
- Re-authentification requise sur actions sensibles (defaut 7200s = 120 min).
- Journalisation securite (`backend/data/logs/security.log` + store SQL si active).
- Headers HTTP de securite (CSP, X-Frame-Options, etc.).
- Redirection HTTP->HTTPS possible cote application (`FORCE_HTTPS`) et serveur (`backend/public/.htaccess`).
- Prise en compte des headers proxy (`X-Forwarded-*`) uniquement si explicitement activee (`TRUST_PROXY_HEADERS=true`).
- Verification preprod des headers securite via `composer check-security-headers -- --url=...`.

## Actions Sensibles Protegees Par Re-auth

Les operations POST suivantes imposent une session "fraiche" (reauth):

- sauvegarde des settings admin/site
- actions d'ecriture sur logs
- suppression de pages
- suppression d'articles
- suppression de discussions

Si la fenetre de re-auth est depassee, la session est coupee et l'admin doit se reconnecter.

## Warning Session Admin (120 min)

- Le timeout d'inactivite reste fixe a `ADMIN_INACTIVITY_TIMEOUT_SECONDS=7200` (120 min).
- A `T-120s` (2 min avant expiration), une modale bloque l'interface admin et affiche :
  - message : "Voulez-vous prolonger la session ?"
  - actions : `Oui, prolonger` / `Non, se deconnecter`
- Sans reponse utilisateur pendant 120 secondes : deconnexion automatique.
- Si l'utilisateur clique `Oui` : appel AJAX `POST /<base_path>/<ADMIN_LOGIN_PATH>/session/ping` avec CSRF pour prolonger la session.
- Si l'utilisateur clique `Non` : deconnexion immediate.

## Clarification Importante Sur L'URL Admin

- Utiliser uniquement une URL admin non devinable est de l'obfuscation.
- Le niveau de securite repose sur les controles ci-dessus, pas sur le nom de route.
- La route admin (`ADMIN_LOGIN_PATH`) reste configurable pour l'ergonomie/organisation, mais ce n'est pas un controle fort.

## Configuration Recommandee (.env)

```env
APP_ENV=production
BASE_URL=https://www.votredomaine.tld

# HTTPS
FORCE_HTTPS=true
FORCE_HTTPS_ON_LOCALHOST=false
FORCE_HTTPS_PORT=
FORCE_HTTPS_EXCLUDED_HOSTS=
TRUST_PROXY_HEADERS=false

# Admin
ADMIN_LOGIN_PATH=admin
ADMIN_SESSION_KEY=changer-cette-cle-session-longue-et-aleatoire
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD_HASH=$2y$...
ADMIN_ALLOWED_IPS=203.0.113.10,198.51.100.0/24
ADMIN_TRUST_PROXY_HEADERS=false

# Anti brute-force login
ADMIN_LOGIN_RATE_LIMIT_ATTEMPTS=5
ADMIN_LOGIN_RATE_LIMIT_WINDOW=900

# Session admin
ADMIN_INACTIVITY_TIMEOUT_SECONDS=7200
ADMIN_REAUTH_TIMEOUT_SECONDS=7200

# 2FA TOTP
ADMIN_TOTP_ENABLED=true
ADMIN_TOTP_SECRET=JBSWY3DPEHPK3PXP
ADMIN_TOTP_SKIP_LOCALHOST=true

# Local seulement, ignore en production meme si active par erreur
ADMIN_LOCAL_PASSWORDLESS_LOCALHOST=false

# Secrets API (hors Git)
DISCUSSIONS_RECAPTCHA_SITE_KEY=
DISCUSSIONS_RECAPTCHA_SECRET_KEY=
INSTAGRAM_USERNAME=
INSTAGRAM_USER_ID=
INSTAGRAM_ACCESS_TOKEN=
```

## Configuration Via Admin UI

Dans `Admin > Settings > Connexion admin`, il est possible de gerer:

- e-mail admin
- mot de passe admin
- IP autorisees (champ CSV/CIDR)
- activation 2FA TOTP
- secret TOTP
- timeout d'inactivite
- fenetre de re-authentification

Les valeurs sont enregistrees dans `backend/config/admin.override.php` (fichier ignore par Git).

## HTTPS: Local Vs Production

- Production: `FORCE_HTTPS=true` et reverse-proxy/webserver correctement configures.
- Localhost: laisser `FORCE_HTTPS_ON_LOCALHOST=false` si aucun endpoint TLS local n'ecoute.
- Si vous forcez HTTPS en local, il faut un serveur qui ecoute en TLS sur le port cible (ex: 18443), sinon navigateur => `ERR_CONNECTION_REFUSED`.

## Connexion Admin Locale Sans Mot De Passe

Le mode `local_passwordless_localhost` est reserve au poste de developpement.
Il ne fonctionne que si l'environnement applicatif n'est pas `production`, `prod` ou `live`, et si l'adresse distante reelle est loopback (`127.0.0.1`, `::1` ou equivalent IPv4 mappe).
Le code ne tient pas compte de `HTTP_HOST`, `SERVER_NAME` ni des headers proxy pour ce bypass.
Quand ce mode est autorise, le formulaire local ne demande ni mot de passe ni code TOTP.
La session creee reste une session admin normale: CSRF, timeout, re-authentification et logs de securite restent actifs.

## Checklist De Verification

1. Webroot public pointe vers `backend/public` uniquement.
2. Acces admin refuse en 403 depuis une IP non allowlist (si allowlist active).
3. Brute-force login depasse la limite et retourne 429.
4. Session admin expire apres inactivite configuree.
5. Le warning session apparait 120s avant expiration, sans reponse => logout au bout de 120s.
6. Action sensible apres delai re-auth force une reconnexion.
7. Avec TOTP actif (hors localhost), login sans code ou code faux est refuse.
8. Aucun secret n'est committe dans le depot.
9. `backend/public/.htaccess` est deployee en prod (redirect HTTPS/host canonique + blocages de fichiers sensibles).
10. `composer check-security-headers -- --url=https://preprod.votredomaine.tld` est vert avant go-live.
11. Si `local_passwordless_localhost` est active en local, verifier qu'un `APP_ENV=production` ou une adresse non-loopback refuse toujours la connexion sans mot de passe.

## Execution Ticket W1-03 (2026-03-20)

Resultat :

- `php core/tools/check_security_headers.php --url=https://www.lescaramagnols.com` -> OK (`status 200`, headers requis presents).
- `php core/tools/check_env.php --env=production --strict-prod-security` -> OK.
- preuves archivees dans `docs/private/recette-preprod-v1-2026-03-20/`.

Checklist detaillee hardening HTTP cible :

- [x] HTTPS effectif sur domaine cible (URL finale en `https://`).
- [x] `Strict-Transport-Security` present quand cible HTTPS.
- [x] `Content-Security-Policy` present.
- [x] `X-Frame-Options` present.
- [x] `X-Content-Type-Options` present.
- [x] `Referrer-Policy` presente.
- [x] `Permissions-Policy` presente.
- [x] `Cross-Origin-Opener-Policy` et `Cross-Origin-Resource-Policy` presents.
- [x] Controle `check_env --strict-prod-security` valide.
- [x] Sorties archivees : `33-check-security-headers-www.txt`, `34-check-env-production-strict.txt`.

## Rotation Et Reponse Incident

En cas de suspicion de compromission:

1. Changer `ADMIN_PASSWORD_HASH`.
2. Changer `ADMIN_SESSION_KEY` (invalidate sessions).
3. Regenerer `ADMIN_TOTP_SECRET`.
4. Tourner les cles reCAPTCHA et Instagram.
5. Restreindre temporairement `ADMIN_ALLOWED_IPS`.
6. Auditer `security.log` sur la plage temporelle concernee.

## Limites Connues Et Suite Recommandee

- Pas de WAF natif dans le repo.
- Pas de blocage automatique type fail2ban dans le repo.
- Pas de 2FA materiel (WebAuthn/U2F) pour le moment.
- Retrait progressif du prive PHP en cours : `/{private}/privacy/anonymize` est bloque depuis M6, les routes restantes sont inventoriees par `php backend/core/tools/private_migration_reconcile.php m6-retirement` avec statut explicite.

Plan court terme recommande:

1. Ajouter WAF/CDN en frontal.
2. Ajouter blocage auto sur pics d'echecs login/403.
3. Ajouter alerting (mail/webhook) sur evenements critiques de `security.log`.

Mise a jour 2026-03-21 :

- le script `composer check-log-alerts` supporte maintenant la notification ops :
  - webhook : `--webhook-url=...` ou `LOG_ALERTS_WEBHOOK_URL`
  - email : `--email-to=...` ou `LOG_ALERTS_EMAIL_TO`
- un pack `systemd` est fourni pour preprod/prod :
  - `backend/tools/check-log-alerts-runner.sh`
  - `backend/tools/systemd/install-check-log-alerts-systemd.sh`
  - templates units/env dans `backend/tools/systemd/`
- la section admin `Parametres > Observabilite ops` permet maintenant de piloter le mode `notify_on` (`alerts`/`always`) sans exposer les secrets webhook/email (toujours geres en configuration systeme).

## Notes Section 4 V1

- Les formulaires contact (page legacy + composant `contact_form` structure) passent maintenant par les helpers CSRF communs (`csrf_token`/`csrf_validate`).
- Le script `composer check-env -- --env=production` enforce des points durs: `FORCE_HTTPS=true`, TOTP activee + secret valide, hygiene des overrides trackes.
- Le mode `composer check-env -- --env=production --strict-prod-security` rend aussi bloquants `ADMIN_SESSION_KEY` trop court et `ADMIN_ALLOWED_IPS` vide/loopback-only.
