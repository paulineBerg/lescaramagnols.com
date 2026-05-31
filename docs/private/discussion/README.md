# Module Discussion privee

Date de mise a jour : 2026-05-31

Ce document decrit le module prive `FamilyDiscussion`, expose dans l'interface sous le menu `Discussions`.
Il couvre l'existant fonctionnel, l'architecture applicative, les garanties de securite et les pistes d'optimisation pour faire evoluer le module vers une experience proche d'un vrai messenger.

Le module ne doit pas etre confondu avec les discussions publiques du blog. Il appartient a l'espace prive, depend des comptes `private_users`, et reste protege par l'authentification, les droits module et les routes privees.

## 1. Perimetre actuel

Le module sert aux echanges entre membres autorises de l'espace prive.

Fonctions disponibles :

- page de listing dediee des conversations sous `/private/discussions`;
- creation d'une conversation directe avec un autre membre actif ayant acces au module;
- creation d'une conversation de groupe avec titre et membres;
- affichage du dernier message, du nombre de messages non lus et de la date d'activite;
- affichage des conversations directes avec l'email de l'autre membre;
- page de conversation sous `/private/discussions/{conversationId}`;
- envoi de messages texte chiffres cote navigateur;
- ajout de fichiers joints limites par taille, extension et type MIME;
- stockage des fichiers joints chiffre au repos cote serveur;
- apercu image quand une piece jointe est une image;
- suppression d'un message ou d'une piece jointe par son auteur, ou par le proprietaire d'un groupe;
- marquage de lecture a l'ouverture et via API;
- invitation email d'un membre depuis l'ecran Discussions;
- retention automatique des messages et fichiers expires, par utilisateur a l'ouverture et via cron;
- export et purge des donnees raccordes au service central RGPD de l'espace prive.

Limites actuelles assumees :

- pas de WebSocket ni de Server-Sent Events;
- rafraichissement navigateur leger par API, pas de temps reel garanti;
- affichage charge les derniers messages par lot serveur limite, sans vraie pagination utilisateur;
- pas de presence en ligne, saisie en cours, reactions, epingles, brouillons, recherche ou notifications push;
- les metadonnees restent visibles cote serveur : participants, dates, taille et nom original des fichiers, presence d'un message chiffre;
- sans cle locale d'un appareil participant, le contenu texte chiffre n'est pas recuperable par le serveur.

## 2. Parcours utilisateur

### Listing

Route : `/private/discussions`

Le listing affiche les conversations accessibles au membre connecte. Il suit le patron des autres pages privees : titre de module, actions de creation, puis tableau/liste des conversations.

Pour une conversation directe, le libelle visible est l'email de l'autre personne. Pour une conversation de groupe, le titre saisi est utilise, avec fallback generique si le titre est absent.

### Conversation

Route : `/private/discussions/{conversationId}`

La page affiche le fil, les pieces jointes, les actions de suppression autorisees et le formulaire d'envoi.
Apres envoi reussi, la redirection conserve l'ancre `#discussion-message-last`, le dernier message porte l'identifiant `discussion-message-last`, et la notice `Message envoye.` est affichee comme popup temporaire.

### Invitations

L'invitation email utilise le catalogue des emails prives et le template `discussion_invite`.
Les envois doivent rester raccordes a la configuration SMTP privee existante et ne doivent pas hardcoder de nouveau contenu email.

### Fichiers

Les fichiers joints sont servis par les routes privees :

- `/private/discussions/files/{attachmentId}`;
- `/private/discussions/files/{attachmentId}/preview`.

Le controleur verifie la session, le droit module et l'appartenance a la conversation avant de lire le fichier. Les fichiers ne sont pas servis directement depuis `backend/public`.

## 3. Architecture applicative

### Routage

Le routage est declare dans `backend/src/PrivatePortal/Http/PrivateRouteResolver.php`.

Routes principales :

