# Logging Applicatif

Date : 2026-03-19

Ce document decrit la journalisation active en V1, avec correlation de requete, rotation locale et scripts d'alerte.

## Objectif

Disposer d'un logging uniforme, exploitable et non bloquant :
- le logging ne doit jamais casser le front-office ni l'admin
- les canaux sont separes par nature d'evenement
- chaque ligne doit etre corrélable a une requete HTTP
- la croissance des fichiers est encadree par rotation/retention

## Couche De Reference

- factory : `backend/src/Logging/LoggerFactory.php`
- facade applicative : `backend/src/Logging/AppEventLogger.php`
- helper global : `app_event_logger()`
- dossier de sortie : `backend/data/logs/`
- script alertes : `backend/core/tools/check_log_alerts.php`

## Correlation Requete

- `backend/src/Http/FrontController.php` resolve un id de correlation pour chaque requete :
  - reprise `X-Request-Id` ou `X-Correlation-Id` si fourni et valide
  - sinon generation locale (`app_generate_request_id()`)
- la reponse HTTP expose toujours `X-Request-Id`
- le contexte est injecte dans `app_request_context_*` puis fusionne dans `AppEventLogger`
- champs standards automatiquement ajoutes aux logs :
  - `request_id`
  - `method`
  - `uri`
  - `path`
  - `client_ip`

## Canaux

- `security.log`
  - connexions admin reussies/refusees
  - acces admin refuses
  - CSRF invalide
  - rate limit sur endpoints sensibles
- `content.log`
  - sauvegardes editoriales (menus, pages, blog)
  - erreurs de validation / persistance
- `access.log`
  - visites front-office GET (`site.visit.page`, `site.visit.not_found`)
  - statut HTTP + metriques de rendu (`render_ms`)
  - contexte visiteur (`ip`, `user_agent`, `referer`, `query`, `visitor_id`)

## Rotation Et Retention

La rotation est geree localement par `LoggerFactory` lors de la creation du handler :
- fichier actif : `<channel>.log`
- archives : `<channel>.log.1` ... `<channel>.log.N`
- rotation declenchee quand la taille atteint `logging.rotation_max_bytes`
- conservation limitee a `logging.retention_files`

Configuration (`backend/.env` / `.env.example`) :
- `LOG_ROTATION_MAX_BYTES` (defaut `5242880`, soit 5 MiB)
- `LOG_RETENTION_FILES` (defaut `14`)

Ces operations sont volontairement non bloquantes (les erreurs de rotation n'interrompent pas la requete).

## Scripts D Exploitation

- verifier les alertes de base sur une fenetre glissante :
  - `composer check-log-alerts`
  - `composer check-log-alerts -- --since-minutes=30`
  - `composer check-log-alerts -- --strict` (exit code `2` si seuil depasse)
  - plage supportee pour `--since-minutes` : `1` a `10080` (J+7)
- notifier un canal ops (webhook/email) :
  - `composer check-log-alerts -- --strict --webhook-url=https://ops.exemple.tld/hooks/log-alerts`
  - `composer check-log-alerts -- --strict --email-to=ops@example.com,astreinte@example.com`
  - mode permanent (meme sans alerte) : `--notify-on=always`
- sequence go-live/J+1/J+7 :
  - voir `docs/v1-go-live-runbook.md`

Seuils par defaut :
- `admin.login.failed >= 10`
- `rate_limited >= 6`
- `http 403 >= 30`
- `http 429 >= 10`

## Regles D Evolution

- toute nouvelle ecriture sensible doit passer par `AppEventLogger`
- ne pas journaliser de mot de passe ni de token CSRF
- preferer des evenements courts et structures
- garder les logs utiles pour l'audit, pas pour du debug front verbeux

## Scheduler systemd (preprod/prod)

Fichiers fournis :

- runner : `backend/tools/check-log-alerts-runner.sh`
- templates units : `backend/tools/systemd/caramagnols-check-log-alerts.{service,timer}.template`
- template env : `backend/tools/systemd/check-log-alerts.env.example`
- installateur : `backend/tools/systemd/install-check-log-alerts-systemd.sh`

Installation type :

```bash
cd /chemin/vers/caramagnols
sudo bash backend/tools/systemd/install-check-log-alerts-systemd.sh --dry-run
sudo bash backend/tools/systemd/install-check-log-alerts-systemd.sh
```

Configuration du canal ops dans `/etc/caramagnols/check-log-alerts.env` :

- webhook : `LOG_ALERTS_WEBHOOK_URL=https://...`
- email : `LOG_ALERTS_EMAIL_TO=ops@example.com,astreinte@example.com`
- mode de notification : `LOG_ALERTS_NOTIFY_ON=alerts|always`

Pilotage admin V2 (sans exposition des secrets) :

- section `Admin > Parametres > Observabilite ops` :
  - `Uniquement en cas d'alerte` (`alerts`)
  - `Toujours notifier` (`always`, utile pour test de reception)
- persistance : `backend/config/site.override.php` (`site.log_alerts.notify_on`)
- les secrets webhook/email restent hors admin dans `/etc/caramagnols/check-log-alerts.env`
- priorite de resolution du mode (`notify_on`) :
  1. option CLI `--notify-on=...`
  2. override admin `site.log_alerts.notify_on`
  3. env systeme `LOG_ALERTS_NOTIFY_ON`

Par defaut, le service accepte les retours `0` et `2` (`--strict` + seuil depasse) comme etats valides (`SuccessExitStatus=0 2`).

## TODO

- raccorder le webhook a la destination monitoring definitive (selon infra cible : Slack/Teams/ELK/Prometheus Alertmanager, etc.)
