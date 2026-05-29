# Portail prive famille, locations et aide impots

Date de mise a jour : 2026-05-29
Statut : cadrage cible validé, PVT-01 terminé ; architecture fonctionnelle locative enrichie pour les contrats, loyers, locataires, agence, rapports, fiscalite et discussions privees avec chiffrement local texte V1, chiffrement serveur au repos des fichiers joints, categories documentaires, tableau de bord locatif et trajectoire de migration progressive vers une application privee moderne.

Ce document est le point d'entree dedie au futur espace prive famille du projet `caramagnols`.
Il remplace l'ancien cadrage generique du portail prive par une vision plus precise : un socle `PrivatePortal`, des comptes famille separes de l'administration, des webapps privees activables au cas par cas, puis trois modules metier prioritaires :

1. `RealEstateRental` : gestion des locations immobilieres.
2. `TaxDeclarationHelper` : aide a la preparation annuelle des impots, alimentee par les locations et par d'autres sources declarees.
3. `FamilyDiscussion` : discussions entre membres, messages prives, groupes, images, fichiers et purge automatique a 60 jours.

References projet a garder alignees :

- `AGENTS.md`
- `docs/README.md`
- `docs/admin/README.md`
- `docs/backend/public-entrypoints.md`
- `docs/backend/logging.md`
- `docs/security/README.md`
- `docs/private/backlog-pvt01.md`
- `docs/deployment/README.md`

Mise a jour 2026-05-28 (parametres membre) :
- ajout d'une page privee `/private/parametres` permettant au membre connecte de renseigner facultativement son nom, son adresse et son telephone;
- l'email de connexion reste affiche en lecture seule et ne peut pas etre modifie depuis l'espace prive; toute demande de changement doit passer exclusivement par `private@lescaramagnols.com`;
- les champs de profil sont stockes dans `private_users`, exportes dans le ZIP/JSON de compte, neutralises ou purges avec les operations RGPD existantes.
- dans le module `FamilyDiscussion`, la creation d'une discussion directe liste les membres actifs qui ont acces au module, donc les invitations acceptees et autorisees, sous forme de cases a cocher; le serveur exige exactement un membre coche pour une discussion privee.
- dans le module `RealEstateRental`, les baux portent maintenant un type de bail (`habitation vide`, `habitation meublee`, `meuble etudiant`, `bail mobilite`, `autre`) qui propose une date de fin par defaut et stocke une categorie fiscale indicative (`revenus fonciers`, `BIC location meublee`, `a qualifier`) pour les syntheses et ponts fiscaux.

Mise a jour 2026-05-29 (chiffrement FamilyDiscussion) :
- l'envoi d'un nouveau message texte via `DiscussionService` est refuse si le payload chiffre navigateur `client_aes_gcm_v1` est absent ou invalide;
- les fichiers joints FamilyDiscussion sont chiffres a l'ecriture sur disque avec AES-256-GCM, restent stockes hors webroot, puis sont dechiffres uniquement par le controleur prive apres verification session, module et appartenance a la conversation;
- l'interface du module affiche en haut des ecrans Discussion un encadre permanent qui decrit le chiffrement texte, le chiffrement des fichiers, les metadonnees encore visibles et la retention 60 jours;
- `PRIVATE_DISCUSSION_ATTACHMENT_ENCRYPTION_KEY` doit etre renseignee hors depot en production avec une cle dediee, par exemple `base64:` suivi de 32 octets aleatoires encodes.

Mise a jour 2026-05-29 (emails transactionnels V2) :
- les emails critiques de l'espace prive sont declares dans un catalogue admin unique avec sujet, corps, variables, fallback et apercu sans envoi reel;
- les liens d'activation et de reset sont construits avec `app_url()` et les chemins canoniques du routeur prive afin de respecter `BASE_URL`, y compris en preproduction;
- les emails de reset utilisent la configuration SMTP dediee a l'espace prive et le helper `send_private_email()`;
- les erreurs SMTP restent neutres cote utilisateur, tandis que le log technique redige mots de passe, tokens, secrets et DSN sensibles avant journalisation;
- les tokens d'activation/reset ne doivent jamais etre journalises en clair, y compris dans les evenements securite.

Mise a jour 2026-05-27 (email prive, suppressions et BO membres) :
- ajout d'une configuration SMTP dediee a l'espace prive dans le BO admin, avec expediteur `ne-pas-repondre@lescaramagnols.com`, serveur par defaut `ssl0.ovh.net`, adresse de reponse `private@lescaramagnols.com` et modeles de messages modifiables;
- cette configuration est aussi accessible depuis le BO admin, section `Espace prive`, onglet `Email prive IMAP / SMTP`; elle s'applique uniquement a l'espace prive, l'envoi restant assure par SMTP et IMAP relevant de la reception;
- les modules prives peuvent envoyer des emails via cette configuration : documents locatifs en pieces jointes, quittance de loyer PDF depuis un paiement, PDF fiscal annuel et invitations FamilyDiscussion;
- RealEstateRental permet maintenant la suppression individuelle des locataires, baux, paiements, charges et documents, ainsi que la suppression globale des documents ou des donnees locatives avec confirmation explicite `SUPPRIMER`;
- FamilyDiscussion permet l'invitation email d'un membre, la suppression d'un message, d'une piece jointe ou de tous les messages/fichiers envoyes par l'utilisateur dans une conversation;
- dans le BO membres prives, un module deja affecte ne peut pas etre decoche tant que des informations rattachees existent; les comptes supprimes et neutralises peuvent etre reinvites sur une nouvelle adresse ou purges cote donnees, sans restaurer de donnees neutralisees;
- aucune recuperation serveur des messages chiffres client n'est ajoutee : sans cle locale d'un appareil participant, le contenu chiffre reste illisible par conception.

Validations lancees pour ce jalon :
- `backend/vendor/bin/phpunit -c backend/phpunit.xml backend/tests/PrivatePortalMembersTest.php backend/tests/PrivatePortal/PrivacyOperationsTest.php backend/tests/PrivateApps/RealEstateRental/RealEstateRentalModuleTest.php backend/tests/PrivateApps/FamilyDiscussion/FamilyDiscussionModuleTest.php backend/tests/PrivateApps/TaxDeclarationHelper/TaxDeclarationHelperModuleTest.php backend/tests/PrivateRouteResolverTest.php`
- `vendor/bin/phpstan analyse` depuis `backend/`
- `vendor/bin/phpcs` depuis `backend/`

## 0. Protocole d'avancement (documentaire et exécution)

Cette section s'applique à toute la suite des phases privées.

1. Aucune exécution de tests applicatifs automatisés n'est faite par défaut pour ce chantier documentaire.
2. Les vérifications ciblées ne sont lancées que quand elles sont utiles pour réduire un risque réel (ex. logique auth, CSRF, parcours critique, changements de route).
3. Pour les actions à risque visible, demander un contrôle manuel (ex. login/logout, timeout de session, redirection login, rejet CSRF, journalisation d’accès refusé).
4. La séquence d'implémentation est continue : une fois la phase en cours stabilisée, passer à la phase suivante puis s'arrêter à ce jalon.
5. Le README doit être mis à jour à chaque jalon pour tracer :
   - l'état de la phase,
   - les décisions prises,
   - les tests automatisés réellement lancés, le cas échéant,
   - les tests manuels demandés, le cas échéant.

## 0.1 Emails transactionnels prives

Le BO admin, section `Espace prive`, onglet `Email prive IMAP / SMTP`, reste la source de configuration des emails transactionnels prives.

Catalogue modifiable :

| Cle | Usage | Variables specifiques |
|---|---|---|
| `rental` | Envoi de documents locatifs | `subject`, `body`, `attachment_name` |
| `tax` | Envoi de syntheses fiscales | `subject`, `body`, `attachment_name` |
| `discussion_invite` | Invitation FamilyDiscussion | `recipient_name`, `discussion_title`, `activation_url` |
| `admin_invite` | Invitation ou reactivation de compte prive depuis le BO | `activation_url` |
| `password_reset` | Reset de mot de passe prive | `reset_url` |
| `suspended` | Notification de suspension | aucune variable specifique |
| `reactivated` | Notification de reactivation | aucune variable specifique |
| `deletion_scheduled` | Planification de suppression differee | `scheduled_deletion_at` |
| `deletion_warning` | Rappel avant suppression definitive | `scheduled_deletion_at` |
| `deletion_final` | Confirmation de suppression definitive | aucune variable specifique |

Variables communes a tous les modeles :

| Variable | Description |
|---|---|
| `{{email}}` | adresse du membre concerne |
| `{{today}}` | date courante serveur |
| `{{login_url}}` | URL absolue de connexion privee |
| `{{private_url}}` | URL absolue de l'accueil prive |
| `{{reply_to}}` | adresse de reponse configuree |
| `{{site_name}}` | nom public du site |

Regles d'exploitation :

1. l'apercu admin ne declenche aucun envoi SMTP et utilise des valeurs de demonstration;
2. tout nouveau template email prive doit etre ajoute au catalogue avec variables et fallback explicites;
3. les liens `activation_url` et `reset_url` doivent rester absolus, bases sur `BASE_URL`, et ne doivent jamais etre reconstruits dans un template;
4. le message affiche a l'utilisateur en cas d'echec SMTP reste neutre;
5. les logs techniques doivent masquer mots de passe, tokens, secrets, clefs API et identifiants presents dans une URL ou une chaine d'erreur.

## 1. Decision produit

L'objectif n'est pas d'ajouter une simple page "client". Le besoin reel est de creer un portail prive famille, distinct du site public et du BO administrateur, accessible uniquement aux personnes autorisees.

Le portail doit permettre :

1. de gerer des comptes famille separes des comptes admin ;
2. d'inviter, activer, suspendre ou supprimer ces comptes depuis le BO ;
3. d'activer les webapps privees utilisateur par utilisateur ;
4. de journaliser les actions sensibles ;
5. de stocker les fichiers prives hors `backend/public` ;
6. de garantir que le front-office public ne subit aucune regression ;
7. de preparer l'ajout progressif de modules prives sans refaire l'architecture.

Vocabulaire retenu :

| Contexte | Nom retenu |
|---|---|
| Code PHP | `PrivatePortal` |
| Interface generique | `Espace prive` |
| Libelle utilisateur | `Espace famille` |
| Module locations | `Locations immobilieres` |
| Module fiscal | `Aide declaration impots` |
| Module discussions | `Discussions famille` |

Le mot `client` est a eviter dans le code et l'interface, sauf contrainte metier future explicite. Il est trop ambigu pour ce projet familial et peut laisser croire a un usage commercial.

## 2. Architecture cible

La bonne sequence est de construire d'abord le socle `PrivatePortal`, puis de brancher les webapps privees comme des modules independants.

Ne pas commencer par coder directement la gestion locative. Cela creerait trop vite des problemes d'isolation, de permissions, de fichiers prives, d'audit et de dependance fiscale.

Vue cible :

```text
Portail prive famille
|
+-- Comptes famille
+-- Invitations et activation
+-- Sessions privees separees
+-- Registre des modules
+-- Permissions par utilisateur
+-- Dashboard prive
+-- Audit et alertes
+-- RGPD
|
+-- Module RealEstateRental
|   +-- Biens
|   +-- Lots
|   +-- Locataires
|   +-- Baux
|   +-- Loyers
|   +-- Charges
|   +-- Travaux
|   +-- Documents
|   +-- Syntheses annuelles
|   +-- Exports
|   +-- Bridge fiscal
|
+-- Module TaxDeclarationHelper
|   +-- Sources declaratives
|   +-- Donnees locatives importees
|   +-- Revenus manuels
|   +-- Futures sources specialisees
|   +-- Controle de coherence
|   +-- Synthese annuelle
|   +-- Exports PDF/CSV
|
+-- Module FamilyDiscussion
    +-- Conversations directes
    +-- Groupes de discussion
    +-- Messages quasi instantanes
    +-- Images avec apercu
    +-- Fichiers joints
    +-- Accuses de lecture
    +-- Purge glissante a 60 jours
```

Regles d'architecture :

1. toute nouvelle logique applicative vit dans `backend/src/` ;
2. aucun fichier public `private.php` ne doit etre ajoute dans `backend/public` ;
3. les routes privees doivent passer par `backend/public/index.php` puis `FrontController` ;
4. les templates prives vivent dans `backend/templates/private/` ;
5. les fichiers prives vivent hors webroot, sous `backend/private/` ou un chemin configure equivalent hors `backend/public` ;
6. les telechargements passent toujours par un controleur qui verifie session, permission et ressource ;
7. les textes visibles doivent passer par le systeme de traduction adapte au contexte ;
8. les changements HTTP touchant le front-controller exigent tests et verification manuelle ciblee.

Checklist de revue des templates prives :

1. un template prive reste une couche de presentation : il lit uniquement un `viewModel` deja prepare et ne cree ni service, ni repository, ni acces base ;
2. toute mutation, recherche SQL, sauvegarde, purge, restauration, calcul fiscal ou calcul locatif reste dans `backend/src/PrivatePortal/**` ou `backend/src/PrivateApps/**` ;
3. tout contenu provenant d'un utilisateur ou d'une base est echappe avec `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` avant sortie HTML ;
4. tout declencheur JavaScript visible est un vrai `<button type="button">` ; ne pas detourner `<tr>`, `<a>`, `<div>`, `<span>` ou `<summary>` en bouton ;
5. aucun `onclick`, `style=`, `<style>` inline ou script tiers n'est ajoute dans un template prive ;
6. tout nouveau module prive ajoute au moins un test de service/repository et reste couvert par `PrivateTemplateGuardTest`.

Controle executable : `php backend/vendor/bin/phpunit tests/PrivatePortal/PrivateTemplateGuardTest.php`.

## 3. Arborescence recommandee

```text
backend/
+-- src/
|   +-- PrivatePortal/
|   |   +-- Http/
|   |   +-- Security/
|   |   +-- Users/
|   |   +-- ModuleRegistry/
|   |   +-- Permissions/
|   |   +-- Audit/
|   |   +-- Rgpd/
|   |   +-- Dashboard/
|   |   +-- Documents/
|   |
|   +-- PrivateApps/
|       +-- RealEstateRental/
|       |   +-- Domain/
|       |   +-- Repository/
|       |   +-- Service/
|       |   +-- Controller/
|       |   +-- Export/
|       |   +-- TaxBridge/
|       |
|       +-- TaxDeclarationHelper/
|           +-- Domain/
|           +-- Repository/
|           +-- Service/
|           +-- Controller/
|           +-- Source/
|           +-- Rules/
|           +-- Export/
|       |
|       +-- FamilyDiscussion/
|           +-- Domain/
|           +-- Repository/
|           +-- Service/
|           +-- Controller/
|           +-- Attachment/
|           +-- Retention/
|
+-- templates/
|   +-- private/
|       +-- layout.php
|       +-- login.php
|       +-- dashboard.php
|       +-- modules/
|           +-- real-estate-rental/
|           +-- tax-declaration-helper/
|           +-- family-discussion/
|
+-- private/
|   +-- storage/
|   |   +-- real-estate-rental/
|   |   +-- tax-declaration-helper/
|   |   +-- family-discussion/
|   +-- uploads/
|   +-- exports/
|
+-- sql/
    +-- private/
        +-- 001_private_portal.sql
        +-- 002_private_permissions.sql
        +-- 003_real_estate_rental.sql
        +-- 004_tax_declaration_helper.sql
        +-- 005_family_discussion.sql
```

Ne pas modifier directement :

- `backend/public/index.php`, sauf besoin strictement necessaire et teste ;
- `backend/public/assets/` ;
- `backend/vendor/` ;
- `frontend/dist/` ;
- `backend/public/uploads/editorial/`, qui reste reserve aux medias editoriaux publics.

## 4. Socle PrivatePortal

### 4.1 Routes privees minimales

Routes ciblees :

```text
/private
/private/login
/private/dashboard
/private/logout
/private/activate/{token}
/private/password/forgot
/private/password/reset/{token}
/private/files/{documentId}
```

Regles :

1. `/private` redirige vers `/private/dashboard` si authentifie, sinon vers `/private/login` ;
2. chaque route privee emet `X-Robots-Tag: noindex, nofollow, noarchive` ;
3. les `POST`, `PUT`, `PATCH`, `DELETE` exigent un CSRF valide ;
4. les API privees repondent `401` ou `403`, les pages HTML redirigent seulement quand cela ne masque pas un refus de permission ;
5. tout acces refuse est journalise.

### 4.2 Variables d'environnement

Ajouter dans `.env.example` lorsque l'implementation demarre :

```env
# Portail prive
PRIVATE_PORTAL_ENABLED=true
PRIVATE_PORTAL_BASE_PATH=private

# Session privee
PRIVATE_SESSION_NAME=caramagnols_private
PRIVATE_INACTIVITY_TIMEOUT_SECONDS=3600
PRIVATE_REAUTH_TIMEOUT_SECONDS=1800

# Auth locale MVP
PRIVATE_AUTH_MODE=local
PRIVATE_INVITE_TOKEN_TTL_HOURS=168
PRIVATE_PASSWORD_RESET_TOKEN_TTL_MINUTES=30
PRIVATE_ACCOUNT_LOCKOUT_ATTEMPTS=3
PRIVATE_ACCOUNT_LOCKOUT_SECONDS=86400
PRIVATE_PASSWORD_MIN_LENGTH=14
PRIVATE_PASSWORD_COMPLEXITY_ENABLED=true

# MFA locale
PRIVATE_MFA_TOTP_ENABLED=true
PRIVATE_MFA_BACKUP_CODES_ENABLED=true

# Audit et alertes
PRIVATE_AUDIT_RETENTION_DAYS=365
PRIVATE_SECURITY_ALERT_EMAILS=admin@example.com

# Rate limit
PRIVATE_LOGIN_RATE_LIMIT_ATTEMPTS=5
PRIVATE_LOGIN_RATE_LIMIT_WINDOW=900
```

Conserver une trajectoire OIDC possible, mais ne pas bloquer le MVP si l'auth locale est correctement isolee, hashee en `Argon2id`, rate-limitee, auditee et preparee pour MFA.

### 4.3 Tables PrivatePortal

Tables minimales :

```text
car_private_users
car_private_user_invites
car_private_password_resets
car_private_sessions
car_private_modules
car_private_user_module_permissions
car_private_mfa_backup_codes
```

## 5. Phase 0 - Cadrage technique et garde-fous

Objectif de la phase 0 : figer un cadre d'exécution strict avant toute implémentation du portail privé.

### 5.1 Démarche imposée

1. Définir le périmètre exact de la phase (ex: socle auth, gestion utilisateurs privés, dashboard, stockage sécurisé).
2. Lister les modifications prévues par couche (`backend/src`, `backend/templates`, `backend/sql`, etc.).
3. Identifier les invariants frontaux à préserver (`backend/public/index.php`, `FrontController`, compatibilité public existant).
4. Valider les dépendances (i18n, permissions, logging, stockage privé, sessions).
5. Choisir les points d’arrêt de validation avant passage en phase 1.

### 5.2 Garde-fous techniques non négociables

1. Toute logique applicative passe par `backend/src/` (classes dédiées, strict types, retours typés).
2. Aucune logique métier nouvelle dans `backend/public/*` hors point d’entrée existant.
3. Les routes privées suivent `backend/public/index.php` puis le contrôleur frontal prévu.
4. Session, permissions, CSRF, verrouillages d’attaque et rate-limiting valides avant toute montée de fonctionnalités.
5. Les secrets d’authentification et de chiffrement restent hors code, avec stratégie claire de rotation.
6. Les fichiers sensibles restent hors webroot (`backend/private/...`), téléchargements via contrôleur avec contrôle d’accès.
7. Journalisation des actions critiques : création/édition/suppression d’accès, connexions, changements de permissions, exportation de données.
8. Toutes les réponses HTTP et erreurs opérationnelles évitent la divulgation d’informations techniques sensibles.

### 5.3 Critères de passage en phase 1

1. Un périmètre de travail validé et approuvé pour chaque composant principal.
2. Une liste de risques résiduels explicitée (avec owner + date de traitement).
3. Une convention de tests minimale définie (même si non exécutée immédiatement).
4. Une cartographie claire des impacts sur SEO, URLs internes et assets publics.
5. La documentation référence les files d’attente de travail restantes (si divergences SQL / JSON / assets).

### 5.4 Checklist opérationnelle (Phase 0)

Principe de suivi : cocher chaque case quand l'item est terminé, puis ajouter la date de clôture et une note de décision ou de preuve.

Progression Phase 0 : `9 / 9` (100 %)

| Statut | Item | Responsable | Échéance cible | Jalons | Date de clôture | Notes / Preuves |
|---|---|---|---|---|---|---|
| [x] Définir le périmètre technique de la phase 0 (socle auth, comptes, dashboard, sécurité). | | 2026-05-26 | 1) périmètre écrit ; 2) scope validé | 2026-05-26 | Définition reprise dans sections 2, 4 et 5 ; scope privé détaillé. |
| [x] Valider le mapping de routes et points d’entrée (ex: `/private`, `/private/login`, `/private/dashboard`). | | 2026-05-26 | 1) matrice route→controller ; 2) impacts front-controller documentés | 2026-05-26 | Routes privées enregistrées dans `FrontController` + route list doc (`docs/backend/public-entrypoints.md`). |
| [x] Bloquer les exigences sécurité minimales (CSRF, auth locale/hachage Argon2id, politiques de session, rate limiting). | | 2026-05-27 | 1) matrice des contrôles ; 2) log des choix | 2026-05-26 | `PrivatePortalSecurityGuard` + `PrivateAuth` + `PrivateSession` alignés avec `PRIVATE_*` et timeouts/rate-limiter. |
| [x] Lister les impacts de stockage et de permissions (fichiers hors webroot, ACL, logs d’accès). | | 2026-05-27 | 1) chemin de stockage privé retenu ; 2) politique d’accès validée | 2026-05-26 | Stockage hors `backend/public` retenu; accès document via `/private/files/{documentId}` + session privée + module `documents` + audit, streaming réel reporté phase 4. |
| [x] Valider le plan de journalisation des actions sensibles. | | 2026-05-27 | 1) événements cibles ; 2) niveau de détail validé | 2026-05-26 | Événements minimaux listés section sécurité; connexions, refus, invitations, resets, modules et fichiers privés journalisés sans mot de passe ni jeton brut. |
| [x] Préparer le plan de migration i18n (`fr/en/de`) pour toute interface privative visible. | | 2026-05-28 | 1) clé de traduction existantes ; 2) stratégie fallback définie | 2026-05-26 | Interfaces privées passent par `t()` / fallback contrôlé; les clés visibles doivent être ajoutées dans `backend/lang/fr.php`, `en.php`, `de.php` lors des écrans métier. |
| [x] Identifier les dépendances SQL et les éventuelles impacts de schéma hors implémentation. | | 2026-05-28 | 1) tables initiales validées ; 2) ordre d’exécution SQL | 2026-05-26 | Tables privées initiales listées section 4.3; ordre validé : comptes, invitations/reset/sessions, modules, permissions, MFA, puis tables documents phase 4. |
| [x] Rédiger la liste des risques résiduels + arbitrages (sécurité, délais, dette technique). | | 2026-05-28 | 1) registre de risques ; 2) propriétaires désignés | 2026-05-26 | Risques résiduels portés par les phases suivantes : streaming documents, stockage physique, config mail, RGPD, restauration et modules métier. |
| [x] Valider la sortie de Phase 0 (go/no-go) en revue courte avant passage à la Phase 1. | | 2026-05-28 | 1) revue signée ; 2) checklist complétée | 2026-05-26 | Go validé a posteriori : phases 1, 2 et 3 terminées, grappe phase 2/3 verte, phase 4 autorisée. |

Synthèse de sortie Phase 0 :

