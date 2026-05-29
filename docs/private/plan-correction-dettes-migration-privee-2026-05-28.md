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

## 2.1 Suivi global des phases

Derniere mise a jour : 2026-05-29.

| Phase | Statut | Lecture rapide |
|---|---|---|
| C0 - Gate preproduction | Fait | Gate preprod validee: readiness OK, headers preprod OK, C1/C2/C3 signes, decision C0 fermee en GO. |
| C1 - Recette manuelle securite privee | Fait | Recette manuelle executee sur la vraie preprod, preuve C1 archivee. |
| C2 - Suppression compte suspendu et cron J+20/J+30 | Fait | Flux sensible joue bout en bout en preprod avec compte jetable, preuve C2 archivee. Livraison SMTP reelle non forcee, renvoyee a V2. |
| C3 - Restauration fichier et base | Fait | Backup ZIP + verify + restore dry-run rejoues sur preprod, preuve archivee, artefacts jetables nettoyes. |
| C4 - Durcissement CSP | A faire | Headers renforces, mais `style-src 'unsafe-inline'` reste a retirer. |
| C5 - Antivirus ou quarantaine documentaire | A faire | Decision retenue, implementation non lancee. |
| C6 - Nettoyage des traces anonymisation | A faire | Inventaire et nettoyage restant a planifier. |
| C7 - Encadrement templates PHP prives | Partiel | Regles d'architecture deja presentes, controle continu a formaliser. |
| V1 - Sauvegardes volumineuses et retention | A faire | Seuils, droits et retention a verifier en conditions reelles. |
| V2 - Emails transactionnels | A faire | Templates existants a auditer, preview admin a ajouter. |
| V3 - Cron et idempotence | A faire | Idempotence J+20/J+30 a prouver. |
| V4 - UX BO et espace prive | A faire | Checklist UI et recette responsive a executer. |
| V5 - Observabilite exploitation | A faire | Evenements critiques et seuils d'alerte a finaliser. |

## 3. Phase C0 - Gate preproduction avant go-live

Objectif : empecher toute mise en production si les controles automatises et manuels ne sont pas passes.

Checklist de suivi :

- [x] Creer le runbook `docs/private/recette-preprod-migration-privee.md`.
- [x] Lister les commandes automatiques obligatoires dans le runbook.
- [x] Ajouter une table de preuves datee.
- [x] Ajouter une section bloquante `Go / No-Go`.
- [x] Configurer la vraie URL preprod : `https://preprod.lescaramagnols.com`.
- [x] Deployer la preprod sous `caramagnols-preprod/backend/public`.
- [x] Configurer `robots.txt` preprod en `Disallow: /`.
- [x] Ajouter `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet` sur la preprod.
- [x] Relancer `security-checklist` avec `ready=true`.
- [x] Relancer `migration-dod` avec `ready=true`.
- [x] Relancer `m5-plan` avec `ready=true`.
- [x] Relancer `m6-retirement` avec `ready=true`.
- [x] Relancer `check-security-headers` sur la vraie preprod avec `Headers requis: OK`.
- [x] Archiver la derniere sortie preprod OK dans le runbook.
- [x] Signer les scenarios manuels C1, C2 et C3 dans le runbook.
- [x] Fermer la decision finale en `GO` uniquement apres C1/C2/C3.

Travaux :

- [x] creer un runbook `docs/private/recette-preprod-migration-privee.md` ;
- [x] y lister les commandes obligatoires ;
- [x] y ajouter une table de preuves avec date, acteur, resultat et lien vers logs/captures ;
- [x] ajouter une section bloquante `Go / No-Go`.

Commandes obligatoires :

```bash
php backend/core/tools/private_migration_reconcile.php security-checklist
php backend/core/tools/private_migration_reconcile.php migration-dod
php backend/core/tools/private_migration_reconcile.php m5-plan
php backend/core/tools/private_migration_reconcile.php m6-retirement
composer check-security-headers -- --url=https://preprod.lescaramagnols.com
```

Critere d'acceptation :

- [x] toutes les commandes retournent `ready=true` ou OK ;
- [x] les tests manuels C1, C2 et C3 sont signes dans le runbook ;
- [x] aucune dette critique ouverte ne reste sans decision explicite.

## 4. Phase C1 - Recette manuelle securite privee

Objectif : couvrir ce que les tests unitaires ne prouvent pas completement.

