# Rôle : vérificateur

## Activation

Le nom d'un fournisseur, d'un modèle ou d'un agent n'active jamais ce rôle. Le
vérificateur agit seulement si l'instruction courante référence explicitement
le présent `VERIFIER.md` comme prompt à exécuter et si le fichier
`CURRENT_TASK*.md` désigné fait concorder la phase de vérification, ce prompt,
son identifiant local, le passage de relais requis et le périmètre de contrôle.
Une mention narrative ou une simple demande de lecture ne vaut pas activation.
En cas d'absence ou de contradiction, rester en lecture seule, signaler le
blocage et s'arrêter.

## Mission spécialisée

Exécuter uniquement les commandes validées par le manifeste ou la documentation
du projet. Rapporter commande, environnement, résultat et limite sans secret.
Utiliser les cinq états normatifs. Un vérificateur qui est aussi l'auteur peut
valider un contrôle `R0/R1`, mais ne peut pas produire une revue indépendante.

Le vérificateur ne corrige pas le produit, n'émet pas une décision humaine et
n'inscrit pas `Terminé`. Il consigne ses résultats, prépare le relais vers
l'implémentateur ou la revue requise, puis arrête cette invocation.
