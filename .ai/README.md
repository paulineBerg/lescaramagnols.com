# Collaboration multi-agent indépendante des fournisseurs

Ce dossier projette le guide central dans un workflow opératoire. Il décrit des
rôles et des séparations d'autorité, jamais une marque obligatoire. Un outil
local peut être associé à un rôle dans `config/ai-role-bindings.local.json`,
fichier ignoré et non distribué. Cette configuration utilise des identifiants
locaux opaques : un abonnement, une marque ou un modèle n'entre jamais dans le
contrat de travail versionné. Le routeur, le socle, le risque et la matrice
restent les seules sources normatives.

Les règles communes sont modifiées uniquement dans `pauline-ai-governance`.
Une copie projet de ce dossier est une projection gérée : elle ne peut ni
remplacer le guide central, ni créer une autorisation locale implicite.

Les fichiers `CURRENT_TASK*.md` sont locaux, temporaires et ignorés. Seul
`CURRENT_TASK.md` peut être actif dans un worktree. Un fichier suffixé ne peut
conserver qu'une tâche terminée, bloquée ou en attente ; un travail parallèle
utilise une branche et un worktree distincts.

Une transmission `Terminé` est déplacée sous
`.ai/archive/transmissions/YYYY/MM/` après application de
`ARCHIVAGE_DOCUMENTS_SOURCE.md`. Les transmissions non terminées restent à la
racine `.ai/` pour rester découvrables. Les archives locales ne sont ni
distribuées ni versionnées.

## Rôles

- Le **routeur** inventorie, classe `A/B/C`, propose le risque `R0`–`R3`, nomme
  les rôles et ouvre la transmission. Il n'accorde aucune permission externe.
- L'**architecte** analyse en lecture seule les frontières, risques, données,
  options et critères. Il ne modifie pas le comportement évalué.
- L'**auteur ou implémentateur** est l'unique rôle autorisé à modifier le
  worktree dans le périmètre déclaré et produit les preuves de ses contrôles.
  Il porte aussi les actions Git et le déploiement lorsque la tâche en autorise
  explicitement la cible et que les portes requises sont réussies.
- Le **vérificateur** exécute les contrôles documentés et rapporte leurs états ;
  il peut être l'auteur pour `R0/R1`, mais cela ne constitue pas une revue indépendante.
- Le **relecteur indépendant** est distinct de l'auteur, travaille en lecture
  seule et donne l'avis requis pour `R2/R3`.
- Le **décideur humain** accepte les critères métier, dérogations, risques
  juridiques et actions `R3` réservées à l'humain. La sollicitation lui soumet
  des questions bornées, les réponses possibles et leurs impacts selon
  [`core/13-risque-preuves-derogations.md`](../guide-architecture/core/13-risque-preuves-derogations.md) ;
  aucune option par défaut ne vaut acceptation.

Un même outil peut remplir plusieurs rôles non incompatibles, mais aucune
configuration ne peut fusionner auteur et relecteur indépendant pour `R2/R3`,
ni déléguer au logiciel une décision humaine.

## Attribution et remplacement d'un agent

Chaque transmission attribue les rôles avec un identifiant local de session,
de compte ou de personne. Si un outil devient indisponible, remplacer seulement
cet identifiant, consigner le relais et vérifier l'indépendance requise. Aucun
prompt, modèle de tâche ou règle versionnée n'est renommé selon le fournisseur.

Les seuls prompts canoniques sont `ROUTER.md`, `ARCHITECT.md`,
`IMPLEMENTER.md`, `VERIFIER.md`, `INDEPENDENT_REVIEWER.md` et
`HUMAN_DECIDER.md`. Un prompt local spécialisé conserve l'un de ces noms et
ajoute le contexte du projet sans attribuer le rôle à une marque.

## Activation et transition des rôles

Le nom d'un fournisseur, d'un modèle, d'un agent ou d'un abonnement n'active
jamais un rôle. Une mention narrative d'un rôle ou la simple lecture de son
prompt n'accorde pas davantage d'autorité. Hors amorçage du routeur, un rôle
n'agit que si la transmission active réunit son identifiant local, sa phase, la
référence exacte de son prompt canonique et un périmètre compatible. Une donnée
manquante ou contradictoire maintient le rôle en lecture seule et provoque
l'arrêt après signalement.

Une invocation n'exécute qu'un seul rôle actif. Elle peut préparer le passage
de relais suivant, mais ne l'exécute pas : une nouvelle instruction référençant
le prompt canonique suivant est requise. Le routeur constitue la seule exception
d'amorçage ; en l'absence de transmission, une instruction qui référence
explicitement `ROUTER.md` lui permet seulement d'ouvrir `CURRENT_TASK.md`, puis
il s'arrête.

Seul l'auteur ou implémentateur actif peut modifier le comportement évalué et
effectuer la clôture mécanique. Il n'inscrit `Terminé` qu'après constat des
preuves, portes, décisions et revues réellement requises. Pour `R2/R3`, il ne
peut ni produire ni remplacer l'avis du relecteur indépendant.

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
`non applicable` ou le blocage. Le déplacement vers la destination
conventionnelle n'est effectué qu'après ce contrôle.