Checklist de suivi :

- [x] Couverture automatisee C1/C2/C3 relancee localement (`48 tests`, `432 assertions`).
- [x] Preprod joignable en HTTP 200 apres correction du routage OVH.
- [x] Creer un compte prive de test dedie sur preprod.
- [x] Executer la recette login/logout/expiration.
- [x] Executer le refus CSRF.
- [x] Executer le cas compte suspendu.
- [x] Executer le cas permission module retiree.
- [x] Executer les cas reset password.
- [x] Executer les cas fichier prive sans session et sans permission.
- [x] Ajouter captures/logs horodates au runbook.

Scenarios :

- [x] login, logout, expiration de session ;
- [x] refus CSRF sur formulaire prive ;
- [x] compte suspendu ;
- [x] permission module retiree ;
- [x] reset password avec lien valide, lien expire et lien deja consomme ;
- [x] acces direct fichier prive sans session ;
- [x] acces fichier prive avec session mais sans permission `documents`.

Livrables :

- [x] runbook rempli ;
- [x] captures ou logs d'evenements sensibles ;
- [x] anomalies classees en `bloquant`, `majeur`, `mineur`.

Critere d'acceptation :

- [x] aucun scenario critique ne donne acces a une ressource interdite ;
- [x] les refus retournent `401`, `403` ou redirection login selon le contexte ;
- [x] les logs ne contiennent ni mot de passe, ni token, ni contenu de document.

Note de recette : une anomalie d'exploitation preprod distincte a ete observee sur l'upload HTTP documentaire (`storage_unavailable`). Elle ne valide pas un acces interdit et ne bloque pas C1, mais elle doit etre reprise dans les phases d'exploitation documentaire/restauration avant go-live.

## 5. Phase C2 - Suppression compte suspendu et cron J+20/J+30

Objectif : valider le flux sensible le plus risque.

Checklist de suivi :

- [x] Identifier ou creer le compte de test preprod dedie.
- [x] Documenter l'etat initial du compte et de ses donnees.
- [x] Declencher la suppression depuis l'admin.
- [x] Verifier la sauvegarde creee.
- [x] Verifier la purge immediate des donnees.
- [x] Verifier l'etat planifie du compte.
- [x] Simuler ou executer J+20 en dry-run.
- [x] Simuler ou executer J+30 en dry-run.
- [x] Verifier l'idempotence par relance cron.
- [x] Archiver logs et preuves dans le runbook.

Flux cible :

- [x] admin clique suppression du compte suspendu ;
- [x] sauvegarde creee ;
- [x] donnees purgees immediatement ;
- [x] compte conserve en etat planifie ;
- [x] email d'information prepare via template admin ; livraison SMTP preprod non forcee et traitee dans V2 ;
- [x] cron J+20 identifie l'avertissement en dry-run cible ;
- [x] cron J+30 supprime le compte et la sauvegarde ;
- [x] les logs prouvent chaque etape.

Travaux :

- [x] ajouter un scenario de recette preprod avec un compte de test dedie ;
- [x] ajouter une commande dry-run si elle n'existe pas deja pour simuler J+20 et J+30 ;
- [x] verifier que l'email J+20 explique clairement que les donnees peuvent etre recuperees via sauvegarde mais que le compte ne sera pas restaure automatiquement ;
- [x] verifier que J+30 supprime compte et sauvegarde.

Critere d'acceptation :

- [x] aucun compte n'est supprime au clic admin ;
- [x] les donnees sont purgees au clic admin ;
- [x] l'avertissement J+20 est cible une seule fois en dry-run preprod ;
- [x] la suppression J+30 est idempotente ;
- [x] une relance cron ne double ni email ni suppression.

## 6. Phase C3 - Restauration fichier et base

Objectif : prouver que la sauvegarde sert reellement.

Note implementation 2026-05-29 : `PrivateBackupService` cree maintenant un ZIP a cote du JSON, avec `backup.json`, `manifest.json` et les fichiers prives sous `files/**`. La commande `private_migration_reconcile.php backup` genere la sauvegarde privee, et `verify-backup` accepte un JSON ou un ZIP, verifie la structure ZIP, lance le dry-run et remonte les conflits SQL explicitement. La restauration reelle reste bloquee sans runbook admin signe.

Checklist de suivi :

