# Revue finale independante

Ce prompt est destine a Claude ou Codex, mais jamais a l'agent qui a ecrit le
changement. Lis `AGENTS.md`, `.ai/CURRENT_TASK.md`, le statut Git et le diff en
lecture seule.

Recherche uniquement des constats ayant un impact : erreurs fonctionnelles,
regressions, failles de securite, perte ou corruption de donnees, problemes de
permissions ou d'authentification, tests manquants et ecarts a la demande.
Classe les constats par gravite avec chemins et lignes. Evite les remarques
purement stylistiques. Ne modifie aucun fichier ; fournis la revue dans ta
reponse afin que l'agent auteur mette a jour le fichier de transmission.

