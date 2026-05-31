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
| `discussion_api_events` | GET | `/private/discussions/api/conversations/{conversationId}/events` | flux SSE court depuis le journal d'evenements |
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
| `DiscussionTimelineService` | timeline paginee par curseurs, sans `OFFSET` |
| `DiscussionEventService` | journal append-only minimal, sans contenu clair |
| `ConversationEventStream` | reponse SSE privee courte avec fallback polling cote navigateur |
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
| `discussion_conversation_events` | journal append-only minimal pour sync, SSE et audit sans contenu clair |
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

## 8. Optimisation strategique vers un vrai messenger

### Diagnostic global

Le module `FamilyDiscussion` est deja bien separe des discussions publiques du blog. Il est rattache a l'espace prive, protege par authentification, droits module et routes privees, et dispose deja des briques critiques : conversations directes, groupes, messages chiffres cote navigateur, fichiers joints chiffres cote serveur, lectures, invitations, retention et raccordement RGPD.

Le point faible principal n'est donc pas le socle de securite. Le vrai ecart avec un messenger fluide se situe dans l'experience et la scalabilite : fil limite, polling simple, absence de journal d'evenements, pas encore d'idempotence d'envoi, pas de presence, pas de saisie en cours, pas de brouillons, pas de recherche locale organisee et pas de notifications.

Strategie retenue :

1. solidifier le socle conversationnel;
2. ajouter le temps reel progressif;
3. ameliorer ensuite l'interface et les fonctions messenger visibles.

Cette trajectoire evite d'ajouter des fonctions agreables mais fragiles. Elle conserve les invariants du projet : rendu serveur PHP, logique metier dans `backend/src/`, donnees privees hors webroot, pas de contenu de message dans les logs, pas de SPA complete, ameliorations TypeScript ciblees et confidentialite non negociable.

### Problemes a corriger en priorite

1. Le fil n'est pas encore scalable.
   Il faut ajouter une pagination curseur avec `before_id`, `after_id`, limite parametrable et chargement progressif depuis le haut du fil. L'objectif est d'eviter `OFFSET` et de garder un fil performant avec conversations longues et nombreuses pieces jointes.

2. Il manque un journal d'evenements conversationnels.
   Les tables metier actuelles stockent l'etat, mais un messenger a aussi besoin d'un flux append-only minimal : message cree, message supprime, piece jointe supprimee, lecture, membre ajoute, sortie, renommage, appareil ajoute ou revoque. Ce journal sert au temps reel, aux notifications, aux synchronisations multi-appareils et a l'audit sans contenu clair.

3. L'envoi n'est pas encore idempotent.
   Un double clic, une reconnexion reseau ou un retry peut creer deux messages. Il faut ajouter `client_message_id`, unique par conversation et appareil.

4. Le temps reel doit etre progressif.
   WebSocket n'est pas la premiere cible. Pour le contexte PHP/OVH, Server-Sent Events est le meilleur premier palier : plus simple, suffisant pour nouveaux messages/lectures/suppressions, securisable via session privee, et compatible avec un fallback polling.

5. Le chiffrement multi-appareils doit etre clarifie.
   Regle cible : le serveur ne recupere jamais le contenu texte en clair. Le protocole doit ensuite formaliser appareils connus, nouvel appareil, enveloppes de cles, revocation, rotation et strategie de secours explicite.

## 9. Modifications cibles par couche

### Base de donnees

Ajouter ou completer :

| Element | Objectif |
|---|---|
| `discussion_conversation_events` | journal append-only des evenements de conversation |
| `discussion_messages.client_message_id` | idempotence d'envoi cote client |
| index conversations par utilisateur/date | listing rapide |
| index messages par conversation/id | pagination curseur |
| index lectures non lues par participant | badges non lus |
| index evenements par conversation/id | flux SSE et sync |
| index pieces jointes par message | lecture et purge rapides |
| index purges par expiration/statut | retention robuste |

