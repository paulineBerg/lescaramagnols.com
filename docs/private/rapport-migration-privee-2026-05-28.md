# Rapport de migration espace prive

Date : 2026-05-28
Perimetre : migration de l'espace prive Les Caramagnols vers un contexte applicatif prive moderne, securise, auditable et compatible avec une trajectoire Symfony.

## 1. Synthese

La migration est dans un etat preparatoire solide. Les controles executables ajoutes pour les phases M5, M6, 14.6 et 14.7 sont verts.

Statut global : OK pour poursuivre la stabilisation et la recette preproduction.
Statut go-live production : pas encore automatique, car certains controles restent volontairement manuels ou dependent de l'exploitation.

Commandes de controle executees :

```bash
php backend/core/tools/private_migration_reconcile.php m5-plan
php backend/core/tools/private_migration_reconcile.php m6-retirement
php backend/core/tools/private_migration_reconcile.php security-checklist
php backend/core/tools/private_migration_reconcile.php migration-dod
```

Resultats observes :

| Controle | Etat | Resume |
|---|---:|---|
| M5 plan de migration modules | OK | `success=true`, `ready=true` |
| M6 retrait progressif legacy | OK | 53 routes actives, 1 route legacy bloquee, 6 templates actifs, 6 permissions, 4 endpoints fichiers controles |
| 14.6 checklist securite | OK | 19 controles, 19 passes, 0 echec, 3 items de recette manuelle |
| 14.7 Definition of Done | OK | 11 criteres, 11 passes, 0 echec |

## 2. OK

### Architecture privee

- Le prive est maintenant gouverne par un contexte HTTP separe : routes privees, guard prive, headers prives et resolution dediee.
- La trajectoire retenue reste pragmatique pour OVH Performance : PHP moderne et Symfony-compatible, sans ajouter un runtime separe tant que l'hebergement ne le justifie pas.
- Le public PHP conserve ses entrees existantes et reste distinct du contexte prive.

### Modules prives

- `Documents` est traite comme module autonome.
- `Bloc-note` existe comme module prive avec logique dediee.
- `FamilyDiscussion`, `RealEstateRental`, `AgencyImports` et `TaxDeclarationHelper` disposent de contrats de migration identifies.
- Les permissions sont centralisees dans le registre de modules prives.

### Donnees et fichiers

- Les fichiers prives restent hors `backend/public`.
- Les telechargements passent par des routes privees controlees.
- Les donnees locatives et fiscales ont des tables sources identifiees.
- Les exports sont regenerables depuis les donnees sources et journals dedies.
- Les imports agence sont rattaches a des batchs, documents sources, issues, mappings et lignes auditables.

### Securite

- CSRF obligatoire sur les mutations cookie-based.
- Sessions privees isolees.
- Cookies prives durcis : `HttpOnly`, `SameSite=Strict`, `Secure` attendu en HTTPS.
- Headers prives : anti-indexation, frame deny, no-sniff, CSP, referrer policy, permissions policy.
- `robots.txt` ne divulgue pas les chemins admin ou prives.
- Les erreurs sensibles sont masquees.
- Les logs doivent rester sur metadata et actions, sans mots de passe ni tokens.

### Retrait legacy

- Les anciennes routes privees restantes sont inventoriees.
- La route d'anonymisation est bloquee explicitement.
- Les routes legacy doivent rester dans un etat connu : conservees, redirigees, bloquees ou retirees.

### Controle et validation

- `security-checklist` donne un controle executable de la checklist 14.6.
- `migration-dod` donne un controle executable de la Definition of Done 14.7.
- Les deux commandes sortent en JSON et retournent un code non nul en cas d'echec.

## 3. Oublis, dettes ou points non entierement clos

### Go-live production

La migration est techniquement coherente, mais le go-live ne doit pas etre considere comme automatique.

Il reste a faire en exploitation :

- recette navigateur reelle sur preproduction ;
- controle HTTP depuis un domaine HTTPS reel ;
- verification des headers en conditions serveur ;
- controle d'un compte suspendu et d'une permission retiree depuis l'interface ;
- test manuel complet de restauration fichier et base ;
- test de suppression compte suspendu avec sauvegarde, purge donnees, email J+20 et suppression compte J+30.

