# Tâche en cours

## Identité

- ID :
- Demande originale :
- Projet et périmètre :
- Hors périmètre :

## Sources de vérité et état initial

- `AGENTS.md` et guides chargés :
- Horodatage de début (ISO 8601 avec fuseau) :
- Branche :
- Référence du commit initial :
- État Git initial (`git status --short`) :
- Modifications préexistantes à préserver :
- Propriétaire du comportement :

## Classifications

- Routage multi-IA : `A`, `B` ou `C` — non déterminé.
- Niveau de risque : `R0`, `R1`, `R2` ou `R3` — non déterminé.
- Déclencheurs et justification :

## Rôles et autorisations

| Rôle | Identifiant local attribué | État | Périmètre ou contrainte |
|---|---|---|---|
| Routeur | non attribué | absent | cadrage et transmission |
| Architecte | non attribué | absent | lecture seule |
| Auteur ou implémentateur | non attribué | absent | unique rôle autorisé à modifier et à livrer selon autorisations |
| Vérificateur | non attribué | absent | contrôles documentés |
| Relecteur indépendant | non attribué | absent | distinct de l'auteur pour `R2/R3` |
| Décideur humain | non attribué | absent | décisions réservées |

L'identifiant local désigne une session, un compte ou une personne sans rendre
son fournisseur normatif. Changer d'outil consiste à remplacer cet identifiant
et à consigner le relais ; cela ne modifie ni le rôle ni ses permissions.

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

- Relecteur indépendant requis : oui/non — non déterminé.
- Décision ou approbation humaine requise :
- Clôture Git : portée uniquement par l'auteur ou implémentateur après réussite
  des portes, selon la spécialisation globale du rôle.
- Dépôt, branche et upstream de clôture : à vérifier avant action.
- Environnement et cible de déploiement autorisés : aucun par défaut.
- Actions destructives ou de production autorisées : aucune par défaut.

## Données, dépendances et rollback

- Données et classification :
- Dépendances ou services externes :
- Sauvegarde ou retour arrière :
- Critères d'arrêt :

## Critères d'acceptation

1.

## Inventaire du routeur


## Analyse de l'architecte


## Plan d'implémentation validé


## Résultat de l'auteur ou implémentateur


## Validations et preuves

| Porte | Contrôle ou commande | État | Preuve ou résultat synthétique |
|---|---|---|---|
| G0 | | absent | |
| G1 | | absent | |
| G2 | | absent | |
| G3 | | absent | |
| G4 | | absent | |
| G5 | | absent | |

États autorisés : `réussi`, `échoué`, `impossible`, `absent`, `non applicable`.

## Décisions, dérogations et dette

- Décisions :
- Dérogations :
- Dette ou risques résiduels :

### Questions soumises au décideur humain

| ID | Question bornée | Réponses possibles et impacts | Recommandation justifiée | Porte bloquée | Réponse, rôle et date |
|---|---|---|---|---|---|
| | | | | | En attente |

Une absence de réponse ou une option par défaut ne vaut pas décision.

## Revue finale indépendante

- Identifiant local du relecteur :
- Indépendance vérifiée par rapport à l'auteur : absent.
- Constats :
- Corrections de l'auteur :
- Avis : en attente.

## Archivage de clôture

- État : absent.
- Sources ou preuves à conserver :
- Cible, durée et niveau de confidentialité :
- Contrôle de la copie et procédure de restauration :

## État

À analyser

États autorisés : `À analyser`, `Planifié`, `En cours`, `À revoir`, `Terminé`, `Bloqué`.

## Archiver

Lorsque l'état est `Terminé`, appliquer l'archivage selon
`.ai/ARCHIVAGE_DOCUMENTS_SOURCE.md` et consigner son résultat.
Une transmission terminée est ensuite déplacée sous
`.ai/archive/transmissions/YYYY/MM/` en conservant son nom suffixé.

## Git et production

Après un archivage réussi, l'auteur ou implémentateur évalue puis applique la
clôture Git autorisée par la spécialisation globale du rôle. Il traite chaque
dépôt séparément et vérifie branche, upstream, diff et portes avant nettoyage,
commit ou push. Il ne déploie que si la transmission nomme explicitement le
périmètre, l'environnement et la cible ; l'archivage ne crée aucune cible.