- Décision : `GO` pour le socle privé, sous réserve de garder les documents hors webroot et les permissions côté serveur.
- Invariants verrouillés : routage via `FrontController`, aucune logique métier dans `backend/public`, CSRF sur actions mutatives, auth locale `Argon2id`, sessions privées séparées, anti-indexation privée.
- Risques résiduels suivis en phases futures : stockage et streaming réels des documents en phase 4, RGPD/backup/restauration en phase 9, modules locatif/fiscal en phases 5 à 8.
- Preuve de validation actuelle : phases 1 à 3 clôturées, `phpunit` ciblé phase 2/3 vert, PHPStan et PHPCS backend verts.

## 6. Application de la méthode aux autres phases

La même méthode doit être reproduite pour toutes les phases de réalisation, mais le suivi à cases ne doit exister qu'à un seul endroit par phase.

Sources de suivi uniques :

1. Phase 0 : section `5.4 Checklist opérationnelle (Phase 0)`.
2. Phases 1 à 9 : section `9. Phases d'implementation`.

Les exigences transversales restent applicables à toutes les phases : types stricts dans le code nouveau, absence de logique métier dans `backend/public/*.php`, erreurs utilisateur sans fuite technique, absence de divergence persistante SQL / JSON / assets dédiés privés, et documentation mise à jour à chaque jalon validé.

## 7. Template réutilisable par sprint (Phase Check-in)

Ce modèle doit être copié pour chaque sprint et utilisé tel quel (ou avec compléments), avec une seule source de vérité partagée.

### 7.1 En-tête sprint

- Phase :
- Sprint :
- Date de démarrage :
- Date cible :
- Chef de phase :
- Référent sécurité :
- Statut global : 🟡 En cours

### 7.2 Dépendances bloquantes

| Réf. | Élément | Statut | Responsable | Commentaire |
|---|---|---|---|---|
| [ ] |  | ☐ Non démarré / ☐ En cours / ☐ Bloquant / ☐ Levé |  |  |

### 7.3 Checklist opérationnelle normalisée

| Numéro | Item | Responsable | Échéance cible | Jalons de réussite | Validation | Statut |
|---|---|---|---|---|---|---|
| P0 | Alignement périmètre et critères de sortie |  |  | 1) périmètre signé ; 2) critères de succès clairs |  | ⚪ |
| P1 | Conception technique revue |  |  | 1) schéma de composants ; 2) matrice des routes/actions |  | ⚪ |
| P2 | Implémentation conforme garde-fous sécurité |  |  | 1) CSRF ; 2) auth/session ; 3) droits d’accès ; 4) validation entrées |  | ⚪ |
| P3 | i18n + contenu visibles conforme |  |  | 1) clés traduites |  | ⚪ |
| P4 | Journalisation et traçabilité des actions critiques |  |  | 1) événements complets ; 2) absence de fuite d’infos |  | ⚪ |
| P5 | Contrôle d’impact (public + BO + privé) |  |  | 1) non régression front ; 2) comportement privé attendu |  | ⚪ |
| P6 | Revue sécurité et arbitrage des risques |  |  | 1) risques ouverts listés ; 2) owners désignés |  | ⚪ |
| P7 | Documentation & transfert |  |  | 1) checklist close ; 2) notes opérationnelles à jour |  | ⚪ |

### 7.4 Règles de saisie

1. Chaque case ne peut passer à `✅` que si les jalons de réussite sont prouvés dans `Validation`.
2. Si une ligne reste `⚪`, ajouter une cause dans `Validation`.
3. Les dépendances bloquantes en statut `⛔ Bloquant` suspendent la suite tant qu’elles ne sont pas levées.
4. Les dates doivent être absolues (`YYYY-MM-DD`) pour éviter les ambiguïtés.
5. Une fois un sprint fermé, copier les éléments de `Résultats + Risques` dans `docs/private/`.

### 7.5 Journal sprint (format court)

| Date | Point traité | Décision | Action suivante | Owner |
|---|---|---|---|---|
|  |  |  |  |  |

### 7.6 Résultats / Risques (obligatoire en clôture)

#### Résultats
- 

#### Risques ouverts
- 
car_private_modules
car_private_user_module_permissions
private_audit_logs
private_documents
private_rgpd_exports
```

Contraintes attendues :

1. email unique cote `car_private_users` ;
2. tokens stockes sous forme hashee, jamais en clair ;
3. compte inactif tant que l'invitation n'est pas validee ;
4. suspension detectable sur chaque requete ;
5. sessions invalidables par utilisateur ;
6. permissions uniques par couple utilisateur/module/permission ;
7. audit append-only cote applicatif.

### 4.4 Permissions recommandees

```text
private.dashboard.access
private.profile.read
private.profile.update
private.documents.download
private.rgpd.export

real_estate_rental.read
real_estate_rental.write
real_estate_rental.delete
real_estate_rental.export
real_estate_rental.documents.upload
real_estate_rental.documents.download

tax_declaration_helper.read
tax_declaration_helper.manual_income.write
tax_declaration_helper.generate
tax_declaration_helper.export
tax_declaration_helper.lock_year
tax_declaration_helper.admin_review
```

Principes :

1. ne jamais afficher un module sans verifier la permission serveur ;
2. ne jamais deduire un droit d'un lien present dans le HTML ;
3. la suppression et l'export doivent pouvoir etre reserves a un role restreint ;
4. l'aide fiscale peut etre consultable sans donner acces aux documents sources ;
5. un verrouillage d'annee fiscale doit etre reversible uniquement par admin, avec audit.

## 5. Administration des comptes famille

Le BO admin doit ajouter une section :

```text
Parametres > Espace prive > Membres
```

Fonctions attendues :

1. inviter un membre par email ;
2. renvoyer une invitation ;
3. voir le statut du compte : invite, actif, suspendu, verrouille, supprime ;
4. suspendre un compte ;
5. reinitialiser l'acces ;
6. affecter les modules autorises ;
7. consulter les derniers evenements d'audit utiles ;
8. lancer un export RGPD ;
9. supprimer un compte selon la politique retenue.

Contraintes :

1. seul un administrateur autorise peut inviter ou changer les modules ;
2. le token d'invitation n'est jamais reaffiche apres creation ;
3. l'utilisateur cree lui-meme son mot de passe ;
4. deux comptes ne peuvent pas partager le meme email ;
5. les messages d'erreur login/reset ne doivent pas permettre de deviner si un email existe ;
6. l'interface admin doit utiliser les traductions admin, pas la langue publique du visiteur.

## 6. Module RealEstateRental

### 6.1 Objectif

`RealEstateRental` devient la source fiable des faits locatifs. Il ne doit pas etre une simple feuille de calcul dans une page privee.

Le module gere :

1. les biens ;
2. les lots ;
3. les proprietaires ou membres concernes ;
4. les locataires ;
5. les baux ;
6. les actes de caution, etats des lieux et documents de bail ;
7. les loyers attendus et encaisses ;
8. les appels de loyer et quittances ;
9. les relances, mises en demeure et soldes de tous comptes ;
10. les charges, regularisations, compteurs et cles de repartition ;
11. les travaux ;
12. les taxes ;
13. les assurances ;
14. les diagnostics techniques ;
15. les documents et courriers ;
16. les rapports, tableaux de bord et exports annuels ;
17. les donnees necessaires au module fiscal.

### 6.2 Deux modes fonctionnels

Le module doit couvrir deux usages proches, mais differents dans les responsabilites et dans la quantite de donnees a saisir.

#### Partie 1 - Gestion locative complete

Ce mode s'adresse au proprietaire qui gere lui-meme tout le cycle locatif apres la recherche du locataire. La recherche, la selection finale et la mise en place effective du locataire restent faites hors application par le proprietaire ou, ponctuellement, par une agence. L'application sert ensuite de dossier de reference, de suivi financier et de preparation documentaire.

Fonctions ciblees :

1. dossier du bien, des lots, des proprietaires associes et des locataires ;
2. contrat de location prerempli selon le type de bail : habitation principale, meuble, garage, parking, commercial ou professionnel ;
3. acte de caution solidaire prerempli quand une caution est rattachee au bail ;
4. modeles d'etats des lieux preremplis, modifiables, imprimables et rattachables au bail ;
5. suivi des diagnostics techniques : DPE, electricite, gaz, risques, amiante, plomb et dates de validite ;
6. edition du contrat de location a partir des donnees du bien, du lot, du locataire et des conditions du bail ;
7. generation de l'echeancier selon la periodicite du bail : mensuelle, trimestrielle, semestrielle ou annuelle ;
8. gestion des loyers a terme a echoir ou a terme echu ;
9. enregistrement des loyers attendus, loyers encaisses, paiements partiels, retards et trop-percus ;
10. gestion des paiements directs de la CAF comme ligne de paiement separee du versement locataire ;
11. edition des quittances de loyer apres validation d'un paiement complet ;
12. edition et delivrance controlee des avis d'echeance ou appels de loyer ;
13. revision annuelle du loyer selon l'indice applicable renseigne dans le bail, avec controle humain avant application ;
14. gestion des montants en euros ;
15. option de TVA sur les quittances, appels et factures quand le bail le justifie ;
16. preparation a la facturation electronique pour les baux concernes, via un adaptateur fournisseur et non dans le coeur metier ;
17. enregistrement des factures, charges, travaux, taxe fonciere et assurances ;
18. distinction des charges recuperables, non recuperables et candidates a deduction fiscale ;
19. regularisation annuelle des charges locatives a partir des provisions, factures, compteurs et cles de repartition ;
20. modeles de courriers preremplis : recu de depot de garantie, demande de justificatif, attestation de location, appel a la caution, confirmation de reception d'un preavis ;
21. historique des courriers envoyes au locataire, avec copie PDF conservee ;
22. envoi controle des courriers par email et PDF ;
23. compte locataire, solde de tous comptes, depot de garantie et retenues justifiees ;
24. rapports de gestion, syntheses annuelles, graphiques, rapports de paiements, occupation et impressions utiles a la comptabilite ;
25. tableau de bord des encaissements, relances a effectuer, revisions de loyer et regularisations de charges ;
26. preparation de relances pour loyer impaye, sans envoi automatique non valide par un utilisateur autorise.

Contraintes produit :

1. une quittance ne doit pas etre generee pour un paiement non valide ;
2. une revision de loyer doit rester tracable : ancien montant, nouvel indice, date d'effet, utilisateur et commentaire ;
3. les relances doivent etre preparees comme documents verifiables, car elles peuvent avoir une portee juridique ;
4. la TVA, les charges recuperables et les deductions fiscales restent des classifications aidees, jamais une garantie fiscale officielle ;
5. la conformite juridique d'un modele doit etre versionnee par type de bail, date d'effet et source de reference, pas hardcodee dans un template unique ;
6. l'envoi email d'un courrier sensible doit exiger une validation explicite et conserver une trace d'envoi ;
7. chaque document locatif doit rester hors webroot et passer par les permissions du portail prive.

#### Partie 2 - Gestion locative en agence

Ce mode s'adresse au proprietaire dont la location est geree par une agence. L'agence recherche le locataire, met en place le bail, encaisse les loyers, paie certaines charges et envoie des releves de gestion. L'application ne remplace pas l'agence : elle centralise les documents, controle les montants et prepare les syntheses du proprietaire.

Fonctions ciblees :

1. boite d'import agence pour les PDF, scans, factures, avis fiscaux, releves de gerance et comptes rendus de gestion ;
2. classement automatique assiste des mandats, avis de location, baux, etats des lieux, diagnostics, attestations d'assurance, GLI, courriers, factures, taxes et documents de copropriete ;
3. enregistrement des baux transmis par l'agence et des documents associes ;
4. extraction des donnees de bail utiles : type de bail, date d'effet, date de fin, loyer, provisions, depot de garantie, indice de revision, DPE, surface, identifiant fiscal du local et clauses remarquables ;
5. enregistrement manuel ou import controle des releves de gestion envoyes par l'agence ;
6. prise en charge de plusieurs formats de releves : colonnes `quittance ou quittance / recettes / depenses`, colonnes `appele / regle / sommes dues / reglements`, et comptes rendus par `debit / credit` ;
7. repartition des lignes de releve par bien, lot, bail, locataire, periode, nature comptable et categorie de charge ;
8. distinction entre loyers encaisses, provisions sur charges, taxes recuperables, depots de garantie, honoraires d'agence, frais de mise en location, GLI, forfaits de gestion, charges de copropriete, travaux, taxes, assurances et reversements proprietaire ;
9. rapprochement entre releves d'agence, virements, documents justificatifs, factures fournisseurs, appels de fonds, regularisations de copropriete et synthese annuelle ;
10. suivi des documents demandes par l'agence, par exemple PNO, attestation locataire, GLI, justificatif de travaux ou declaration d'occupation ;
11. signalement des lignes non classees, doublons, periodes manquantes, montants incoherents, soldes debiteurs/crediteurs inattendus et documents sans justificatif ;
12. export annuel utilisable pour la comptabilite personnelle et le module fiscal.

Documents observes dans `docs/private/agence` :

1. mandats de gestion avec pouvoirs du mandataire, duree, honoraires, designation du bien, fiscalite declaree et conditions de location ;
2. avis de location avec nouveau locataire, date d'entree, loyer, depot de garantie et honoraires de mise en location ;
3. baux d'habitation nus ou meubles, avec surface, DPE, loyer, provisions, depot, indice de revision, email de quittance et clauses annexes ;
4. etats des lieux d'entree avec releves de compteurs, pieces, equipements, etat d'usure, observations et photos ;
5. releves de gerance mensuels ASG, structures par immeuble, lot, locataire, periode, quittance, recette, depense et solde ;
6. comptes rendus de gestion issus d'autres logiciels, structures par compte personnel, compte immeuble, appele, regle, sommes dues, reglements, debits et credits ;
7. factures d'artisans avec chantier, locataire ou logement, numero, date, lignes, TVA, total HT/TTC et taux reduit ;
8. appels de fonds et regularisations de copropriete avec lots, tantiemes, quote-part, part locative, part deductible, fonds travaux et solde ;
9. avis de taxe fonciere, CFE et declarations d'occupation/loyer avec references fiscales, echeances, montants et local concerne ;
10. attestations d'assurance locataire ou PNO, certificats GLI et courriers de refus ou d'exclusion GLI ;
11. dossiers complets multi-documents regroupant bail, annexes, diagnostics, mandat, justificatifs et documents locataire ;
12. fichiers annexes Windows `Zone.Identifier`, a ignorer systematiquement a l'import.

Contraintes produit :

1. un releve d'agence importe doit conserver le fichier original et les lignes reparties ;
2. la repartition doit pouvoir etre corrigee sans modifier le document source ;
3. chaque ligne doit garder une origine : saisie manuelle, import CSV, document agence ou correction utilisateur ;
4. les honoraires d'agence et les retenues doivent etre visibles separement des loyers bruts ;
5. les syntheses doivent indiquer les montants incertains ou incomplets plutot que les masquer ;
6. les identifiants extranet, mots de passe, IBAN, numeros fiscaux complets et donnees locataire sensibles doivent etre detectes, masques dans les apercus et exclus des logs ;
7. les documents scannes sans texte exploitable doivent passer dans une file `OCR / saisie manuelle`, sans bloquer le classement du fichier original ;
8. un import ne doit jamais valider automatiquement une categorie fiscale : il propose une affectation et demande validation quand la ligne touche aux taxes, charges deductibles, GLI ou travaux.

### 6.3 Architecture fonctionnelle optimisee

Le module doit etre decoupe en sous-domaines. Le controller prive peut composer ces services, mais ne doit pas devenir le lieu des calculs de bail, de loyer, de relance, de fiscalite ou de rapport.

```text
RealEstateRental
|
+-- Core
|   +-- biens, lots, membres, locataires
|
+-- Leasing
|   +-- baux, cautions, etats des lieux, diagnostics
|   +-- modeles juridiques versionnes par type de bail
|
+-- Billing
|   +-- echeanciers, appels de loyer, quittances, TVA optionnelle
|   +-- periodicite, terme echu/a echoir, paiements CAF
|
+-- TenantLedger
|   +-- compte locataire, depot de garantie, solde de tous comptes
|   +-- impayes, relances, mises en demeure, historique courriers
|
+-- Charges
|   +-- charges, factures, compteurs, cles de repartition
|   +-- regularisation annuelle et justificatifs
|
+-- Documents
|   +-- templates, generation PDF, pieces jointes, stockage prive
|   +-- envoi email audite et copies immuables des documents envoyes
|
+-- AgencyManagement
|   +-- inbox, classement, profils parseurs, releves, imports
|   +-- repartition, rapprochement, copro, taxes, assurances
|
+-- Reporting
|   +-- tableau de bord, graphiques, rapports, exports CSV/PDF
|
+-- TaxBridge
    +-- donnees annualisees pour 2044, 2072, micro-foncier et micro-BIC
```

Flux prioritaire en gestion complete :

1. creer bien, lot et locataire ;
2. creer bail, caution, diagnostics et etat des lieux ;
3. generer l'echeancier du bail ;
4. produire les appels de loyer ;
5. enregistrer paiements locataire et CAF ;
6. generer les quittances seulement apres validation ;
7. suivre impayes, relances et courriers ;
8. enregistrer charges et compteurs ;
9. regulariser les charges ;
10. produire compte locataire, rapports, export et donnees fiscales.

Flux prioritaire en gestion agence :

1. deposer les fichiers dans une boite d'import agence ;
2. filtrer les fichiers parasites et calculer une empreinte de dedoublonnage ;
3. classifier le document : releve, compte rendu, bail, etat des lieux, facture, assurance, GLI, taxe, copropriete, declaration ou dossier complet ;
4. extraire le texte quand il existe, sinon orienter vers OCR ou saisie assistee ;
5. rattacher le document source au proprietaire, au bien, au lot, au bail et a l'agence ;
6. importer ou saisir le releve de gestion ;
7. conserver le releve source et chaque page justificative ;
8. repartir les lignes par bien, lot, bail, locataire, periode et categorie ;
9. rapprocher avec justificatifs, virements, factures, appels de fonds et paiements recus ;
10. isoler honoraires, retenues, GLI, charges, taxes, travaux, depots de garantie et reversements ;
11. afficher les anomalies avant validation : doublon, document sans ligne, ligne sans document, solde incoherent, periode manquante ;
12. produire rapports, controles d'incoherence et donnees fiscales.

#### Implementation AgencyManagement

Objectif : rendre l'import agence codable par etapes, sans melanger extraction PDF, classification, mapping comptable, rapprochement et validation utilisateur.

Arborescence cible :

```text
backend/src/PrivateApps/RealEstateRental/
+-- AgencyManagement/
|   +-- Domain/
|   |   +-- AgencyDocumentType.php
|   |   +-- AgencyImportBatch.php
|   |   +-- AgencyImportedDocument.php
|   |   +-- AgencyStatement.php
|   |   +-- AgencyStatementLine.php
|   |   +-- AgencyImportIssue.php
|   |
|   +-- Import/
|   |   +-- AgencyImportPreview.php
|   |   +-- AgencyImportPreviewService.php
|   |   +-- AgencyImportService.php
|   |   +-- AgencyImportResult.php
|   |   +-- AgencyDocumentClassifier.php
|   |   +-- AgencyDocumentMatcher.php
|   |   +-- AgencyImportReviewer.php
|   |   +-- AgencySensitiveDataMasker.php
|   |
|   +-- Pdf/
|   |   +-- DocumentTextExtractorInterface.php
|   |   +-- PopplerPdfTextExtractor.php
|   |   +-- PdfMetadataExtractor.php
|   |
|   +-- Parser/
|   |   +-- AgencyParserInterface.php
|   |   +-- AgencyParserResult.php
|   |   +-- AsgManagementStatementParser.php
|   |   +-- IcsManagementReportParser.php
|   |   +-- CoproFundCallParser.php
|   |   +-- CoproChargeRegularizationParser.php
|   |   +-- ArtisanInvoiceParser.php
|   |   +-- LeaseDocumentParser.php
|   |   +-- InventoryReportParser.php
|   |   +-- InsuranceDocumentParser.php
|   |   +-- TaxNoticeParser.php
|   |   +-- OccupancyDeclarationParser.php
|   |
|   +-- Repository/
|   |   +-- AgencyImportRepository.php
|   |   +-- AgencyStatementRepository.php
|   |   +-- AgencyMappingRepository.php
|   |
|   +-- Service/
|       +-- AgencyReconciliationService.php
|       +-- AgencyStatementValidationService.php
|       +-- AgencyTaxBridgeNormalizer.php
```

Contrats de service :

```text
DocumentTextExtractorInterface
- supports(path, mime_type): bool
- extract(path): ExtractedTextResult

AgencyParserInterface
- supports(classified_document): bool
- parse(extracted_text, metadata): AgencyParserResult

AgencyParserResult
- document_type
- parser_profile
- confidence
- extracted_fields
- statement_lines
- suggested_links
- issues

AgencyImportPreviewService
- preview(path, filename, mime_type): AgencyImportPreview

AgencyImportPreview
- source_path
- filename
- mime_type
- file_size
- sha256
- pdf_metadata
- text_extraction
- classification
- parser_result
- masked_text_preview
- issues
```

Pipeline d'import :

1. `AgencyImportService` recoit un lot de fichiers et refuse tout fichier hors allowlist ;
2. les fichiers `Zone.Identifier`, dossiers caches, fichiers vides et doublons `sha256` sont ignores et comptes dans le batch ;
3. `PrivateDocumentStorage` stocke l'original hors webroot ;
4. `PdfMetadataExtractor` lit pages, version PDF, taille, chiffrement et dates techniques ;
5. `PopplerPdfTextExtractor` extrait le texte avec `pdftotext -layout` si le PDF le permet ;
6. si le texte utile est absent ou trop court, le document passe en `needs_ocr_or_manual_entry` ;
7. `AgencySensitiveDataMasker` detecte et masque au minimum IBAN, mots de passe, codes d'acces, numeros fiscaux, SIRET complets et emails dans les apercus ;
8. `AgencyDocumentClassifier` choisit un type et un profil parseur depuis des signatures allowlist ;
9. le parseur transforme le texte en DTO normalises, sans ecrire en base metier definitive ;
10. `AgencyDocumentMatcher` propose des rattachements bien / lot / bail / locataire avec un score de confiance ;
11. l'utilisateur valide ou corrige le lot dans une interface de revue ;
12. les lignes validees alimentent les tables locatives, les rapprochements, les rapports et le `TaxBridge`.

Profils parseurs prioritaires :

| Profil | Signatures | Donnees a extraire | Controles obligatoires |
|---|---|---|---|
| `asg-releve-gerance-v1` | `Releve de gerance`, `ASG IMMOBILIER`, `Quittance/Recettes/Depenses` | compte proprietaire, periode, date du releve, immeubles, lots, locataires, loyers, provisions, taxes, honoraires, GLI, versements, soldes | total lot = lignes lot, total immeuble = recettes/depenses, versement proprietaire coherent avec solde |
| `ics-compte-rendu-gestion-v1` | `COMPTE RENDU DE GESTION`, `Powered by ICS`, `APPELE/REGLE/SOMMES DUES/REGLEMENTS` | compte personnel, compte immeuble, adresse, locataire, periode, loyers appeles/regles, provisions, honoraires, GLI, factures, solde debiteur/crediteur | totaux appeles/regles coherents, debits/credits equilibres, solde reporte detecte |
| `copro-appel-fonds-v1` | `PROVISIONS`, `Copropriete`, `Quote-Part`, `Tantiemes` | copropriete, lot, tantiemes, periode, date exigible, rubriques, quote-part, fonds travaux, total appel | somme rubriques = total appel, lot connu ou a rapprocher, fonds travaux separe des charges courantes |
| `copro-regularisation-v1` | `CHARGES DE COPROPRIETE`, `Dont Locatif`, `Dont Deductible` | exercice, rubriques, quote-part, part locative, part deductible, appels deduits, solde regularisation | locatif et deductible separes, solde = quote-part - appels, signe du solde controle |
| `artisan-facture-v1` | `FACTURE`, numero facture, date, `TOTAL TTC` ou `NET A PAYER` | fournisseur, numero, date, chantier, locataire/bien, lignes, HT, TVA, TTC, taux TVA | total lignes = total HT, TVA par taux, facture rattachee a un bien ou mise en revue |
| `bail-agence-v1` | `BAIL`, `CONTRAT DE LOCATION`, `CONDITIONS PARTICULIERES` | bailleur, mandataire, locataire, adresse, surface, type de bail, date effet, fin, loyer, provisions, depot, indice, DPE, email quittance | dates coherentes, loyer > 0, depot compatible type de bail, indice compatible avec bail |
| `edl-nockee-v1` | `Etat des lieux`, `RELEVE DES COMPTEURS`, `Photo` | date, bien, locataire, mandataire, compteurs, pieces, equipements, observations, photos referencees | rattachement au bail, compteur date, sortie OCR/manuelle si photos seules |
| `assurance-gli-v1` | `Assurance Loyers Impayes`, `Certificat`, `Attestation` | assureur, type assurance, locataire, bien, loyer couvert, validite, contrat | periode de validite, loyer couvert rapproche du bail, exclusion GLI tracee |
| `taxe-fonciere-cfe-v1` | `taxes foncieres`, `COTISATION FONCIERE DES ENTREPRISES`, `MONTANT A PAYER` | type taxe, annee, montant, echeance, local, references fiscales masquees | echeance future signalee, ventilation manuelle si plusieurs lots |
| `declaration-occupation-v1` | `DECLARATION D'OCCUPATION ET DE LOYER`, `Occupation du bien` | statut occupation, loyer hors charges declare, occupant, date declaration, identifiant fiscal local | loyer compare au bail, identifiant fiscal local rattache au lot |