Schema cible minimal pour `discussion_conversation_events` :

| Champ | Role |
|---|---|
| `id` | curseur croissant |
| `conversation_id` | conversation concernee |
| `actor_user_id` | acteur prive, nullable si evenement systeme |
| `event_type` | type normalise |
| `event_payload_json` | metadonnees minimales, sans contenu de message |
| `client_event_id` | idempotence des evenements client |
| `request_id` | correlation technique sans secret |
| `created_at` | date serveur |

Statuts a clarifier :

- `active` : contenu disponible;
- `deleted` : suppression utilisateur visible, contenu potentiellement encore non purge selon regle produit;
- `redacted` : contenu neutralise volontairement, trace conservee;
- `expired` : contenu arrive a retention;
- `purged` : contenu et fichiers neutralises/supprimes.

### Backend

Services a creer ou renforcer :

| Service cible | Role |
|---|---|
| `DiscussionTimelineService` | chargement initial, pagination avant/apres, normalisation timeline |
| `DiscussionEventService` | creation append-only, lecture incrementale, source SSE |
| `ConversationEventStream` | abstraction SSE d'abord, WebSocket possible plus tard |
| `DiscussionMessageCommandService` | envoi idempotent, suppression, redaction, validation payload chiffre |
| `DiscussionNotificationService` | notifications email neutres, preferences, digest futur |
| `DiscussionMediaService` | scan, miniatures, galerie, quotas, orphelins |
| `DiscussionCryptoDeviceService` | appareils, rotation, revocation, enveloppes de cles |

Regles backend :

- aucune route publique directe;
- aucun contenu de message dans `discussion_conversation_events`;
- aucun contenu clair dans les logs;
- toute nouvelle table/fichier doit entrer dans backup, export, purge immediate et suppression differee;
- toute action destructive doit rester idempotente ou rejouable sans effet double.

### Frontend

Extraction progressive du JavaScript inline vers :

- `frontend/src/js/private-discussion.ts`;
- `frontend/src/scss/private-discussion.scss`.

Le rendu initial doit rester cote PHP. Le JavaScript hydrate ensuite les interactions : pagination, SSE/fallback polling, etat d'envoi, brouillon local, drag and drop, collage image et preview avant envoi.

Regles frontend :

- ne pas transformer le module en SPA;
- ne pas mettre de logique metier dans les templates;
- ne pas stocker de secret ou cle dans `localStorage`;
- ne pas indexer le contenu dechiffre cote serveur;
- garder les actions visibles sur de vrais boutons.

### Interface utilisateur

Cible desktop :

- liste des conversations a gauche;
- fil actif a droite;
- panneau de detail optionnel seulement quand utile;
- composer sticky en bas du fil;
- actions compactes et stables.

Cible mobile :

- liste en premier;
- ouverture d'une conversation en vue simple;
- retour explicite a la liste;
- composer fixe ou sticky sans masquer les messages;
- aucun debordement horizontal.

Fonctions UI a ajouter apres le socle :

- etat `envoi`, `envoye`, `erreur`;
- drag and drop de fichiers;
- collage image;
- preview avant upload;
- suppression locale avant envoi;
- brouillon local par conversation;
- recherche locale dans les messages deja dechiffres et charges;
- filtre pieces jointes.

Reactions, reponses citees et epingles ne viennent qu'apres stabilisation de la timeline, des evenements et du temps reel.

### Notifications

Priorite aux notifications email neutres, pas aux notifications navigateur.

Regles :

- sujet et corps via templates mail prives;
- contenu neutre, sans message ni nom de fichier sensible;
- lien vers la conversation;
- preferences par conversation : notifier, muet, digest, jamais;
- pas d'email a un membre sorti de la conversation;
- retries futurs via file d'attente, sans boucle d'envoi en cas d'erreur SMTP.

Table cible possible : `discussion_notification_preferences`.

### Fichiers et medias

Priorites :

