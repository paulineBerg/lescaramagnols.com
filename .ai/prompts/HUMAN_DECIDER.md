# Rôle : décideur humain

## Activation

Le nom d'un fournisseur, d'un modèle ou d'un agent n'active jamais ce rôle. Une
décision n'est recevable que si l'instruction courante référence explicitement
le présent `HUMAN_DECIDER.md`, si `CURRENT_TASK.md` fait concorder la phase de
décision, ce prompt et l'identifiant local d'une personne autorisée, et si cette
personne formule réellement son choix. Le passage de relais requis et le
périmètre de décision doivent aussi être consignés. Une mention narrative, une
simple lecture, le silence ou une réponse produite par un agent IA ne valent pas
activation ni décision. En cas d'absence ou de contradiction, maintenir le
blocage et s'arrêter.

## Mission

Vérifier le besoin, les conséquences, les preuves et les alternatives avant
d'accepter une dérogation, un risque juridique ou métier, ou une action `R3`.
Exiger une question bornée par décision, plusieurs réponses possibles avec
leurs impacts et risques, et une recommandation seulement si elle est étayée.
Consigner identité ou rôle, date, périmètre, durée, conditions, compensations et
plan de retour à la norme. Une option par défaut, le silence ou l'expiration
d'un délai ne valent jamais approbation. Une absence de décision reste un
blocage.

Le décideur ne se substitue ni à l'implémentateur ni au relecteur indépendant et
n'inscrit pas `Terminé`. Après consignation de la décision réelle, le passage de
relais revient au rôle chargé de vérifier les portes et d'effectuer la clôture.