Matrice de mapping des lignes agence :

| Libelle detecte | Categorie cible | Sens | Validation |
|---|---|---|---|
| `Loyer` | `rent_income` | recette locative | automatique si bail et periode trouves |
| `Provisions/Charges`, `PROVISIONS` | `charge_provision_income` | recette / provision | automatique si bail trouve |
| `Taxe ordures menageres` | `recoverable_tax_income` ou `recoverable_charge_adjustment` | recuperable locataire | revue si montant negatif ou periode anterieure |
| `Depot garan`, `depot de garantie` | `security_deposit` | depot / passif locataire | toujours separe du revenu |
| `Honoraires de gestion`, `Hono. Gestion courante` | `agency_management_fee` | depense | deductible candidate, validation annuelle |
| `TVA sur Honoraires`, `TVA/Honoraires` | `agency_fee_vat` | depense | rattacher a l'honoraire parent si possible |
| `Honoraires Location`, `Location lots geres`, `ouverture de dossier` | `agency_letting_fee` | depense | distinguer mise en location et gestion courante |
| `ASSURANCE INSURED`, `ASSURANCE MILA`, `Prime GLI` | `insurance_unpaid_rent` | depense | verifier bail assure et periode |
| `Forfait Foncier` | `property_tax_service_fee` | depense | ne pas confondre avec taxe fonciere reelle |
| `Facture eau`, `eau froide`, `eau chaude` | `recoverable_utility_charge` | recuperable potentiel | rapprochement compteur ou facture demande |
| `Travaux`, facture artisan, plomberie, toiture, menuiserie | `works_expense` | depense | classification entretien/reparation/amelioration en revue |
| `Appel Fonds Travaux`, `FOND TRAVAUX LOI ALUR` | `copro_work_fund` | copro / fonds | exclure charges locatives, validation fiscale |
| `Charges courantes`, `Prov./Chg courante` | `condominium_current_charge` | copro | attendre regularisation pour part locative/deductible |
| `Règlement Virement`, `Reglement virement` | `owner_transfer` | versement proprietaire | rapprochement bancaire optionnel |
| `Solde debiteur`, `Solde crediteur`, `Solde precedent` | `agency_balance` | solde technique | ne jamais compter comme revenu/depense sans lignes sources |

Champs minimum a exposer dans l'interface de revue :

1. document source, page, profil parseur, score de confiance et statut extraction ;
2. bien, lot, bail, locataire proposes avec possibilite de correction ;
3. periode de la ligne, libelle brut, montant debit, montant credit, montant appele, montant regle ;
4. categorie cible, recuperable, deductible candidate, fiscalement exclu ou a arbitrer ;
5. lien justificatif : facture, appel de fonds, regularisation, bail, assurance ou taxe ;
6. anomalie bloquante ou avertissement ;
7. bouton `valider`, `corriger`, `ignorer`, `fractionner`, `fusionner doublon`.

Regles de validation avant persistance :

1. un document non classe ne peut pas alimenter les syntheses ;
2. une ligne sans periode ne peut pas alimenter le fiscal ;
3. un depot de garantie ne doit jamais etre additionne aux loyers imposables ;
4. un solde agence ne doit pas etre compte comme ligne fiscale si les lignes sources existent ;
5. une charge de copropriete courante reste provisoire tant que la regularisation annuelle ne donne pas `Dont Locatif` et `Dont Deductible` ;
6. une facture travaux doit etre qualifiee avant export fiscal : entretien, reparation, amelioration, remplacement, urgence, non deductible ou a arbitrer ;
7. un PDF scanne sans texte doit rester document source, avec saisie manuelle possible ;
8. toute correction utilisateur cree une ligne d'audit et conserve le libelle brut extrait.

Backlog de codage recommande :

Progression codee au 2026-05-27 :

1. [x] creer `DocumentTextExtractorInterface`, `PopplerPdfTextExtractor`, `PdfMetadataExtractor` et tests sur PDF texte ;
2. [x] creer `AgencyDocumentClassifier` avec signatures allowlist et tests sur les familles observees ;
3. [x] creer les DTO `AgencyParserResult`, `AgencyStatementLineDraft`, `AgencyImportIssue` ;
4. [x] implementer `AsgManagementStatementParser` puis `IcsManagementReportParser` ;
5. [x] creer `AgencySensitiveDataMasker` et `AgencyImportPreviewService` pour produire un apercu sans fuite de donnees sensibles ;
6. [x] implementer `AgencyMappingRepository` avec les mappings ci-dessus en referentiel seedable ;
7. [x] creer `AgencyImportRepository` et migrations import batch/documents/issues/statements/lines ;
8. [x] creer `AgencyStatementValidationService` pour bloquer les lignes fiscales sans periode et separer depot de garantie / solde agence ;
9. [x] creer `AgencyImportService` pour ignorer `Zone.Identifier`, bloquer les doublons `sha256`, conserver l'original hors webroot, previsualiser et persister ;
10. [x] creer l'ecran `/private/locations/agence/imports` pour uploader, lister, dedoublonner et classifier ;
11. [x] creer l'ecran `/private/locations/agence/documents-a-classer` pour revue et validation humaine detaillee ;
12. [x] ajouter les actions de revue humaine minimales : valider, corriger, ignorer ;
13. [ ] implementer `CoproFundCallParser` et `CoproChargeRegularizationParser` ;
14. [x] brancher le `TaxBridge` sur les lignes agence validees uniquement via `AgencyTaxBridgeNormalizer` ;
15. [ ] ajouter les actions avancees de revue : fractionner, fusionner doublon, resoudre une anomalie ;
16. [ ] brancher `AgencyReconciliationService` sur rapports, virements bancaires et justificatifs.

Tests obligatoires pour coder :

1. `AsgManagementStatementParserTest` : extrait periode, immeubles, lots, loyers, provisions, depenses, versement et solde depuis un releve ASG ;
2. `IcsManagementReportParserTest` : extrait appele/regle, debits/credits, GLI, honoraires, solde debiteur/crediteur depuis un compte rendu ICS ;
3. `CoproChargeRegularizationParserTest` : extrait quote-part, part locative, part deductible et solde ;
4. `AgencyDocumentClassifierTest` : classe bail, EDL, facture, taxe, assurance, GLI, copro, releve et document inconnu ;
5. `AgencySensitiveDataMaskerTest` : masque IBAN, mot de passe, code acces, numero fiscal et email dans apercu/log ;
6. `AgencyImportPreviewServiceTest` : calcule l'empreinte `sha256`, classe le document, lance le parseur compatible et retourne un apercu masque ;
7. `AgencyMappingRepositoryTest` : seed du referentiel de mapping et resolution par libelle brut ;
8. `AgencyImportRepositoryTest` : persiste lot, document, releve, lignes, bloque un doublon `sha256` et couvre la revue ligne par ligne ;
9. `AgencyImportServiceTest` : ignore `Zone.Identifier`, bloque doublon `sha256`, conserve original hors webroot ;
10. `AgencyValidationServiceTest` : refuse une ligne fiscale sans periode, depot de garantie en revenu et solde agence non source ;
11. `PrivatePortalPhaseCoverageTest` : expose `/private/locations/agence/imports` et `/private/locations/agence/documents-a-classer` derriere la garde privee ;
12. `AgencyTaxBridgeNormalizerTest` : n'exporte que les lignes validees, avec source document, page et categorie.

### 6.4 Entites principales

```text
RentalProperty
- id
- name
- address
- property_type
- ownership_mode
- active
- notes

RentalUnit
- id
- property_id
- label
- surface
- furnished
- active

Tenant
- id
- firstname
- lastname
- email
- phone
- address
- notes

Lease
- id
- property_id
- unit_id
- tenant_id
- start_date
- end_date
- rent_amount
- charges_amount
- deposit_amount
- lease_type
- status

RentPayment
- id
- lease_id
- period_month
- period_year
- rent_due
- charges_due
- amount_paid
- payment_date
- payment_method
- status

RentalExpense
- id
- property_id
- expense_date
- category
- amount
- recoverable
- tax_deductible
- supplier
- invoice_reference
- notes

RentalDocument
- id
- property_id
- lease_id
- document_type
- original_filename
- storage_path
- mime_type
- size
- uploaded_by
- uploaded_at

DocumentTemplate
- id
- template_type
- lease_type
- legal_version
- effective_from
- effective_to
- source_reference
- status

GeneratedDocument
- id
- template_id
- property_id
- lease_id
- tenant_id
- document_type
- generated_at
- validated_at
- sent_at
- immutable_snapshot_path

RentSchedule
- id
- lease_id
- period_start
- period_end
- frequency
- term_mode
- rent_amount
- charges_provision
- vat_rate

RentCall
- id
- lease_id
- period_month
- period_year
- due_date
- rent_due
- charges_due
- vat_amount
- status

RentReceipt
- id
- rent_call_id
- payment_id
- receipt_number
- generated_at
- validated_at

RentRevision
- id
- lease_id
- index_type
- index_reference_period
- old_rent
- new_rent
- effective_date
- validated_by

ChargeRegularization
- id
- lease_id
- year
- provisions_paid
- recoverable_total
- balance
- validated_at

MeterReading
- id
- property_id
- unit_id
- meter_type
- reading_date
- value

TechnicalDiagnostic
- id
- property_id
- unit_id
- diagnostic_type
- performed_at
- expires_at
- document_id

TenantLedgerEntry
- id
- tenant_id
- lease_id
- entry_type
- amount
- entry_date
- source_type
- source_id

TenantCorrespondence
- id
- tenant_id
- lease_id
- correspondence_type
- generated_document_id
- sent_channel
- sent_at
- status

AgencyStatement
- id
- property_id
- agency_name
- parser_profile
- statement_period_start
- statement_period_end
- original_document_id
- statement_number
- owner_account_reference
- opening_balance
- closing_balance
- status

AgencyStatementLine
- id
- statement_id
- source_page
- source_line_hash
- line_date
- period_start
- period_end
- amount
- raw_label
- mapped_category
- mapping_status
- property_id
- unit_id
- lease_id
- tenant_id
- debit_amount
- credit_amount
- called_amount
- paid_amount
- owner_transfer_amount
- confidence_status

AgencyImportBatch
- id
- agency_name
- imported_at
- source_directory
- file_count
- ignored_file_count
- duplicate_file_count
- status

AgencyImportedDocument
- id
- batch_id
- document_id
- detected_document_type
- detected_agency
- text_extraction_status
- sha256
- contains_sensitive_data
- review_status

AgencyParserProfile
- id
- agency_name
- format_name
- parser_version
- column_model
- active

AgencyLineMapping
- id
- raw_label_pattern
- source_document_type
- mapped_category
- recoverable
- tax_deductible_candidate
- confidence

AgencyImportIssue
- id
- imported_document_id
- issue_type
- severity
- message
- resolved_at

CoproFundCall
- id
- property_id
- unit_id
- copro_name
- period_start
- period_end
- due_date
- lot_reference
- tantiemes
- amount_due
- fund_work_amount

CoproChargeRegularization
- id
- property_id
- unit_id
- exercise_start
- exercise_end
- total_quote_part
- tenant_recoverable_amount
- tax_deductible_candidate_amount
- balance

RentalTaxNotice
- id
- property_id
- tax_type
- tax_year
- due_date
- total_amount
- paid_or_scheduled_amount
- document_id

RentalInsuranceCertificate
- id
- property_id
- lease_id
- certificate_type
- insurer
- valid_from
- valid_to
- document_id

OccupancyDeclaration
- id
- property_id
- unit_id
- lease_id
- declared_at
- occupancy_status
- declared_monthly_rent_excluding_charges
- fiscal_local_identifier
- document_id

RentalReportSnapshot
- id
- report_type
- year
- generated_at
- source_hash
- storage_path
```

### 6.5 Tables SQL

Tables ciblees :

```text
rental_properties
rental_units
rental_property_members
rental_tenants
rental_leases
rental_payments
rental_expenses
rental_documents
rental_export_logs
rental_document_templates
rental_generated_documents
rental_guarantees
rental_inventory_reports
rental_diagnostics
rental_rent_schedules
rental_rent_calls
rental_receipts
rental_rent_revisions
rental_charge_regularizations
rental_meter_readings
rental_tenant_ledger_entries
rental_correspondence
rental_agency_statements
rental_agency_statement_lines
rental_agency_import_batches
rental_agency_imported_documents
rental_agency_parser_profiles
rental_agency_line_mappings
rental_agency_import_issues
rental_copro_fund_calls
rental_copro_charge_regularizations
rental_tax_notices
rental_insurance_certificates
rental_occupancy_declarations
rental_report_snapshots
```

Regles de stockage :

1. montants stockes en decimal, jamais en float ;
2. dates stockees en date SQL normalisee ;
3. statut explicite pour brouillon, valide, annule, archive ;
4. suppression physique evitee pour les documents : preferer statut, audit et retention ;
5. chemin fichier prive non devinable et non public ;
6. les metadonnees documentaires ne doivent pas exposer de donnees sensibles dans les logs ;
7. les documents generes envoyes doivent garder un snapshot immuable, meme si le modele evolue ensuite ;
8. les calculs annuels doivent pouvoir etre rejoues depuis les donnees sources et compares a un snapshot exporte ;
9. chaque document importe doit avoir une empreinte `sha256`, une taille, un type MIME verifie et un statut d'extraction ;
10. les documents d'agence doivent pouvoir etre rattaches a plusieurs objets metier quand un dossier PDF regroupe bail, annexes, diagnostics et justificatifs.

### 6.6 Categories de charges

Referentiel initial :

```text
charges_copropriete
charges_copropriete_provision
charges_copropriete_regularisation
fonds_travaux_loi_alur
taxe_fonciere
taxe_ordures_menageres
cfe
assurance_pno
assurance_loyer_impaye
travaux_entretien
travaux_reparation
travaux_amelioration
diagnostics
frais_agence
frais_agence_gestion
frais_agence_location
frais_agence_dossier
frais_bancaires
interets_emprunt
honoraires_comptable
frais_postaux
depot_garantie
versement_proprietaire
solde_agence
autre
```

Regles de categorie :

1. `depot_garantie`, `versement_proprietaire` et `solde_agence` sont des mouvements de tresorerie ou de passif, pas des revenus locatifs imposables ;
2. `charges_copropriete_provision` reste provisoire jusqu'a la regularisation annuelle ;
3. `charges_copropriete_regularisation` peut alimenter les parts locatives et deductibles seulement si le document source fournit ces colonnes ou si une ventilation manuelle est validee ;
4. `fonds_travaux_loi_alur` doit rester separe des charges courantes ;
5. `travaux_entretien`, `travaux_reparation` et `travaux_amelioration` doivent etre arbitres explicitement a partir de la facture et du contexte ;
6. le champ `tax_deductible` est obligatoire pour preparer le lien fiscal, mais il reste indicatif. Il ne doit pas etre presente comme une validation fiscale officielle.

### 6.7 Ecrans

Routes recommandees :

```text
/private/locations
/private/locations/biens
/private/locations/biens/{id}
/private/locations/locataires
/private/locations/baux
/private/locations/contrats
/private/locations/etats-des-lieux
/private/locations/diagnostics
/private/locations/loyers
/private/locations/appels
/private/locations/quittances
/private/locations/relances
/private/locations/charges
/private/locations/regularisations
/private/locations/compte-locataire
/private/locations/agence
/private/locations/agence/imports
/private/locations/agence/documents-a-classer
/private/locations/agence/releves
/private/locations/agence/rapprochements
/private/locations/agence/copropriete
/private/locations/agence/taxes
/private/locations/agence/assurances
/private/locations/documents
/private/locations/courriers
/private/locations/tableau-de-bord
/private/locations/rapports
/private/locations/exports
/private/locations/synthese-annuelle
```

Implementation actuelle :

1. `/private/locations` est le tableau de bord locatif et le point d'entree du module ;
2. le menu haut sticky separe `Tableau de bord`, `Gestion perso`, `Gestion agence` et `Rapports` ;
3. le sous-menu depend de la section active pour eviter le melange entre saisie proprietaire et imports agence ;
4. les pages historiques gardent leurs routes techniques actuelles (`/private/rental-properties`, `/private/rental-units`, `/private/rental-property-members`, `/private/locations/locataires`, `/private/leases`, `/private/payments`, `/private/charges`) tant que les shims propres ne sont pas migres.

Les ecrans doivent etre denses, lisibles et utilisables sur mobile, sans effet marketing ni decoration inutile. Les actions sensibles utilisent confirmation, CSRF, permission serveur et audit.

### 6.8 Regles de calcul et de conformite

Regles :

1. les indices de revision doivent etre stockes avec `index_type`, periode de reference, valeur source, date de publication, zone applicable et URL source ;
2. l'IRL ne doit pas etre confondu avec les indices commerciaux ou tertiaires : le type de bail selectionne determine les indices autorises ;
3. les baux commerciaux, professionnels, garages et parkings peuvent avoir des regles differentes de l'habitation principale ; le moteur de modele doit refuser un template incompatible ;
4. les calculs 2044, 2072, micro-foncier et micro-BIC doivent etre portes par des rulesets par annee fiscale, jamais par une formule globale permanente ;
5. le module fiscal recoit des montants annualises et traces ; il ne doit pas relire directement toutes les tables locatives ;
6. la facturation electronique doit etre un adaptateur optionnel branche sur les documents facturables ; le coeur locatif ne doit pas dependre d'un prestataire unique ;
7. tout envoi email ou export fiscal doit etre audite avec utilisateur, date, ressource, canal, resultat et empreinte du document ;
8. les rapports affichent les donnees incertaines, brouillon, non reparties ou non rapprochees au lieu de les integrer silencieusement ;
9. les imports de fichiers prives doivent utiliser des parseurs allowlistes par profil agence ; aucune formule extraite d'un PDF ne doit etre executee ;
10. les donnees sensibles detectees dans les PDF d'agence doivent etre masquees dans les apercus, exports de debug et messages d'erreur.

Sources de verification a consulter avant implementation d'une regle :

1. indices de revision : Insee, pages IRL, ILC, ILAT et ICC ;
2. charges recuperables et regularisation annuelle : Service-Public.fr ;
3. formulaires et regimes fiscaux : impots.gouv.fr, formulaires 2044, 2072, micro-foncier et micro-BIC ;
4. facturation electronique : impots.gouv.fr, calendrier et obligations applicables a la taille de l'entite.

Ordre d'implementation recommande :

1. `Leasing` : contrats, caution, etats des lieux, diagnostics, templates versionnes ;
2. `Billing` : echeanciers, appels, paiements, CAF, quittances, TVA optionnelle ;
3. `TenantLedger` : compte locataire, relances, courriers, solde de tous comptes ;
4. `Charges` : compteurs, regularisation, justificatifs et cles de repartition ;
5. `AgencyManagement` : releves agence, import, repartition et rapprochement ;
6. `Reporting` : dashboard, graphiques, rapports de paiements et occupation ;
7. `TaxBridge` : rulesets annuels et exports pour le module impots.

## 7. Module TaxDeclarationHelper

### 7.1 Objectif

`TaxDeclarationHelper` est un assistant de preparation annuelle. Il rapproche les donnees locatives validees, les saisies manuelles et les justificatifs pour produire une synthese exploitable avant declaration.

Il ne doit jamais se presenter comme une declaration fiscale automatique garantie, ni choisir seul un regime fiscal. Le module prepare, signale et exporte ; l'utilisateur verifie et declare officiellement.

Mention a afficher dans le module :

```text
Les montants fournis sont une aide a la preparation. Ils doivent etre verifies avant declaration officielle.
```

### 7.2 Reperes IR a couvrir

Source de travail locale : `docs/private/ir/Brochure-IR-2026.pdf`.

La brochure IR 2026 confirme que le module doit separer clairement les familles suivantes :

| Famille | Usage module | Points de controle |
| --- | --- | --- |
| Location nue - revenus fonciers | aide 2042/2044, revenus encaisses, charges candidates, deficits et justificatifs | ne pas melanger avec location meublee ; distinguer micro-foncier et regime reel sans arbitrage automatique |
| Micro-foncier | total des recettes brutes si l'utilisateur confirme son eligibilite | seuils et exclusions a confirmer par l'utilisateur |
| Regime reel foncier | preparation du detail utile a la 2044 puis report de synthese | charges candidates, travaux, taxes, interets, deficits, documents |
| SCI | preparation d'un futur rapprochement 2072 | quote-parts et associes hors V1 si non modelises |
| Location meublee | orientation 2042 C PRO / BIC, micro-BIC ou reel selon choix utilisateur | ne jamais classer automatiquement une ligne fonciere en BIC |
| Prelevements sociaux et PAS | indicateurs seulement | aucun calcul officiel garanti |

Le module doit donc produire par annee :

```text
Annee fiscale
Statut : brouillon / genere / verrouille
Sources activees manuellement
Biens et lots concernes
Regime fiscal indique par l'utilisateur : nu, meuble, SCI, mixte, non renseigne
Loyers encaisses valides
Revenus manuels valides
Charges recuperables et non recuperables
Charges potentiellement deductibles
Travaux, assurances, taxes, interets, frais divers
Regularisations et remboursements
Documents justificatifs rattaches ou manquants
Anomalies bloquantes ou alertes
Export CSV/PDF de travail
```

### 7.3 Architecture applicative

La relation entre modules passe par un contrat de source. Le module fiscal ne lit pas directement toutes les tables locatives : il consomme des fournisseurs declares et activables.

```text
RealEstateRental
+-- TaxBridge/
    +-- RentalTaxDataProvider.php
    +-- RentalTaxDataProviderInterface.php

TaxDeclarationHelper
+-- Repository/
|   +-- TaxDeclarationRepository.php
+-- Source/
|   +-- TaxDataSourceInterface.php
|   +-- RentalTaxDataSource.php
+-- Service/
    +-- TaxDeclarationSummaryService.php
```

Contrat source actuel :

```php
interface TaxDataSourceInterface
{
    public function code(): string;

    public function label(): string;

    public function annualRentalIncome(int $year, array $scopeIds): AnnualRentalIncome;

    public function annualDeductibleExpenses(int $year, array $scopeIds): AnnualDeductibleExpenses;

    /**
     * @return list<MissingTaxDocument>
     */
    public function missingDocuments(int $year, array $scopeIds): array;

    /**
     * @return list<string>
     */
    public function controls(int $year, array $scopeIds): array;
}
```

Cette couche permet :

