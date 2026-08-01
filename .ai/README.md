# Collaboration multi-IA

Ce dossier organise la transmission d'une tâche entre Mistral Vibe, Codex et
Claude Code. Il est une projection opérationnelle du guide central, jamais une
seconde source normative. Le routeur, le socle essentiel, les niveaux de risque
et la matrice restent dans `guide-architecture/` du dépôt de gouvernance.

Les fichiers `CURRENT_TASK*.md` sont locaux et temporaires. Le dépôt de
gouvernance les ignore tous ; chaque projet consommateur doit fournir une règle
équivalente dans son propre `.gitignore` et la vérifier avant de conserver une
transmission suffixée. L'installateur du workflow ne modifie pas les règles Git
des projets. Seul `CURRENT_TASK.md` est le nom canonique actif et lu
automatiquement par les scripts. Un fichier suffixé peut conserver une tâche
terminée, bloquée ou en attente, mais deux tâches ne sont jamais actives dans le
même worktree ; un travail réellement parallèle utilise une branche et un
worktree distincts. Les sept documents listés dans
`config/ai-workflow-files.txt` sont les sources canoniques déployées dans les
projets : le présent README, la procédure d'archivage, le modèle de tâche et les
quatre prompts.

## Rôles

- Mistral Vibe Pro reçoit la demande, fait un inventaire court, choisit le
  routage et peut exécuter seul les tâches A.
- Codex vérifie l'analyse, implémente les niveaux B et C, adapte les tests,
  valide et examine le diff.
- Claude Code analyse en lecture seule l'architecture, la sécurité, les flux de
  données et les cas limites ; il peut assurer une revue indépendante.

## Deux classifications

- Le routage `A/B/C` organise les agents : A simple, B implémentation non
  triviale, C analyse préalable pour sécurité, données, migration, production
  ou risque de perte.
- Le niveau `R0/R1/R2/R3` détermine les portes et preuves. Il est indépendant du
  routage et suit `core/13-risque-preuves-derogations.md`.

Une tâche documentaire peut être `B/R0` ; une seule ligne de configuration de
production peut être `C/R3`. Le fichier de tâche justifie les deux décisions.

## Règle d'auteur unique

Le champ `Agent autorisé à modifier` de `CURRENT_TASK.md` fait foi. Pour A,
Mistral peut écrire. Pour B et C, Codex écrit le code et les autres agents
restent en lecture seule. Si un outil nommé est indisponible, le responsable
humain peut désigner un remplaçant sans cumuler auteur et revue indépendante.
Les mises à jour du fichier de transmission ne valent pas autorisation de
modifier le code.

Avant toute modification, lire `AGENTS.md`, `CURRENT_TASK.md` et
`git status --short`. Ne jamais écraser les changements existants. Des branches
ou worktrees distincts sont obligatoires pour un futur travail parallèle.

## Sécurité, validation et archivage

Ne jamais placer de secrets, données personnelles, dumps, uploads, caches,
logs, sauvegardes ou chemins sensibles dans ce dossier. Les sujets production,
authentification, permissions, jetons et données sont au minimum routés C ; le
niveau de risque est évalué séparément. Aucun commit, push, déploiement ou
écriture distante sans autorisation explicite.

Exécuter uniquement les tests, lints, formats et builds vérifiés dans les
manifestes ou la documentation du projet. Chaque contrôle prend l'état
`réussi`, `échoué`, `impossible`, `absent` ou `non applicable`. Pour `R2` et
`R3`, la revue finale doit être indépendante ; pour `R3`, l'acceptation humaine
et le rollback requis restent bloquants.

Avant de déclarer une tâche `Terminé`, appliquer obligatoirement
[`ARCHIVAGE_DOCUMENTS_SOURCE.md`](ARCHIVAGE_DOCUMENTS_SOURCE.md) et consigner le
résultat dans la transmission. Un archivage vérifié est `réussi`; l'absence
justifiée de source ou preuve à conserver est `non applicable`; une conservation
requise sans cible sûre reste bloquante. L'archivage n'est jamais automatique.
