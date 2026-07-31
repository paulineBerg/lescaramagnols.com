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
- Acces SSH OVH depuis ce poste : utiliser l'alias local `ovh-boutique`, par exemple `ssh ovh-boutique`.
- L'alias `ovh-boutique` est gere hors depot dans la configuration SSH locale (`~/.ssh/config`) ; ne jamais versionner cle privee, mot de passe, host complet non public ou secret d'acces.
- Chemin backend production OVH : `/home/lescaramgl-ssh/caramagnols/backend`.
- Commande de diagnostic distante typique : `ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols/backend && php core/tools/check_vite_assets.php --public-root=public"`.
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

## Stockage Runtime Privé

- Depuis la migration du 2026-07-18, le stockage runtime privé de production est séparé physiquement du code :
  - code : `/home/lescaramgl-ssh/caramagnols/backend/` ;
  - runtime : `/home/lescaramgl-ssh/caramagnols-runtime/private-storage/`.
- Le dossier `caramagnols-runtime/` est volontaire : il protège les documents utilisateurs des déploiements, nettoyages, rsync et opérations Git sur le backend.
- `PRIVATE_STORAGE_ROOT` doit pointer vers `/home/lescaramgl-ssh/caramagnols-runtime/private-storage` en production.
- L'ancien chemin `/home/lescaramgl-ssh/caramagnols/backend/private/storage/` est conservé uniquement comme transition/rollback tant qu'une procédure de rollback validée ne demande pas explicitement son archivage.
- Ne jamais recréer, déplacer, supprimer, archiver ou synchroniser `caramagnols-runtime/private-storage/**` sans suivre la politique `backend/docs/STORAGE_RUNTIME_POLICY.md` et, pour l'historique de migration, le runbook archivé `backend/docs/archive/2026-07-storage/RUNBOOK_STORAGE_MIGRATION.md`.
- La production OVH est l'unique source de vérité pour ces données (invariant absolu).
- `backend/private/storage/**` et tout export/copie locale de données runtime sont exclus du versionnage Git et ne doivent JAMAIS être déployés vers la production.
- Les scripts de déploiement (`deploy-release.sh`, `deploy-fast.sh`) doivent rester conçus pour ne jamais supprimer ni remplacer les fichiers runtime distants.
- Les dossiers hexadécimaux (ex: `01/`, `0b/36/`) sont légitimes : sharding SHA-256 à 2 niveaux pour répartition des fichiers.
- Permissions attendues sur le runtime OVH : dossiers `770`, fichiers `660`, sans accès `others`. Sur OVH, le groupe effectif peut être `users` si `chown ...:www-data` est refusé.
- Voir `backend/docs/STORAGE_RUNTIME_POLICY.md` pour les détails complets et `backend/docs/archive/2026-07-storage/RUNBOOK_STORAGE_MIGRATION.md` pour l'historique de migration et les checklists.
- **Règle critique** : Local -> Production = CODE SEULEMENT, aucune donnée SQL, aucun upload, aucun document runtime.

<!-- BEGIN MANAGED MULTI-AI WORKFLOW -->
## Workflow multi-IA

- Lire `.ai/README.md`, `.ai/CURRENT_TASK.md`, les règles applicables et l'état Git avant toute intervention.
- Classer séparément le routage `A/B/C` des agents et le risque `R0/R1/R2/R3` des contrôles ; justifier les deux.
- Le fichier `.ai/CURRENT_TASK.md` nomme un seul auteur et les éventuels relecteur indépendant ou décideur humain.
- Pour A, Mistral peut être l'auteur. Pour B et C, Codex est l'auteur par défaut ; Claude intervient en lecture seule.
- Deux agents ne modifient jamais simultanément le même worktree. Pour `R2` et `R3`, l'auteur ne réalise pas sa revue indépendante.
- Préserver les changements existants et n'exécuter que les validations réellement disponibles.
- Étiqueter chaque contrôle `réussi`, `échoué`, `impossible`, `absent` ou `non applicable` ; ne jamais inventer une preuve.
- Ne placer aucun secret, donnée personnelle, dump, log ou contenu sensible dans les prompts ou rapports.
- Aucun commit, push, déploiement, accès production, migration, import/export ou synchronisation sans autorisation applicable.
- Les détails opératoires sont dans `.ai/` ; les règles normatives restent dans le guide central.
<!-- END MANAGED MULTI-AI WORKFLOW -->