- [x] Creer un jeu de test preprod avec donnees SQL et fichiers.
- [x] Generer une sauvegarde complete.
- [x] Verifier la structure ZIP.
- [x] Lancer `verify-backup`.
- [x] Lancer la restauration dry-run.
- [x] Documenter les conditions d'une restauration reelle.
- [x] Archiver les preuves dans le runbook.

Travaux :

- [x] creer une sauvegarde contenant donnees SQL et fichiers ;
- [x] verifier le ZIP structure ;
- [x] lancer `verify-backup` ;
- [x] lancer restauration dry-run ;
- [x] definir les conditions d'une restauration reelle admin ;
- [x] documenter ce qui est restaure et ce qui ne l'est pas.

Critere d'acceptation :

- [x] la sauvegarde JSON/ZIP est valide ;
- [x] les fichiers sont presents dans des chemins non publics ;
- [x] les donnees SQL sont reinsertables sans conflit silencieux ;
- [x] la restauration reelle reste une action admin consciente, auditee et non automatique.

## 7. Phase C4 - Durcissement CSP

Objectif : supprimer l'exception `style-src 'unsafe-inline'`.

Choix retenu : extraction progressive des styles inline vers assets CSS prives, sans refonte visuelle.

Checklist de suivi :

- [x] Headers de securite renforces sur preprod.
- [x] Scripts applicatifs avec nonce conserves.
- [x] Inventorier les styles inline prives.
- [x] Extraire les styles vers un asset CSS prive.
- [x] Retirer `style-src 'unsafe-inline'`.
- [x] Valider le rendu private/admin desktop et mobile.
- [x] Relancer `security-checklist`.

Travaux :

- [x] inventorier les `<style>` inline dans les templates prives ;
- [x] creer un asset CSS prive dedie ;
- [x] migrer les styles du layout prive ;
- [x] migrer les styles des modules `Documents`, `Bloc-note`, `Discussions`, `Locations`, `Aide impots` ;
- [x] modifier la CSP pour retirer `style-src 'unsafe-inline'` ;
- [x] garder les scripts avec nonce.

Critere d'acceptation :

- [x] aucune balise `<style>` inline non justifiee dans les templates prives ;
- [x] CSP privee sans `unsafe-inline` pour `script-src` et `style-src` ;
- [x] rendu BO/private identique sur desktop et mobile ;
- [x] `security-checklist` reste vert apres retrait effectif.

Preuves C4 :

- `docs/private/recette-preprod-migration-privee/27-c4-security-checklist-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/28-c4-deploy-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/29-c4-check-security-headers-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/30-c4-security-checklist-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/31-c4-private-login-http-local-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/32-c4-inline-style-inventory-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/33-c4-validations-locales-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/34-c4-rendu-local-headless-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/35-c4-reserve-http-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/36-c4-deploy-preprod-final-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/37-c4-security-checklist-preprod-final-2026-05-29.json`

Reserve : la validation HTTP externe de la preproduction repond actuellement en `403` Apache/OVH avant execution PHP sur `/private/login`. La validation CSP applicative C4 a donc ete faite par tests locaux, `security-checklist` locale et preprod CLI, puis smoke HTTP local via front-controller.

## 8. Phase C5 - Antivirus ou quarantaine documentaire

Objectif : encadrer le risque fichier en production.

Choix retenu : contrat de scan optionnel, avec quarantaine par defaut si scanner configure.

Checklist de suivi :

- [x] Choisir le contrat de configuration du scanner.
- [x] Ajouter les statuts documentaires si necessaire.
- [x] Bloquer le telechargement des fichiers non propres.
- [x] Journaliser les resultats de scan.
- [x] Tester le mode sans scanner configure.
- [x] Tester le mode scanner configure avec fichier refuse.

Travaux :

- [x] ajouter une configuration `PRIVATE_DOCUMENT_SCAN_COMMAND` ou equivalente ;
- [x] ajouter un statut documentaire `pending_scan`, `clean`, `infected`, `scan_unavailable` si necessaire ;
- [x] empecher le telechargement utilisateur d'un fichier `pending_scan` ou `infected` ;
- [x] journaliser resultat, code retour, duree et erreur technique ;
- [x] conserver le stockage hors webroot.

Critere d'acceptation :

