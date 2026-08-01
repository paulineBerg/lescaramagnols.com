# Règles du projet Les Caramagnols

- La production est la source de vérité fonctionnelle et éditoriale ; le local
  sert au développement et aux tests.
- Toute tâche concernant la production, l'authentification, les permissions,
  les jetons, le RGPD, SQL, les migrations ou les flux de données est de niveau C.
- Un dry run doit rester sans écriture et sans nettoyage destructif.
- Ne jamais recopier automatiquement des données locales en production.
- Ne jamais versionner ni synchroniser comme du code les uploads, stockages
  privés, caches, journaux, sauvegardes, dumps, secrets ou données générées.
- Toute suppression ou tout nettoyage requiert une validation explicite.

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
- Avant l'état `Terminé`, appliquer `.ai/ARCHIVAGE_DOCUMENTS_SOURCE.md` et consigner un archivage `réussi`, `non applicable` ou bloquant ; aucun déplacement n'est automatique.
- Les détails opératoires sont dans `.ai/` ; les règles normatives restent dans le guide central.
<!-- END MANAGED MULTI-AI WORKFLOW -->
