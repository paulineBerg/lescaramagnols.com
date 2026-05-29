# Plan de correction des dettes de migration privee

Date : 2026-05-28
Document source : `docs/private/rapport-migration-privee-2026-05-28.md`
Perimetre : correction des points listes dans `## 3. Oublis, dettes ou points non entierement clos`.

## 1. Objectif

Ce plan transforme les dettes restantes en phases de travail executables.

Principe retenu : ne pas ajouter une nouvelle couche technique avant d'avoir verrouille la recette, la securite d'exploitation et les chemins de restauration.

Decision : les corrections doivent d'abord renforcer l'exploitation de l'existant PHP moderne/Symfony-compatible. Une app Symfony separee ne doit etre envisagee qu'apres validation hebergement, supervision et processus de deploiement.

## 2. Synthese des dettes a corriger

| Dette | Risque | Priorite | Choix retenu |
|---|---:|---:|---|
| Go-live production non automatique | regression ou faille non vue en local | P0 | gate preprod documente avant production |
| Recette manuelle incomplete | faux sentiment de securite | P0 | runbook de recette obligatoire |
| CSP avec exception `style-src 'unsafe-inline'` | surface XSS residuelle | P1 | extraction progressive des styles inline |
| Pas de scanner antivirus documentaire | fichier malveillant stocke hors webroot mais conserve | P1 | interface de scan optionnelle + quarantaine |
| Templates PHP prives encore actifs | dette de migration structurelle | P2 | conserver temporairement, durcir le contrat |
| Traces historiques anonymisation | confusion fonctionnelle et maintenance | P2 | nettoyage progressif noms/routes/messages |
| Suppression/sauvegarde/cron J+20/J+30 a valider en conditions reelles | perte ou conservation incorrecte | P0 | scenario bout-en-bout en preprod |

## 3. Phase C0 - Gate preproduction avant go-live

Objectif : empecher toute mise en production si les controles automatises et manuels ne sont pas passes.

Travaux :

- creer un runbook `docs/private/recette-preprod-migration-privee.md` ;
- y lister les commandes obligatoires ;
- y ajouter une table de preuves avec date, acteur, resultat et lien vers logs/captures ;
- ajouter une section bloquante `Go / No-Go`.

Commandes obligatoires :

```bash
php backend/core/tools/private_migration_reconcile.php security-checklist
php backend/core/tools/private_migration_reconcile.php migration-dod
php backend/core/tools/private_migration_reconcile.php m5-plan
php backend/core/tools/private_migration_reconcile.php m6-retirement
composer check-security-headers -- --url=https://preprod.lescaramagnols.com
```

Critere d'acceptation :

- toutes les commandes retournent `ready=true` ou OK ;
- les tests manuels C1, C2 et C3 sont signes dans le runbook ;
- aucune dette critique ouverte ne reste sans decision explicite.

## 4. Phase C1 - Recette manuelle securite privee

Objectif : couvrir ce que les tests unitaires ne prouvent pas completement.

Scenarios :

1. login, logout, expiration de session ;
2. refus CSRF sur formulaire prive ;
3. compte suspendu ;
4. permission module retiree ;
5. reset password avec lien valide, lien expire et lien deja consomme ;
6. acces direct fichier prive sans session ;
7. acces fichier prive avec session mais sans permission `documents`.

Livrables :

- runbook rempli ;
- captures ou logs d'evenements sensibles ;
- anomalies classees en `bloquant`, `majeur`, `mineur`.

Critere d'acceptation :

- aucun scenario critique ne donne acces a une ressource interdite ;
- les refus retournent `401`, `403` ou redirection login selon le contexte ;
- les logs ne contiennent ni mot de passe, ni token, ni contenu de document.

## 5. Phase C2 - Suppression compte suspendu et cron J+20/J+30

Objectif : valider le flux sensible le plus risque.

Flux cible :

1. admin clique suppression du compte suspendu ;
2. sauvegarde creee ;
3. donnees purgees immediatement ;
4. compte conserve en etat planifie ;
5. email d'information envoye ;
6. cron J+20 envoie l'avertissement ;
7. cron J+30 supprime le compte et la sauvegarde ;
8. les logs prouvent chaque etape.

Travaux :

- ajouter un scenario de recette preprod avec un compte de test dedie ;
- ajouter une commande dry-run si elle n'existe pas deja pour simuler J+20 et J+30 ;
- verifier que l'email J+20 explique clairement que les donnees peuvent etre recuperees via sauvegarde mais que le compte ne sera pas restaure automatiquement ;
- verifier que J+30 supprime compte et sauvegarde.

Critere d'acceptation :

- aucun compte n'est supprime au clic admin ;
- les donnees sont purgees au clic admin ;
- l'avertissement J+20 part une seule fois ;
- la suppression J+30 est idempotente ;
- une relance cron ne double ni email ni suppression.

## 6. Phase C3 - Restauration fichier et base

Objectif : prouver que la sauvegarde sert reellement.