- [x] si aucun scanner n'est configure, le comportement actuel reste stable et documente ;
- [x] si un scanner est configure, un fichier non valide est bloque ;
- [x] aucune erreur scanner ne divulgue de chemin systeme sensible a l'utilisateur ;
- [x] l'admin voit un etat simple et comprehensible.

Contrat C5 retenu :

- `PRIVATE_DOCUMENT_SCAN_COMMAND` vide : aucun scanner n'est lance, les documents conservent le statut `clean` et le comportement historique reste stable.
- `PRIVATE_DOCUMENT_SCAN_COMMAND` renseigne une commande sans shell avec placeholders optionnels `{file}`, `{name}`, `{mime}` ; si `{file}` est absent, le chemin du fichier est ajoute en dernier argument.
- Code retour `0` : `clean`; code retour `1` : `infected`; tout autre code, timeout ou erreur d'execution : `scan_unavailable`.
- Les statuts `pending_scan`, `infected` et `scan_unavailable` sont visibles dans la liste Documents mais bloques au telechargement.
- Les erreurs techniques sont journalisees et tronquees, sans affichage de chemin systeme a l'utilisateur.

Preuves C5 :

- `docs/private/recette-preprod-migration-privee/38-c5-security-checklist-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/39-c5-phpunit-storage-checklist-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/40-c5-frontend-build-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/41-c5-deploy-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/42-c5-security-checklist-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/43-c5-check-security-headers-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/44-c5-schema-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/45-c5-security-checklist-preprod-final-2026-05-29.json`

## 9. Phase C6 - Nettoyage des traces anonymisation

Objectif : eviter la confusion fonctionnelle.

Checklist de suivi :

- [x] Faire l'inventaire des occurrences `anonymize`, `anonymized`, `anonymous`.
- [x] Classer les traces historiques sans effet.
- [x] Classer les traces encore appelees.
- [x] Renommer les elements sans risque migration.
- [x] Documenter les alias conserves.
- [x] Verifier l'absence de texte visible lie a l'anonymisation.

Travaux :

- [x] rechercher les noms internes encore lies a `anonymize`, `anonymized`, `anonymous` ;
- [x] distinguer les traces historiques sans effet et les traces encore appelees ;
- [x] renommer uniquement ce qui peut l'etre sans casser les migrations ;
- [x] garder si besoin un alias technique documente pour compatibilite ;
- [x] supprimer les textes visibles qui parlent encore d'anonymisation.

Alias techniques conserves :

- `PrivateDataProtectionService::anonymizeAccount()` delegue vers `redactAccountForDeletion()` ;
- `PrivateDataProtectionService::purgeAnonymizedAccount()` delegue vers `purgeDeletedAccount()` ;
- `PrivateUserRepository::anonymize()` delegue vers `redactForDeletion()` ;
- `PrivateUserRepository::restoreAnonymized()` delegue vers `restoreDeletedInvite()` ;
- la route legacy `/private/privacy/anonymize` reste retraitee et bloque l'ancien flux self-service.

Decision :

- les actions visibles et les libelles applicatifs utilisent la terminologie suppression / neutralisation ;
- les nouvelles valeurs techniques generees utilisent `@deleted.invalid` et des slugs `*-supprime*` ;
- les alias `anonymize*` restants sont strictement internes et conserves pour compatibilite, sans migration destructive des donnees historiques.

Critere d'acceptation :

- [x] aucune action visible ne parle d'anonymisation ;
- [x] les routes legacy d'anonymisation restent bloquees ;
- [x] les tests suppression/sauvegarde restent verts ;
- [x] aucune donnee historique n'est perdue par renommage.

Preuves :

- inventaire apres nettoyage : `docs/private/recette-preprod-migration-privee/46-c6-inventory-after-cleanup-2026-05-29.txt` ;
- tests suppression / route legacy / membres : `docs/private/recette-preprod-migration-privee/47-c6-phpunit-privacy-legacy-2026-05-29.txt` ;
- checklist locale : `docs/private/recette-preprod-migration-privee/48-c6-security-checklist-local-2026-05-29.json` ;
- deploiement preprod : `docs/private/recette-preprod-migration-privee/49-c6-deploy-preprod-2026-05-29.txt` ;
- checklist preprod : `docs/private/recette-preprod-migration-privee/50-c6-security-checklist-preprod-2026-05-29.json` ;
- headers preprod : `docs/private/recette-preprod-migration-privee/51-c6-check-security-headers-preprod-2026-05-29.txt`.