1. scan antivirus ou file d'attente de scan;
2. blocage tant que le fichier n'est pas sain;
3. miniatures asynchrones chiffrees hors webroot;
4. quotas par utilisateur et par conversation;
5. nettoyage des fichiers orphelins avec dry-run JSON;
6. galerie media.

Statuts fichiers cibles :

- `pending_scan`;
- `available`;
- `blocked`;
- `deleted`;
- `purged`.

Commandes CLI a prevoir :

```bash
php backend/core/tools/scan_private_discussion_attachments.php --dry-run --json
php backend/core/tools/cleanup_private_discussion_orphans.php --dry-run --json
```

### Observabilite

Ajouter une vue ops sans contenu prive, reservee aux profils autorises.

Metriques utiles :

- messages envoyes;
- echecs d'envoi;
- refus d'acces;
- erreurs de dechiffrement client sans contenu;
- temps de purge;
- retards de retention;
- erreurs de stream;
- echecs de scan fichiers;
- volume anormal de messages ou pieces jointes;
- rate-limit.

Les alertes `check_log_alerts.php` pourront ensuite couvrir les erreurs SSE, les echecs de scan et les retards de retention.

### Tests

Couverture cible :

| Niveau | Cas |
|---|---|
| PHPUnit | idempotence, pagination, droits, lecture/suppression, evenements, fichiers, purge RGPD, cles/appareils |
| Concurrence | double envoi, double suppression, ouverture sur deux appareils |
| Playwright | listing, ouverture, envoi, popup, scroll dernier message, suppression, upload, mobile |
| Accessibilite | navigation clavier, lecteur d'ecran, focus visible, vrais boutons |
| Charge | conversations longues, nombreuses pieces jointes, fichiers lourds/refuses |

## 10. Phases de developpement et checklists

### Phase D1 - Socle conversationnel durable

Objectif : rendre le fil scalable, robuste et rejouable.

Checklist :

- [x] ajouter une migration SQL privee pour les index manquants;
- [x] ajouter `client_message_id` dans `discussion_messages`;
- [x] ajouter une contrainte d'unicite adaptee a `conversation_id + sender_private_user_id + client_message_id`;
- [x] ajouter pagination curseur `before_id` et `after_id`;
- [x] eviter toute pagination par `OFFSET`;
- [x] clarifier les statuts `deleted`, `redacted`, `expired`, `purged`;
- [x] adapter `DiscussionRepository`;
- [x] ajouter `DiscussionTimelineService`;
- [x] tester conversations longues et lots de messages;
- [x] verifier backup/export/purge apres changement de schema.

Methodes utiles a prevoir :

- `findMessagesBefore()`;
- `findMessagesAfter()`;
- `findConversationTimeline()`;
- `findUnreadCountsForUser()`.

Pieges a eviter :

- suppression physique trop precoce;
- confusion entre suppression utilisateur et purge RGPD;
- perte de trace utile pour moderation ou audit sans contenu clair.

### Phase D2 - Journal d'evenements append-only

Objectif : preparer temps reel, sync et notifications.

Checklist :

- [x] creer `discussion_conversation_events`;
- [x] creer `DiscussionEventService`;
- [x] ecrire un evenement a chaque message cree;
- [x] ecrire un evenement a chaque message supprime/redige;
- [x] ecrire un evenement a chaque piece jointe supprimee;
- [x] ecrire un evenement a chaque lecture;
- [x] ecrire un evenement a chaque ajout/sortie membre;
- [x] ecrire un evenement a chaque appareil ajoute/revoque;
- [x] ne jamais stocker le contenu du message dans l'evenement;
- [x] ajouter tests d'idempotence et de payload minimal.

Pieges a eviter :

- transformer cette table en log technique verbeux;
- stocker des noms de fichiers sensibles sans necessite;
- exposer les payloads bruts dans une vue ops.

### Phase D3 - Temps reel progressif

