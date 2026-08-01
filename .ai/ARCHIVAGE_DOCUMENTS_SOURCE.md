# Archivage des documents source et preuves

Ce document décrit une opération de clôture ; il n'autorise ni suppression, ni
déplacement hors périmètre. Les règles actives restent dans leur propriétaire
normatif. Un audit, une preuve ou une source historique archivée ne doit jamais
concurrencer la version active.

## Application obligatoire

Toute tâche déclarée `Terminé` applique la présente procédure et consigne son
résultat dans la transmission : `réussi` si un archivage vérifié a été réalisé,
`non applicable` si aucun document source ou preuve ne doit être conservé, ou
un état bloquant si une exigence d'archivage ne peut pas être satisfaite. Une
tâche ne peut pas omettre silencieusement ce contrôle.

Cette obligation impose une décision et une preuve, pas un déplacement
automatique. Les fichiers `CURRENT_TASK*.md` restent locaux et ignorés ; un nom
suffixé peut conserver une transmission historique sans devenir une archive
normative ou versionnée.

## Avant d'archiver

1. Confirmer que la tâche est terminée selon les portes `G0` à `G5` et qu'aucune
   décision ou validation requise n'est en attente.
2. Identifier séparément règle active, document source, preuve, artefact
   temporaire et donnée interdite.
3. Définir propriétaire, cible d'archive, durée de conservation, accès et
   procédure de restauration. Sans convention projet, proposer l'archivage mais
   ne pas inventer de cible ; consigner alors le contrôle comme bloquant si la
   conservation est obligatoire, ou `non applicable` si aucun élément ne doit
   être conservé.
4. Retirer ou masquer secrets, données personnelles, dumps, logs bruts, chemins
   privés et configurations runtime. Leur présence bloque l'archivage dans Git.

## Manifeste minimal

Pour chaque élément conservé : identifiant de tâche, chemin ou nom logique,
type, date, source, statut, motif de conservation, durée, niveau de
confidentialité, empreinte si utile et lien vers l'exigence prouvée. Ne pas
copier la donnée sensible dans le manifeste.

## Exécution et contrôle

- Préférer une copie vérifiée avant tout retrait de la source.
- Ne supprimer la source qu'avec l'autorisation applicable et après contrôle de
  la copie ; une archive non restaurable n'est pas une sauvegarde.
- Conserver les preuves minimales et bornées, pas les sorties complètes par
  défaut.
- Vérifier liens actifs, absence de doublon normatif, permissions et statut Git.
- Consigner ce qui a été archivé, ce qui a été détruit, la récupération possible
  et les éléments volontairement non conservés.

Le projet choisit son stockage et sa politique de rétention selon ses risques et
obligations. Ce fichier ne fixe aucune durée universelle.
