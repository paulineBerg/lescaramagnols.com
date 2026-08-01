# Collaboration multi-agent indépendante des fournisseurs

Ce dossier projette le guide central dans un workflow opératoire. Il décrit des
rôles et des séparations d'autorité, jamais une marque obligatoire. Un outil
local peut être associé à un rôle dans `config/ai-role-bindings.local.json`,
fichier ignoré et non distribué. Le routeur, le socle, le risque et la matrice
restent les seules sources normatives.

Les fichiers `CURRENT_TASK*.md` sont locaux, temporaires et ignorés. Seul
`CURRENT_TASK.md` peut être actif dans un worktree. Un fichier suffixé ne peut
conserver qu'une tâche terminée, bloquée ou en attente ; un travail parallèle
utilise une branche et un worktree distincts.

## Rôles

- Le **routeur** inventorie, classe `A/B/C`, propose le risque `R0`–`R3`, nomme
  les rôles et ouvre la transmission. Il n'accorde aucune permission externe.
- L'**architecte** analyse en lecture seule les frontières, risques, données,
  options et critères. Il ne modifie pas le comportement évalué.
- L'**auteur ou implémentateur** est l'unique rôle autorisé à modifier le
  worktree dans le périmètre déclaré et produit les preuves de ses contrôles.
- Le **vérificateur** exécute les contrôles documentés et rapporte leurs états ;
  il peut être l'auteur pour `R0/R1`, mais cela ne constitue pas une revue indépendante.
- Le **relecteur indépendant** est distinct de l'auteur, travaille en lecture
  seule et donne l'avis requis pour `R2/R3`.
- Le **décideur humain** accepte les critères métier, dérogations, risques
  juridiques et actions `R3` réservées à l'humain.

Un même outil peut remplir plusieurs rôles non incompatibles, mais aucune
configuration ne peut fusionner auteur et relecteur indépendant pour `R2/R3`,
ni déléguer au logiciel une décision humaine.

## Routage et risque

- `A` : tâche bornée réalisable par auteur/vérificateur unique si le risque ne
  requiert pas d'indépendance.
- `B` : implémentation non triviale avec auteur identifié et vérification explicite.
- `C` : analyse d'architecture préalable, puis implémentation et vérification ;
  le niveau de risque décide de la revue indépendante et de l'approbation humaine.

Le routage organise le travail. Le risque `R0`–`R3` impose portes et preuves.
Une tâche documentaire peut être `B/R0`; une ligne de production peut être
`C/R3`.

## Autorité, relais et escalade

`CURRENT_TASK.md` nomme un seul auteur. Tout passage de relais consigne rôle,
périmètre, état Git, hypothèses, changements et preuves. Un rôle suspend son
action et escalade en cas de contradiction, cible ambiguë, élargissement de
périmètre, secret, donnée personnelle non maîtrisée ou permission manquante.

Aucun rôle ne peut s'attribuer commit, push, déploiement, production, migration,
import/export, destruction, approbation humaine ou indépendance. Une analyse
n'autorise pas une modification. Un contrôle non exécuté reste `absent` ou
`impossible`, jamais `réussi`.

## Validation et archivage

Utiliser seulement `réussi`, `échoué`, `impossible`, `absent` et
`non applicable`. Pour `R2/R3`, la revue indépendante est bloquante ; pour
`R3`, l'acceptation humaine et le rollback vérifié le sont également. Avant
`Terminé`, appliquer `ARCHIVAGE_DOCUMENTS_SOURCE.md` et consigner `réussi`,
`non applicable` ou le blocage. Aucun archivage n'est automatique.

## Compatibilité des anciens prompts

Les anciens noms de fichiers comportant une marque restent des redirections de
migration. Les nouveaux points d'entrée sont `ROUTER.md`, `ARCHITECT.md`,
`IMPLEMENTER.md`, `VERIFIER.md`, `INDEPENDENT_REVIEWER.md` et
`HUMAN_DECIDER.md`. Une personnalisation locale peut conserver un ancien nom,
mais son autorité reste celle du rôle correspondant.