| Nom routeur | Methode | Chemin | Role |
|---|---:|---|---|
| `discussion_index` | GET/POST | `/private/discussions` | listing, creation de conversation, invitation |
| `discussion_conversation` | GET/POST | `/private/discussions/{conversationId}` | lecture et envoi dans un fil |
| `discussion_api_conversations` | GET/POST | `/private/discussions/api/conversations` | liste ou creation API |
| `discussion_api_messages` | GET/POST | `/private/discussions/api/conversations/{conversationId}/messages` | lecture incremental ou envoi API |
| `discussion_api_crypto_devices` | GET/POST | `/private/discussions/api/crypto/devices` | appareils crypto du membre |
| `discussion_api_conversation_keys` | GET/POST | `/private/discussions/api/conversations/{conversationId}/keys` | enveloppes de cles par conversation |
| `discussion_api_members` | POST | `/private/discussions/api/conversations/{conversationId}/members` | ajout de membres a un groupe |
| `discussion_api_leave` | POST | `/private/discussions/api/conversations/{conversationId}/leave` | sortie d'une conversation |
| `discussion_api_read` | POST | `/private/discussions/api/conversations/{conversationId}/read` | marquage de lecture |
| `discussion_file` | GET | `/private/discussions/files/{attachmentId}` | telechargement controle |
| `discussion_file_preview` | GET | `/private/discussions/files/{attachmentId}/preview` | apercu controle |

### Controleur

Le controleur est `backend/src/PrivatePortal/Http/PrivatePortalController.php`.

Responsabilites :

- verifier l'authentification et le droit module `discussions`;
- appliquer CSRF et rate limit sur les actions POST;
- declencher la purge des contenus expires a l'ouverture;
- router vers les services metier;
- rendre les templates ou les reponses JSON;
- appliquer les headers prives et la logique de telechargement controle;
- journaliser les refus d'acces, envois, suppressions et telechargements sensibles.

### Services

| Classe | Role |
|---|---|
| `DiscussionService` | orchestration metier : conversations, messages, membres, suppressions, validation de payload chiffre |
| `DiscussionAccessPolicy` | regles d'acces conversation : lecture, envoi, gestion des membres |
| `DiscussionAttachmentStorage` | validation, chiffrement, stockage, lecture et suppression des fichiers joints |
| `DiscussionRetentionService` | purge des messages et pieces jointes expires |
| `DiscussionRepository` | persistence SQL et hydratation des conversations, messages, pieces jointes, lectures, appareils et cles |

### Templates et assets

Templates :

- `backend/templates/private/modules/family-discussion/index.php`;
- `backend/templates/private/modules/family-discussion/conversation.php`.

Styles et interactions communes :

- `frontend/src/scss/private.scss`;
- JavaScript inline nonce dans le template conversation pour la crypto locale, le polling, la position sur le dernier message et la popup de confirmation.

Les templates doivent rester minces : pas de calcul metier complexe, pas de contournement des helpers d'echappement, pas d'action JavaScript visible sur un element autre qu'un vrai bouton quand une action est attendue.

## 4. Modele de donnees

Tables SQL du module :

| Table logique | Role |
|---|---|
| `discussion_conversations` | conversations directes ou groupes |
| `discussion_conversation_members` | participants, role, date d'ouverture, sortie |
| `discussion_messages` | messages, contenu chiffre, statut de purge, expiration |
| `discussion_message_attachments` | pieces jointes et metadonnees de stockage |
| `discussion_message_reads` | lectures par utilisateur et message |
| `discussion_crypto_devices` | appareils crypto declares par membre |
| `discussion_conversation_keys` | cles de conversation enveloppees par utilisateur/appareil |
| `discussion_retention_runs` | traces de purge de retention |

Les tables sont referencees dans :

- `backend/sql/private/*.sql`;
- `DiscussionRepository::ensureSchema()`;
- `PrivateMigrationService`;
- `PrivateBackupService`;
- `PrivateDataProtectionService`;
- `PrivateSecurityChecklistService`.

Tout ajout de table ou de fichier physique au module doit aussi etre declare dans les scopes de sauvegarde, export, purge immediate et suppression differee.

## 5. Securite et confidentialite

Garanties actuelles :

