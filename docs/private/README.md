# Portail prive famille, locations et aide impots

Date de mise a jour : 2026-05-26
Statut : cadrage cible, non implemente

Ce document est le point d'entree dedie au futur espace prive famille du projet `caramagnols`.
Il remplace l'ancien cadrage generique du portail prive par une vision plus precise : un socle `PrivatePortal`, des comptes famille separes de l'administration, des webapps privees activables au cas par cas, puis deux modules metier prioritaires :

1. `RealEstateRental` : gestion des locations immobilieres.
2. `TaxDeclarationHelper` : aide a la preparation annuelle des impots, alimentee par les locations et par d'autres sources declarees.

References projet a garder alignees :

- `AGENTS.md`
- `docs/README.md`
- `docs/admin/README.md`
- `docs/backend/public-entrypoints.md`
- `docs/backend/logging.md`
- `docs/security/README.md`
- `docs/private/backlog-pvt01.md`
- `docs/deployment/README.md`

## 1. Decision produit

L'objectif n'est pas d'ajouter une simple page "client". Le besoin reel est de creer un portail prive famille, distinct du site public et du BO administrateur, accessible uniquement aux personnes autorisees.

Le portail doit permettre :

1. de gerer des comptes famille separes des comptes admin ;
2. d'inviter, activer, suspendre, anonymiser ou supprimer ces comptes depuis le BO ;
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
    +-- Sources declaratives
    +-- Donnees locatives importees
    +-- Revenus manuels
    +-- Futures sources specialisees
    +-- Controle de coherence
    +-- Synthese annuelle
    +-- Exports PDF/CSV
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
|
+-- templates/
|   +-- private/
|       +-- layout.php
|       +-- login.php
|       +-- dashboard.php
|       +-- modules/
|           +-- real-estate-rental/
|           +-- tax-declaration-helper/
|
+-- private/
|   +-- storage/
|   |   +-- real-estate-rental/
|   |   +-- tax-declaration-helper/
|   +-- uploads/
|   +-- exports/
|
+-- sql/
    +-- private/
        +-- 001_private_portal.sql
        +-- 002_private_permissions.sql
        +-- 003_real_estate_rental.sql
        +-- 004_tax_declaration_helper.sql
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
private_users
private_user_invites
private_password_resets
private_sessions
private_modules
private_user_module_permissions
private_audit_logs
private_documents
private_rgpd_exports
```

Contraintes attendues :

1. email unique cote `private_users` ;
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
3. voir le statut du compte : invite, actif, suspendu, verrouille, supprime/anonymise ;
4. suspendre un compte ;
5. reinitialiser l'acces ;
6. affecter les modules autorises ;
7. consulter les derniers evenements d'audit utiles ;
8. lancer un export RGPD ;
9. supprimer ou anonymiser un compte selon la politique retenue.

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
6. les loyers attendus et encaisses ;
7. les charges ;
8. les travaux ;
9. les taxes ;
10. les assurances ;
11. les documents ;
12. les exports annuels ;
13. les donnees necessaires au module fiscal.

### 6.2 Entites principales

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
```