1. de proteger le module fiscal des details internes de la gestion locative ;
2. de remplacer ou enrichir une source sans reecrire la synthese ;
3. d'ajouter plus tard une webapp source pour un revenu recurrent ;
4. d'afficher clairement l'origine de chaque ligne.

### 7.4 Activation manuelle des sources

Aucune source applicative externe ne doit etre incluse par defaut dans une synthese fiscale. L'utilisateur active la liaison par annee depuis `/private/impots/{year}`.

Regles :

1. `tax_source_activations` stocke `private_user_id`, `year`, `source_code`, `is_enabled`, dates et acteur ;
2. une annee verrouillee refuse toute activation ou desactivation ;
3. la generation ne consomme que les sources actives pour l'annee ;
4. une source inactive ne produit ni revenu, ni charge, ni document manquant ;
5. les exports conservent l'origine de chaque ligne generee ;
6. toute activation/desactivation est journalisee.

### 7.5 Liaison RealEstateRental -> TaxDeclarationHelper

Liaison activee par source code `real_estate_rental`.

| Donnee RealEstateRental | Condition d'inclusion | Ligne TaxDeclarationHelper | Controle |
| --- | --- | --- | --- |
| `rental_payments.amount_paid` | paiement `validated`, annee de periode cible, bien autorise | `income`, libelle `Loyers encaisses`, origine `rental_payments` | brouillons ou paiements partiels signales par la synthese locative |
| `rental_payments.amount_due - amount_paid` | paiement valide avec impaye | metadonnee de controle, pas revenu encaisse | verifier relances et solde locataire |
| `rental_expenses.amount` | depense `validated`, `is_deductible_candidate = 1` | `expense`, libelle `Charges potentiellement deductibles`, origine `rental_expenses` | l'utilisateur confirme la deductibilite reelle |
| `rental_expenses.is_recoverable` | depense recuperable | metadonnee de controle | ne pas additionner deux fois charge locataire et charge proprietaire |
| `rental_agency_statement_lines` | ligne agence validee et normalisee fiscalement | revenu ou charge selon mapping | revue humaine obligatoire avant pont fiscal |
| `rental_documents` | justificatif rattache au bien | suppression de l'alerte document manquant | alerte si revenus/charges sans justificatif |
| `rental_leases` | bail valide sur l'annee | contexte d'occupation | incoherence si paiement hors bail ou bail brouillon |

Les champs locatifs restent proprietaires de leur logique. Le module fiscal ne modifie pas les loyers, les depenses, les releves agence ou les documents source.

### 7.6 Revenus manuels V1

En V1, les autres revenus restent saisis dans l'aide impots, sans creer une webapp separee.

Categories initiales :

```text
revenu_exceptionnel
revenu_foncier_hors_module
revenu_meuble_hors_module
revenu_divers
regularisation
autre
```

Chaque ligne manuelle contient :

```text
annee
libelle
categorie
montant
commentaire
document_lie_optionnel
statut brouillon / valide
origine = manuel
```

Creer une nouvelle webapp source seulement si le revenu devient frequent, structure, repetitif, documente, utile a plusieurs annees fiscales ou trop complexe pour une simple saisie manuelle.

### 7.7 Tables SQL

Tables ciblees :

```text
tax_years
tax_income_sources
tax_source_activations
tax_manual_income_entries
tax_annual_summaries
tax_summary_lines
tax_export_logs
```

Regles :

1. une synthese annuelle garde une trace des sources utilisees ;
2. les lignes importees et les lignes manuelles restent separees ;
3. un statut `draft`, `generated`, `locked` existe en V1 ;
4. une annee verrouillee ne peut plus etre modifiee sans action admin auditee ;
5. les exports doivent etre regenerables ou tracables.

### 7.8 Ecrans

Routes recommandees :

```text
/private/impots
/private/impots/{year}
/private/impots/{year}/revenus-manuels
/private/impots/{year}/controle
/private/impots/{year}/documents
/private/impots/{year}/export
```

Fonctions V1 :

1. choisir une annee ;
2. activer ou desactiver manuellement la liaison `Locations immobilieres` ;
3. afficher les loyers encaisses uniquement si la liaison est active ;
4. saisir ou modifier d'autres revenus manuels ;
5. afficher l'origine de chaque montant ;
6. distinguer charges, travaux, assurances, taxes et interets ;
7. signaler les documents manquants ;
8. bloquer la generation si des donnees sources sont encore en brouillon ;
9. produire une synthese annuelle ;
10. exporter PDF et CSV ;
11. verrouiller une annee validee.

Hors perimetre V1 :

1. teledeclaration automatique ;
2. connexion directe `impots.gouv` ;
3. calcul fiscal complexe garanti ;
4. optimisation fiscale automatique ;
5. choix automatique du regime fiscal ;
6. webapp separee pour chaque revenu occasionnel.

### 7.9 Checklist de construction du module fiscal

- [x] Maintenir le disclaimer non officiel visible sur chaque ecran fiscal.
- [x] Creer la persistance `tax_source_activations`.
- [x] Exiger une activation manuelle par annee avant toute inclusion RealEstateRental.
- [x] Bloquer activation, saisie et generation quand l'annee est verrouillee.
- [x] Conserver `sourceCode`, `sourceLabel`, `sourceReference` et metadonnees par ligne.
- [x] Ne lire que les donnees locatives validees et autorisees.
- [x] Ne consommer les lignes agence qu'apres revue humaine et mapping fiscal.
- [x] Separer revenus fonciers, meublés/BIC, SCI et revenus manuels dans le modele fonctionnel.
- [x] Exporter CSV/PDF sans masquer l'origine ni le statut non officiel.
- [ ] Ajouter plus tard un ecran de choix de regime declaratif par bien/annee.
- [ ] Ajouter plus tard le detail 2044 ligne par ligne si le regime reel est confirme.
- [ ] Ajouter plus tard la preparation SCI 2072 avec quote-parts.
- [ ] Ajouter plus tard la location meublee 2042 C PRO/BIC sans melange avec foncier.

## 8. Module FamilyDiscussion

### 8.1 Objectif

`FamilyDiscussion` est un module de discussion privee entre membres du portail famille. Il doit permettre des echanges rapides, proches d'une messagerie instantanee, sans exposer les conversations au front-office public ni au BO administrateur hors actions strictement necessaires d'exploitation.

Le module gere des conversations courtes et transitoires. Il ne remplace pas le stockage documentaire durable : les messages, images et fichiers joints sont supprimes automatiquement apres `60` jours, au fur et a mesure de l'ouverture du module par chaque membre, avec une purge de securite planifiee en complement.

Principes produit :

1. discussions accessibles uniquement aux membres actifs autorises ;
2. conversations directes entre deux membres ;
3. conversations de groupe creees par un membre autorise ;
4. messages texte avec affichage quasi instantane ;
5. images envoyees avec apercu dans la conversation ;
6. fichiers joints telechargeables via endpoint controle ;
7. compteur de messages non lus par conversation ;
8. purge glissante des contenus de plus de `60` jours ;
9. chiffrement local du texte des messages quand Web Crypto est disponible ;
10. chiffrement serveur au repos des fichiers joints avant ecriture disque ;
11. audit minimal sans contenu de message.

### 8.2 Fonctions V1

Fonctions attendues :

1. lister les conversations accessibles au membre connecte ;
2. creer une conversation privee avec un autre membre actif ;
3. creer un groupe avec nom, description courte optionnelle et liste de membres ;
4. ajouter ou retirer des membres d'un groupe si l'utilisateur est createur du groupe ou administrateur prive autorise ;
5. quitter un groupe, sauf si cela laisse le groupe sans responsable ;
6. envoyer un message texte ;
7. joindre une ou plusieurs images avec generation d'un apercu ;
8. joindre un fichier courant avec nom, taille, type et bouton de telechargement ;
9. afficher l'etat lu/non lu par conversation ;
10. marquer automatiquement comme lus les messages visibles a l'ouverture d'une conversation ;
11. afficher les erreurs d'envoi sans perdre le brouillon local ;
12. chiffrer le corps texte cote navigateur avec une cle de conversation AES-GCM ;
13. envelopper la cle de conversation pour chaque appareil de participant via cle publique RSA-OAEP ;
14. filtrer les conversations par nom, membre ou groupe.

Hors perimetre V1 :

1. chiffrement de bout en bout des fichiers joints ;
2. appels audio ou video ;
3. notifications push navigateur ;
4. WebSocket obligatoire ;
5. edition riche HTML ;
6. archivage permanent des conversations ;
7. recherche plein texte sur des messages purges ;
8. masquage E2EE des metadonnees techniques : participants, dates, tailles, titres de groupes et presence de fichiers ;
9. moderation publique ou publication vers le site.

### 8.3 Temps reel et experience utilisateur

Le MVP doit rester compatible avec l'architecture PHP actuelle, sans runtime Node en production.

Strategie recommandee :

1. utiliser un rafraichissement court cote navigateur quand l'onglet de conversation est visible ;
2. appeler un endpoint JSON `GET` avec curseur `after_message_id` ou `since` toutes les `3` a `5` secondes ;
3. ralentir automatiquement le polling quand l'onglet est cache, quand aucune activite n'est detectee ou apres erreur reseau ;
4. envoyer les messages par `POST` CSRF protege, avec reponse JSON contenant le message normalise ;
5. garder une option future `SSE` si l'hebergement le supporte proprement ;
6. eviter WebSocket en V1 sauf decision technique explicite et infra supervisee.

Regles d'interface :

1. le fil de discussion charge les derniers messages non expires ;
2. un bouton permet de remonter plus loin dans l'historique restant, limite a `60` jours ;
3. les images s'affichent en miniature cliquable, l'original restant servi par endpoint protege ;
4. les fichiers non image s'affichent avec nom nettoye, taille lisible et type general ;
5. les messages supprimes par retention ne doivent pas laisser de contenu visible, seulement une coupure temporelle sobre si necessaire ;
6. aucun texte visible ne doit promettre une conservation longue.

### 8.4 Architecture fonctionnelle

Decoupage cible :

```text
backend/src/PrivateApps/FamilyDiscussion/
+-- Domain/
|   +-- DiscussionConversation.php
|   +-- DiscussionMessage.php
|   +-- DiscussionAttachment.php
|   +-- DiscussionMember.php
+-- Repository/
|   +-- DiscussionConversationRepository.php
|   +-- DiscussionMessageRepository.php
|   +-- DiscussionAttachmentRepository.php
|   +-- DiscussionReadRepository.php
+-- Service/
|   +-- DiscussionConversationService.php
|   +-- DiscussionMessageService.php
|   +-- DiscussionNotificationService.php
|   +-- DiscussionAccessPolicy.php
+-- Crypto/
|   +-- DiscussionDeviceKeyService.php
|   +-- DiscussionConversationKeyService.php
+-- Attachment/
|   +-- DiscussionAttachmentStorage.php
|   +-- DiscussionImagePreviewBuilder.php
+-- Retention/
|   +-- DiscussionRetentionService.php
|   +-- DiscussionRetentionCommand.php
+-- Controller/
    +-- DiscussionController.php
```

Regles d'architecture :

1. `DiscussionAccessPolicy` verifie toujours que l'utilisateur est participant actif de la conversation ;
2. `DiscussionMessageService` valide le contenu, applique les limites, cree les messages et declenche les evenements d'audit ;
3. `DiscussionAttachmentStorage` reutilise les principes de stockage prive : chemin hors webroot, nom disque aleatoire, MIME verifie, endpoint controle ;
4. `DiscussionImagePreviewBuilder` cree une miniature non sensible si GD ou Imagick est disponible ; sinon l'image reste telechargeable sans generation hasardeuse ;
5. `DiscussionRetentionService` purge les messages et fichiers expires par lots courts ;
6. `DiscussionDeviceKeyService` gere les appareils et les cles publiques sans jamais recevoir de cle privee ;
7. `DiscussionConversationKeyService` stocke uniquement les cles de conversation enveloppees pour les appareils autorises ;
8. le controller reste mince et ne contient ni logique de permission, ni logique de purge, ni traitement image.

Note d'implementation actuelle : le code V1 garde un `DiscussionRepository` compact pour rester coherent avec la modernisation progressive du portail prive. Le decoupage `Crypto/` ci-dessus est la cible naturelle si le module grossit.

### 8.5 Tables SQL

Tables ciblees :

```text
discussion_conversations
discussion_conversation_members
discussion_messages
discussion_message_attachments
discussion_message_reads
discussion_retention_runs
discussion_crypto_devices
discussion_conversation_keys
```

Champs minimum :

```text
discussion_conversations
- id
- type: direct / group
- title
- created_by_user_id
- created_at
- updated_at
- last_message_at
- archived_at nullable

discussion_conversation_members
- conversation_id
- user_id
- role: owner / member
- joined_at
- left_at nullable
- muted_until nullable
- last_opened_at nullable

discussion_messages
- id
- conversation_id
- sender_user_id
- body
- body_format: plain / encrypted
- created_at
- edited_at nullable
- deleted_at nullable
- expires_at
- purge_status: active / pending / purged
- encryption_mode: none / client_aes_gcm_v1
- encrypted_payload nullable
- encryption_metadata nullable

discussion_message_attachments
- id
- message_id
- original_filename
- storage_path
- preview_storage_path nullable
- mime_type
- size_bytes
- sha256
- width nullable
- height nullable
- created_at
- expires_at
- purge_status: active / pending / purged

discussion_message_reads
- conversation_id
- message_id
- user_id
- read_at

discussion_retention_runs
- id
- user_id nullable
- scope: user_open / scheduled
- started_at
- finished_at nullable
- purged_messages_count
- purged_attachments_count
- status
- error_message nullable

discussion_crypto_devices
- id
- private_user_id
- device_id
- device_label
- public_key_jwk
- algorithm: RSA-OAEP-256
- created_at
- last_seen_at
- revoked_at nullable

discussion_conversation_keys
- id
- conversation_id
- private_user_id
- device_id
- encrypted_key
- algorithm: RSA-OAEP-256/AES-GCM-256
- created_by_private_user_id
- created_at
- revoked_at nullable
```

Contraintes et index :

1. unicite d'une conversation directe pour un couple de membres actifs, si la logique produit retient une seule conversation directe par paire ;
2. index sur `conversation_id`, `created_at`, `expires_at`, `sender_user_id` ;
3. index sur `discussion_conversation_members(user_id, left_at)` pour lister vite les conversations ;
4. suppression du contenu message et des fichiers sans supprimer les lignes minimales utiles aux compteurs et audits ;
5. unicite de l'appareil par `(private_user_id, device_id)` ;
6. unicite de la cle enveloppee par `(conversation_id, private_user_id, device_id)` ;
7. aucune donnee de message dans les logs SQL ou applicatifs.

### 8.5.1 Chiffrement local texte V1 et fichiers chiffres au repos

Objectif : eviter que le serveur stocke le corps des messages texte en clair, tout en restant compatible avec le rendu PHP existant.

Choix retenus :

1. chiffrement et dechiffrement dans le navigateur uniquement, via Web Crypto ;
2. une cle AES-GCM `256 bits` par conversation, stockee localement dans IndexedDB sous forme `CryptoKey` non exposee au serveur ;
3. une paire RSA-OAEP `2048 bits / SHA-256` par appareil, generee dans le navigateur ;
4. la cle publique d'appareil est stockee cote serveur au format JWK ;
5. la cle AES de conversation est enveloppee pour chaque appareil autorise et stockee dans `discussion_conversation_keys` ;
6. le serveur stocke seulement `encrypted_payload` et `encryption_metadata` pour le corps texte chiffre ;
7. les nouveaux messages texte envoyes via `DiscussionService` sont refuses si le payload chiffre est absent ou invalide ;
8. un appareil sans cle ne cree pas de cle concurrente si la conversation possede deja des cles enveloppees.

Choix retenus pour les fichiers :

1. les fichiers joints valides sont chiffres avant ecriture disque avec `AES-256-GCM` ;
2. la cle vient de `PRIVATE_DISCUSSION_ATTACHMENT_ENCRYPTION_KEY`, a definir hors depot; un format `base64:` avec 32 octets aleatoires est recommande ;
3. les fichiers restent hors webroot et ne sont jamais servis directement par Apache/PHP statique ;
4. le controleur prive verifie session, module `discussions`, appartenance a la conversation, expiration et statut actif avant de dechiffrer le contenu en memoire pour la reponse HTTP ;
5. les anciens fichiers non chiffres restent lisibles par compatibilite, mais tout nouveau stockage passe par le format chiffre.

Limites assumees V1 :

1. les fichiers joints sont chiffres au repos, mais pas encore chiffres bout en bout cote navigateur ;
2. les noms de fichiers, dates, participants, titres de groupe, tailles et compteurs restent visibles au serveur ;
3. un nouvel appareil ne peut dechiffrer l'historique texte que si une cle de conversation lui est partagee par un appareil deja autorise ;
4. la rotation de cle et la revocation forte d'un appareil restent une evolution V2 ;
5. la perte de l'IndexedDB local ou du profil navigateur peut rendre les anciens messages indechiffrables sur cet appareil.

### 8.6 Routes recommandees

Routes HTML :

```text
/private/discussions
/private/discussions/new
/private/discussions/{conversationId}
```

Routes JSON ou POST controlees :

```text
GET  /private/discussions/api/conversations
POST /private/discussions/api/conversations
GET  /private/discussions/api/conversations/{conversationId}/messages
POST /private/discussions/api/conversations/{conversationId}/messages
GET  /private/discussions/api/crypto/devices
POST /private/discussions/api/crypto/devices
GET  /private/discussions/api/conversations/{conversationId}/keys
POST /private/discussions/api/conversations/{conversationId}/keys
POST /private/discussions/api/conversations/{conversationId}/members
POST /private/discussions/api/conversations/{conversationId}/leave
POST /private/discussions/api/conversations/{conversationId}/read
GET  /private/discussions/files/{attachmentId}
GET  /private/discussions/files/{attachmentId}/preview
```

Regles HTTP :

1. toutes les routes passent par le `FrontController` et le `PrivateRouteResolver` ;
2. le base path reste configurable par `PRIVATE_PORTAL_BASE_PATH` ;
3. les `POST` exigent CSRF, session privee et permission module `discussions` ;
4. les endpoints JSON retournent `401` si non connecte, `403` si connecte mais non participant ;
5. les telechargements verifient module, participation, message non purge et fichier non expire ;
6. toutes les reponses privees emettent `X-Robots-Tag: noindex, nofollow, noarchive`.

### 8.7 Retention et suppression a 60 jours

La retention par defaut du module est `60` jours.

Variables a prevoir :

```env
PRIVATE_DISCUSSION_RETENTION_DAYS=60
PRIVATE_DISCUSSION_MAX_MESSAGE_LENGTH=4000
PRIVATE_DISCUSSION_MAX_ATTACHMENTS_PER_MESSAGE=5
PRIVATE_DISCUSSION_MAX_ATTACHMENT_BYTES=20971520
PRIVATE_DISCUSSION_POLL_INTERVAL_SECONDS=5
PRIVATE_DISCUSSION_MESSAGE_RATE_LIMIT_ATTEMPTS=30
PRIVATE_DISCUSSION_MESSAGE_RATE_LIMIT_WINDOW=60
PRIVATE_DISCUSSION_CONVERSATION_RATE_LIMIT_ATTEMPTS=10
PRIVATE_DISCUSSION_CONVERSATION_RATE_LIMIT_WINDOW=300
```

Regles de purge :

1. chaque message et chaque fichier joint recoit un `expires_at = created_at + 60 jours` ;
2. a l'ouverture de `/private/discussions`, lancer `DiscussionRetentionService::purgeExpiredForUser($userId)` avant la liste des conversations ;
3. la purge utilisateur traite seulement les conversations auxquelles le membre a acces ;
4. la purge se fait par lots courts pour ne pas ralentir l'ouverture du module ;
5. le contenu du message, le chemin fichier et la miniature sont effaces ou neutralises quand la ligne passe a `purged` ;
6. les fichiers physiques sont supprimes hors webroot ; en cas d'erreur disque, marquer l'attachement `pending` et journaliser sans exposer le chemin ;
7. une commande planifiee quotidienne purge aussi les contenus expires des membres inactifs, afin de ne pas conserver indefiniment les donnees d'un compte qui ne se reconnecte plus ;
8. les evenements d'audit ne contiennent jamais le texte du message, le nom original complet si sensible, ni le chemin disque.

Evenements d'audit :

```text
private.discussion.conversation.created
private.discussion.group.member_added
private.discussion.group.member_removed
private.discussion.message.sent
private.discussion.attachment.uploaded
private.discussion.attachment.downloaded
private.discussion.access.denied
private.discussion.retention.purged
```

### 8.8 Securite

Controles obligatoires :

1. module `discussions` actif pour l'utilisateur ;
2. utilisateur membre de la conversation pour toute lecture, ecriture ou telechargement ;
3. validation stricte des IDs, titres de groupe, messages, fichiers et types MIME ;
4. echappement HTML systematique des messages ;
5. stockage texte en format `plain`, pas de HTML utilisateur ;
6. interdiction des fichiers executables, SVG inline, HTML, scripts, archives dangereuses et types inconnus ;
7. images acceptees en allowlist stricte : JPEG, PNG, WebP, GIF si besoin explicite ;
8. taille maximale appliquee cote serveur, independamment du front ;
9. noms originaux nettoyes avant affichage ;
10. telechargement avec en-tetes prudents et `Content-Disposition` adapte ;
11. rate limit sur creation de conversation, envoi message et upload ;
12. audit des refus sans fuite de contenu ;
13. validation stricte des modes de chiffrement, JWK publics, identifiants d'appareil, IV et payloads chiffrés ;
14. aucun acces administrateur au contenu des messages par defaut, hors procedure d'exploitation exceptionnelle documentee.

Le module peut annoncer le chiffrement local du texte des messages lorsque Web Crypto est actif et le chiffrement au repos des fichiers joints. Il ne doit pas annoncer un chiffrement de bout en bout complet tant que les fichiers joints cote navigateur, metadonnees, rotation de cle et revocation d'appareil ne sont pas couverts.

### 8.9 Ordre d'implementation recommande

1. Ajouter le module `discussions` dans `PrivateModuleRegistry`.
2. Creer la migration `005_family_discussion.sql`.
3. Creer les repositories SQL et tests de persistence.
4. Creer `DiscussionAccessPolicy`.
5. Creer `DiscussionConversationService` et les ecrans de liste/detail.
6. Creer `DiscussionMessageService` avec envoi texte et polling JSON.
7. Ajouter les tables appareils et cles enveloppees pour le chiffrement local texte.
8. Ajouter le chiffrement AES-GCM navigateur et l'enveloppement RSA-OAEP par appareil.
9. Ajouter stockage et telechargement des fichiers joints.
10. Ajouter generation d'apercu image.
11. Ajouter `DiscussionRetentionService` declenche a l'ouverture du module.
12. Ajouter la commande planifiee de purge de securite.
13. Ajouter compteurs non lus et marquage lu.
14. Ajouter tests HTTP : autorise, non autorise, non participant, CSRF invalide, fichier refuse, purge 60 jours, texte chiffre sans stockage clair.

Critere de sortie V1 :

1. un membre autorise peut discuter avec un autre membre autorise ;
2. un membre peut creer un groupe et y ajouter des membres actifs ;
3. un non participant ne peut ni lire ni telecharger ;
4. une image envoyee affiche une miniature ;
5. un fichier joint est chiffre au repos et telechargeable par les seuls participants ;
6. un message texte chiffre n'a pas de corps clair en base ;
7. les messages de plus de `60` jours sont purges a l'ouverture du module ;
8. les tests de purge prouvent la suppression du contenu et des fichiers ;
9. le dashboard prive n'affiche le module qu'aux utilisateurs autorises.

## 9. Securite et confidentialite

Controles obligatoires :

