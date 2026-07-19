# Logging Applicatif

Date : 2026-03-19

Ce document decrit la journalisation active en V1, avec correlation de requete, rotation locale et scripts d'alerte.

Reference complementaire :
- `../consolidation-lot-d.md`

## Objectif

Disposer d'un logging uniforme, exploitable et non bloquant :
- le logging ne doit jamais casser le front-office ni l'admin
- les canaux sont separes par nature d'evenement
- chaque ligne doit etre corrélable a une requete HTTP
- la croissance des fichiers est encadree par rotation/retention

Mise a jour 2026-04-16 :
- le bloc `backend/src/Logging/*` et ses tests associes sont identifies comme un sous-lot D autonome, a consolider a part du blog, de l'admin et du frontend tooling

Mise a jour 2026-05-29 :
- `check_log_alerts.php` couvre aussi les evenements critiques de l'espace prive : login refuse, CSRF refuse, rate limit prive, email prive echoue, backup echoue, alerte taille backup, purge de suppression compte echouee et cron echouee;
- chaque alerte expose une severite normalisee `warning`, `error` ou `critical`, plus une `overall_severity`;
- l'option CLI `--log-dir` permet de tester une alerte sur un dossier de logs isole sans injecter de donnees factices dans les logs de production;
- les rapports et notifications ops restent des compteurs et des noms de metriques, sans recopier les lignes de log brutes.

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
  - rate limit télémétrie discussion (`blog.discussion.client_telemetry_rate_limited`)
- `content.log`
  - sauvegardes editoriales (menus, pages, blog)
  - télémétrie client discussion blog (`blog.discussion.client_telemetry`)
  - coordination Cron Center (`cron.scheduler.*`, `cron.job.*`)
  - erreurs de validation / persistance
- `access.log`
  - visites front-office GET (`site.visit.page`, `site.visit.not_found`)
  - erreurs HTTP 500 capturees par le front-controller (`site.request.error`)
  - fatals/uncaught exceptions web captures au niveau bootstrap (`site.request.fatal`)
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

Les logs SQL visibles dans `Admin > Logs` sont purges par le job Cron Center `purge_sql_logs` :
- retention standard par defaut : `90` jours
- retention renforcee par defaut : `365` jours pour le canal `security` et les niveaux `warning`/`error`/`critical`/`alert`/`emergency`
- le job est actif par defaut, planifie a `03:40`, et ses arguments peuvent etre ajustes dans `Parametres > Cron Center`

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
- purger les logs SQL visibles dans l'admin :
  - `composer purge-sql-logs -- --dry-run`
  - `composer purge-sql-logs -- --days=90 --keep-sensitive-days=365`
  - plage supportee pour `--days` et `--keep-sensitive-days` : `1` a `3650`
- sequence go-live/J+1/J+7 :
  - voir `docs/deployment/runbook-v1-go-live.md`
- coordonner les jobs planifies SQL depuis le point d'entree OVH :
  - `composer cron-center`
  - `composer cron-center -- --dry-run`
  - `composer cron-center -- --job=publish_scheduled_blog_articles`
  - l'historique SQL conserve les 100 dernieres executions par job

Seuils par defaut :
- `admin.login.failed >= 10`
- `private.login.rejected >= 5`
- `rate_limited >= 6`
- `private.rate_limited >= 3`
- `private.csrf.rejected >= 3`
- `private.*email_failed >= 1`
- `ops.backup.failed` ou `private.backup.failed >= 1`
- `backup_recommended_size_exceeded >= 1`
- `private.account_deletion_backups_purge.failed >= 1`
- `http 403 >= 30`
- `http 429 >= 10`
- `cron.job.failed` ou `cron.scheduler.failed >= 1`

Severites par defaut :

| Metrique | Severite |
|---|---|
| `login_failed`, `private_login_failed`, `rate_limited`, `private_csrf_rejected`, `http_403`, `http_429`, `private_backup_warning` | `warning` |
| `private_rate_limited`, `private_email_failed`, `cron_failed` | `error` |
| `private_backup_failed`, `private_purge_failed` | `critical` |

Options privees utiles :

```bash
composer check-log-alerts -- --json --strict --private-email-failed-threshold=1
composer check-log-alerts -- --json --strict --private-backup-failed-threshold=1
composer check-log-alerts -- --json --strict --private-purge-failed-threshold=1
composer check-log-alerts -- --json --strict --private-csrf-threshold=3 --private-rate-limit-threshold=3
```

## Regles D Evolution

- toute nouvelle ecriture sensible doit passer par `AppEventLogger`
- ne pas journaliser de mot de passe ni de token CSRF
- preferer des evenements courts et structures
- garder les logs utiles pour l'audit, pas pour du debug front verbeux

## Capture Des Erreurs HTTP

- `backend/src/Http/FrontController.php` journalise maintenant les exceptions non gerees sous `site.request.error`
- contexte attendu :
  - `request_id`
  - `method`
  - `uri`
  - `path`
  - `status=500`
  - `referer`
  - `user_agent`
  - `exception`
  - `error`
  - `file`
  - `line`
- le front-controller renvoie ensuite une page `500` minimale avec `X-Request-Id`

Filet de securite supplementaire :
- `backend/core/bootstrap.php` enregistre `site.request.fatal` pour les fatals web et les exceptions non rattrapees qui surviennent avant le logging HTTP nominal
- si `AppEventLogger` n'est pas encore disponible, le bootstrap emet un fallback compact via `error_log`

Limite importante :
- une entree publique legacy qui contourne `backend/public/index.php` et le bootstrap du depot contournera aussi cette observabilite
- en production, toute racine historique ou wrapper legacy doit donc deleguer vers `backend/public/index.php` plutot que conserver sa propre logique

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
- rappel d'une meme alerte persistante : `LOG_ALERTS_REPEAT_NOTIFY_MINUTES=180`
- memoire anti-doublon : `LOG_ALERTS_STATE_FILE=/srv/caramagnols/backend/var/log/check-log-alerts-state.json`

Anti-spam :

- en mode `alerts`, une nouvelle alerte ou une nouvelle metrique en alerte notifie immediatement;
- si le meme ensemble d'alertes reste present a chaque passage du timer, la notification est temporisee jusqu'au delai `LOG_ALERTS_REPEAT_NOTIFY_MINUTES`;
- un retour a l'etat OK efface la memoire, donc une alerte ulterieure renotifie immediatement;
- le mode `always` contourne cette temporisation et doit rester reserve aux tests de reception.

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
