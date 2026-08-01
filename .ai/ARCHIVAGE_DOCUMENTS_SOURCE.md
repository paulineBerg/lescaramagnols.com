# Archivage des documents source et preuves

Ce document décrit une opération de clôture ; il n'autorise ni suppression, ni
déplacement hors périmètre. Les règles actives restent dans leur propriétaire
normatif. Un audit, une preuve ou une source historique archivée ne doit jamais
concurrencer la version active.

## Destinations conventionnelles

Choisir la destination selon le propriétaire réel de l'élément :

| Élément | Destination | Statut Git |
|---|---|---|
| transmission locale terminée | `.ai/archive/transmissions/YYYY/MM/` | locale, à ignorer |
| preuve locale nettoyée à conserver | `.governance/archive/YYYY/MM/<TASK-ID>/` | locale, à ignorer |
| analyse versionnée explicitement remplacée | `analyses/archive/YYYY/` | suivie |

Une transmission déplacée conserve son nom `CURRENT_TASK-<ID>.md` et doit déjà
porter l'état `Terminé`. Une tâche `À revoir`, `Bloqué`, `Planifié` ou `En
cours` reste directement sous `.ai/` afin de demeurer visible. Le dossier de
preuves contient un `MANIFEST.md` conforme au manifeste minimal ci-dessous.

`analyses/archive/YYYY/` est réservé à un rapport réellement remplacé. Mettre à
jour ses liens entrants dans la même tâche et conserver dans le document son
statut historique. Une règle active, une décision applicable, une dérogation
ouverte, un profil, un manifeste projet ou un guide courant ne va jamais dans
ces dossiers : il reste chez son propriétaire normatif.

Les dossiers `.ai/archive/` et `.governance/archive/` sont locaux. Avant leur
première utilisation dans un projet, vérifier que son `.gitignore` les exclut ;
le référentiel central fournit ces deux exclusions. Une preuve qui doit être
partagée est synthétisée et nettoyée dans un rapport versionné sous `analyses/`
ou `analyses/archive/YYYY/`, sans copier de secret, donnée personnelle, dump,
log brut ou configuration runtime.

## Application obligatoire

Toute tâche déclarée `Terminé` applique la présente procédure et consigne son
résultat dans la transmission : `réussi` si un archivage vérifié a été réalisé,
`non applicable` si aucun document source ou preuve ne doit être conservé, ou
un état bloquant si une exigence d'archivage ne peut pas être satisfaite. Une
tâche ne peut pas omettre silencieusement ce contrôle.

Cette obligation impose une décision et une preuve, pas un déplacement
automatique. Les fichiers `CURRENT_TASK*.md` restent locaux et ignorés. Après
clôture, les déplacer vers le mois correspondant sous
`.ai/archive/transmissions/` ; ils restent des transmissions historiques et ne
deviennent jamais une archive normative ou versionnée.

## Avant d'archiver

1. Confirmer que la tâche est terminée selon les portes `G0` à `G5` et qu'aucune
   décision ou validation requise n'est en attente.
2. Identifier séparément règle active, document source, preuve, artefact
   temporaire et donnée interdite.
3. Définir propriétaire, cible d'archive, durée de conservation, accès et
   procédure de restauration. Utiliser les destinations conventionnelles
   ci-dessus ; une règle locale plus proche peut les spécialiser sans réduire
   les protections de données. Si aucune catégorie ne convient, proposer une
   destination mais ne pas l'inventer silencieusement.
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
- Après déplacement des transmissions, vérifier qu'aucun `CURRENT_TASK.md`
  actif ne subsiste et que chaque fichier archivé porte bien l'état `Terminé`.

Le projet choisit son stockage et sa politique de rétention selon ses risques et
obligations. Ce fichier ne fixe aucune durée universelle.