Travaux :

- creer une sauvegarde contenant donnees SQL et fichiers ;
- verifier le ZIP structure ;
- lancer `verify-backup` ;
- lancer restauration dry-run ;
- definir les conditions d'une restauration reelle admin ;
- documenter ce qui est restaure et ce qui ne l'est pas.

Critere d'acceptation :

- la sauvegarde JSON/ZIP est valide ;
- les fichiers sont presents dans des chemins non publics ;
- les donnees SQL sont reinsertables sans conflit silencieux ;
- la restauration reelle reste une action admin consciente, auditee et non automatique.

## 7. Phase C4 - Durcissement CSP

Objectif : supprimer l'exception `style-src 'unsafe-inline'`.

Choix retenu : extraction progressive des styles inline vers assets CSS prives, sans refonte visuelle.

Travaux :

- inventorier les `<style>` inline dans les templates prives ;
- creer un asset CSS prive dedie ;
- migrer les styles du layout prive ;
- migrer les styles des modules `Documents`, `Bloc-note`, `Discussions`, `Locations`, `Aide impots` ;
- modifier la CSP pour retirer `style-src 'unsafe-inline'` ;
- garder les scripts avec nonce.

Critere d'acceptation :

- aucune balise `<style>` inline non justifiee dans les templates prives ;
- CSP privee sans `unsafe-inline` pour `script-src` et `style-src` ;
- rendu BO/private identique sur desktop et mobile ;
- `security-checklist` reste vert.

## 8. Phase C5 - Antivirus ou quarantaine documentaire

Objectif : encadrer le risque fichier en production.

Choix retenu : contrat de scan optionnel, avec quarantaine par defaut si scanner configure.

Travaux :

- ajouter une configuration `PRIVATE_DOCUMENT_SCAN_COMMAND` ou equivalente ;
- ajouter un statut documentaire `pending_scan`, `clean`, `infected`, `scan_unavailable` si necessaire ;
- empecher le telechargement utilisateur d'un fichier `pending_scan` ou `infected` ;
- journaliser resultat, code retour, duree et erreur technique ;
- conserver le stockage hors webroot.

Critere d'acceptation :

- si aucun scanner n'est configure, le comportement actuel reste stable et documente ;
- si un scanner est configure, un fichier non valide est bloque ;
- aucune erreur scanner ne divulgue de chemin systeme sensible a l'utilisateur ;
- l'admin voit un etat simple et comprehensible.

## 9. Phase C6 - Nettoyage des traces anonymisation

Objectif : eviter la confusion fonctionnelle.

Travaux :

- rechercher les noms internes encore lies a `anonymize`, `anonymized`, `anonymous` ;
- distinguer les traces historiques sans effet et les traces encore appelees ;
- renommer uniquement ce qui peut l'etre sans casser les migrations ;
- garder si besoin un alias technique documente pour compatibilite ;
- supprimer les textes visibles qui parlent encore d'anonymisation.

Critere d'acceptation :

- aucune action visible ne parle d'anonymisation ;
- les routes legacy d'anonymisation restent bloquees ;
- les tests suppression/sauvegarde restent verts ;
- aucune donnee historique n'est perdue par renommage.

## 10. Phase C7 - Encadrement des templates PHP prives

Objectif : conserver temporairement les templates PHP sans laisser la dette grossir.

Choix retenu : interdire toute nouvelle logique metier dans les templates.

Regles :

- templates = affichage uniquement ;
- logique metier dans `backend/src/PrivatePortal` ou `backend/src/PrivateApps` ;
- tout nouveau module doit avoir service/repository/test dedie ;
- tout bouton JS doit etre un vrai `<button type=\"button\">` ;
- aucune sortie utilisateur sans echappement.

Critere d'acceptation :

- les nouveaux ecrans prives suivent ces regles ;
- les revues de code bloquent les calculs metier dans templates ;
- `migration-dod` reste vert.

## 11. Points de vigilance complementaires

Cette section couvre les points listes dans `## 4. Autres points de vigilance` du rapport de migration.

### Phase V1 - Sauvegardes volumineuses et retention

Objectif : garantir que les sauvegardes restent exploitables quand les documents deviennent nombreux.

Travaux :

- definir une taille maximale de sauvegarde recommandee ;
- ajouter une alerte si une sauvegarde depasse un seuil configurable ;
- verifier les droits `0600` sur fichiers et `0700` sur dossiers ;
- documenter la retention des sauvegardes liees aux suppressions compte ;
- verifier que les sauvegardes J+30 sont bien supprimees avec le compte.

Critere d'acceptation :

- une sauvegarde volumineuse ne casse pas la generation ZIP ;
- le chemin de sauvegarde reste hors webroot ;
- les droits fichiers sont controles ;
- la retention est explicite et testable.

### Phase V2 - Emails transactionnels

Objectif : fiabiliser les emails critiques du prive.

Travaux :