### 6.3 Tables SQL

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
```

Regles de stockage :

1. montants stockes en decimal, jamais en float ;
2. dates stockees en date SQL normalisee ;
3. statut explicite pour brouillon, valide, annule, archive ;
4. suppression physique evitee pour les documents : preferer statut, audit et retention ;
5. chemin fichier prive non devinable et non public ;
6. les metadonnees documentaires ne doivent pas exposer de donnees sensibles dans les logs.

### 6.4 Categories de charges

Referentiel initial :

```text
charges_copropriete
taxe_fonciere
assurance_pno
assurance_loyer_impaye
travaux_entretien
travaux_reparation
travaux_amelioration
diagnostics
frais_agence
frais_bancaires
interets_emprunt
honoraires_comptable
frais_postaux
autre
```

Le champ `tax_deductible` est obligatoire pour preparer le lien fiscal, mais il reste indicatif. Il ne doit pas etre presente comme une validation fiscale officielle.

### 6.5 Ecrans

Routes recommandees :

```text
/private/locations
/private/locations/biens
/private/locations/biens/{id}
/private/locations/locataires
/private/locations/baux
/private/locations/loyers
/private/locations/charges
/private/locations/documents
/private/locations/exports
/private/locations/synthese-annuelle
```

Les ecrans doivent etre denses, lisibles et utilisables sur mobile, sans effet marketing ni decoration inutile. Les actions sensibles utilisent confirmation, CSRF, permission serveur et audit.

## 7. Module TaxDeclarationHelper

### 7.1 Objectif

`TaxDeclarationHelper` est un assistant de preparation et de controle. Il lit les donnees locatives validees, accepte des revenus manuels au cas par cas et pourra integrer plus tard d'autres webapps sources.

Il ne doit jamais se presenter comme une declaration fiscale automatique garantie.

Mention a afficher dans le module :

```text
Les montants fournis sont une aide a la preparation. Ils doivent etre verifies avant declaration officielle.
```

### 7.2 Donnees par annee

Le module doit produire une synthese annuelle distinguant toujours l'origine des donnees :

```text
Annee fiscale
Biens concernes
Loyers encaisses
Autres revenus saisis manuellement
Autres revenus importes depuis une future webapp source
Charges encaisses
Charges potentiellement deductibles
Travaux
Assurances
Taxes
Interets eventuels
Frais divers
Documents manquants
Anomalies
Export de synthese
```

### 7.3 Sources declaratives

La relation entre modules doit passer par un contrat de source, pas par une lecture directe de toutes les tables locatives depuis le module fiscal.

```text
RealEstateRental
+-- TaxBridge/
    +-- RentalTaxDataProvider.php

TaxDeclarationHelper
+-- Source/
|   +-- TaxDataSourceInterface.php
|   +-- ManualIncomeSource.php
|   +-- RentalTaxDataSource.php
+-- Service/
    +-- AnnualTaxSummaryBuilder.php
```

Contrat indicatif :

```php
interface RentalTaxDataProviderInterface
{
    public function getAnnualRentalIncome(int $year, int $userId): AnnualRentalIncome;

    public function getAnnualDeductibleExpenses(int $year, int $userId): AnnualDeductibleExpenses;

