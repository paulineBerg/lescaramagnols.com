<!-- BEGIN MANAGED CENTRAL TASK CONTEXT -->
> **Gouvernance active 2.4** — Workflow et prompts :
> `../pauline-ai-governance/.ai/README.md` et `../pauline-ai-governance/.ai/prompts/`.
> La présente transmission et son archive restent locales à ce projet.
>
> **Mode** : mono-agent par défaut. Pour toute demande explicitement activée,
> l'agent principal `/root` peut cumuler routage, architecture,
> implémentation, vérification, corrections et clôture sans nouveau relais.
> Une revue indépendante reste facultative. Les anciennes mentions de
> fournisseurs, de prompts successifs ou de relecteur obligatoire ci-dessous
> sont remplacées par cette règle, sans modifier le besoin ni les preuves.
>
> **Limites** : cette attribution n'active pas une tâche seulement planifiée et
> n'autorise ni production, déploiement, migration, destruction, action externe
> ou décision humaine. Ces actions restent soumises à leur demande explicite.
<!-- END MANAGED CENTRAL TASK CONTEXT -->

# Tâche en cours

## Identité

- ID : GOV-GIT-CARAMAGNOLS-20260731
- Demande originale : retirer les autorisations Git ou production implicites confirmées par l'audit de gouvernance.
- Projet et périmètre : lescaramagnols.com ; README.md, section Git et déploiement
- Hors périmètre : code fonctionnel, données, runtime, production, commit, push et déploiement.

## Classifications

- Routage multi-IA : B — correction documentaire de gouvernance confiée à Codex.
- Niveau de risque : R1 — règle locale d'autorisation, changement réversible sans runtime.

## Rôles et autorisations

- Agent autorisé à modifier : Codex.
- Relecteur indépendant requis : non pour R1.
- Décision humaine : retrait/reformulation confirmé le 2026-07-31 ; aucune dérogation.

## Critères d'acceptation

1. Aucune formulation locale ne vaut autorisation implicite de commit, push, force-push ou déploiement.
2. Les procédures utiles restent documentées mais exigent une demande explicite avant exécution.
3. Le diff reste documentaire, minimal et sans changement utilisateur écrasé.
4. Les contrôles documentaires et `git diff --check` réussissent.

## Validations prévues

- G0 : règles, état Git, propriétaire et décision vérifiés.
- G1 : recherche ciblée et syntaxe Markdown.
- G2 à G4 : non applicables, aucun comportement runtime ni livraison.
- G5 : diff relu, `git diff --check`, risques résiduels rapportés.

## État

Terminé

## Résultat

- Fichiers modifiés : README.md.
- Le force-push nominal est retiré et publication/déploiement exigent une demande explicite.
- Aucun commit, push, déploiement, cache, runtime ou donnée touché.

## Validations

- G0 : réussi — règles, état Git propre initial et décision humaine vérifiés.
- G1 : réussi — recherche ciblée et diff documentaire relus.
- G2 : non applicable — aucun comportement runtime modifié.
- G3 : non applicable — aucune donnée, sécurité applicative ou production touchée.
- G4 : non applicable — aucune livraison exécutée.
- G5 : réussi — \`git diff --check\` retourne 0 et le diff reste borné.

## Risques résiduels

- Les autres écarts du parc restent hors périmètre de cette correction locale.