- acces reserve aux membres authentifies;
- droit module `discussions` obligatoire;
- verification de participation avant lecture d'une conversation, d'un message ou d'un fichier;
- CSRF sur les actions d'ecriture;
- rate limit separe pour creation de conversation et envoi de message;
- messages texte exiges en mode chiffre `client_aes_gcm_v1` quand un corps texte est fourni;
- fichiers joints chiffres sur disque avec AES-256-GCM;
- stockage hors webroot sous le stockage prive configure;
- validation stricte des pieces jointes : nom, extension, MIME, taille, checksum;
- headers prives et `Cache-Control: private, no-store` sur les fichiers servis;
- echappement HTML dans les templates;
- logs applicatifs sans contenu de message;
- retention par defaut de 60 jours;
- purge RGPD centralisee avec neutralisation des messages, pieces jointes, appartenances, appareils et cles.

Secret important :

`PRIVATE_DISCUSSION_ATTACHMENT_ENCRYPTION_KEY` doit etre defini hors depot avec une cle dediee. La forme recommandee est `base64:` suivi de 32 octets aleatoires encodes.

Points de vigilance :

- les noms originaux des fichiers restent des metadonnees lisibles serveur;
- l'email de l'autre participant est affiche dans les conversations directes;
- la recherche serveur dans les contenus chiffres n'est pas possible sans changer le modele de confidentialite;
- toute evolution "messenger" doit preserver la separation entre contenu chiffre, metadonnees necessaires et journaux d'exploitation.

## 6. Configuration

Configuration principale : `backend/config/config.php`, cle `private.discussions`.

Parametres utilises :

| Cle | Role |
|---|---|
| `storage_root_path` | racine du stockage prive |
| `retention_days` | duree de conservation des messages et fichiers |
| `max_message_length` | longueur maximale du message texte |
| `max_attachments_per_message` | nombre maximal de fichiers joints par message |
| `max_attachment_bytes` | taille maximale d'une piece jointe |
| `poll_interval_seconds` | intervalle de rafraichissement client |
| `message_rate_limit_attempts` | nombre d'envois autorises par fenetre |
| `message_rate_limit_window` | fenetre de rate limit message |
| `conversation_rate_limit_attempts` | nombre de creations autorisees par fenetre |
| `conversation_rate_limit_window` | fenetre de rate limit conversation |
| `attachment_encryption_key` | secret de chiffrement des fichiers joints |
| `allowed_extensions` | extensions autorisees |
| `allowed_mime_types` | types MIME autorises |

## 7. Exploitation

Purge planifiee :

```bash
php backend/core/tools/purge_private_discussions.php --dry-run --json
php backend/core/tools/purge_private_discussions.php --json
```

La purge est inscrite dans le Cron Center prive sous le job `purge_private_discussions`.

Controles utiles :

```bash
cd backend
./vendor/bin/phpunit --configuration phpunit.xml tests/PrivateApps/FamilyDiscussion/FamilyDiscussionModuleTest.php
./vendor/bin/phpunit --configuration phpunit.xml tests/PrivatePortal/PrivateUiGuardTest.php tests/PrivateRouteResolverTest.php
composer phpstan
composer phpcs
```

Controle d'exploitation :

```bash
cd backend
composer check-log-alerts -- --json --strict --cron-failed-threshold=1 --private-rate-limit-threshold=3
```

## 8. Optimisations et TODO vers un vrai messenger

### Priorite 1 - Robustesse conversationnelle

- Ajouter une pagination curseur sur les messages : `before_id`, `after_id`, limite parametrable, chargement progressif depuis le haut du fil.
- Introduire une table d'evenements de conversation append-only : message cree, message supprime, piece jointe supprimee, membre ajoute, lecture, sortie.
- Rendre les actions d'envoi idempotentes avec une cle client `client_message_id` unique par conversation et appareil.
- Distinguer clairement `deleted`, `purged`, `redacted` et `expired` pour eviter de perdre l'historique utile de moderation sans conserver le contenu.
- Ajouter des index dedies aux lectures frequentes : conversations par utilisateur/date, messages par conversation/date, messages non lus par participant.

### Priorite 2 - Temps reel progressif