    /**
     * @return list<MissingTaxDocument>
     */
    public function getMissingTaxDocuments(int $year, int $userId): array;
}
```

Cette couche permet :

1. de proteger le module fiscal des details internes de la gestion locative ;
2. de remplacer ou enrichir une source sans reecrire la synthese ;
3. d'ajouter plus tard une webapp source pour un revenu recurrent ;
4. d'afficher clairement l'origine de chaque ligne.

### 7.4 Revenus manuels V1

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

### 7.5 Tables SQL

Tables ciblees :

```text
tax_years
tax_income_sources
tax_manual_income_entries
tax_annual_summaries
tax_summary_lines
tax_export_logs
```

Regles :

1. une synthese annuelle garde une trace des sources utilisees ;
2. les lignes importees et les lignes manuelles restent separees ;
3. un statut `draft`, `generated`, `locked`, `archived` doit exister ;
4. une annee verrouillee ne peut plus etre modifiee sans action admin auditee ;
5. les exports doivent etre regenerables ou tracables.

### 7.6 Ecrans

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
2. afficher les loyers encaisses ;
3. saisir ou modifier d'autres revenus manuels ;
4. afficher l'origine de chaque montant ;
5. distinguer charges, travaux, assurances, taxes et interets ;
6. signaler les documents manquants ;
7. bloquer la generation si des donnees sources sont encore en brouillon ;
8. produire une synthese annuelle ;
9. exporter PDF et CSV ;
10. verrouiller une annee validee.

Hors perimetre V1 :

1. teledeclaration automatique ;
2. connexion directe `impots.gouv` ;
3. calcul fiscal complexe garanti ;
4. optimisation fiscale automatique ;
5. choix automatique du regime fiscal ;
6. webapp separee pour chaque revenu occasionnel.

## 8. Securite et confidentialite

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
10. `Disallow: /private/` dans `robots.txt` ;
11. aucun token dans `localStorage` ;
12. aucun fichier prive dans `backend/public` ;
13. aucun chemin disque prive dans les reponses HTTP ;
14. logs sans mot de passe, token, document sensible ou montant inutile ;
15. erreurs utilisateur non verbeuses ;
16. actions sensibles journalisees avec `request_id`.

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

## 9. Phases d'implementation

Chaque phase doit etre livree avec tests, verification manuelle ciblee et documentation mise a jour si le comportement change.

### Phase 0 - Cadrage technique et garde-fous

Objectif : figer le perimetre avant code.

Checklist :

- [ ] Valider que `docs/private/README.md` est la source documentaire du chantier.
- [ ] Verifier les contraintes de `AGENTS.md`, `docs/backend/public-entrypoints.md`, `docs/backend/logging.md` et `docs/security/README.md`.
- [ ] Decider si l'auth MVP est locale ou OIDC des le depart.
- [ ] Confirmer les valeurs de timeout, lockout, retention audit et taille max upload.
- [ ] Definir les roles admin autorises a gerer l'espace prive.
- [ ] Definir les chemins de stockage prives par environnement.
- [ ] Definir la politique RGPD : export, anonymisation, suppression, retention.
- [ ] Preparer la liste des routes et permissions V1.
- [ ] Identifier les tests obligatoires par phase.

Definition of Done :

- [ ] Les decisions bloquantes sont ecrites dans ce README ou un ticket dedie.
- [ ] Aucun secret ni chemin local sensible n'est versionne.
- [ ] Le backlog d'execution est aligne avec `docs/private/backlog-pvt01.md`.

### Phase 1 - Socle HTTP PrivatePortal

Objectif : creer les routes privees sans casser le front-office.

Checklist :

- [ ] Ajouter les variables `PRIVATE_*` dans `backend/config/config.php` et `backend/.env.example`.
- [ ] Creer `backend/src/PrivatePortal/Http/PrivateRouteResolver.php`.
- [ ] Brancher le resolver prive dans `backend/src/Http/FrontController.php`.
- [ ] Creer les templates `backend/templates/private/layout.php`, `login.php`, `dashboard.php`.
- [ ] Ajouter `/private`, `/private/login`, `/private/dashboard`, `/private/logout`.
- [ ] Ajouter `X-Robots-Tag` sur toutes les reponses privees.
- [ ] Ajouter ou verifier `Disallow: /private/` dans `robots.txt`.
- [ ] Tester la non-regression des routes publiques, RSS, sitemap, blog et admin.

Definition of Done :

- [ ] `/private` non authentifie redirige vers le login prive.
- [ ] Le dashboard ne s'affiche qu'avec une session privee valide.
- [ ] Les routes publiques existantes gardent le meme comportement.
- [ ] Les tests `FrontController` et `PrivatePortal` passent.

### Phase 2 - Identite famille et sessions separees

Objectif : ne jamais reutiliser les comptes admin pour la famille.

Checklist :

- [ ] Creer les migrations `private_users`, `private_user_invites`, `private_password_resets`, `private_sessions`.
- [ ] Hasher les mots de passe avec `Argon2id`.
- [ ] Stocker les tokens invitation/reset sous forme hashee.
- [ ] Implementer invitation, activation, login, logout, reset mot de passe.
- [ ] Creer une session privee dediee avec nom de cookie distinct.
- [ ] Regenerer l'ID de session au login et logout.
- [ ] Appliquer timeout d'inactivite.
- [ ] Appliquer verrouillage apres 3 echecs pendant 24h.
- [ ] Ajouter le support MFA TOTP et codes de secours.
- [ ] Journaliser les connexions, echecs, verrouillages et resets.

Definition of Done :

- [ ] Un compte invite n'est pas actif avant activation.
- [ ] Un email ne peut pas creer deux comptes famille.
- [ ] Les erreurs login/reset ne divulguent pas l'existence d'un compte.
- [ ] Les tests `PrivatePortalSecurity` couvrent succes, refus, expiration et lockout.

### Phase 3 - BO admin membres et permissions

Objectif : piloter les acces famille depuis le BO.

Checklist :

- [ ] Ajouter `Parametres > Espace prive > Membres`.
- [ ] Ajouter invitation, renvoi, suspension, reset, suppression/anonymisation.
- [ ] Creer `private_modules` et `private_user_module_permissions`.
- [ ] Creer `PrivateModuleRegistry`.
- [ ] Ajouter l'affectation des modules par utilisateur.
- [ ] Refuser cote serveur toute modification de droits par un membre famille.
- [ ] Ajouter audit des changements de droits.
- [ ] Ajouter tests admin sur CSRF, permissions et validation.

Definition of Done :

- [ ] Seul un admin autorise peut affecter les modules.
- [ ] Le dashboard prive affiche uniquement les modules autorises.
- [ ] L'acces direct a une route non autorisee retourne `403` et genere un audit.

### Phase 4 - Stockage prive et documents

Objectif : garantir qu'aucun document prive n'est servi directement par URL.

Checklist :

- [ ] Creer `backend/private/storage`, `uploads` et `exports` ou leurs chemins configures.
- [ ] Ajouter un service de stockage prive.
- [ ] Ajouter `private_documents` si le modele commun est retenu.
- [ ] Verifier extension, MIME, taille et nom original.
- [ ] Generer un chemin disque non devinable.
- [ ] Servir les fichiers via `/private/files/{documentId}`.
- [ ] Verifier permission sur chaque telechargement.
- [ ] Ajouter audit upload/download/delete.
- [ ] Documenter backup et restauration des fichiers prives.

Definition of Done :

- [ ] Aucun fichier prive n'est present dans `backend/public`.
- [ ] Une URL directe vers disque est impossible.
- [ ] Un membre sans droit ne peut ni lister ni telecharger un document.

### Phase 5 - Module RealEstateRental, noyau metier

Objectif : creer la source fiable des donnees locatives.

Checklist :

- [ ] Creer `backend/src/PrivateApps/RealEstateRental/`.
- [ ] Creer les migrations `rental_properties`, `rental_units`, `rental_property_members`.
- [ ] Creer les entites/domain objects et repositories.
- [ ] Creer les ecrans biens et lots.
- [ ] Ajouter validation stricte des champs adresse, type, surface, statut.
- [ ] Ajouter permissions `read`, `write`, `delete`.
- [ ] Ajouter tests repository/service/controller.
- [ ] Ajouter audit creation, modification, archivage.

Definition of Done :

- [ ] Un utilisateur voit uniquement les biens autorises.
- [ ] Les ecritures invalides sont refusees cote serveur.
- [ ] L'archivage ne casse pas les historiques.

### Phase 6 - Locations, loyers, charges et documents

Objectif : couvrir le cycle locatif utile a la synthese annuelle.

Checklist :

- [ ] Creer `rental_tenants`, `rental_leases`, `rental_payments`, `rental_expenses`, `rental_documents`.
- [ ] Ajouter ecrans locataires, baux, loyers, charges, documents.
- [ ] Distinguer charges recuperables et charges potentiellement deductibles.
- [ ] Ajouter statuts brouillon/valide/annule.
- [ ] Empecher la generation fiscale depuis des donnees brouillon.
- [ ] Ajouter upload/download documents par permission.
- [ ] Ajouter synthese annuelle locative.
- [ ] Ajouter exports locatifs CSV/PDF.
- [ ] Tester les cas multi-biens, bail termine, paiement partiel et charge non deductible.

Definition of Done :

- [ ] Les loyers et charges d'une annee sont recalculables depuis les donnees sources.
- [ ] Les documents restent hors webroot.
- [ ] Les exports sont traces dans l'audit.

### Phase 7 - Bridge fiscal et sources declaratives

Objectif : relier locations et impots sans dependance fragile.

Checklist :

- [ ] Creer `RealEstateRental/TaxBridge/RentalTaxDataProviderInterface.php`.
- [ ] Creer `RentalTaxDataProvider`.
- [ ] Creer `TaxDeclarationHelper/Source/TaxDataSourceInterface.php`.
- [ ] Creer `RentalTaxDataSource`.
- [ ] Creer les value objects `AnnualRentalIncome`, `AnnualDeductibleExpenses`, `MissingTaxDocument`.
- [ ] Ajouter tests sur agregations annuelles.
- [ ] Ajouter controle bloquant si donnees sources brouillon ou incoherentes.
- [ ] Documenter le contrat pour futures webapps sources.

Definition of Done :

- [ ] Le module impots ne lit pas directement toutes les tables locatives.
- [ ] Chaque montant expose au fiscal indique sa source.
- [ ] Les incoherences remontent sous forme de controle, pas d'erreur silencieuse.

### Phase 8 - Module TaxDeclarationHelper

Objectif : produire une aide annuelle multi-sources.

Checklist :

- [ ] Creer `backend/src/PrivateApps/TaxDeclarationHelper/`.
- [ ] Creer `tax_years`, `tax_income_sources`, `tax_manual_income_entries`, `tax_annual_summaries`, `tax_summary_lines`, `tax_export_logs`.
- [ ] Ajouter les routes `/private/impots`, `/{year}`, `/revenus-manuels`, `/controle`, `/documents`, `/export`.
- [ ] Ajouter saisie manuelle de revenus.
- [ ] Ajouter affichage des donnees locatives importees.
- [ ] Ajouter affichage de l'origine de chaque ligne.
- [ ] Ajouter controles de coherence et documents manquants.
- [ ] Ajouter generation de synthese.
- [ ] Ajouter exports PDF/CSV.
- [ ] Ajouter verrouillage d'annee et deverrouillage admin audite.
- [ ] Afficher la mention d'aide non officielle.

Definition of Done :

- [ ] Une synthese annuelle distingue sources locatives, manuelles et futures sources.
- [ ] Une annee verrouillee ne peut pas etre modifiee par un membre.
- [ ] Les exports n'exposent que les donnees autorisees.

### Phase 9 - RGPD, exploitation et go-live prive

Objectif : rendre le portail exploitable en production.

Checklist :

- [ ] Implementer export RGPD compte famille.
- [ ] Implementer anonymisation/suppression selon politique validee.
- [ ] Definir retention audit et purge.
- [ ] Ajouter alertes sur echecs login, 403, 429 et erreurs 5xx privees.
- [ ] Ajouter backup/restauration SQL et fichiers prives.
- [ ] Ajouter verification de restauration.
- [ ] Verifier headers de securite sur routes privees.
- [ ] Verifier robots et absence d'indexation.
- [ ] Tester parcours desktop et mobile.
- [ ] Documenter runbook incident.

Definition of Done :

- [ ] Les donnees privees sont exportables et supprimables/anonymisables.
- [ ] Les logs sont exploitables sans contenir de secrets.
- [ ] Une restauration testee existe avant mise en production.
- [ ] Le front-office public ne presente pas de regression.

## 10. Commandes de validation ciblees

Adapter les filtres aux noms finaux des tests.

```bash
cd backend
composer test -- --filter PrivatePortal
composer test -- --filter PrivatePortalSecurity
composer test -- --filter PrivatePortalDashboard
composer test -- --filter PrivatePortalAudit
composer test -- --filter RealEstateRental
composer test -- --filter TaxDeclarationHelper
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
13. verrouillage et deverrouillage admin audite.