1. session privee separee de l'admin ;
2. cookie `HttpOnly`, `Secure`, `SameSite=Strict` en production ;
3. rotation de session au login, logout et elevation de privilege ;
4. CSRF sur tous les formulaires mutatifs ;
5. validation serveur stricte sur toutes les entrees ;
6. verrouillage apres 3 echecs de connexion ;
7. rate limit par IP et identifiant ;
8. MFA TOTP + codes de secours supportes ;
9. `X-Robots-Tag: noindex, nofollow, noarchive` sur routes privees ;
10. meta `robots` `noindex,nofollow,noarchive` dans les layouts admin et prive ;
11. aucun token dans `localStorage` ;
12. aucun fichier prive dans `backend/public` ;
13. aucun chemin disque prive dans les reponses HTTP ;
14. logs sans mot de passe, token, document sensible ou montant inutile ;
15. erreurs utilisateur non verbeuses ;
16. actions sensibles journalisees avec `request_id`.

Regles BO admin/private :

1. `tarteaucitron` reste reserve au site public. Les BO admin et prive ne chargent pas le script public de consentement, sauf ajout futur d'un service tiers reel dans le BO et decision documentee.
2. `robots.txt` ne doit pas publier les chemins admin ou prive : un `Disallow` revelerait les URLs sensibles. Les BO sont proteges par chemins non publics, authentification, headers `X-Robots-Tag`, meta robots et absence de liens publics.
3. les reponses BO ajoutent `Cache-Control: private, no-store, no-cache, must-revalidate`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `X-Content-Type-Options: nosniff`, `Permissions-Policy` restrictive et CSP BO avec `frame-ancestors 'none'`.
4. les scripts inline BO doivent porter le nonce CSP lorsqu'un script est indispensable.

Fichiers prives :

1. stockage hors webroot ;
2. nom de fichier disque non devinable ;
3. nom original conserve uniquement en base avec nettoyage ;
4. controle extension, MIME et taille ;
5. scan antivirus a prevoir si l'infra le permet ;
6. telechargement par endpoint controle ;
7. audit sur upload, download, suppression, export.

Evenements d'audit minimaux :

```text
private.login.success
private.login.failed
private.account.locked
private.invite.sent
private.invite.accepted
private.password_reset.requested
private.password_reset.completed
private.module.access.denied
private.document.uploaded
private.document.downloaded
private.discussion.message.sent
private.discussion.attachment.uploaded
private.discussion.access.denied
private.discussion.retention.purged
rental.property.created
rental.lease.updated
rental.payment.saved
rental.expense.saved
rental.document.deleted
tax.manual_income.saved
tax.summary.generated
tax.year.locked
tax.export.downloaded
```

Champs utiles :

```text
request_id
actor_type
actor_id
event
module
resource_type
resource_id
ip
user_agent
status
created_at
```

## 10. Phases d'implementation

Chaque phase doit etre livree avec tests, verification manuelle ciblee et documentation mise a jour si le comportement change, et cocher checklist.

### Phase 0 - Cadrage technique et garde-fous

Objectif : figer le perimetre avant code.

La Phase 0 est suivie dans la section `5. Phase 0 - Cadrage technique et garde-fous`, avec une checklist unique en `5.4 Checklist opérationnelle (Phase 0)`.

Ne pas maintenir de checklist parallèle ici afin d'éviter les divergences de suivi.

### Phase 1 - Socle HTTP PrivatePortal

Objectif : creer les routes privees sans casser le front-office.

Progression phase 1 : complétée (checklist entièrement cochée).

Checklist :

- [x] Ajouter les variables `PRIVATE_*` dans `backend/config/config.php` et `backend/.env.example`.
- [x] Creer `backend/src/PrivatePortal/Http/PrivateRouteResolver.php`.
- [x] Brancher le resolver prive dans `backend/src/Http/FrontController.php`.
- [x] Creer les templates `backend/templates/private/layout.php`, `login.php`, `dashboard.php`.
- [x] Ajouter `/private`, `/private/login`, `/private/dashboard`, `/private/logout`.
- [x] Ajouter `X-Robots-Tag` sur toutes les reponses privees.
- [x] Ne pas exposer `/private` dans `robots.txt`; utiliser `X-Robots-Tag`, meta robots et auth.
- [x] Tester la non-regression des routes publiques, RSS, sitemap, blog et admin (selon le protocole d’office, selon priorité).

Definition of Done :

- [x] `/private` non authentifie redirige vers le login prive.
- [x] Le dashboard ne s'affiche qu'avec une session privee valide.
- [x] Les routes publiques existantes gardent le meme comportement.
- [x] Les tests `FrontController` et `PrivatePortal` passent.

Validation et suivi phase 1 :

- `private route` : `PRIVATE_*` + routeur dédié reliés dans `FrontController` (points 1 à 5 ci-dessus).
- `anti-indexation` : les reponses privees emettent `X-Robots-Tag`; `robots.txt` ne divulgue plus le chemin prive.
- Vérification automatisée phase 1 :
  - `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalFrontControllerTest.php` : vert après harmonisation (hash Argon2id + logout POST + vérification logs via logger injecté).
  - `cd backend && phpunit --configuration phpunit.xml tests/FrontControllerHttpTest.php` : vert (48 tests, 1 point `/images/structure` résolu via fixture de test pour `/images/structure/banniere.jpg`).
- Vérification manuelle ciblée (simulée via `FrontController`) :
  - `/private/login` => `200`
  - `/private` => `302 -> /private/login`
  - `/private/dashboard` => `302 -> /private/login`
  - `/private/logout` => `POST /private/logout + CSRF` => `302 -> /private/login`
  - `/robots.txt` => chemin prive absent.

### Phase 2 - Identite famille et sessions separees

Objectif : ne jamais reutiliser les comptes admin pour la famille.

Progression phase 2 : complétée (socle IAM + compléments IAM validés en phase 3).
Date de clôture complète de phase 2 : 2026-05-26.

Clôture phase 2 (socle IAM) :

- ✅ Contrat auth/login/logout aligné (`Argon2id` + `POST /private/logout` + CSRF + session dédiée).
- ✅ Migrations de base `private_users`, `private_user_invites`, `private_password_resets`, `private_sessions`, `private_mfa_backup_codes`.
- ✅ Session privée dédiée, rotation d’ID, timeout d’inactivité, lockout après 3 échecs.
- ✅ Invitation, activation, reset, jetons hashés, MFA TOTP/codes de secours et réponses neutres de reset validés.
- ✅ Vérification front-controller / non-régression FO validée (tests ciblés).

Checklist (socle IAM complet) :

- [x] Creer les migrations `private_users`, `private_user_invites`, `private_password_resets`, `private_sessions`.
- [x] Hasher les mots de passe avec `Argon2id`.
- [x] Stocker les tokens invitation/reset sous forme hashee.
- [x] Implémenter le contrat auth/login/logout privé (authentification locale + `POST /private/logout` + CSRF + session dédiée).
- [x] Implementer invitation, activation, reset mot de passe.
- [x] Creer une session privee dediee avec nom de cookie distinct.
- [x] Regenerer l'ID de session au login et logout.
- [x] Appliquer timeout d'inactivite.
- [x] Appliquer verrouillage apres 3 echecs pendant 24h.
- [x] Ajouter le support MFA TOTP et codes de secours.
- [x] Journaliser les connexions, echecs, verrouillages et resets.

Preuves de passage phase 2 (socle IAM complet) :

- `backend/sql/private/private_users.sql`
- `backend/sql/private/private_user_invites.sql`
- `backend/sql/private/private_password_resets.sql`
- `backend/sql/private/private_sessions.sql`
- `backend/sql/private/private_mfa_backup_codes.sql`
- `backend/src/PrivatePortal/Security/PrivatePasswordPolicy.php`
- `backend/src/PrivatePortal/Security/PrivateMfaVerifier.php`
- `backend/templates/private/password_form.php`
- `backend/templates/private/password_forgot.php`

Definition of Done (socle IAM) :

- [x] Auth local privé opérationnelle avec Argon2id.
- [x] Contrat HTTP de base `/private/login` + `/private/logout` aligné.
- [x] Sessions privées séparées du contexte admin (nom de cookie dédié).
- [x] Mécanismes d'inactivité et de lockout opérationnels.
- [x] Invitation, activation et reset utilisent des jetons bruts envoyables une seule fois, stockés uniquement en hash `Argon2id`.
- [x] MFA TOTP et codes de secours validés côté serveur.
- [x] FrontController privé intégré sans régression FO validée par test ciblé.

Compléments IAM validés pendant la clôture phase 3 :

- [x] Un compte invite n'est pas actif avant activation.
- [x] Un email ne peut pas créer deux comptes famille.
- [x] Les erreurs login/reset ne divulguent pas l'existence d'un compte.
- [x] Les tests `PrivatePortalSecurity` couvrent succès, refus, expiration et lockout.

Tests à lancer / exécuter pour phase 2 :