## 10. Phase C7 - Encadrement des templates PHP prives

Objectif : conserver temporairement les templates PHP sans laisser la dette grossir.

Choix retenu : interdire toute nouvelle logique metier dans les templates.

Checklist de suivi :

- [x] Regle d'architecture deja presente : logique metier hors templates.
- [x] Regle projet deja presente : nouveaux modules prives avec services/repositories/tests.
- [x] Regle projet deja presente : vrai `<button>` pour action JavaScript.
- [x] `migration-dod` relance avec `ready=true`.
- [x] Ajouter une checklist de revue dediee aux templates prives.
- [x] Verifier les templates prives existants contre cette checklist.
- [x] Corriger les ecarts trouves dans les templates existants.

Regles :

- [x] templates = affichage uniquement ;
- [x] logique metier dans `backend/src/PrivatePortal` ou `backend/src/PrivateApps` ;
- [x] tout nouveau module doit avoir service/repository/test dedie ;
- [x] tout bouton JS doit etre un vrai `<button type=\"button\">` ;
- [x] aucune sortie utilisateur sans echappement.

Critere d'acceptation :

- [x] les nouveaux ecrans prives suivent ces regles ;
- [x] les revues de code bloquent les calculs metier dans templates ;
- [x] `migration-dod` reste vert.

Decision :

- la checklist de revue des templates prives est documentee dans `docs/private/README.md` ;
- `PrivateTemplateGuardTest` bloque les nouveaux ecarts sur `backend/templates/private/**` : style inline, event handler inline, pseudo-boutons, acces SQL/base, instanciation service/repository et operations d'ecriture ;
- les lignes cliquables des templates locatifs `properties.php` et `units.php` ont ete remplacees par de vrais boutons `type="button"` ;
- le bouton de fermeture du dialogue biens utilise l'attribut generique `data-private-dialog-close`.

Preuves :

- inventaire templates prives : `docs/private/recette-preprod-migration-privee/52-c7-private-template-inventory-2026-05-29.txt` ;
- validations locales : `docs/private/recette-preprod-migration-privee/53-c7-local-validation-2026-05-29.txt` ;
- `migration-dod` locale : `docs/private/recette-preprod-migration-privee/54-c7-migration-dod-local-2026-05-29.json` ;
- checklist securite locale : `docs/private/recette-preprod-migration-privee/55-c7-security-checklist-local-2026-05-29.json` ;
- deploiement templates et README DoD preprod : `docs/private/recette-preprod-migration-privee/56-c7-deploy-preprod-2026-05-29.txt` ;
- `migration-dod` preprod : `docs/private/recette-preprod-migration-privee/57-c7-migration-dod-preprod-2026-05-29.json` ;
- checklist securite preprod : `docs/private/recette-preprod-migration-privee/58-c7-security-checklist-preprod-2026-05-29.json` ;
- headers preprod : `docs/private/recette-preprod-migration-privee/59-c7-check-security-headers-preprod-2026-05-29.txt`.

## 11. Points de vigilance complementaires

Cette section couvre les points listes dans `## 4. Autres points de vigilance` du rapport de migration.

### Phase V1 - Sauvegardes volumineuses et retention

Objectif : garantir que les sauvegardes restent exploitables quand les documents deviennent nombreux.

Checklist de suivi :

- [x] Definir un seuil de taille maximale recommande.
- [x] Ajouter ou verifier une alerte de depassement de seuil.
- [x] Verifier les droits fichiers/dossiers sur preprod.
- [x] Documenter la retention des sauvegardes de suppression compte.
- [x] Prouver la suppression des sauvegardes a J+30.
- [x] Tester une sauvegarde volumineuse ou un jeu de test representatif.

Travaux :

- [x] definir une taille maximale de sauvegarde recommandee ;
- [x] ajouter une alerte si une sauvegarde depasse un seuil configurable ;
- [x] verifier les droits `0600` sur fichiers et `0700` sur dossiers ;
- [x] documenter la retention des sauvegardes liees aux suppressions compte ;
- [x] verifier que les sauvegardes J+30 sont bien supprimees avec le compte.

Decision :

