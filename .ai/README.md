# Collaboration multi-IA

Ce dossier organise la transmission d'une tache entre Mistral Vibe, Codex et
Claude Code. `CURRENT_TASK.md` est local, temporaire et ignore par Git. Les
autres fichiers sont destines a etre versionnes.

## Roles

- Mistral Vibe Pro : recoit la demande, fait un inventaire court, choisit le
  niveau et execute seul les taches A.
- Codex : verifie l'analyse, implemente les niveaux B et C, adapte les tests,
  valide et examine le diff.
- Claude Code : analyse en lecture seule l'architecture, la securite, les flux
  de donnees et les cas limites ; il peut revoir un changement critique.

## Routage

- Niveau A : documentation, texte, CSS simple, inventaire, recherche, petite
  correction ou test simple, en principe sur trois fichiers au maximum.
- Niveau B : fonctionnalite, bug non trivial, plusieurs fichiers, interface
  d'administration, refactoring ou tests importants.
- Niveau C : authentification, jetons, permissions, securite, RGPD, migration,
  import/export, base de donnees, synchronisation, production ou risque de
  perte de donnees.

## Regle d'auteur unique

Le champ `Agent autorise a modifier` de `CURRENT_TASK.md` fait foi. Pour A,
Mistral peut ecrire. Pour B et C, Codex ecrit le code et les autres agents
restent en lecture seule. Les mises a jour du fichier de transmission ne valent
pas autorisation de modifier le code.

Avant toute modification, lire `AGENTS.md`, `CURRENT_TASK.md` et
`git status --short`. Ne jamais ecraser les changements existants. Les branches
ou worktrees distincts sont obligatoires pour un futur travail parallele.

## Securite et validation

Ne jamais placer de secrets, donnees personnelles, dumps, uploads, caches,
logs, sauvegardes ou chemins sensibles dans ce dossier. Les sujets production,
authentification, permissions, jetons et donnees sont automatiquement de niveau
C. Aucun commit, push, deploiement ou ecriture distante sans autorisation
explicite.

Executer uniquement les tests, lints, formats et builds verifies dans les
manifestes ou la documentation du projet. Si aucune commande n'est confirmee,
indiquer `non determinee` dans `CURRENT_TASK.md`.