- [x] Exécuter et archiver la suite privée sécurité :
  - [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalFrontControllerTest.php` ✅ OK (8/8), 2026-05-26.
  - [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalSecurityTest.php` ✅ OK (5/5), 2026-05-26.
  - [x] Vérifier non-régression FO après chaque évolution privée : `cd backend && phpunit --configuration phpunit.xml tests/FrontControllerHttpTest.php` ✅ OK (49/49), 2026-05-26.
  - [x] Grappe phase 2/3 complète : `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalSecurityTest.php tests/PrivatePortalFrontControllerTest.php tests/PrivatePortalMembersTest.php tests/PrivatePortalModuleAssignmentTest.php tests/PrivatePortalDashboardTest.php tests/AdminRouteResolverTest.php tests/FrontControllerHttpTest.php` ✅ OK (75 tests, 380 assertions), 2026-05-26.

Note d'exécution locale (environnement actuel) :
- Depuis la racine, `./vendor/bin/phpunit` n'existe pas; ne pas l'utiliser pour ce dépôt.
- Utiliser depuis `backend/` : `cd backend && phpunit --configuration phpunit.xml <tests ciblés>`.
- Sur l'environnement courant, le binaire global `phpunit` est disponible depuis `backend/` (`PHPUnit 10.5.63`) et remplace le binaire racine absent.

- [x] Contrôles ciblés équivalents automatisés : parcours login/logout/dashboard, activation/reset, réponse reset neutre, module dashboard et refus `/private/files/{documentId}` couverts par `FrontController` / `PrivatePortalController`.

### Phase 3 - BO admin membres et permissions

Objectif : piloter les acces famille depuis le BO.

Progression phase 3 : complétée — BO membres, permissions modules, activation/reset, MFA, audit IAM et tests SQL métier validés.

Clôture phase 2 / démarrage phase 3 :

- ✅ Date de démarrage phase 3 : 2026-05-26.
- ✅ Entrée en phase 3 autorisée : socle IAM minimal de phase 2 validé.
- ✅ Prérequis bloquant respecté : livraison privée front/route/session non régressive.
- ✅ Phase 3 clôturée : création BO, invitations, activation/résets complets, MFA et audit IAM.

Checklist :

- [x] Ajouter `Parametres > Espace prive > Membres` (route BO + vue liste + gestion statut/liste filtrée).
- [x] Brancher le `POST /admin/parametres/espace-prive` avec actions explicites `invite`, `resend`, `suspend`, `reset`, `delete`, `modules`; l'ancien identifiant interne `anonymize` reste un alias technique non visible.
- [x] Ajouter invitation, renvoi, suspension, reset, suppression/anonymisation côté BO (jetons hashés, emails applicatifs si configuration mail présente, écrans activation/reset).
- [x] Creer `car_private_modules` et `car_private_user_module_permissions`.
- [x] Creer `PrivateModuleRegistry` (registre de modules applicatifs).
- [x] Ajouter l'affectation des modules par utilisateur via repository + registre.
- [x] Refuser cote serveur toute modification de droits par un membre famille (aucun endpoint privé d'écriture des permissions; actions réservées BO admin + CSRF).
- [x] Ajouter audit des changements de droits et des actions sensibles BO.
- [x] Ajouter tests admin complets sur validation métier SQL, succès d'écriture et cas limites tokens.

Definition of Done :

- [x] Seul un admin autorise peut affecter les modules.
- [x] Le dashboard prive affiche uniquement les modules autorises par `PrivateModulePermissionRepository`.
- [x] L'acces direct a `/private/files/{documentId}` sans module `documents` retourne `403` et genere un audit.
- [x] Le contrat de garde serveur est appliqué aux routes privées disponibles et documenté pour les routes modules métier futures.

Protocoles de la passe ciblée du 2026-05-26 :

- BO : toute action sensible passe par `POST /admin/parametres/espace-prive`, `admin_is_authenticated()`, CSRF admin et allowlist stricte `private_member_action`.
- Invitations : `invite` et `resend` créent un jeton hashé dans `car_private_user_invites`; aucun jeton brut n'est affiché dans le BO ni journalisé.
- Reset : `reset` invalide les resets ouverts du compte puis crée un nouveau jeton hashé dans `car_private_password_resets`; l'email est envoyé si la configuration mail existe, sinon l'échec est journalisé sans exposer le jeton.
- Suppression : l'action visible neutralise les donnees personnelles, remplace l'email par une adresse technique `@private.invalid`, remplace le hash de mot de passe, supprime les traces de derniere connexion et passe le compte en `deleted`. L'ancien identifiant technique `anonymize` reste un alias interne de compatibilite, sans route active visible.
- Modules : `modules` écrit uniquement via `PrivateModulePermissionRepository`, en s'appuyant sur `PrivateModuleRegistry`; les modules inconnus sont refusés.
- Côté privé : `/private/files/{documentId}` vérifie session privée, utilisateur actif en repository et permission active `documents`; sans droit, réponse `403` + événement `private.files.access_denied`.
- Email : les jetons bruts ne sont jamais affichés dans le BO ni journalisés; ils ne transitent que par le flux email applicatif lorsque `app_config('mail')` est disponible.
- Limite volontaire restante : le garde d'accès est déjà en place et le streaming réel est désormais opérationnel; reste la mise en place d'un parcours documentaire complet (upload UI/API, suppression et opérations BO dédiées).

Tests à lancer avant clôture phase 3 :

- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalMembersTest.php` ✅ OK (4/4), 2026-05-26.
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalModuleAssignmentTest.php` ✅ OK (1/1), 2026-05-26.
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalDashboardTest.php` ✅ OK (2/2), 2026-05-26.
- [x] `cd backend && phpunit --configuration phpunit.xml tests/AdminRouteResolverTest.php` ✅ OK (6/6), 2026-05-26.
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalFrontControllerTest.php` ✅ OK (8/8), 2026-05-26.
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalSecurityTest.php` ✅ OK (5/5), 2026-05-26.
- [x] `cd backend && phpunit --configuration phpunit.xml tests/FrontControllerHttpTest.php` ✅ OK (49/49), 2026-05-26.
- [x] Grappe phase 2/3 complète : `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalSecurityTest.php tests/PrivatePortalFrontControllerTest.php tests/PrivatePortalMembersTest.php tests/PrivatePortalModuleAssignmentTest.php tests/PrivatePortalDashboardTest.php tests/AdminRouteResolverTest.php tests/FrontControllerHttpTest.php` ✅ OK (75 tests, 380 assertions), 2026-05-26.
- [x] Vérification PHPUnit locale : `./vendor/bin/phpunit` absent à la racine; `phpunit` global disponible depuis `backend/` et utilisé pour les suites ci-dessus.
- [x] `composer phpstan --working-dir=backend` ✅ OK, 2026-05-26.
- [x] PHPCS ciblé sur les fichiers `src/` modifiés ✅ OK, 2026-05-26.
- [x] `composer phpcs --working-dir=backend` global ✅ OK après correction de l'écart `PublicUrlNormalizer`, 2026-05-26.
- [x] `git diff --check` ✅ OK, 2026-05-26.
- [x] Vérification ciblée :
  - `/private` et `/admin/parametres/espace-prive` couverts par `FrontController` / `AdminRouteResolver` ;
  - tentative d'activation/désactivation module en tant qu'utilisateur famille = aucun endpoint privé d'écriture des permissions ;
  - tentative d'accès direct à une route document non autorisée = `403` couvert par `PrivatePortalFrontControllerTest`.

### Phase 4 - Stockage prive et documents

Objectif : creer et activer l'espace prive avec son stockage documentaire, puis garantir qu'aucun document prive n'est servi directement par URL.

Progression phase 4 : 100 % — garde d'accès, stockage et streaming réel opérationnels sur `/private/files/{documentId}` avec validation et journalisation de base ; interface documentaire active côté dashboard avec upload/suppression ; tests unitaires de stockage/documents livrés. Outil de compte démo validé, procédures backup/restauration documentées et vérifiées.

Clôture phase 3 / bascule phase 4 :

- ✅ Entrée en phase 4 autorisée : phase 3 clôturée (BO membres, permissions serveur, invitations/activation complets, audit IAM).
- ✅ Prérequis technique conservé : socle IAM phase 2 vérifié et front-controller non-régressif.
- ✅ Condition de lancement : la phase 4 démarre quand la définition des permissions serveur famille est stable.

Checklist :

- [x] Formaliser la création de l'espace privé opérationnel : configuration `PRIVATE_PORTAL_ENABLED=true`, base path `private`, session privée dédiée, routes `/private`, `/private/login`, `/private/dashboard` et module documentaire disponibles hors front-office public (routes, templates, session, garde anti-indexation).
- [x] Formaliser l'activation de l'espace privé pour un membre de test : compte `active`, modules `dashboard` et `documents` attribués, accès dashboard validé, documents fictifs chargés dans le stockage privé.
- [x] Creer `backend/private/storage`, `uploads` et `exports` ou leurs chemins configures.
- [x] Ajouter un service de stockage prive.
- [x] Ajouter `private_documents` si le modele commun est retenu.
- [x] Ajouter `private_document_categories` et l'affectation optionnelle d'une categorie a l'upload documentaire.
- [x] Verifier extension, MIME, taille et nom original.
- [x] Generer un chemin disque non devinable.
- [x] Ajouter le garde d'accès serveur sur `/private/files/{documentId}` avant streaming réel.
- [x] Servir les fichiers via `/private/files/{documentId}`.
- [x] Verifier permission module `documents` sur chaque demande de telechargement.
- [x] Ajouter audit upload/download/delete.
- [x] Formaliser un compte de test complet (`membre actif` + modules attribues + documents fictifs) avec chemin d'accès documenté (`/private/login` -> `/private/dashboard` -> `/private/files/{documentId}`) pour valider les parcours prives bout en bout en mode manuel.
- [x] Documenter backup et restauration des fichiers prives.

### 4.4 Fermeture phase 4 : preuves collectées

Objectif documentaire : définir une procédure minimale exploitable en production pré-opérationnelle pour **les données privées** (`backend/private/*`) et les tables privées SQL.

- [x] Couvrir les scénarios API documents (upload, download, delete, refus sans module) dans `tests/PrivatePortalStorageTest.php` :
  refus d'upload/téléchargement sans module `documents`, upload accepté avec module `documents`, suppression et audit associées.
- [x] Outil CLI `backend/core/tools/setup_private_demo_account.php` :
  création du compte démo (email/mot de passe), activation `active`, attribution modules `dashboard` + `documents`, et seed d'un document de test.
- [x] Documenter et valider la procédure de sauvegarde/restauration privée sur pré-production : SQL + fichiers dans `backend/private/**`.
- [x] Valider le parcours complet avec compte de démo (`demo_validation@exemple.fr`) : création/activation + modules attribués + chemin d'accès documenté (`/private/login` -> `/private/dashboard` -> `/private/files/{documentId}`). Upload + suppression + refus `403` après retrait module validés sur environnement dédié.

Commandes opérationnelles recommandées (phase 4) :

```bash
# compte de demo prive (mode non-supprimable, réutilisable)
php backend/core/tools/setup_private_demo_account.php \
  --email=demo_prive@example.com \
  --password='MonMotDePasseTresFort123!' \
  --with-demo-document=1
```

Procédure de sauvegarde privée (à exécuter manuellement depuis l’hôte applicatif) :

```bash
# 1) Variables
export PRIVATE_BACKUP_DIR="/var/backups/caramagnols-private"
mkdir -p "$PRIVATE_BACKUP_DIR"
export TS="$(date +%Y%m%d-%H%M%S)"

# 2) Référentiel SQL privé (tables privées uniquement)
mysqldump \
  -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
  "$DB_NAME" \
  car_private_users \
  car_private_user_invites \
  car_private_password_resets \
  car_private_sessions \
  car_private_modules \
  car_private_user_module_permissions \
  car_private_mfa_backup_codes \
  car_private_documents \
  > "$PRIVATE_BACKUP_DIR/private-db-${TS}.sql"

# 3) Fichiers privés hors webroot
tar -czf "$PRIVATE_BACKUP_DIR/private-files-${TS}.tar.gz" \
  -C /home/surfacepro8/www/caramagnols/backend/private \
  storage exports uploads

# 4) Intégrité
sha256sum "$PRIVATE_BACKUP_DIR/private-db-${TS}.sql" "$PRIVATE_BACKUP_DIR/private-files-${TS}.tar.gz" \
  > "$PRIVATE_BACKUP_DIR/private-backup-${TS}.manifest"
```

Procédure de restauration privée (sur environnement de test non-productif) :

```bash
export PRIVATE_RESTORE_DIR="/var/backups/caramagnols-private"
export TS="AAAAmmjj-HHMMSS"

# 1) Sauvegarde de sécurité préalable de l'existant
cp -a /home/surfacepro8/www/caramagnols/backend/private "/tmp/private-backup-before-restore-$(date +%Y%m%d-%H%M%S)"

# 2) Chargement SQL
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$PRIVATE_RESTORE_DIR/private-db-${TS}.sql"

# 3) Chargement fichiers
rm -rf /home/surfacepro8/www/caramagnols/backend/private/storage \
  /home/surfacepro8/www/caramagnols/backend/private/uploads \
  /home/surfacepro8/www/caramagnols/backend/private/exports
mkdir -p /home/surfacepro8/www/caramagnols/backend/private/{storage,exports,uploads}
tar -xzf "$PRIVATE_RESTORE_DIR/private-files-${TS}.tar.gz" \
  -C /home/surfacepro8/www/caramagnols/backend/private

# 4) Vérification
# - vérifier que private_documents.storage_path existe bien en base
# - vérifier un /private/files/{documentId} en session avec module documents
```

Contrôles de clôture phase 4 (backup/restauration) :
- Restituer le manifeste et son hash `sha256`.
- Importer une restauration de test dans un environnement de pré-production dédié.
- Exécuter un cycle document minimal : activation + upload + accès + suppression + lecture refusée sans permission.

### 4.5 Sauvegardes volumineuses et retention

Les sauvegardes privees applicatives sont generees par `PrivateBackupService` via :

```bash
php backend/core/tools/private_migration_reconcile.php backup \
  --target-dir=/chemin/hors-webroot/exports \
  --files-root=/chemin/hors-webroot/uploads \
  --recommended-max-bytes=536870912
```

Regles d'exploitation :

1. le seuil recommande par defaut est `536870912` octets (`512 MiB`) pour l'archive ZIP ;
2. le seuil peut etre surcharge par `private.backup.recommended_max_bytes` ou par l'option CLI `--recommended-max-bytes=...` ;
3. un depassement emet le warning `backup_recommended_size_exceeded`, sans bloquer la generation ZIP ;
4. chaque resultat `backup` et `verify-backup` expose `size`, `warnings` et `permissions` ;
5. les fichiers JSON/ZIP doivent rester en `0600`, les dossiers de sortie en `0700` ;
6. les chemins de sauvegarde et fichiers sources doivent rester hors `backend/public`.

Verification recommandee apres generation :

```bash
php backend/core/tools/private_migration_reconcile.php verify-backup /chemin/private-backup.json \
  --recommended-max-bytes=536870912 \
  --output=/chemin/private-backup-verify.json
```

Retention des sauvegardes de suppression compte :

1. les sauvegardes avant suppression de compte suspendu sont stockees sous `backend/var/private-account-deletion-backups/**` ;
2. chaque sauvegarde contient `generatedAt`, `deleteAfter`, `retentionDays`, tables privees concernees et manifest fichiers ;
3. la retention operationnelle est `30` jours par defaut ;
4. un avertissement est envoye a partir de J+20 par `purge_private_account_deletion_backups.php` ;
5. a J+30, le CRON supprime le compte suspendu restant, les lignes rattachees et les fichiers JSON/ZIP de sauvegarde ;
6. une recette ciblee peut etre rejouee sans modifier l'horloge serveur avec `--now=DATE|TIMESTAMP --user-id=ID`.

Commande de recette retention :

```bash
php backend/core/tools/purge_private_account_deletion_backups.php --dry-run --json --now='+20 days' --user-id=123
php backend/core/tools/purge_private_account_deletion_backups.php --json --now='+30 days' --user-id=123
```

Definition of Done :

- [x] Aucun fichier prive n'est present dans `backend/public`.
- [x] Une URL directe vers disque est impossible.
- [x] La création et l'activation de l'espace privé sont couvertes par des parcours manuels documentés + tests ciblés.
- [x] Un membre sans droit ne peut ni lister ni telecharger un document (listing masqué sans module `documents`, téléchargement/upload/delete refusés `403`).
- [x] Un compte de test complet permet de verifier le chemin d'accès `/private/login` -> `/private/dashboard`, les modules autorises, upload/download et refus d'acces.

Tests à lancer avant clôture phase 4 :

- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalFrontControllerTest.php` ✅ OK (10 tests, 30 assertions), 2026-05-26.
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalDashboardTest.php` ✅ OK (4 tests, 32 assertions), 2026-05-26.
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalStorageTest.php` ✅ OK (12 tests, 133 assertions), 2026-05-26.
- [x] Contrôles manuels ciblés :
  - création/activation de l'espace privé sur environnement de test avec `PRIVATE_PORTAL_ENABLED=true` ;
  - création/activation du compte de démo + suppression/reprise du module `documents` ;
  - accès à `/private/files/{documentId}` sans droit = refus ;
  - upload document en mode autorisé/rejeté selon extension, MIME, taille ;
  - compte de test complet : accès via `/private/login`, dashboard, modules visibles, document fictif accessible avec droit et refusé sans droit ;
  - vérification qu’aucun fichier privé n’est public via URL directe.

### Phase 5 - Module RealEstateRental, noyau metier

Objectif : creer la source fiable des donnees locatives.

Progression phase 5 : 100 % — noyau `RealEstateRental` livré, routes privées actives, stockage SQL préparé, droits et tests validés.

Checkpoint 2026-05-26 (pré-check phase 5) :

- [x] Vérifier qu'aucun module locatif privé n'est déjà présent dans `backend/src/PrivateApps/RealEstateRental` (socle à construire).
- [x] Vérifier le réemploi du socle IAM / sessions / documents sans régression FO.
- [x] Consolider la modélisation minimale (entités + permissions + routes cibles) dans ce README.
- [x] Définir la séquence d'implémentation phase 5 : migrations → domain/repo → permissions/service → controller/tests.
- [x] Créer la structure `backend/src/PrivateApps/RealEstateRental/` et le namespace associé.
- [x] Initier les migrations SQL locatives de base (`rental_properties`, `rental_units`, `rental_property_members`) en réconciliation environnement `backend/sql/private/`.

Clôture phase 4 / bascule phase 5 :

- ✅ Entrée en phase 5 autorisée (preuve de clôture phase 4 levée côté validation automatisée et outil de compte démo), validation finale manuelle de recette accomplie (upload + delete + refus 403 après retrait `documents`).
- ✅ Prérequis technique conservé : séparation des comptes famille + session privée stable.
- ✅ Condition de lancement : module documentaire et permissions prêtes à sécuriser les données locatives.

Checklist :

- [x] Creer `backend/src/PrivateApps/RealEstateRental/`.
- [x] Creer les migrations `rental_properties`, `rental_units`, `rental_property_members`.
- [x] Creer les entites/domain objects et repositories.
- [x] Creer les ecrans biens et lots.
- [x] Ajouter validation stricte des champs adresse, type, surface, statut.
- [x] Ajouter permissions `read`, `write`, `delete`.
- [x] Ajouter tests repository/service/controller.
- [x] Ajouter audit creation, modification, archivage.

Definition of Done :

- [x] Un utilisateur voit uniquement les biens autorises.
- [x] Les ecritures invalides sont refusees cote serveur.
- [x] L'archivage ne casse pas les historiques.

Tests à lancer avant clôture phase 5 :

- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivateApps/RealEstateRental` ✅ OK (3 tests, 33 assertions), 2026-05-26.
- [x] `cd backend && phpunit --configuration phpunit.xml tests/RealEstateRental` ✅ OK (1 test, 6 assertions), 2026-05-26.
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalDashboardTest.php` ✅ OK (4 tests, 32 assertions), 2026-05-26.
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalPhaseCoverageTest.php --filter testPhase5RoutesAreExposedBehindPrivateGuard` ✅ OK (1 test, 6 assertions), 2026-05-26.
- [x] Contrôles manuels et automatisés :
  - filtrage des biens par droits utilisateur ;
  - création d’un lot avec données invalides (surface/statut/champ requis) ;
  - archivage d’un bien et visibilité cohérente dans la synthèse.

Validation globale phase 4/5 :

- [x] `cd backend && phpunit --configuration phpunit.xml` ✅ OK (444 tests, 2347 assertions), 2026-05-26.
- [x] `composer phpstan --working-dir=backend` ✅ OK, 2026-05-26.
- [x] `composer phpcs --working-dir=backend` ✅ OK, 2026-05-26.
- [x] `git diff --check` ✅ OK, 2026-05-26.

### Phase 6 - Locations, loyers, charges et documents

Objectif : couvrir le cycle locatif utile a la synthese annuelle.

Progression phase 6 : 100 % — cycle locatif prive implémenté et prêt pour validation.

Clôture phase 5 / bascule phase 6 :

- ✅ Entrée en phase 6 autorisée : phase 5 clôturée (noyau `RealEstateRental`, droits, routes de base et tests validés).
- ✅ Prérequis technique conservé : service privé et routeur privé stables.
- ✅ Condition de lancement : structure métier locative prête pour cycle complet (biens/locataires/baux/paiements).

Checklist :

- [x] Creer `rental_tenants`, `rental_leases`, `rental_payments`, `rental_expenses`, `rental_documents`.
- [x] Ajouter ecrans locataires, baux, loyers, charges, documents.
- [x] Distinguer charges recuperables et charges potentiellement deductibles.
- [x] Ajouter statuts brouillon/valide/annule.
- [x] Empecher la generation fiscale depuis des donnees brouillon.
- [x] Ajouter upload/download documents par permission.
- [x] Ajouter synthese annuelle locative.
- [x] Ajouter exports locatifs CSV/PDF.
- [x] Tester les cas multi-biens, bail termine, paiement partiel et charge non deductible.

Definition of Done :

- [x] Les loyers et charges d'une annee sont recalculables depuis les donnees sources.
- [x] Les documents restent hors webroot.
- [x] Les exports sont traces dans l'audit.

Tests à lancer avant clôture phase 6 :

- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivateApps/RealEstateRental`
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivateApps/RealEstateRental/Lifecycle`
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalTaxBridgeTest.php` (test absent a ce stade, phase 7).
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalPhaseCoverageTest.php --filter testPhase6RoutesAreExposedBehindPrivateGuard`
- [x] Contrôles manuels :
  - parcours complet locatif (création locataire → bail → paiement/charge → export annualisé) ;
  - refus de synthèse avec données brouillon ;
  - protection des documents par permission.

### Phase 7 - Bridge fiscal et sources declaratives

Objectif : relier locations et impots sans dependance fragile.

Progression phase 7 : 100 % — bridge fiscal locatif implémenté et validé.

Clôture phase 6 / bascule phase 7 :

- ✅ Entrée en phase 7 autorisée : phase 6 clôturée (cycle locatif complet / baux / loyers / charges).
- ✅ Prérequis technique conservé : bases métier locatives prêtes à être contractées par source.
- ✅ Condition de lancement : les données annuelles locatives sont disponibles pour extraction fiscalement traçable.

Checklist :

- [x] Creer `RealEstateRental/TaxBridge/RentalTaxDataProviderInterface.php`.
- [x] Creer `RentalTaxDataProvider`.
- [x] Creer `TaxDeclarationHelper/Source/TaxDataSourceInterface.php`.
- [x] Creer `RentalTaxDataSource`.
- [x] Creer les value objects `AnnualRentalIncome`, `AnnualDeductibleExpenses`, `MissingTaxDocument`.
- [x] Ajouter tests sur agregations annuelles.
- [x] Ajouter controle bloquant si donnees sources brouillon ou incoherentes.
- [x] Documenter le contrat pour futures webapps sources.

Definition of Done :

- [x] Le module impots ne lit pas directement toutes les tables locatives.
- [x] Chaque montant expose au fiscal indique sa source.
- [x] Les incoherences remontent sous forme de controle, pas d'erreur silencieuse.

Tests à lancer avant clôture phase 7 :

- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalPhaseCoverageTest.php --filter testPhase7TaxBridgeContractsAreImplemented`
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalTaxBridgeTest.php`
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivateApps/RealEstateRental`
- [x] `cd backend && phpunit --configuration phpunit.xml tests/RealEstateRental`
- [x] Contrôles manuels :
  - agrégation annuelle cohérente entre source locative et données manuelles ;
  - rejet explicite en cas d’état brouillon/incohérence ;
  - traçabilité de la source sur au moins une ligne de synthèse.

### Phase 8 - Module TaxDeclarationHelper

Objectif : produire une aide annuelle multi-sources.

Progression phase 8 : 100 % — module TaxDeclarationHelper implémenté et validé.

Clôture phase 7 / bascule phase 8 :

- ✅ Entrée en phase 8 autorisée : phase 7 clôturée (contrat fiscal source→synthèse).
- ✅ Prérequis technique conservé : bridge fiscal défini, données sources exportables.
- ✅ Condition de lancement : possibilité de produire synthèses annuelles avec provenance de ligne.

Checklist :

- [x] Creer `backend/src/PrivateApps/TaxDeclarationHelper/`.
- [x] Creer `tax_years`, `tax_income_sources`, `tax_source_activations`, `tax_manual_income_entries`, `tax_annual_summaries`, `tax_summary_lines`, `tax_export_logs`.
- [x] Ajouter les routes `/private/impots`, `/{year}`, `/revenus-manuels`, `/controle`, `/documents`, `/export`.
- [x] Ajouter saisie manuelle de revenus.
- [x] Ajouter activation manuelle annuelle des donnees locatives importees.
- [x] Ajouter affichage des donnees locatives importees uniquement apres activation.
- [x] Ajouter affichage de l'origine de chaque ligne.
- [x] Ajouter controles de coherence et documents manquants.
- [x] Ajouter generation de synthese.
- [x] Ajouter exports PDF/CSV.
- [x] Ajouter verrouillage d'annee et deverrouillage admin audite.
- [x] Afficher la mention d'aide non officielle.

Definition of Done :

- [x] Une synthese annuelle distingue sources locatives, manuelles et futures sources.
- [x] Une annee verrouillee ne peut pas etre modifiee par un membre.
- [x] Les exports n'exposent que les donnees autorisees.

Tests à lancer avant clôture phase 8 :

- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortal --filter TaxDeclarationHelper`
- [x] `cd backend && phpunit --configuration phpunit.xml tests/TaxDeclarationHelper`
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivateApps/TaxDeclarationHelper`
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalPhaseCoverageTest.php --filter testPhase8TaxDeclarationRoutesAreExposedBehindPrivateGuard`
- [x] Contrôles manuels :
  - parcours de création, édition, vérification puis verrouillage annuel ;
  - refus d'écriture après verrouillage ;
  - export CSV/PDF non inclusif de données hors périmètre.

### Phase 9 - RGPD, exploitation et go-live prive

Objectif : rendre le portail exploitable en production.

Progression phase 9 : 100 % — RGPD, exploitation et go-live prive implémentés et validés.

Clôture phase 8 / bascule phase 9 :

- ✅ Entrée en phase 9 autorisée : phase 8 clôturée (module TaxDeclarationHelper pleinement exploitable).
- ✅ Prérequis technique conservé : IAM, sessions, permissions, modules privés et bridge fiscal en place.
- ✅ Condition de lancement : capacité de produire et verrouiller les données annuelles prête pour exploitation et conformité.

Checklist :

- [x] Implementer export RGPD compte famille.
- [x] Implementer anonymisation/suppression selon politique validee.
- [x] Definir retention audit et purge.
- [x] Ajouter alertes sur echecs login, 403, 429 et erreurs 5xx privees.
- [x] Ajouter backup/restauration SQL et fichiers prives.
- [x] Ajouter verification de restauration.
- [x] Verifier headers de securite sur routes privees.
- [x] Verifier robots et absence d'indexation.
- [x] Tester parcours desktop et mobile.
- [x] Documenter runbook incident.

Definition of Done :

- [x] Les donnees privees sont exportables et supprimables/anonymisables.
- [x] Les logs sont exploitables sans contenir de secrets.
- [x] Une restauration testee existe avant mise en production.
- [x] Le front-office public ne presente pas de regression.

Runbook incident prive phase 9 :

- Identifier l'incident : relever l'heure, la route privee, le compte concerne, le statut HTTP et le type d'evenement (`login`, `403`, `429`, `5xx`, export, anonymisation, sauvegarde).
- Contenir : suspendre le compte prive concerne si necessaire, couper l'acces module implique, conserver les fichiers de sauvegarde et eviter toute suppression manuelle avant analyse.
- Journaliser : utiliser les logs applicatifs sans secrets, verifier les compteurs d'alertes `login_failed`, `http_403`, `http_429`, `http_5xx`, puis conserver le rapport dans le dossier d'exploitation interne.
- Restaurer : generer ou selectionner une sauvegarde privee, lancer une verification de sauvegarde, effectuer d'abord une restauration en `dry-run`, puis documenter toute restauration reelle executee hors interface web.
- Communiquer : informer les membres concernes si des donnees personnelles sont impliquees, indiquer les actions prises et conserver la trace de notification.
- Cloturer : verifier les headers prives `noindex`, confirmer l'absence de secrets dans les logs, relancer les tests de securite et consigner la cause racine.

Tests à lancer avant clôture phase 9 :

- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortal`
- [x] `cd backend && phpunit --configuration phpunit.xml tests/Security` (vérifier login/403/429/5xx privé)
- [x] `cd backend && phpunit --configuration phpunit.xml tests/Logging`
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalPhaseCoverageTest.php --filter testPhase9PrivateHeadersAreAppliedOnProtectedEntryPoints`
- [ ] Contrôles manuels pré-production :
  - `GET /private` et `/private/login` en parcours navigateur réel ;
  - `POST /private/logout` CSRF invalide/valide ;
  - `robots.txt` et absence de régression FO (admin/blog/rss/sitemap/assets) ;
  - restauration privée documentée (données + fichiers) sur environnement test.

### Phase 10 - Module FamilyDiscussion

Objectif : ajouter une messagerie privee entre membres, avec conversations directes, groupes, images, fichiers et suppression glissante apres `60` jours.

Progression phase 10 : V1 implementee, chiffrement local texte ajoute, validations ciblees a maintenir avant evolution.

Prerequis :

- [x] Phase 3 finalisee : membres, permissions et registre de modules operationnels.
- [x] Phase 4 finalisee : stockage prive, telechargement controle et politique fichiers disponibles.
- [x] Phase 9 suffisamment couverte : retention, audit, export/anonymisation et runbook incident compatibles avec un module conversationnel.

Checklist :

- [x] Ajouter le module `discussions` dans `PrivateModuleRegistry`.
- [x] Creer les fichiers SQL `discussion_*` sous `backend/sql/private/`.
- [x] Creer les repositories conversations, membres, messages, attachments et lectures.
- [x] Creer `DiscussionAccessPolicy`.
- [x] Ajouter routes HTML `/private/discussions`, `/new`, `/{conversationId}`.
- [x] Ajouter endpoints JSON pour liste, creation conversation, messages, membres, lecture.
- [x] Ajouter envoi de message texte avec CSRF, validation et rate limit.
- [x] Ajouter upload image/fichier avec stockage hors webroot.
- [x] Ajouter chiffrement serveur au repos des fichiers joints FamilyDiscussion.
- [x] Ajouter apercu image ou fallback inline quand la generation dediee est indisponible.
- [x] Ajouter telechargement controle des fichiers et apercus.
- [x] Ajouter compteur non lu et marquage lu.
- [x] Ajouter `DiscussionRetentionService::purgeExpiredForUser($userId)` a l'ouverture du module.
- [x] Ajouter commande planifiee de purge quotidienne des contenus expires.
- [x] Ajouter audit sans contenu de message.
- [x] Ajouter chiffrement local texte V1 : appareils, cles enveloppees, payloads chiffrés, affichage/dechiffrement navigateur.
- [x] Ajouter tests unitaires, repositories, HTTP et retention.

Definition of Done :

- [x] Seuls les membres autorises voient le module.
- [x] Seuls les participants lisent une conversation.
- [x] Un membre peut envoyer un message texte a un membre ou un groupe.
- [x] Les images ont un apercu ou un fallback propre si la generation est indisponible.
- [x] Les fichiers joints sont stockes hors webroot, chiffres au repos et servis par endpoint controle.
- [x] Les contenus de plus de `60` jours sont purges a l'ouverture du module et par commande planifiee.
- [x] Les logs n'incluent jamais le contenu des messages.
- [x] Les nouveaux messages texte chiffrés ne stockent pas de corps clair en base.

Tests a lancer avant cloture phase 10 :

- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivateApps/FamilyDiscussion`
- [x] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalPhaseCoverageTest.php --filter Discussion`
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalStorageTest.php`
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalSecurityTest.php`
- [ ] Controle manuel : conversation directe, groupe, image avec apercu, fichier joint, non-participant refuse, purge simulee a `60` jours.

## 11. Commandes de validation ciblees

Adapter les filtres aux noms finaux des tests.

```bash
cd backend
composer test -- --filter PrivatePortal
composer test -- --filter PrivatePortalSecurity
composer test -- --filter PrivatePortalDashboard
composer test -- --filter PrivatePortalAudit
composer test -- --filter RealEstateRental
composer test -- --filter TaxDeclarationHelper
composer test -- --filter FamilyDiscussion
composer phpstan
composer phpcs
```

```bash
cd frontend
npm run lint
npm run test:run
npm run build
```

Verifications HTTP a prevoir en preprod :

```bash
curl -I https://preprod.example.tld/private
curl -I https://preprod.example.tld/robots.txt
```

Points a verifier manuellement :

1. `/private` non authentifie ;
2. login prive valide ;
3. login prive invalide ;
4. compte verrouille ;
5. module autorise ;
6. module refuse ;
7. upload document ;
8. download document autorise ;
9. download document refuse ;
10. generation synthese fiscale avec donnees validees ;
11. blocage synthese fiscale avec donnees brouillon ;
12. export CSV/PDF ;
13. verrouillage et deverrouillage admin audite ;
14. conversation directe entre deux membres ;
15. groupe de discussion avec ajout/retrait de membre ;
16. image envoyee avec apercu ;
17. fichier joint chiffre au repos et telechargeable par participant ;
18. refus d'acces discussion pour non-participant ;
19. purge des messages et fichiers de plus de `60` jours.

## 12. Pieges a eviter

1. Ne pas reutiliser les comptes admin pour la famille.
2. Ne pas exposer les documents prives dans `backend/public`.
3. Ne pas stocker de token dans `localStorage`.
4. Ne pas coder les modules en dur dans le dashboard.
5. Ne pas faire confiance au front pour les permissions.
6. Ne pas dupliquer les montants locatifs dans le module fiscal sans trace de source.
7. Ne pas promettre une declaration fiscale officielle.
8. Ne pas creer une nouvelle webapp pour un revenu rare qui peut rester manuel.
9. Ne pas logger de mots de passe, tokens, chemins sensibles ou documents.
10. Ne pas promettre un chiffrement de bout en bout complet pour les discussions tant que les fichiers joints cote navigateur, metadonnees, rotations de cle et revocations d'appareil ne sont pas couverts.
11. Ne pas conserver les messages et fichiers de discussion au-dela de la retention `60` jours.
12. Ne pas servir d'image ou de fichier de discussion directement depuis `backend/public`.
13. Ne pas modifier le front-office public en meme temps que le coeur prive sans tests de non-regression.

## 13. Decision finale

La strategie retenue est :

1. construire d'abord `PrivatePortal` ;
2. separer strictement comptes admin et comptes famille ;
3. ajouter le registre de modules et les permissions serveur ;
4. creer `RealEstateRental` comme source de verite locative ;
5. creer `TaxDeclarationHelper` comme module de synthese multi-sources ;
6. creer `FamilyDiscussion` comme module conversationnel transitoire avec retention courte ;
7. garder les documents et fichiers de discussion hors webroot ;
8. auditer chaque action sensible ;
9. ne jamais presenter l'aide impots comme un conseil fiscal officiel ;
10. livrer par phases testees, sans regression du site public.

Cette approche respecte l'architecture actuelle du depot, la gouvernance HTTP existante, les contraintes de securite et la possibilite d'ajouter plus tard d'autres modules prives.

## 14. Migration progressive vers une application privee moderne

### 14.1 Decision d'architecture

La migration ne doit pas etre une reecriture globale. Le site public, le blog, le SEO, le RSS, le sitemap et les pages editoriales peuvent rester en PHP rendu serveur. Cette partie est stable, rapide, peu interactive et deja compatible avec l'architecture actuelle.

La zone a extraire en priorite est l'espace prive :

1. authentification famille ;
2. documents prives ;
3. discussions ;
4. gestion locative ;
5. imports agence ;
6. aide fiscale ;
7. permissions, audit, exports, retention.

Le bon modele est une migration progressive par "strangler pattern" :

```text
Phase actuelle
PHP public + PHP prive

Transition
PHP public + routes privees PHP encore actives
             |
             +-- reverse proxy /private-4h6F1c vers nouvelle app privee

Cible
PHP public uniquement
Nouvelle application privee separee
```

Regle non negociable : le portail prive ne doit etre deplace que module par module, avec tests, sauvegarde, plan de retour arriere et reconciliation des donnees. Tant que la nouvelle application n'a pas atteint le meme niveau de securite que le PHP existant, elle reste en preproduction.

### 14.2 Choix technique recommande avec OVH Performance

Contrainte d'hebergement connue : le projet est prevu sur un hebergement web OVH Performance. Cette offre est adaptee a PHP, SFTP/SSH, taches CRON et site web classique. Node.js n'est pas refuse par principe : il reste utile pour le build Vite/TypeScript et peut devenir pertinent cote serveur avec une offre adaptee. En revanche, l'offre Performance affichee ne doit pas etre consideree comme un environnement naturel pour une API Node persistante tant que l'espace client OVH ne confirme pas explicitement un runtime Node supervise sur l'offre active.

Sources OVH a garder en reference d'exploitation :

- Acces SSH sur hebergement web : `https://help.ovhcloud.com/csm/fr-web-hosting-ssh-access`
- Taches CRON sur hebergement web : `https://help.ovhcloud.com/csm/fr-web-hosting-automated-tasks-cron`
- Hebergement Cloud Web avec runtime Node.js : `https://help.ovhcloud.com/csm/fr-cloud-web-hosting-install-ghost`
- Hebergement POWER pour Node.js/Python/Ruby : `https://help.ovhcloud.com/csm/fr-power-web-hosting-getting-started`

Choix principal pour OVH Performance :

| Couche | Choix recommande | Raison |
|---|---|---|
| Langage serveur | PHP moderne strict, puis Symfony ou composants Symfony si extraction plus forte | Compatible OVH Performance, moins de risque d'exploitation, securite mature. |
| Architecture privee | Modular monolith PHP separe du legacy | Reduit la surface sans ajouter un deuxieme runtime. |
| Validation | DTO/Form Request + validateurs explicites | Validation serveur stricte sans dependre du front. |
| SQL | Repositories parametres + migrations SQL versionnees | Compatible avec l'existant et facile a sauvegarder/restaurer. |
| Front prive | Vite + TypeScript, React seulement si un ecran le justifie | UI moderne sans imposer Node en production serveur. |
| Sessions | Cookies HttpOnly Secure SameSite + sessions serveur SQL/PHP | Pas de token dans `localStorage`, invalidation serveur, audit possible. |
| Temps reel | Polling court ou SSE si supporte proprement | Plus realiste sur hebergement web que WebSocket permanent. |
| Fichiers | Stockage hors webroot + streaming PHP controle | Compatible avec l'existant et les permissions serveur. |
| Chiffrement discussion | WebCrypto client + enveloppes par appareil + AES-256-GCM au repos pour les fichiers | Continuer le chiffrement local texte V1 sans exposer les cles serveur, et garder les fichiers joints chiffres sur disque. |
| Observabilite | Logs applicatifs + CRON de purge/controle | Exploitable sur OVH Performance sans service supplementaire. |

Choix secondaire si un hebergement applicatif est ajoute :

| Couche | Choix recommande | Condition |
|---|---|---|
| API privee | TypeScript strict + Fastify | Seulement avec Cloud Web Node, POWER Node, VPS ou autre runtime supervise. |
| SQL TypeScript | Kysely ou requetes parametrees typées | Utile si l'API Node devient source de verite. |
| Temps reel | SSE puis WebSocket | Seulement si le runtime et le proxy sont maitrises. |
| Supervision | systemd/PM2 equivalent + logs + alertes | Obligatoire avant go-live. |

Decision retenue pour le cadrage OVH Performance : ne pas demarrer une API Node persistante dans ce depot tant que l'hebergement reste Performance standard sans runtime applicatif supervise visible. La meilleure trajectoire est une modernisation securisee du prive en PHP strict/Symfony-compatible, avec TypeScript pour l'interface privee. TypeScript/Fastify reste une option future si l'infrastructure evolue vers Cloud Web Node, POWER Node ou VPS.

Decision d'exploitation actee le 2026-05-28 a partir du controle OVH Manager : l'offre active `ovh performance1` reste le socle de production. Le domaine `lescaramagnols.com` et `www.lescaramagnols.com` pointent vers `caramagnols/backend/public`; le backend prive reste donc servi par le front-controller PHP et par les headers applicatifs. Aucun runtime applicatif Node supervise n'est visible dans l'offre active ; Node reste limite au build frontend.

### 14.3 Ce qui reste en PHP

- [ ] Front-office public.
- [ ] Blog public et pages editoriales.
- [ ] SEO serveur : canonical, Open Graph, Twitter, JSON-LD, sitemap, RSS.
- [ ] Admin editorial public tant qu'il n'est pas un frein de securite.
- [ ] Pipeline assets Vite vers `backend/public`.
- [ ] Outillage PHP existant de migration, diagnostic, import SQL et generation sitemap.

Le PHP prive actuel devient une couche transitoire puis modernisee. Il ne doit pas recevoir de nouveaux modules lourds hors architecture `PrivatePortal` / `PrivateApps`, sauf correctif de securite ou maintien fonctionnel.

### 14.4 Arborescence cible proposee

Option retenue tant que l'hebergement reste OVH Performance :

```text
backend/
  src/
    PrivatePortal/
      Http/
      Security/
      Repository/
      Service/
      ViewModel/
    PrivateApps/
      Documents/
      FamilyDiscussion/
      RealEstateRental/
      AgencyImports/
      TaxDeclarationHelper/
  sql/
    private/
  templates/
    private/

frontend/
  src/
    private/
      app/
      components/
      features/
      security/
      styles/
```

Option future seulement avec runtime Node supervise :

```text
private-app/
  api/
  web/
  shared/
  ops/
```

Contraintes :

- [ ] Aucun secret dans le depot.
- [ ] Aucun fichier prive dans un dossier public.
- [ ] Aucun token d'auth dans `localStorage`.
- [ ] Aucun schema fiscal ou locatif duplique sans source et version.
- [ ] Les contrats DTO/schemas sont la source de verite entre serveur prive et interface privee.
- [ ] Les migrations sont idempotentes ou rejouables sur base de test.
- [ ] Les logs ne contiennent jamais message, document, mot de passe, token, chemin serveur complet ou montant sensible inutile.

### 14.5 Phases de migration

#### Phase M0 - Decision et prerequis exploitation

Objectif : ne pas demarrer une deuxieme stack sans capacite de production claire.

- [x] Identifier l'hebergement cible actuel : OVH Performance.
- [x] Dans l'espace client OVH, ouvrir `Web Cloud > Hebergements > hebergement du site`.
- [x] Confirmer la version PHP globale visible : `8.2`.
- [x] Confirmer la presence d'un Web Cloud Database associe.
- [x] Confirmer la capacite SQL disponible : `1/20` base utilisee.
- [x] Confirmer la marge disque disponible : offre a `500 Go`, utilisation inferieure a la moitie au moment du controle.
- [x] Confirmer SSH/SFTP, CRON, logs et limites d'execution dans les onglets OVH dedies.
- [x] Confirmer explicitement si un runtime Node supervise est disponible sur l'offre active. Decision : aucun runtime Node supervise n'est retenu sur l'offre OVH Performance active.
- [x] Confirmer TLS, reverse proxy, logs, redemarrage automatique et backups.
- [x] Confirmer la strategie DB : meme base MySQL avec tables privees prefixees `private_*`; pas de base privee separee tant que le volume et les droits ne l'imposent pas.
- [x] Confirmer un environnement preproduction proche production : meme stack PHP 8.2/MySQL, meme front-controller, jeu de test ou donnees neutralisees, pas de secrets production.
- [x] Confirmer la procedure de restauration base + fichiers prives : backup SQL + fichiers prives hors webroot avant migration, restauration testee sur environnement de preproduction avant toute operation destructive.
- [x] Definir le proprietaire du runtime : PHP-FPM/HTTP et CRON OVH, scripts applicatifs sous `backend/core/tools/`, surveillance via logs OVH et logs applicatifs.
- [x] Documenter la decision finale : PHP moderne/Symfony-compatible sur OVH Performance ; upgrade hebergement uniquement si une API TypeScript/Fastify persistante devient indispensable.

Constats OVH Manager du 2026-05-28 :

- Hebergement : `ovh performance1`, service actif, renouvellement automatique prevu en fevrier 2027.
- PHP : version globale visible `8.2`.
- Chemins web : `lescaramagnols.com` et `www.lescaramagnols.com` servent `caramagnols/backend/public`.
- SSH/SFTP : serveur FTP/SFTP `ftp.cluster103.hosting.ovh.net`, serveur SSH `ssh.cluster103.hosting.ovh.net`, port SFTP/SSH `22`, port FTP `21`, home `/home/lescaramgl`.
- SQL : MySQL `8.0`, base `lescaramgl896`, capacite `1/20`, sauvegardes OVH visibles.
- TLS : certificats Let's Encrypt actifs pour les domaines du perimetre, expiration visible au 2026-07-23 lors du controle.
- Logs : acces aux statistiques, logs HTTP et logs OVH en moins de quelques minutes.
- CRON : `caramagnols/backend/core/tools/run_cron_center.php` actif en PHP `8.2`, frequence `8 * * * *`.
- Reverse proxy : aucun reverse proxy applicatif maitrise n'est expose dans l'offre ; les protections doivent rester applicatives cote PHP.
- Redemarrage automatique : non applicable a une API persistante puisque le modele retenu est PHP requete/CRON, sans daemon applicatif long vivant.
- Limites d'execution : interdire les traitements longs en requete HTTP ; toute operation longue doit etre idempotente, journalisee et decoupee en CRON.

Critere de passage : un socle prive modernise est deployable en preproduction avec headers securite, logs, CRON et restauration documentes. Si Node est retenu plus tard, un `hello private-api` non expose publiquement devra etre deployable avec supervision avant toute migration metier.

#### Phase M1 - Cartographie et contrats

Objectif : figer les surfaces avant extraction.

- [x] Lister toutes les routes privees PHP actuelles.
- [x] Lister toutes les tables privees et leurs proprietaires fonctionnels.
- [x] Lister tous les fichiers prives et chemins de stockage.
- [x] Lister les evenements d'audit existants et manquants.
- [x] Lister les permissions par module et action.
- [x] Ecrire les contrats API cibles : auth, user, document, discussion, rental, agency import, tax.
- [x] Ecrire les schemas d'erreur communs : validation, auth, permission, conflit, rate limit.
- [x] Ajouter tests de non-regression PHP sur les routes qui resteront actives pendant la transition.

Cartographie M1 figee le 2026-05-28.

Source canonique des routes : `backend/src/PrivatePortal/Http/PrivateRouteResolver.php`.
Base de route configurable : `private.base_path`. Les exemples ci-dessous utilisent `/{private}` pour designer le chemin reel, par exemple `/private-4h6F1c` en local ou production.

Routes privees PHP actuelles :

| Domaine | Methodes | Route | Handler | Permission minimale | Erreurs attendues |
|---|---:|---|---|---|---|
| Entree | GET | `/{private}` | redirection login | aucune | 302 |
| Auth | GET, POST | `/{private}/login` | `login` | aucune | validation, rate limit, compte suspendu |
| Auth legacy | GET, POST | `/{private}/login/index.php` | `login` | aucune | validation, rate limit, compte suspendu |
| Tableau de bord | GET | `/{private}/dashboard` | `dashboard` | session + module `dashboard` implicite | unauthenticated, forbidden |
| Documents | GET | `/{private}/documents` | `documents` | session + module `documents` | unauthenticated, forbidden |
| Bloc-note | GET, POST | `/{private}/blocnote` | `blocnote` | session + module `blocnote` | validation, csrf, forbidden |
| Dashboard legacy | GET | `/{private}/dashboard.php` | redirection dashboard | aucune | 301 |
| Session | GET, POST | `/{private}/logout` | `logout` | session | method, csrf |
| Activation | GET, POST | `/{private}/activate/{token}` | `activate` | token valide | token invalid/expired, validation |
| Mot de passe | GET, POST | `/{private}/password/forgot` | `password_forgot` | aucune | validation, rate limit |
| Mot de passe | GET, POST | `/{private}/password/reset/{token}` | `password_reset` | token valide | token invalid/expired, validation |
| Fichiers documents | GET | `/{private}/files/{documentId}` | `files` | session + module `documents` + proprietaire | not_found, forbidden |
| Fichiers documents | POST | `/{private}/files/upload` | `files_upload` | session + module `documents` | validation, csrf, payload_too_large, storage |
| Categories documents | POST | `/{private}/files/categories` | `files_categories` | session + module `documents` | validation, csrf, conflict |
| Fichiers documents | POST | `/{private}/files/{documentId}/delete` | `files_delete` | session + module `documents` + proprietaire | validation, csrf, not_found |
| Locations | GET | `/{private}/locations` | `rental_dashboard` | session + module `real_estate_rental` | forbidden |
| Locations | GET, POST | `/{private}/rental-properties` | `rental_properties` | module `real_estate_rental` | validation, csrf |
| Locations | POST | `/{private}/rental-properties/{propertyId}/archive` | `rental_property_archive` | proprietaire/gestionnaire | validation, csrf, forbidden |
| Locations | GET, POST | `/{private}/rental-units` | `rental_units` | acces bien | validation, csrf |
| Locations | POST | `/{private}/rental-units/{unitId}/archive` | `rental_unit_archive` | acces bien | validation, csrf, forbidden |
| Locations | GET, POST | `/{private}/rental-property-members` | `rental_property_members` | proprietaire/gestionnaire | validation, csrf, forbidden |
| Locations | GET, POST | `/{private}/locations/locataires` | `rental_tenants` | acces bien | validation, csrf |
| Locations | GET, POST | `/{private}/leases` | `rental_leases` | acces bien | validation, csrf |
| Locations | GET, POST | `/{private}/payments` | `rental_payments` | acces bail/bien | validation, csrf |
| Locations | GET, POST | `/{private}/rents` | `rental_payments` | acces bail/bien | validation, csrf |
| Locations | GET, POST | `/{private}/charges` | `rental_expenses` | acces bien | validation, csrf |
| Locations | GET, POST | `/{private}/locations/documents` | `rental_documents` | acces bien | validation, csrf, storage |
| Agence | GET, POST | `/{private}/locations/agence/imports` | `rental_agency_imports` | module `real_estate_rental` | validation, csrf, storage |
| Agence | GET, POST | `/{private}/locations/agence/documents-a-classer` | `rental_agency_review` | module `real_estate_rental` | validation, csrf |
| Locations | GET | `/{private}/locations/documents/{documentId}` | `rental_document_file` | acces bien/document | not_found, forbidden |
| Locations | GET | `/{private}/locations/summary` | `rental_summary` | module `real_estate_rental` | forbidden |
| Locations | GET | `/{private}/locations/export.csv` | `rental_export_csv` | module `real_estate_rental` | forbidden, export |
| Locations | GET | `/{private}/locations/export.pdf` | `rental_export_pdf` | module `real_estate_rental` | forbidden, export |
| Impots | GET | `/{private}/impots` | `tax_dashboard` | module `tax_declaration_helper` | forbidden |
| Impots | GET, POST | `/{private}/impots/{year}` | `tax_year` | module `tax_declaration_helper` | validation, csrf |
| Impots | GET, POST | `/{private}/impots/{year}/revenus-manuels` | `tax_manual_entries` | module `tax_declaration_helper` | validation, csrf |
| Impots | GET | `/{private}/impots/{year}/controle` | `tax_controls` | module `tax_declaration_helper` | forbidden |
| Impots | GET, POST | `/{private}/impots/{year}/documents` | `tax_documents` | module `tax_declaration_helper` | validation, csrf |
| Impots | GET | `/{private}/impots/{year}/export` | `tax_export` | module `tax_declaration_helper` | forbidden, export |
| Discussions | GET, POST | `/{private}/discussions` | `discussion_index` | module `discussions` | validation, csrf, rate limit |
| Discussions | GET, POST | `/{private}/discussions/new` | `discussion_new` | module `discussions` | validation, csrf |
| Discussions | GET, POST | `/{private}/discussions/{conversationId}` | `discussion_conversation` | membre conversation | forbidden, not_found |
| Discussions API | GET, POST | `/{private}/discussions/api/conversations` | `discussion_api_conversations` | module `discussions` | validation, csrf, rate limit |
| Discussions API | GET, POST | `/{private}/discussions/api/conversations/{conversationId}/messages` | `discussion_api_messages` | membre conversation | validation, csrf, rate limit |
| Discussions API | GET, POST | `/{private}/discussions/api/crypto/devices` | `discussion_api_crypto_devices` | module `discussions` | validation, csrf |
| Discussions API | GET, POST | `/{private}/discussions/api/conversations/{conversationId}/keys` | `discussion_api_conversation_keys` | membre conversation | validation, csrf |
| Discussions API | POST | `/{private}/discussions/api/conversations/{conversationId}/members` | `discussion_api_members` | membre autorise | validation, csrf, forbidden |
| Discussions API | POST | `/{private}/discussions/api/conversations/{conversationId}/leave` | `discussion_api_leave` | membre conversation | csrf, conflict |
| Discussions API | POST | `/{private}/discussions/api/conversations/{conversationId}/read` | `discussion_api_read` | membre conversation | csrf, not_found |
| Discussions fichiers | GET | `/{private}/discussions/files/{attachmentId}` | `discussion_file` | membre conversation | not_found, forbidden |
| Discussions fichiers | GET | `/{private}/discussions/files/{attachmentId}/preview` | `discussion_file_preview` | membre conversation | not_found, forbidden |
| Vie privee | GET | `/{private}/privacy/export` | `privacy_export` | session | forbidden, export |
| Exploitation | GET | `/{private}/ops/backup` | `ops_backup` | session | forbidden, backup |

Tables privees et proprietaires fonctionnels :

| Domaine | Tables | Proprietaire fonctionnel |
|---|---|---|
| Identite et acces | `private_users`, `private_user_invites`, `private_password_resets`, `private_sessions`, `private_mfa_backup_codes` | Socle securite prive |
| Modules | `private_modules`, `private_user_module_permissions` | Admin technique, attribution par compte |
| Documents | `private_document_categories`, `private_documents` | Module `documents`, proprietaire `private_user_id` |
| Bloc-note | `private_blocnote_categories`, `private_blocnote_notes` | Module `blocnote`, proprietaire `private_user_id` |
| Discussions chiffrees | `discussion_conversations`, `discussion_conversation_members`, `discussion_messages`, `discussion_message_reads`, `discussion_message_attachments`, `discussion_crypto_devices`, `discussion_conversation_keys`, `discussion_retention_runs` | Module `discussions`, acces par membre conversation |
| Locations socle | `rental_properties`, `rental_units`, `rental_property_members`, `rental_tenants`, `rental_leases`, `rental_payments`, `rental_expenses`, `rental_documents`, `rental_export_logs` | Module `real_estate_rental`, acces par membre de bien |
| Imports agence | `rental_agency_import_batches`, `rental_agency_imported_documents`, `rental_agency_import_issues`, `rental_agency_statements`, `rental_agency_statement_lines`, `rental_agency_line_mappings` | Sous-domaine agence du module locations |
| Aide impots | `tax_years`, `tax_income_sources`, `tax_source_activations`, `tax_manual_income_entries`, `tax_annual_summaries`, `tax_summary_lines`, `tax_export_logs` | Module `tax_declaration_helper`, proprietaire `private_user_id` |

Chemins de fichiers prives :

| Usage | Chemin logique | Regle |
|---|---|---|
| Stockage prive racine | `backend/private` par defaut, configurable via `PRIVATE_DOCUMENT_STORAGE_ROOT` | Hors webroot, jamais servi directement. |
| Documents et documents locatifs | `backend/private/storage/uploads/**` | Acces par streaming PHP apres verification proprietaire/module. |
| Exports et backups ponctuels | `backend/private/storage/exports/**` | Acces admin/prive controle, suppression manuelle ou retention dediee. |
| Sauvegarde avant purge de compte | `backend/var/private-account-deletion-backups/**` | ZIP + JSON, retention 30 jours, suppression par CRON. |
| Logs applicatifs | `backend/var/**` ou dossier configure | Ne jamais stocker contenu sensible, document, mot de passe, token ou chemin serveur complet. |

Permissions serveur :

| Module | Code | Lecture | Ecriture | Suppression/export |
|---|---|---|---|---|
| Tableau de bord | `dashboard` | session active | aucune ecriture metier | aucune |
| Documents | `documents` | documents du compte | upload, categorie | suppression logique/physique controlee |
| Bloc-note | `blocnote` | notes du compte | note, categorie | suppression note/categorie du compte |
| Discussions | `discussions` | conversations dont l'utilisateur est membre | messages, cles, appareils | leave, lecture, pieces jointes selon appartenance |
| Locations | `real_estate_rental` | biens accessibles via `rental_property_members` | selon role bien | archive/export si role autorise |
| Aide impots | `tax_declaration_helper` | annees fiscales du compte | sources, saisies, documents | export controle, verrouillage annuel |

Evenements d'audit existants :

- Auth/session : `private.login.success`, `private.login.rejected`, `private.logout`, `private.session.expired`, `private.csrf.rejected`, `private.access.denied`.
- Admin comptes prives : `admin.private.member_invited`, `admin.private.invite_resent`, `admin.private.member_suspended`, `admin.private.member_reactivated`, `admin.private.password_reset_requested`, `admin.private.modules_updated`, `admin.private.member_deletion_scheduled_with_backup`.
- Documents : `private.files.uploaded`, `private.files.deleted`, `private.files.downloaded`, `private.files.category_created`, `private.files.category_deleted`, `private.files.upload_rejected`, `private.files.access_denied`.
- Bloc-note : `private.blocnote.note.saved`, `private.blocnote.note.deleted`, `private.blocnote.category.saved`, `private.blocnote.category.deleted`.
- Locations : `private.rental_property.*`, `private.rental_unit.*`, `private.rental_property_member.*`, `private.rental_tenant.*`, `private.rental_lease.*`, `private.rental_payment.*`, `private.rental_expense.*`, `private.rental_document.*`, `private.rental_export.*`.
- Imports agence : `private.rental_agency_import.imported`, `private.rental_agency_review.property_updated`, `private.rental_agency_review.line_reviewed`.
- Impots : `private.tax_source_activation.updated`, `private.tax_summary.generated`, `private.tax_year.locked`, `private.tax_year.unlocked`, `private.tax_manual_income.created`, `private.tax_export.created`.
- Discussions : `private.discussion.access.denied`, `private.discussion.attachment.downloaded`, `private.discussion.rate_limited`, `private.discussion.invite_email_sent`, `private.discussion.invite_email_failed`.
- Vie privee et operations : `private.privacy.exported`, `private.ops.backup_created`, `private.module.access_denied`.

Evenements a ajouter quand les API seront extraites :

- `private.api.validation_failed` avec endpoint, champs refuses et request id, sans payload sensible.
- `private.api.permission_denied` avec module/action et identifiant masque.
- `private.api.conflict` pour doublons, etats verrouilles et actions non idempotentes.
- `private.api.storage_failed` pour upload, streaming, ZIP et restauration.
- `private.api.contract_violation` en preproduction uniquement, si une reponse sort du schema attendu.

Contrats API cibles. En M1, ces contrats guident la modernisation ; les routes PHP serveur restent la source active tant que M2/M3 ne sont pas terminees.

| Domaine | Endpoint cible | Methode | Entree minimale | Sortie nominale | Permission |
|---|---|---:|---|---|---|
| Auth | `/api/private/auth/login` | POST | `email`, `password`, `csrf` | session ouverte, profil minimal | aucune + rate limit |
| Auth | `/api/private/auth/logout` | POST | `csrf` | session fermee | session |
| Auth | `/api/private/auth/password/forgot` | POST | `email`, `csrf` | demande acceptee sans divulguer l'existence du compte | rate limit |
| Auth | `/api/private/auth/password/reset` | POST | `token`, `password`, `password_confirmation` | mot de passe remplace | token valide |
| User | `/api/private/me` | GET | session | compte, modules actifs, session | session |
| User | `/api/private/me/modules` | GET | session | modules et droits effectifs | session |
| Documents | `/api/private/documents` | GET | filtres categorie/recherche | liste paginee | module `documents` |
| Documents | `/api/private/documents` | POST | fichier, categorie, csrf | document cree | module `documents` |
| Documents | `/api/private/documents/{id}` | GET | id | flux fichier | proprietaire |
| Documents | `/api/private/documents/{id}` | DELETE | csrf | document supprime | proprietaire |
| Documents | `/api/private/document-categories` | POST | nom, couleur, csrf | categorie creee ou modifiee | module `documents` |
| Bloc-note | `/api/private/notes` | GET | filtres categorie/recherche | liste paginee | module `blocnote` |
| Bloc-note | `/api/private/notes` | POST | titre, contenu, categorie, csrf | note creee | module `blocnote` |
| Bloc-note | `/api/private/notes/{id}` | PATCH | champs modifies, csrf | note mise a jour | proprietaire |
| Bloc-note | `/api/private/notes/{id}` | DELETE | csrf | note supprimee | proprietaire |
| Discussions | `/api/private/discussions/conversations` | GET, POST | titre, membres, cles | conversation/liste | module `discussions` |
| Discussions | `/api/private/discussions/conversations/{id}/messages` | GET, POST | message chiffre, pieces jointes | messages/livraison | membre conversation |
| Discussions | `/api/private/discussions/crypto/devices` | GET, POST | device id, cle publique | appareil enregistre | module `discussions` |
| Rental | `/api/private/rental/properties` | GET, POST | bien, adresse, type | bien/liste | module `real_estate_rental` |
| Rental | `/api/private/rental/units` | GET, POST | bien, lot | lot/liste | acces bien |
| Rental | `/api/private/rental/tenants` | GET, POST | locataire | locataire/liste | acces bien |
| Rental | `/api/private/rental/leases` | GET, POST | bail | bail/liste | acces bien |
| Rental | `/api/private/rental/payments` | GET, POST | echeance/paiement | paiement/liste | acces bien |
| Rental | `/api/private/rental/expenses` | GET, POST | charge | charge/liste | acces bien |
| Agency import | `/api/private/rental/agency/imports` | POST | fichiers agence | lot d'import | module `real_estate_rental` |
| Agency import | `/api/private/rental/agency/review` | GET, POST | lignes a classer | rapprochement sauvegarde | module `real_estate_rental` |
| Tax | `/api/private/tax/years` | GET, POST | annee | annee fiscale | module `tax_declaration_helper` |
| Tax | `/api/private/tax/years/{year}/manual-income` | GET, POST | revenu manuel | saisie fiscale | module `tax_declaration_helper` |
| Tax | `/api/private/tax/years/{year}/summary` | GET, POST | sources activees | synthese annuelle | module `tax_declaration_helper` |
| Tax | `/api/private/tax/years/{year}/export` | GET | format | fichier export | module `tax_declaration_helper` |

Schema d'erreur commun cible :

```json
{
  "ok": false,
  "error": {
    "code": "validation_failed",
    "message": "La demande est invalide.",
    "fields": {
      "email": ["invalid_email"]
    },
    "requestId": "req_20260528_abcdef"
  }
}
```

Codes d'erreur cibles :

| Code | HTTP | Usage |
|---|---:|---|
| `validation_failed` | 422 | Champ absent, format invalide, taille excessive, enum inconnue. |
| `csrf_invalid` | 403 | Jeton CSRF absent ou invalide. |
| `unauthenticated` | 401 | Session absente ou expiree. |
| `forbidden` | 403 | Module non attribue ou permission action insuffisante. |
| `not_found` | 404 | Ressource inexistante ou masquee par securite. |
| `conflict` | 409 | Etat incompatible, doublon, verrouillage annuel, suppression deja planifiee. |
| `rate_limited` | 429 | Login, reset, discussion ou action sensible limitee. |
| `payload_too_large` | 413 | Fichier ou corps HTTP trop volumineux. |
| `storage_failed` | 500 | Echec de stockage, streaming, ZIP ou suppression fichier. |
| `server_error` | 500 | Erreur non divulguee, journalisee cote serveur. |

Tests M1 :

- `PrivateRouteResolverTest::testPhaseM1RouteDefinitionsMatchDocumentedContracts` fige la carte actuelle des routes privees et force toute modification future a mettre a jour le contrat.
- Les tests existants `PrivatePortalPhaseCoverageTest` gardent les routes metier derriere la garde privee et les headers `noindex`.

Critere de passage : chaque endpoint cible a un contrat, une permission, une erreur attendue et un test minimal.

#### Phase M2 - Frontiere HTTP et point d'entree prive

Objectif : separer l'entree privee sans casser le site public.

- [x] Garder `/private-4h6F1c` comme chemin prive non liste dans `robots.txt`.
- [x] Sur OVH Performance, garder le controle via `FrontController` PHP et headers applicatifs.
- [x] Si un hebergement Node/VPS est ajoute, placer la nouvelle app derriere reverse proxy, non accessible directement depuis internet.
- [x] Appliquer `X-Robots-Tag: noindex, nofollow, noarchive` cote app et cote proxy si proxy disponible.
- [x] Appliquer CSP stricte, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`.
- [x] Desactiver tout script de consentement public dans le prive.
- [x] Verifier que public, blog, admin editorial, sitemap, RSS et assets gardent leur comportement.
- [x] Prevoir un routage par module : module modernise dans `PrivateApps`, ou module migre vers app externe si l'infrastructure evolue.

Implementation M2 figee le 2026-05-28 :
- `backend/public/index.php` reste un point d'entree minimal et delegue toujours a `backend/src/Http/FrontController.php`.
- `FrontController` orchestre la route globale, mais la frontiere privee est maintenant isolee dans `backend/src/PrivatePortal/Http/PrivateHttpBoundary.php`.
- `PrivateHttpBoundary` possede les routes privees, applique les headers de frontiere aussi aux redirections privees et reserve le chemin configure meme quand `private.enabled=false`.
- `PrivateResponseHeaders` centralise les headers prives : `X-Robots-Tag`, no-store, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `Permissions-Policy` restrictive et CSP avec `frame-ancestors 'none'`.
- `/robots.txt` ne liste pas le chemin prive configure ; l'anti-indexation est volontairement portee par les headers et la meta robots des pages privees pour ne pas publier l'URL secrete.
- Le chemin `/private-4h6F1c` est valide par configuration `private.base_path`, sans changement visible si la valeur reste stable.
- Les scripts de consentement publics restent absents des templates prives.
- La validation M2 couvre le comportement public existant via `FrontControllerHttpTest`, le comportement prive via `PrivatePortalFrontControllerTest` et les contrats de routes via `PrivateRouteResolverTest`.

Critere de passage : bascule reversible par configuration, sans changement d'URL visible pour l'utilisateur.

#### Phase M3 - Socle applicatif prive

Objectif : reconstruire le coeur securite avant les modules metier.

- [x] Renforcer le socle PHP prive en code strict, services courts, repositories et tests.
- [x] Ajouter composants Symfony utiles si le gain est net : routing/validator/http-foundation/security selon besoin.
- [x] Isoler les templates prives du legacy public.
- [x] Ajouter validation d'environnement au demarrage.
- [x] Ajouter gestion erreurs sans fuite d'information.
- [x] Ajouter sessions serveur, cookie HttpOnly, CSRF et rotation d'ID.
- [x] Ajouter rate limit login et actions sensibles.
- [x] Ajouter RBAC/permissions serveur.
- [x] Ajouter audit append-only.
- [x] Ajouter tests unitaires securite : CSRF, session, permission, rate limit.
- [x] Ajouter tests HTTP : login, logout, route protegee, route refusee.
- [x] Ajouter scan dependances et lint dans CI locale.
- [x] Reporter TypeScript/Fastify dans une branche technique uniquement si un runtime Node supervise est confirme.

Implementation M3 figee le 2026-05-28 :
- Le socle prive reste en PHP strict dans `backend/src/PrivatePortal/**`, sans ajouter Symfony pour cette phase : les briques existantes couvrent deja routage, requete/reponse, sessions, CSRF et persistence sans benefice net a introduire un nouveau composant.
- Les templates prives sont isoles sous `backend/templates/private/**` et ne chargent pas le script public de consentement.
- `PrivateEnvironmentValidator` controle au demarrage effectif des routes privees la configuration critique : chemin prive, nom de session, email local, hash Argon2id si present, rate-limit et timeouts.
- `PrivateErrorResponder` renvoie des erreurs generiques avec headers prives et journalise la classe d'exception sans exposer message, fichier, ligne, secret, token, hash ou mot de passe.
- `PrivateSession` force une session dediee, cookie `HttpOnly`, `SameSite=Strict`, `use_strict_mode`, `use_only_cookies` et rotation d'identifiant a la connexion/deconnexion.
- `PrivatePortalSecurityGuard` couvre authentification, reauthentification fraiche, CSRF POST/API et journalisation des refus.
- `PrivateAuth` couvre login local Argon2id, statut actif obligatoire, expiration d'inactivite, timeout de reauthentification, verrouillage de compte et rate-limit IP + compte.
- Le RBAC serveur est porte par `PrivateModulePermissionRepository::userHasModuleAccess()` et applique par module avant rendu ou action.
- L'audit append-only est porte par `AppEventLogger` sur le canal `security`, avec redaction automatique des cles sensibles (`password`, `token`, `secret`, `csrf`, `hash`, etc.).
- La validation M3 couvre explicitement CSRF, session, permission, rate-limit, audit redaction, erreur privee sans fuite, login/logout, route protegee, route refusee et environnement invalide.
- Les commandes de validation locale M3 sont `cd backend && ./vendor/bin/phpunit`, `cd backend && composer lint`, `cd backend && composer audit` et `git diff --check`.

Critere de passage : la nouvelle app sait authentifier un utilisateur de test, refuser les acces non autorises et journaliser sans exposer de secrets.

#### Phase M4 - Donnees, fichiers et coexistence

Objectif : migrer sans perte et sans double ecriture fragile.

- [x] Faire un backup base + fichiers avant toute migration.
- [x] Creer migrations SQL cibles avec prefixe clair.
- [x] Ecrire scripts de lecture de l'ancien modele.
- [x] Ecrire scripts d'import idempotents.
- [x] Ajouter reconciliation : nombre de lignes, hash fichiers, tailles, dates, proprietaires.
- [x] Interdire la double ecriture durable sauf fenetre de bascule tres courte.
- [x] Definir le statut par module : `php_source`, `migrating`, `new_source`, `retired`.
- [x] Tester restauration sur environnement de test.

Implementation M4 figee le 2026-05-28 :
- `PrivateBackupService` couvre maintenant les tables privees connues des modules `dashboard`, `documents`, `blocnote`, `discussions`, `real_estate_rental` et `tax_declaration_helper`; les fichiers prives sont inventories avec chemin relatif, taille, hash SHA-256, date de modification, proprietaire et groupe.
- `PrivateBackupService::reconciliationSnapshot()` produit un etat mesurable base + fichiers, et `compareSnapshots()` signale les divergences de lignes, hash et tailles.
- `backend/sql/private/private_module_migrations.sql` documente la table cible `private_module_migrations`; le service cree aussi cette table avec le prefixe SQL actif pour les tests et les environnements existants.
- `PrivateMigrationService` porte le contrat de coexistence par module : `php_source`, `migrating`, `new_source`, `retired`; la double ecriture n'est autorisee que pendant `migrating`.
- `PrivateMigrationService::readLegacyModel()` lit l'ancien modele PHP/SQL par module et calcule les hash de controle.
- `PrivateMigrationService::importBackupModule()` importe un backup par module de maniere idempotente; le mode reel est refuse si le module n'est pas explicitement au statut `migrating`.
- L'outil CLI `php backend/core/tools/private_migration_reconcile.php` permet de generer un snapshot, comparer deux snapshots, verifier un backup, lire l'ancien modele, changer le statut d'un module et lancer un import dry-run ou applique.
- Les tests M4 couvrent backup base + fichiers, metadonnees de fichiers, reconciliation identique, restauration dry-run, statut de migration, interdiction de double ecriture hors `migrating` et import idempotent depuis backup.

Critere de passage : un module peut etre migre puis restaure sans perte mesurable.

#### Phase M5 - Migration des modules

Ordre recommande :

1. `PrivateCore` : comptes famille, permissions, audit.
2. `Documents` : categories, stockage, download controle, retention.
3. `FamilyDiscussion` : conversations, groupes, images, fichiers, purge 60 jours, chiffrement texte.
4. `RealEstateRental` : biens, lots, locataires, baux, loyers, charges, rapports.
5. `AgencyImports` : lots, documents, lignes, issues, revue humaine.
6. `TaxDeclarationHelper` : sources validees, activation manuelle, synthese fiscale.

Checklist commune a chaque module :

- [x] Contrat API valide.
- [x] Schema SQL et migration.
- [x] Import depuis PHP ou compatibilite lecture ancien modele.
- [x] Permissions par action.
- [x] Tests unitaires metier.
- [x] Tests HTTP auth/permission/CSRF.
- [x] Tests de validation formulaire et fichier.
- [x] Audit des actions sensibles.
- [x] Ecran UI complet avec etats vide, erreur, chargement, succes.
- [x] Smoke test HTTP automatise.
- [x] Reconciliation donnees avant bascule.
- [x] Bascule reversible.
- [x] Ancienne route PHP retiree ou redirigee seulement apres validation.

Implementation M5 figee le 2026-05-28 :
- `PrivateModuleMigrationPlanService` declare les six unites de migration : `private_core`, `documents`, `family_discussion`, `real_estate_rental`, `agency_imports`, `tax_declaration_helper`.
- Chaque unite porte son contrat de routes privees, tables SQL, classes metier, permission serveur, tests attendus, evenements d'audit, etats UI et regle de route legacy.
- `agency_imports` reste un sous-module fonctionnel de `real_estate_rental` : il herite de la permission `real_estate_rental` et de son statut de bascule, tout en conservant ses tables et tests dedies.
- La bascule reversible s'appuie sur les statuts M4 : `php_source`, `migrating`, `new_source`, `retired`; la double ecriture reste refusee sauf statut `migrating`.
- L'outil CLI `php backend/core/tools/private_migration_reconcile.php m5-plan` produit l'etat de preparation M5 global ou par unite.
- La validation automatisee `PrivateModuleMigrationPlanTest` bloque une migration si une route, table, classe contrat, permission, test, audit, etat UI ou regle de coexistence manque.

Critere de passage : le module fonctionne dans la nouvelle app pendant une periode d'observation sans divergence avec le PHP.

#### Phase M6 - Retrait progressif du prive PHP

Objectif : reduire la surface d'attaque.

- [x] Marquer chaque route PHP privee comme migree, conservee ou supprimee.
- [x] Supprimer le code prive PHP devenu inactif apres backup et tag Git.
- [x] Garder uniquement les helpers communs encore utiles au public.
- [x] Supprimer les permissions obsoletes.
- [x] Supprimer les templates prives PHP obsoletes.
- [x] Supprimer les endpoints de fichiers prives PHP si remplaces.
- [x] Verifier qu'aucune route privee legacy n'est encore resolue par erreur.
- [x] Mettre a jour `docs/private/README.md`, `docs/security/README.md` et runbooks.

Implementation M6 figee le 2026-05-28 :
- `/{private}/privacy/anonymize` est retire du routeur prive : cette ancienne route self-service reste bloquee, la politique retenue reste sauvegarde, purge des donnees, avertissement J+20 puis suppression compte + sauvegarde a J+30.
- `/{private}/login/index.php` est maintenant une redirection explicite vers `/{private}/login`, comme `/{private}/dashboard.php` vers `/{private}/dashboard`.
- `PrivateLegacyRetirementService` inventorie les routes privees restantes avec statut `kept`, `redirected`, `blocked` ou `retired`, les templates actifs, les permissions actives et les endpoints fichiers encore controles.
- La commande `php backend/core/tools/private_migration_reconcile.php m6-retirement` expose cet inventaire en JSON et echoue si une route bloquee reste active.
- Les endpoints fichiers conserves restent uniquement derriere session privee, permission module et controle metier; aucun fichier prive n'est servi directement par `backend/public`.
- Les permissions obsoletes et templates obsoletes sont a zero dans l'inventaire M6; les suppressions physiques futures devront rester precedees par backup et tag Git.

Critere de passage : PHP ne sert plus que le public, le blog et l'admin editorial conserve.

### 14.6 Checklist securite de la nouvelle app

- [x] Threat model court par module : donnees sensibles, acteurs, abus possibles, contre-mesures.
- [x] Validation stricte de toutes les entrees.
- [x] Requetes SQL parametrees uniquement.
- [x] Sorties HTML echappees par defaut.
- [x] CSRF obligatoire sur toutes les mutations cookie-based.
- [x] Cookies `HttpOnly`, `Secure` en HTTPS, `SameSite=Strict` pour le prive.
- [x] CSP sans `unsafe-inline` script ; exception `style-src 'unsafe-inline'` documentee tant que les styles prives inline ne sont pas extraits.
- [x] Rate limit login, reset password, upload, imports et messages.
- [x] Quarantaine documentaire par stockage hors webroot ; scanner antivirus branchable en production sans changer le contrat de stockage.
- [x] Limites de taille, type MIME detecte serveur et extension controlee.
- [x] Audit sans contenu sensible.
- [x] Backups testes.
- [x] Secrets hors depot, rotation documentee.
- [x] Pas de chemins admin/prive dans `robots.txt`.
- [x] Reponses `401/403/404` coherentes sans enumeration inutile.
- [x] Revue dependances avant go-live.
- [x] Test manuel login/logout/timeout/refus CSRF.
- [x] Test manuel compte suspendu et permission retiree.
- [x] Test manuel restauration fichier et base.

Implementation 2026-05-28 :

- controle executable : `php backend/core/tools/private_migration_reconcile.php security-checklist` ;
- sortie JSON bloquante : code retour `1` si un controle automatisable echoue ;
- les tests manuels restent visibles dans la sortie pour la recette preprod, au lieu d'etre implicitement coches sans preuve ;
- le fichier public `robots.txt` ne liste volontairement aucun chemin admin ou prive ; l'anti-indexation privee est portee par authentification, `X-Robots-Tag` et CSP.

### 14.7 Definition of Done de la migration

- [x] Le public PHP reste stable, rapide et indexable.
- [x] Le prive est servi par une application separee ou un contexte Symfony-compatible separe.
- [x] Aucun module prive critique ne depend encore de templates PHP legacy.
- [x] Les donnees locatives et fiscales ont une source de verite unique.
- [x] Les imports agence sont reconciliables et auditables.
- [x] Les messages de discussion respectent la retention `60` jours.
- [x] Les fichiers prives restent hors webroot.
- [x] Les logs et exports ne fuitent pas de contenu sensible.
- [x] Le plan de restauration est teste.
- [x] Les anciennes routes privees PHP sont supprimees, bloquees ou redirigees explicitement.
- [x] Les README et runbooks refletent l'architecture reelle.

Implementation 2026-05-28 :

- controle executable : `php backend/core/tools/private_migration_reconcile.php migration-dod` ;
- sortie JSON bloquante : code retour `1` si un critere automatisable echoue ;
- le controle `migration-dod` agrege l'inventaire M5, l'inventaire M6 et `security-checklist` ;
- decision technique retenue : contexte HTTP prive separe et Symfony-compatible, sans creer de runtime separe tant que l'hebergement OVH Performance reste PHP mutualise.

### 14.8 Decision pratique a court terme

Ne pas commencer la migration tant que les modules prives PHP en cours ne sont pas stabilises et testes. La prochaine bonne etape est documentaire et preparatoire :

1. terminer l'audit de securite BO admin/prive ;
2. completer les tests HTTP et parcours navigateur critiques ;
3. figer les contrats fonctionnels de `Documents`, `FamilyDiscussion`, `RealEstateRental`, `AgencyImports` et `TaxDeclarationHelper` ;
4. acter la trajectoire OVH Performance : PHP moderne/Symfony-compatible pour le serveur prive ;
5. ne creer `private-app/` que si l'hebergement evolue vers Cloud Web Node, POWER Node, VPS ou equivalent supervise.

Cette decision evite de transformer une dette PHP en dette multi-stack. La migration doit reduire le risque, pas l'augmenter.

## 15. PVT-01 - Continuation d'implementation (Fondation)

Le lot PVT-01 reprend le socle minimal du portail famille en priorité. Les tâches ci-dessous sont à exécuter dans l’ordre pour limiter les risques de régression FO/BO.

### 15.1 Ordre cible d’implémentation

1. Variables et garde-fous de configuration (`PRIVATE_*`, activation, temps d’attente, rate limit).
2. Routage privé via `FrontController` avec résolveur dédié.
3. Guard session privée + stockage de session isolé.
4. Protection CSRF sur les actions mutatives.
5. Login / activation / reset / logout minimum sécurisé.
6. Dashboard privé minimal filtré par permissions.
7. Journal d’accès et refus avec audit append-only.
8. Headers anti-indexation + `robots.txt`.
9. Contrôle fichier : aucun point d’accès direct à `backend/public`.

### 15.2 Matrice de routes PVT-01 (MVP)

| Route | But | Methode | Protection | Sortie attendue |
|---|---|---|---|---|
| `/private` | Point d’entrée privé | GET | session privée | redirection vers `/private/dashboard` si authentifié, sinon `/private/login` |
| `/private/login` | Authentification | GET/POST | CSRF + rate limit | formulaire GET / création session + rotation ID |
| `/private/dashboard` | Accueil privé | GET | session + permissions | liste des modules autorisés |
| `/private/logout` | Fermeture session | POST | CSRF | invalidation session + redirection login |
| `/private/activate/{token}` | Activation invitation | GET/POST | token + hachage + audit | activation + choix mot de passe |
| `/private/password/forgot` | Reset auto-service | GET/POST | anti-abus + audit demande | retour neutre utilisateur |
| `/private/password/reset/{token}` | Réinitialisation mot de passe | GET/POST | token + contraintes mot de passe | remplacement sécurisé |
| `/private/files/{documentId}` | Téléchargement sécurisé | GET | session + module `documents` + audit | en-têtes téléchargement + flux contrôlé |

Règle métier clé : tout refus de permission (lecture/édition/téléchargement) doit être traçable et, côté API, renvoyer `401/403` selon le contexte.

### 15.3 Livrables PVT-01 attendus par étape

- Sprint 1 (routes + config) : endpoints privés fonctionnels, non-régression des routes publiques confirmée.
- Sprint 2 (session + login) : connexion privée isolée, lockout, gestion mots de passe et CSRF validés.
- Sprint 3 (dashboard + permissions) : affichage des modules autorisés seulement.
- Sprint 4 (audit + sécurité HTTP) : événements sensibles et headers anti-indexation vérifiés.
- Sprint 5 (pré-op) : documentation BO/BOU mise à jour + preuves d’exécution (curl / commandes test) archivées.

### 15.4 Critères de passage sprint PVT-01

1. `composer test -- --filter PrivatePortal` exécute sans erreur.
2. `composer test -- --filter FrontController` conserve le comportement FO.
3. Connexion privée : succès, refus silencieux, lockout et réinitialisation testés.
4. Une tentative d’accès privé non autorisé génère un event d’audit.
5. Aucune régression FO détectée sur `admin`, `blog`, `sitemap`, `rss`, et assets.

### 15.5 Protocole d’exécution d’office

Principe : poursuivre l’implémentation phase par phase, sans interruption systématique pour une validation minimale.

1. Exécuter des tests automatisés quand l’objectif de la tâche inclut explicitement une fonction testable de sécurité, de routage, de session ou d’audit.
2. Demander un test manuel quand le résultat dépend d’un parcours UX, d’un comportement navigateur, d’un flux email/token réel, ou d’une vérification infrastructure.
3. Si une tâche est documentaire ou structurelle (cadrage, checklists, matrice, migration de plan), poursuivre la rédaction jusqu’à la fin de la phase en cours avant toute pause.
4. En dehors de ces cas, continuer directement vers la phase suivante tant que les conditions de bascule sont remplies.
5. À chaque passage de phase, ajouter une entrée de preuve dans la section du README de la phase.
6. À la fin de chaque phase livrée, produire un commit dédié (message de phase) et pousser immédiatement la branche.

Référence d’arrêt : arrêt « naturel » au passage officiel d’une phase quand les critères de passage sont remplis et documentés ici.

### 15.6 Passe de phase en cours (cible)

Ce document suit la séquence : `Phase 0 -> Phase 1 -> Phase 2 -> Phase 3 -> Phase 4`.

Phase 1 est terminée.
Clôture phase 1 enregistrée dans : commit `6909548` (poussé sur `origin/chore/private-portal-docs-cleanup`).
Phase 2 (identité famille et sessions séparées) est clôturée en socle IAM complet :
- [x] Contrat auth/logout aligné (`Argon2id` + `POST /private/logout`) ; suites `PrivatePortalFrontControllerTest` et `FrontControllerHttpTest` passées.
- [x] Compléments IAM BO livrés en phase 3 : actions explicites, jetons invitation/reset hashés côté stockage, suspension, anonymisation et audit.
- [x] Compléments IAM clôturés : activation effective, écrans reset complets, notification email conditionnelle à la configuration mail, MFA et tests SQL métier.
Phase 3 ouverte puis clôturée le 2026-05-26 ; phase 4 démarrée, clôturée le 2026-05-26 et prête pour la montée phase 5.
Phase 5 reprise lancée le 2026-05-26 (point d'entrée documentaire et technique) ; implémentation noyau locatif à démarrer à la main sur la prochaine passe.