- seuil recommande par defaut : `536870912` octets (`512 MiB`) pour une archive privee ZIP ;
- seuil surchargeable par configuration applicative `private.backup.recommended_max_bytes` ou par CLI `--recommended-max-bytes=...` ;
- `PrivateBackupService::createBackup()` et `verifyBackup()` retournent `size`, `warnings` et `permissions` ;
- le warning `backup_recommended_size_exceeded` est emis sans bloquer la generation ZIP quand le seuil est depasse ;
- les fichiers de sauvegarde JSON/ZIP sont forces en `0600`, les dossiers cibles en `0700` ;
- les sauvegardes de suppression compte restent sous `backend/var/private-account-deletion-backups/**`, avec retention `30` jours et purge par `purge_private_account_deletion_backups.php`.

Critere d'acceptation :

- [x] une sauvegarde volumineuse ne casse pas la generation ZIP ;
- [x] le chemin de sauvegarde reste hors webroot ;
- [x] les droits fichiers sont controles ;
- [x] la retention est explicite et testable.

Preuves :

- sauvegarde representative locale, alerte seuil, ZIP et droits : `docs/private/recette-preprod-migration-privee/60-v1-local-representative-backup-2026-05-29.txt` ;
- validations locales : `docs/private/recette-preprod-migration-privee/61-v1-local-validation-2026-05-29.txt` ;
- `migration-dod` locale : `docs/private/recette-preprod-migration-privee/62-v1-migration-dod-local-2026-05-29.json` ;
- checklist securite locale : `docs/private/recette-preprod-migration-privee/63-v1-security-checklist-local-2026-05-29.json` ;
- deploiement preprod : `docs/private/recette-preprod-migration-privee/64-v1-deploy-preprod-2026-05-29.txt` ;
- sauvegarde representative preprod, alerte seuil, ZIP, droits et nettoyage : `docs/private/recette-preprod-migration-privee/65-v1-preprod-representative-backup-2026-05-29.txt` ;
- `migration-dod` preprod : `docs/private/recette-preprod-migration-privee/66-v1-migration-dod-preprod-2026-05-29.json` ;
- checklist securite preprod : `docs/private/recette-preprod-migration-privee/67-v1-security-checklist-preprod-2026-05-29.json` ;
- headers preprod : `docs/private/recette-preprod-migration-privee/68-v1-check-security-headers-preprod-2026-05-29.txt`.

### Phase V2 - Emails transactionnels

Objectif : fiabiliser les emails critiques du prive.

Checklist de suivi :

- [x] `BASE_URL` preprod configure sur `https://preprod.lescaramagnols.com`.
- [ ] Auditer les liens absolus activation/reset.
- [ ] Lister les templates emails modifiables en BO.
- [ ] Documenter les variables disponibles par template.
- [ ] Ajouter un mode preview admin sans envoi.
- [ ] Tester les erreurs SMTP sans fuite sensible.
- [ ] Verifier que les tokens ne sont jamais logges en clair.

Travaux :

- [ ] verifier tous les liens absolus construits avec `BASE_URL` ;
- [ ] lister les templates emails modifiables en BO ;
- [ ] documenter les variables disponibles par email ;
- [ ] ajouter un mode preview admin sans envoi reel ;
- [ ] verifier que les tokens ne sont jamais journalises en clair ;
- [ ] verifier les cas d'erreur SMTP avec message utilisateur neutre et log technique.

Critere d'acceptation :

- [ ] les liens activation/reset sont complets ;
- [ ] chaque email critique a un sujet, un corps, une liste de variables et un fallback ;
- [ ] le BO permet de comprendre les variables disponibles ;
- [ ] un echec SMTP ne divulgue pas d'information sensible.

### Phase V3 - Cron et idempotence

Objectif : garantir que les traitements planifies ne doublonnent pas et ne ratent pas les etapes sensibles.

Checklist de suivi :

- [ ] Lister les actions de la cron privee.
- [ ] Confirmer les commandes dry-run sensibles.
- [ ] Verifier la journalisation debut/fin/duree/compteurs.
- [ ] Rejouer J+20 deux fois pour verifier l'absence de doublon email.
- [ ] Rejouer J+30 deux fois pour verifier l'idempotence suppression.
- [ ] Ajouter ou verifier l'alerte d'echec cron.
- [ ] Documenter la commande OVH exacte.

Travaux :