Objectif : remplacer le polling simple par SSE avec fallback.

Checklist :

- [x] creer l'abstraction `ConversationEventStream`;
- [x] ajouter la route privee `/private/discussions/api/conversations/{conversationId}/events`;
- [x] verifier session, module et participation avant stream;
- [x] lire depuis `discussion_conversation_events`;
- [x] envoyer les evenements recents par curseur;
- [x] conserver fallback polling;
- [x] tester deconnexion, reprise et absence de droit;
- [x] documenter les limites hebergement si SSE est coupe.

Pieges a eviter :

- connexion ouverte sans controle de session;
- exposition d'une conversation non autorisee;
- envoi de contenu clair dans le flux;
- dependance forte a SSE sans fallback.

Limite hebergement retenue : le endpoint SSE renvoie un flux court des evenements recents puis laisse le navigateur se reconnecter avec `Last-Event-ID` ou `after_event_id`. Si un proxy OVH ou le navigateur coupe SSE, le polling existant reste actif et recharge les messages par `after_message_id`.

### Phase D4 - Interface messenger

Objectif : rendre l'usage fluide sans transformer le module en SPA.

Checklist :

- [ ] creer `frontend/src/js/private-discussion.ts`;
- [ ] creer `frontend/src/scss/private-discussion.scss` si necessaire;
- [ ] garder le rendu initial PHP;
- [ ] ajouter layout deux panneaux desktop;
- [ ] ajouter vue mobile liste puis fil;
- [ ] rendre le composer sticky;
- [ ] ajouter etats d'envoi;
- [ ] ajouter drag and drop;
- [ ] ajouter collage image;
- [ ] ajouter preview avant upload;
- [ ] ajouter brouillon local sans secret;
- [ ] ajouter recherche locale uniquement dans messages dechiffres charges;
- [ ] tester 390px, 768px et desktop.

Pieges a eviter :

- logique metier dans le template;
- secret crypto en `localStorage`;
- action visible sur `<span>` ou `<a>` sans vraie navigation;
- debordement horizontal.

### Phase D5 - Crypto multi-appareils V2

Objectif : rendre le chiffrement comprehensible et durable sur plusieurs appareils.

Checklist :

- [ ] formaliser le protocole crypto V2;
- [ ] definir statuts appareil `trusted`, `pending`, `revoked`;
- [ ] utiliser `discussion_crypto_devices`;
- [ ] utiliser `discussion_conversation_keys`;
- [ ] ajouter UI "cet appareil est connu";
- [ ] ajouter UI "nouvel appareil detecte";
- [ ] ajouter action "revoquer cet appareil";
- [ ] ajouter action "regenerer les cles de conversation";
- [ ] documenter la strategie de recuperation;
- [ ] tester rotation et revocation.

Regle centrale :

Le serveur ne doit jamais promettre une recuperation du contenu texte si aucune cle locale autorisee ne peut le dechiffrer.

### Phase D6 - Notifications email neutres

Objectif : informer sans fuite de contenu.

Checklist :

- [ ] ajouter `discussion_notification_preferences`;
- [ ] creer `DiscussionNotificationService`;
- [ ] ajouter templates mail prives configurables;
- [ ] envoyer un email neutre de nouveau message;
- [ ] ajouter preference conversation muette;
- [ ] ajouter preference digest futur;
- [ ] ne pas notifier un membre sorti;
- [ ] ne pas envoyer le contenu du message;
- [ ] ne pas envoyer le nom sensible d'un fichier;
- [ ] tester erreur SMTP sans fuite utilisateur.

### Phase D7 - Fichiers et medias renforces

Objectif : mieux controler les pieces jointes.

Checklist :