## 11. Pieges a eviter

1. Ne pas reutiliser les comptes admin pour la famille.
2. Ne pas exposer les documents prives dans `backend/public`.
3. Ne pas stocker de token dans `localStorage`.
4. Ne pas coder les modules en dur dans le dashboard.
5. Ne pas faire confiance au front pour les permissions.
6. Ne pas dupliquer les montants locatifs dans le module fiscal sans trace de source.
7. Ne pas promettre une declaration fiscale officielle.
8. Ne pas creer une nouvelle webapp pour un revenu rare qui peut rester manuel.
9. Ne pas logger de mots de passe, tokens, chemins sensibles ou documents.
10. Ne pas modifier le front-office public en meme temps que le coeur prive sans tests de non-regression.

## 12. Decision finale

La strategie retenue est :

1. construire d'abord `PrivatePortal` ;
2. separer strictement comptes admin et comptes famille ;
3. ajouter le registre de modules et les permissions serveur ;
4. creer `RealEstateRental` comme source de verite locative ;
5. creer `TaxDeclarationHelper` comme module de synthese multi-sources ;
6. garder les documents hors webroot ;
7. auditer chaque action sensible ;
8. ne jamais presenter l'aide impots comme un conseil fiscal officiel ;
9. livrer par phases testees, sans regression du site public.

Cette approche respecte l'architecture actuelle du depot, la gouvernance HTTP existante, les contraintes de securite et la possibilite d'ajouter plus tard d'autres modules prives.
