# Regles projet - Les Caramagnols

Ce fichier complete les regles communes de `~/www/AGENTS.md` pour ce depot.

## Source de verite

- La production `https://www.lescaramagnols.com/` est la version maitre du projet.
- En cas d'ecart entre le depot local, la documentation et la production, considerer la production comme reference fonctionnelle et editoriale.
- Avant toute evolution qui touche les routes, menus, contenus publics, assets visibles, securite HTTP ou espace admin, comparer avec la production et signaler les ecarts connus.
- Ne pas remplacer un comportement de production observe par une hypothese locale sans verification.

## Synchro

- workspace sous \\wsl.localhost\Ubuntu\home\surfacepro8\Workspace
- repos sous \\wsl.localhost\Ubuntu\home\surfacepro8\www\repos
- Scripts globaux de gestion workspace/repos sous `/home/surfacepro8/Workspace/dev-tools/`.
- Pour retrouver les scripts disponibles, utiliser d'abord :
  - `rg --files -g '*.sh' -g 'Makefile' -g 'composer.json' -g 'package.json'`
  - `find /home/surfacepro8/Workspace/dev-tools -maxdepth 3 -type f -name '*.sh' -print`
  - `composer --working-dir=backend run-script --list`
  - `npm --prefix frontend run`
- Scripts dev-tools utiles :
  - inventaire et clone des repos GitHub personnels : `/home/surfacepro8/Workspace/dev-tools/scripts/sync-github.sh` (necessite `gh auth login`, ne pousse pas de commits) ;
  - etat de tous les repos du workspace : `/home/surfacepro8/Workspace/dev-tools/scripts/status-all.sh` ;
  - fetch global : `/home/surfacepro8/Workspace/dev-tools/scripts/fetch-all.sh` ;
  - mise a jour globale : `/home/surfacepro8/Workspace/dev-tools/scripts/update-all.sh` ;
  - reconstruction du workspace VS Code : `/home/surfacepro8/Workspace/dev-tools/scripts/rebuild-workspace.sh` ;
  - generation fichiers VS Code : `/home/surfacepro8/Workspace/dev-tools/scripts/generate-vscode.sh` ;
  - sauvegarde projets/workspace : `/home/surfacepro8/Workspace/dev-tools/scripts/backup-projects.sh`.
- Ne jamais faire de commit, push GitHub, update global, sauvegarde, deploy prod ou ecriture SQL distante sans demande explicite et verification prealable de `git status`.
- Pour pousser vers GitHub, verifier d'abord s'il existe un workflow ou une consigne locale (`README.md`, `.github/`, `Makefile`, scripts du depot). A defaut, utiliser les commandes Git standards apres confirmation explicite ; les scripts `dev-tools` ne font pas de push automatique.

## Espace admin

- La route canonique admin de production est `/espace-admin-7k9m2p`.
- Ne pas tenter de contourner l'authentification admin, le 2FA, les tokens CSRF ou les protections de session.
- Toute reconstruction locale de l'admin doit conserver au minimum les controles de securite observes en production : session securisee, CSRF, 2FA et en-tetes de securite.

## Espace prive

- La route canonique de production pour l'espace prive est `/espace-private-4h6F1c`.
- Ne pas exposer l'espace prive via `/private` en production ; ce chemin doit rester non fonctionnel publiquement.
- Toute modification de `PRIVATE_PORTAL_BASE_PATH` doit etre testee en local puis sur production avec au minimum :
  - `/espace-private-4h6F1c/login` retourne le formulaire prive ;
  - `/private/login` ne retourne pas le formulaire prive ;
  - `/espace-admin-7k9m2p` reste fonctionnel.

## Architecture privee

- `backend/src/PrivatePortal` est reserve au socle de l'espace prive : HTTP, routes, securite, sessions, utilisateurs, permissions et operations transverses.
- Les modules applicatifs prives doivent vivre sous `backend/src/PrivateApps`.
- Ne pas ajouter de nouvelle logique metier applicative dans `PrivatePortal` sans justification explicite ; preferer un module dedie dans `PrivateApps`.
- Regles detaillees et architecture des modules : `backend/src/PrivateApps/AGENTS.md` et `backend/src/PrivateApps/README.md`.

## Deploiement

- La preprod est abandonnee depuis le 2026-07-17 : `prod` est la seule cible de deploiement maintenue.
- `backend/private/` est synchronise vers la production par `deploy-release.sh` en mode additif uniquement (rsync sans `--delete`) : un deploy ne doit jamais supprimer ni ecraser les fichiers runtime prives distants.
- Scripts de deploiement prod a chercher sous `backend/tools/` :
  - deploy complet : `backend/tools/deploy-release.sh` ;
  - deploy rapide des changements backend : `backend/tools/deploy-fast.sh` ;
  - synchronisation du front publie : `backend/tools/sync-published-frontend-tree.sh` ;
  - synchronisation des uploads editoriaux runtime : `backend/tools/sync-editorial-uploads.sh`.
- Les scripts de deploy exigent `REMOTE_HOST` et `REMOTE_BACKEND` ; preferer un `--dry-run` quand il existe avant toute ecriture distante.
- Le deploy synchronise le schema SQL attendu par le code sauf option explicite `--no-schema-sync`.
- Avant deploy prod : verifier `git status`, executer les tests/lints pertinents, verifier les assets publies, puis controler immediatement la prod apres deploy.

## SQL et contenu editorial

- Source SQL editoriale : migrations dans `backend/sql/editorial/`, tables privees dans `backend/sql/private/`.
- Commandes Composer a retrouver via `composer --working-dir=backend run-script --list`.
- Commandes courantes :
  - import editorial JSON vers SQL : `composer --working-dir=backend editorial-import-sql` ;
  - import blog JSON vers SQL : `composer --working-dir=backend blog-import-sql` ;
  - synchronisation schema deploy : `composer --working-dir=backend deploy-schema-sync` ;
  - backup/restore editorial : `php backend/core/tools/editorial_backup_restore.php backup|restore ...`.
- Push du contenu editorial SQL local vers OVH prod : `backend/tools/push-local-sql-to-ovh.sh --live`.
- Ce push SQL lit par defaut les variables depuis `$HOME/.caramagnols/ops/caramagnols-ops.env` via `CARAMAGNOLS_OPS_ENV_FILE`; ne jamais versionner ce fichier ni afficher ses secrets.
- Avant toute ecriture SQL distante : faire un backup, lire le diff local/prod, verifier les suppressions, et n'utiliser `--allow-delete` ou `--include-discussions` que sur demande explicite.

## Synchronisation

- Lors d'une remise en coherence, inventorier d'abord les routes et contenus visibles en production.
- Preferer des changements progressifs et verifies par comparaison locale/prod.
- Ne jamais inventer de secrets de production dans le depot ; utiliser des exemples ou variables d'environnement locales.