- [ ] ajouter statuts `pending_scan`, `available`, `blocked`, `deleted`, `purged`;
- [ ] ne servir que les fichiers `available`;
- [ ] ajouter scan antivirus ou file d'attente;
- [ ] generer miniatures hors webroot;
- [ ] chiffrer miniatures et fichiers;
- [ ] ajouter quotas utilisateur/conversation;
- [ ] ajouter cleanup orphelins avec dry-run JSON;
- [ ] ajouter galerie media;
- [ ] verifier scopes backup/export/purge.

### Phase D8 - Observabilite sans contenu prive

Objectif : suivre l'exploitation sans exposer les messages.

Checklist :

- [ ] creer une vue ops reservee;
- [ ] afficher volumes et erreurs;
- [ ] afficher retards de purge;
- [ ] afficher erreurs de stream;
- [ ] afficher echecs de scan;
- [ ] afficher rate-limit;
- [ ] afficher erreurs de dechiffrement client sans contenu;
- [ ] enrichir `check_log_alerts.php`;
- [ ] ne pas afficher de cles, messages ni noms sensibles inutiles.

### Phase D9 - Recette et qualite produit

Objectif : stabiliser avant enrichissements visibles.

Checklist :

- [ ] ajouter tests PHPUnit idempotence;
- [ ] ajouter tests PHPUnit pagination;
- [ ] ajouter tests PHPUnit evenements;
- [ ] ajouter tests PHPUnit crypto/appareils;
- [ ] ajouter tests concurrence;
- [ ] ajouter Playwright desktop/mobile;
- [ ] tester compte sans droit Discussion;
- [ ] tester conversations longues;
- [ ] tester fichiers lourds/refuses;
- [ ] tester accessibilite clavier et focus.

## 11. Ordre recommande de developpement

1. Pagination + index SQL + statuts propres.
2. Idempotence d'envoi avec `client_message_id`.
3. Table append-only `discussion_conversation_events`.
4. SSE avec fallback polling.
5. Interface deux panneaux + composer sticky.
6. Brouillons locaux + upload ameliore.
7. Crypto multi-appareils V2.
8. Notifications email neutres.
9. Scan fichiers + quotas + galerie.
10. Observabilite ops.
11. Playwright + accessibilite + charge.

## 12. Ce qu'il ne faut pas faire maintenant

- ne pas commencer par reactions, epingles et reponses citees;
- ne pas ajouter WebSocket avant d'avoir teste SSE;
- ne pas indexer le contenu dechiffre cote serveur;
- ne pas mettre le contenu des messages dans les notifications;
- ne pas stocker de cles dans `localStorage`;
- ne pas multiplier les routes publiques;
- ne pas creer de fichiers hors scopes backup/export/RGPD;
- ne pas mettre la logique de messagerie dans les templates.

## 13. Vision finale attendue

Le module cible doit devenir un vrai messenger prive familial :

- affichage fluide meme avec de longues conversations;
- nouveaux messages visibles presque en temps reel;
- envoi fiable meme en cas de double clic ou reseau instable;
- interface claire sur desktop et mobile;
- fichiers mieux controles, scannes et organises;
- notifications utiles mais discretes;
- chiffrement compatible multi-appareils;
- audit exploitable sans fuite de contenu prive;
- RGPD, purge, sauvegarde et suppression toujours coherents;
- tests solides avant chaque evolution.

Le resultat final doit rester simple pour l'utilisateur, mais strict cote architecture : module prive, securise, tracable, evolutif, sans exposition publique directe et sans perte de confidentialite.

## 14. Regles de contribution

Toute evolution du module doit respecter ces invariants :

1. aucune route publique directe pour les contenus prives;
2. aucune suppression de donnees utilisateur hors `PrivateDataProtectionService` pour les operations RGPD;
3. aucune nouvelle table ou stockage fichier sans inscription dans sauvegarde, export et purge;
4. aucun email hardcode sans template prive configurable;
5. aucun contenu de message dans les logs;
6. aucun changement de crypto sans test de compatibilite et documentation de migration;
7. validation locale minimale : PHPUnit cible Discussion, route resolver, PHPStan ou controle plus large selon le risque.