- Remplacer le polling simple par Server-Sent Events pour les nouveaux messages, lectures et suppressions.
- Garder le polling comme fallback navigateur ou hebergement.
- Prevoir une abstraction `ConversationEventStream` pour pouvoir basculer plus tard vers WebSocket sans changer les services metier.
- Ajouter des tests de concurrence : double envoi, double suppression, ouverture simultanee sur deux appareils.

### Priorite 3 - Chiffrement multi-appareils

- Formaliser le protocole crypto V2 : creation de cle de conversation, rotation, enveloppes par appareil, revocation.
- Ajouter un parcours d'ajout d'appareil lisible : appareil connu, nouvel appareil, validation, regeneration des enveloppes.
- Prevoir une strategie de recuperation explicite : aucune recuperation serveur, ou phrase de secours chiffree localement, mais pas de demi-mesure implicite.
- Journaliser uniquement les metadonnees de securite necessaires : appareil ajoute, appareil revoque, rotation, echec de dechiffrement local.

### Priorite 4 - Experience utilisateur

- Passer a une interface deux panneaux sur desktop : liste a gauche, fil actif a droite, retour simple sur mobile.
- Rendre le composer sticky en bas du fil, avec hauteur stable et actions compactes.
- Ajouter glisser-deposer, collage d'image, preview avant envoi et suppression locale avant upload.
- Ajouter brouillon local par conversation dans le navigateur.
- Ajouter recherche locale dans les messages dechiffres deja charges, sans indexer le contenu clair cote serveur.
- Ajouter reactions, reponses citees, epingles et filtre pieces jointes seulement apres stabilisation de la couche temps reel.

### Priorite 5 - Notifications

- Ajouter notifications email de nouveau message avec contenu neutre par defaut.
- Respecter les preferences par conversation : muet, digest, jamais notifier.
- Utiliser la configuration SMTP membre ou privee selon le modele produit retenu, sans hardcoder d'expediteur.
- Ajouter une file d'attente future pour gros volumes et retries SMTP.
- Etudier les notifications navigateur seulement apres consentement explicite et sans dependance tierce inutile.

### Priorite 6 - Fichiers et medias

- Ajouter antivirus ou file d'attente de scan pour les pieces jointes Discussion, avec blocage tant que le statut n'est pas sain.
- Generer les miniatures en tache de fond pour les images lourdes.
- Ajouter quotas par utilisateur et par conversation.
- Ajouter nettoyage des fichiers orphelins avec dry-run JSON.
- Ajouter galerie media et filtre par type de piece jointe.

### Priorite 7 - Observabilite et exploitation

- Ajouter des metriques dediees : messages envoyes, echecs d'envoi, refus d'acces, temps de purge, erreurs de dechiffrement client remontees sans contenu.
- Ajouter un tableau ops sans contenu prive : volumes, erreurs, files d'attente, retentions en retard.
- Completer les alertes `check_log_alerts.php` avec erreurs de stream temps reel, echec de scan et volume anormal.
- Ajouter tests de charge sur conversations longues et nombreuses pieces jointes.

### Priorite 8 - Qualite produit

- Ajouter tests navigateur Playwright sur desktop/mobile : listing, ouverture, envoi, popup, scroll dernier message, suppression, upload.
- Ajouter tests d'accessibilite clavier et lecteurs d'ecran sur le fil et le composer.
- Ajouter tests de non-regression visuelle sur conversation vide, conversation longue, pieces jointes et erreurs.
- Documenter un protocole de recette preprod avec deux comptes membres actifs et un compte sans droit Discussion.

## 9. Regles de contribution

Toute evolution du module doit respecter ces invariants :

1. aucune route publique directe pour les contenus prives;
2. aucune suppression de donnees utilisateur hors `PrivateDataProtectionService` pour les operations RGPD;
3. aucune nouvelle table ou stockage fichier sans inscription dans sauvegarde, export et purge;
4. aucun email hardcode sans template prive configurable;
5. aucun contenu de message dans les logs;
6. aucun changement de crypto sans test de compatibilite et documentation de migration;
7. validation locale minimale : PHPUnit cible Discussion, route resolver, PHPStan ou controle plus large selon le risque.