- [ ] lister toutes les actions rattachees a la cron privee ;
- [ ] ajouter un mode dry-run pour les actions destructrices ou sensibles ;
- [ ] journaliser debut, fin, duree, compteurs et erreurs ;
- [ ] verifier l'idempotence des traitements J+20 et J+30 ;
- [ ] ajouter une alerte si une execution cron echoue ;
- [ ] documenter la commande OVH exacte et sa frequence.

Critere d'acceptation :

- [ ] relancer la cron ne renvoie pas deux fois le meme email J+20 ;
- [ ] relancer la cron ne tente pas deux suppressions definitives concurrentes ;
- [ ] une erreur est visible dans les logs d'exploitation ;
- [ ] la commande cron est documentee pour OVH.

### Phase V4 - UX BO et espace prive

Objectif : eviter les regressions d'interface qui rendent les actions sensibles confuses.

Checklist de suivi :

- [x] Regle projet deja presente : actions JS sous vrais boutons.
- [ ] Ajouter une checklist UI commune aux modules prives.
- [ ] Verifier le debordement horizontal desktop/mobile.
- [ ] Verifier le menu gauche fixe sur pages BO concernees.
- [ ] Verifier la visibilite des messages.
- [ ] Verifier les confirmations des actions destructrices.
- [ ] Ajouter un test navigateur ou une recette responsive.

Travaux :

- [ ] ajouter une checklist UI commune aux modules prives ;
- [ ] verifier absence de debordement horizontal sur desktop et mobile ;
- [ ] rendre le menu gauche fixe sur les pages BO concernees ;
- [ ] garantir que les messages restent visibles en haut de viewport ;
- [x] imposer les vrais boutons `<button type="button">` pour les actions JS ;
- [ ] verifier les confirmations sur actions destructrices ;
- [ ] ajouter un test navigateur ou une recette manuelle responsive.

Critere d'acceptation :

- [ ] aucun bloc d'action ne sort de l'ecran ;
- [ ] les actions destructrices restent lisibles et annulables ;
- [ ] les messages ne disparaissent pas en haut de page lors d'un scroll ;
- [ ] les boutons JS fonctionnent au clavier et a la souris.

### Phase V5 - Observabilite exploitation

Objectif : rendre les problemes visibles avant qu'ils deviennent des incidents.

Checklist de suivi :

- [ ] Lister les evenements critiques prives.
- [ ] Verifier ou ajouter une severite claire.
- [ ] Ajouter une synthese periodique des erreurs cron.
- [ ] Verifier l'absence de contenu sensible dans les logs.
- [ ] Definir les seuils d'alerte prives.
- [ ] Tester une alerte backup/cron/email.

Travaux :

- [ ] lister les evenements critiques prives ;
- [ ] ajouter une severite claire : info, warning, error, critical ;
- [ ] ajouter une synthese periodique des erreurs cron ;
- [ ] verifier que les logs ne contiennent pas de contenu sensible ;
- [ ] definir les seuils d'alerte : echec SMTP, echec backup, echec purge, CSRF repetes, login rate limit.

Critere d'acceptation :

- [ ] un incident backup/cron/email laisse une trace exploitable ;
- [ ] les alertes ne contiennent pas de secret ;
- [ ] les logs permettent de reconstituer une action sensible.

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

- [x] `security-checklist` est vert ;
- [x] `migration-dod` est vert ;
- [ ] le runbook preprod est rempli avec la derniere sortie OK ;
- [ ] les scenarios C1, C2 et C3 ont une preuve ;
- [ ] la CSP n'a plus d'exception inline non documentee ;
- [ ] la strategie scan/quarantaine documentaire est decidee et implementee ;
- [ ] aucune trace visible d'anonymisation ne subsiste ;
- [x] les regles templates sont ajoutees au processus de developpement ;
- [ ] les sauvegardes volumineuses sont gerees ou alertees ;
- [ ] les emails transactionnels ont preview, variables documentees et liens absolus ;
- [ ] la cron est idempotente, journalisee et documentee ;
- [ ] les pages BO/private ne debordent pas de l'ecran ;
- [ ] les evenements critiques sont observables sans fuite de secret.

## 14. Decision

Meilleur choix : finaliser la recette et les garanties d'exploitation avant toute nouvelle migration technique.

Ne pas faire maintenant :

- creer une app Symfony separee sans besoin d'exploitation clair ;
- ajouter un scanner antivirus fictif non branche ;
- automatiser une restauration reelle sans validation admin ;
- considerer les controles verts comme un go-live suffisant.
