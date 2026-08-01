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
- Classer séparément le routage `A/B/C` et le risque `R0/R1/R2/R3` ; justifier les deux.
- Attribuer explicitement les rôles utiles : routeur, architecte, auteur/implémentateur, vérificateur, relecteur indépendant et décideur humain.
- `.ai/CURRENT_TASK.md` nomme un seul auteur ; l'outil associé à chaque rôle reste une configuration locale non normative.
- Deux rôles ne modifient jamais simultanément le même worktree. Pour `R2/R3`, auteur et relecteur indépendant sont distincts.
- Aucun agent ne s'attribue une approbation humaine, une revue indépendante, une permission externe ou une preuve non obtenue.
- Préserver les changements existants et n'exécuter que les validations réellement documentées.
- Étiqueter chaque contrôle `réussi`, `échoué`, `impossible`, `absent` ou `non applicable`.
- Ne placer aucun secret, donnée personnelle, dump, log ou contenu sensible dans les prompts ou rapports.
- Aucun commit, push, déploiement, production, migration, transfert ou destruction sans autorisation applicable.
- Avant `Terminé`, appliquer `.ai/ARCHIVAGE_DOCUMENTS_SOURCE.md` et consigner le résultat ; aucun déplacement n'est automatique.
- Les détails opératoires sont dans `.ai/` ; les règles normatives restent dans le guide central.
<!-- END MANAGED MULTI-AI WORKFLOW -->
