# Ops Sync

Outillage local pour centraliser les acces OVH de `caramagnols` sans toucher aux scripts de deploiement existants dans `backend/tools/`.

## Ce qui existe deja

Le projet dispose deja de :

- `backend/tools/deploy-fast.sh`
- `backend/tools/deploy-release.sh`

Ces scripts savent pousser le backend vers OVH, mais demandent de renseigner manuellement les variables `REMOTE_HOST` et `REMOTE_BACKEND`, et ne couvrent pas l'acces SQL direct.

## Ce que fait `.ops-sync`

- centralise la configuration OVH dans `config/ovh.env.local`
- fournit un point d'entree SSH vers OVH
- fournit un point d'entree MySQL vers la base OVH
- permet de dumper la base OVH en local
- encapsule les scripts de deploiement backend existants
- expose une commande de purge du cache runtime distant

## Configuration

Le fichier local actif est :

```bash
/home/surfacepro8/www/caramagnols/.ops-sync/config/ovh.env.local
```

Il est ignore par Git. Une copie d'exemple est fournie dans :

```bash
/home/surfacepro8/www/caramagnols/.ops-sync/config/ovh.env.local.example
```

Par defaut, les scripts SQL lisent les identifiants de base depuis :

```bash
/home/surfacepro8/www/caramagnols/backend/.env
```

Si tu veux utiliser un autre fichier d'environnement, modifie `LOCAL_BACKEND_ENV_FILE` dans `ovh.env.local`.

## Commandes

Verifier la config, SSH et SQL :

```bash
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/status-sync.sh
```

Ouvrir un shell SSH OVH :

```bash
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/ssh-ovh.sh
```

Executer une commande distante :

```bash
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/ssh-ovh.sh "cd /home/lescaramgl-ssh/caramagnols/backend && ls -la"
```

Ouvrir un client MySQL interactif sur la base OVH :

```bash
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/sql-ovh.sh
```

Lancer une requete SQL :

```bash
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/sql-ovh.sh --query="SHOW TABLES"
```

Sauvegarder la base OVH en local :

```bash
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/dump-db-ovh.sh
```

Importer la base OVH dans la base locale configuree comme dans le workflow Prestashop :

```bash
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/pull-caramagnols-db.sh
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/pull-caramagnols-db.sh --live
```

Pour eviter de ressaisir le mot de passe SQL local a chaque execution :

```bash
mysql_config_editor set --login-path=caramagnols-local --host=127.0.0.1 --port=3306 --user=root --password
```

Puis dans `config/ovh.env.local` :

```bash
LOCAL_DB_LOGIN_PATH="caramagnols-local"
```

Importer la base OVH dans une base locale explicite :

```bash
LOCAL_DB_PASSWORD='motdepasse_local' \
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/import-ovh-db-to-local.sh \
  --local-db=caramagnols \
  --local-user=caramagnols
```

Deploy backend rapide :

```bash
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/push-backend-fast.sh --dry-run
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/push-backend-fast.sh
```

Deploy backend complet :

```bash
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/push-backend-release.sh --dry-run
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/push-backend-release.sh
```

Purger le cache runtime OVH :

```bash
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/clear-ovh-cache.sh
bash /home/surfacepro8/www/caramagnols/.ops-sync/bin/clear-ovh-cache.sh --live
```

## Notes

- `status-sync.sh` est volontairement en lecture seule.
- `clear-ovh-cache.sh` est en dry-run par defaut.
- Les dumps SQL sont stockes dans `.ops-sync/backups/`.
- Les logs sont stockes dans `.ops-sync/logs/`.
