# Tâche en cours

## Demande originale


## Projet et périmètre


## Niveau de routage

A, B ou C - non déterminé

## Rôles et autorisations

| Rôle | Identifiant local attribué | État | Périmètre ou contrainte |
|---|---|---|---|
| Routeur | non attribué | absent | cadrage et transmission |
| Architecte | non attribué | absent | lecture seule |
| Auteur ou implémentateur | non attribué | absent | unique rôle autorisé à modifier et à livrer selon autorisations |
| Vérificateur | non attribué | absent | contrôles documentés |
| Relecteur indépendant | non attribué | absent | distinct de l'auteur pour `R2/R3` |
| Décideur humain | non attribué | absent | décisions réservées |

L'identifiant local désigne une session, un compte ou une personne. Un relais
remplace uniquement cet identifiant et conserve le rôle, le périmètre, l'état
Git et les preuves. Le fournisseur ou l'abonnement n'est jamais normatif.

- Phase active : `routage`, `architecture`, `implementation`, `verification`,
  `revue_independante`, `decision_humaine` ou `cloture` — non déterminée.
- Prompt actif : chemin canonique `.ai/prompts/*.md` — non attribué.
- Identifiant local actif : non attribué.
- Passage de relais entrant : absent.
- Périmètre d'écriture de la phase : aucun par défaut.

Un rôle n'est activé que par la concordance de ces champs, de son attribution
dans la table et d'une instruction courante référençant son prompt canonique.
Le nom d'un fournisseur, d'un modèle ou d'un agent, une mention narrative ou la
simple lecture d'un prompt ne valent jamais activation. Une invocation ne passe
pas implicitement au rôle suivant.

- Clôture Git : portée uniquement par l'auteur ou implémentateur après réussite
  des portes, selon la spécialisation globale du rôle.
- Dépôt, branche et upstream de clôture : à vérifier avant action.
- Environnement et cible de déploiement autorisés : aucun par défaut.
- Actions destructives ou de production autorisées : aucune par défaut.

## Contraintes et exclusions


## Inventaire du routeur


## Analyse de l'architecte


## Plan d'implémentation validé


## Résultat de l'auteur ou implémentateur


## Tests et validations


## Revue finale indépendante

- Identifiant local du relecteur :
- Indépendance vérifiée par rapport à l'auteur : absent.
- Constats :
- Corrections de l'auteur :
- Avis : en attente.


## État

À analyser

États autorisés : `À analyser`, `Planifié`, `En cours`, `À revoir`, `Terminé`, `Bloqué`.

## État Terminé

lorsque terminé archiver les documents sources selon .ai\ARCHIVAGE_DOCUMENTS_SOURCE.md

## Git et production

Après validation et archivage, l'auteur ou implémentateur applique la clôture
Git autorisée par la spécialisation globale du rôle, dépôt par dépôt. Il ne
déploie que si la tâche nomme explicitement le périmètre, l'environnement et la
cible ; l'archivage ne crée aucune cible.