### CSP

La CSP privee n'autorise pas `unsafe-inline` pour les scripts lorsque le nonce est present. En revanche, une exception `style-src 'unsafe-inline'` reste documentee car plusieurs styles prives sont encore inline.

Ce n'est pas bloquant maintenant, mais il faudra extraire les styles inline vers des assets dedies si l'objectif devient une CSP stricte sans exception.

### Antivirus documentaire

La quarantaine actuelle repose sur le stockage hors webroot, les controles MIME/extension/taille et les routes privees. Aucun scanner antivirus n'est encore branche dans le depot.

Ce point est acceptable en local et preproduction. En production, il faut decider si l'hebergement permet un scanner externe ou une validation differée des uploads sensibles.

### Recette manuelle

Trois controles restent volontairement manuels dans la checklist securite :

- login, logout, timeout, refus CSRF ;
- compte suspendu et permission retiree ;
- restauration fichier et base.

Ces controles ne doivent pas etre oublies avant mise en production. Ils ne sont pas remplaces par les tests unitaires.

### Templates prives

Il reste des templates PHP actifs pour le contexte prive. Ce n'est pas un retour au legacy public, mais ce n'est pas encore une application Symfony separee.

Decision actuelle : conserver ce modele tant que l'hebergement OVH Performance impose une trajectoire PHP simple. Si le projet migre vers VPS, Cloud Web Node ou environnement supervise, une extraction vers Symfony pourra etre reconsideree.

### Nettoyage historique

Le code contient encore des traces historiques de l'ancien vocabulaire autour de l'anonymisation. La route critique est bloquee, mais les noms internes historiques peuvent rester a nettoyer progressivement s'ils ne sont plus utiles.

## 4. Autres points de vigilance

### Sauvegardes

Le principe cible est bon : sauvegarde, purge des donnees, email J+20, suppression compte et sauvegarde J+30.

A surveiller :

- taille des sauvegardes ZIP si les documents deviennent nombreux ;
- droits fichiers `0600` et dossiers non publics ;
- procedure de restauration admin ;
- tracabilite des restaurations reelles.

### Emails transactionnels

Les emails sont essentiels pour activation, reset password, suspension/reactivation et suppression planifiee.

A surveiller :

- liens absolus construits depuis `BASE_URL` ;
- textes modifiables via BO ;
- variables disponibles documentees ;
- logs d'envoi sans fuite de token.

### Cron

La cron existante doit rester le point de passage pour :

- retention discussions ;
- alertes J+20 ;
- suppression compte/sauvegarde J+30 ;
- operations de maintenance privee futures.

A surveiller :

- idempotence des traitements ;
- absence de double email ;
- logs d'execution ;
- alerte en cas d'echec.

### UX BO et espace prive

Les regles ajoutees recemment restent bonnes :

- pas d'element hors ecran ;
- boutons comme vrais `<button type="button">` quand ils declenchent une action JS ;
- menu gauche fixe ;
- messages visibles en haut de l'ecran ;
- rendu responsive sans debordement horizontal.

Ces regles doivent rester des contraintes de developpement pour les futurs modules.

## 5. Recommandations prioritaires

1. Conserver `security-checklist` et `migration-dod` comme gates avant chaque changement important du prive.
2. Ajouter une recette preproduction documentee avec captures ou logs pour les trois tests manuels restants.
3. Extraire progressivement les styles inline prives pour supprimer l'exception CSP `style-src 'unsafe-inline'`.
4. Decider explicitement de la strategie antivirus documentaire en production.
5. Continuer le retrait des traces historiques d'anonymisation si elles ne servent plus aucun flux metier.
6. Ne pas creer une app Symfony separee tant que l'hebergement ne permet pas une exploitation propre et supervisee.

## 6. Decision finale

OK : la migration est coherente, controlee et testable dans le depot.
Oubli principal : ne pas confondre controles automatises verts et recette preproduction complete.
Autre : la meilleure suite est une recette terrain, pas une nouvelle couche technique.