- verifier tous les liens absolus construits avec `BASE_URL` ;
- lister les templates emails modifiables en BO ;
- documenter les variables disponibles par email ;
- ajouter un mode preview admin sans envoi reel ;
- verifier que les tokens ne sont jamais journalises en clair ;
- verifier les cas d'erreur SMTP avec message utilisateur neutre et log technique.

Critere d'acceptation :

- les liens activation/reset sont complets ;
- chaque email critique a un sujet, un corps, une liste de variables et un fallback ;
- le BO permet de comprendre les variables disponibles ;
- un echec SMTP ne divulgue pas d'information sensible.

### Phase V3 - Cron et idempotence

Objectif : garantir que les traitements planifies ne doublonnent pas et ne ratent pas les etapes sensibles.

Travaux :

- lister toutes les actions rattachees a la cron privee ;
- ajouter un mode dry-run pour les actions destructrices ou sensibles ;
- journaliser debut, fin, duree, compteurs et erreurs ;
- verifier l'idempotence des traitements J+20 et J+30 ;
- ajouter une alerte si une execution cron echoue ;
- documenter la commande OVH exacte et sa frequence.

Critere d'acceptation :

- relancer la cron ne renvoie pas deux fois le meme email J+20 ;
- relancer la cron ne tente pas deux suppressions definitives concurrentes ;
- une erreur est visible dans les logs d'exploitation ;
- la commande cron est documentee pour OVH.

### Phase V4 - UX BO et espace prive

Objectif : eviter les regressions d'interface qui rendent les actions sensibles confuses.

Travaux :

- ajouter une checklist UI commune aux modules prives ;
- verifier absence de debordement horizontal sur desktop et mobile ;
- rendre le menu gauche fixe sur les pages BO concernees ;
- garantir que les messages restent visibles en haut de viewport ;
- imposer les vrais boutons `<button type="button">` pour les actions JS ;
- verifier les confirmations sur actions destructrices ;
- ajouter un test navigateur ou une recette manuelle responsive.

Critere d'acceptation :

- aucun bloc d'action ne sort de l'ecran ;
- les actions destructrices restent lisibles et annulables ;
- les messages ne disparaissent pas en haut de page lors d'un scroll ;
- les boutons JS fonctionnent au clavier et a la souris.

### Phase V5 - Observabilite exploitation

Objectif : rendre les problemes visibles avant qu'ils deviennent des incidents.

Travaux :

- lister les evenements critiques prives ;
- ajouter une severite claire : info, warning, error, critical ;
- ajouter une synthese periodique des erreurs cron ;
- verifier que les logs ne contiennent pas de contenu sensible ;
- definir les seuils d'alerte : echec SMTP, echec backup, echec purge, CSRF repetes, login rate limit.

Critere d'acceptation :

- un incident backup/cron/email laisse une trace exploitable ;
- les alertes ne contiennent pas de secret ;
- les logs permettent de reconstituer une action sensible.

## 12. Ordre recommande

Ordre de travail prioritaire :

1. C0 - Gate preproduction ;
2. C1 - Recette manuelle securite ;
3. C2 - Suppression compte suspendu et cron J+20/J+30 ;
4. C3 - Restauration fichier et base ;
5. C4 - Durcissement CSP ;
6. C5 - Antivirus ou quarantaine documentaire ;
7. C6 - Nettoyage anonymisation ;
8. C7 - Encadrement templates PHP prives.
9. V1 - Sauvegardes volumineuses et retention ;
10. V2 - Emails transactionnels ;
11. V3 - Cron et idempotence ;
12. V4 - UX BO et espace prive ;
13. V5 - Observabilite exploitation.

Raison : les quatre premieres phases reduisent le risque production immediat. Les phases suivantes reduisent la dette, la surface d'attaque et les risques d'exploitation.

## 13. Definition de fin de correction

Les dettes de la section 3 seront considerees corrigees quand :

- `security-checklist` est vert ;
- `migration-dod` est vert ;
- le runbook preprod est rempli ;
- les scenarios C1, C2 et C3 ont une preuve ;
- la CSP n'a plus d'exception inline non documentee ;
- la strategie scan/quarantaine documentaire est decidee ;
- aucune trace visible d'anonymisation ne subsiste ;
- les regles templates sont ajoutees au processus de developpement.
- les sauvegardes volumineuses sont gerees ou alertees ;
- les emails transactionnels ont preview, variables documentees et liens absolus ;
- la cron est idempotente, journalisee et documentee ;
- les pages BO/private ne debordent pas de l'ecran ;
- les evenements critiques sont observables sans fuite de secret.

## 14. Decision

Meilleur choix : finaliser la recette et les garanties d'exploitation avant toute nouvelle migration technique.

Ne pas faire maintenant :

- creer une app Symfony separee sans besoin d'exploitation clair ;
- ajouter un scanner antivirus fictif non branche ;
- automatiser une restauration reelle sans validation admin ;
- considerer les controles verts comme un go-live suffisant.
