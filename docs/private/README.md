# Portail prive famille, locations et aide impots

Date de mise a jour : 2026-05-26
Statut : cadrage cible validé, PVT-01 terminé, phase 2 (identité famille / sessions séparées) en cours de clôture du socle IAM minimal.

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

Progression Phase 0 : `3 / 9` (33 %)

| Statut | Item | Responsable | Échéance cible | Jalons | Date de clôture | Notes / Preuves |
|---|---|---|---|---|---|---|
| [x] Définir le périmètre technique de la phase 0 (socle auth, comptes, dashboard, sécurité). | | 2026-05-26 | 1) périmètre écrit ; 2) scope validé | 2026-05-26 | Définition reprise dans sections 2, 4 et 5 ; scope privé détaillé. |
| [x] Valider le mapping de routes et points d’entrée (ex: `/private`, `/private/login`, `/private/dashboard`). | | 2026-05-26 | 1) matrice route→controller ; 2) impacts front-controller documentés | 2026-05-26 | Routes privées enregistrées dans `FrontController` + route list doc (`docs/backend/public-entrypoints.md`). |
| [x] Bloquer les exigences sécurité minimales (CSRF, auth locale/hachage Argon2id, politiques de session, rate limiting). | | 2026-05-27 | 1) matrice des contrôles ; 2) log des choix | 2026-05-26 | `PrivatePortalSecurityGuard` + `PrivateAuth` + `PrivateSession` alignés avec `PRIVATE_*` et timeouts/rate-limiter. |
| [ ] Lister les impacts de stockage et de permissions (fichiers hors webroot, ACL, logs d’accès). | | 2026-05-27 | 1) chemin de stockage privé retenu ; 2) politique d’accès validée | | |
| [ ] Valider le plan de journalisation des actions sensibles. | | 2026-05-27 | 1) événements cibles ; 2) niveau de détail validé | | |
| [ ] Préparer le plan de migration i18n (`fr/en/de`) pour toute interface privative visible. | | 2026-05-28 | 1) clé de traduction existantes ; 2) stratégie fallback définie | | |
| [ ] Identifier les dépendances SQL et les éventuelles impacts de schéma hors implémentation. | | 2026-05-28 | 1) tables initiales validées ; 2) ordre d’exécution SQL | | |
| [ ] Rédiger la liste des risques résiduels + arbitrages (sécurité, délais, dette technique). | | 2026-05-28 | 1) registre de risques ; 2) propriétaires désignés | | |
| [ ] Valider la sortie de Phase 0 (go/no-go) en revue courte avant passage à la Phase 1. | | 2026-05-28 | 1) revue signée ; 2) checklist complétée | | |

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

Chaque phase doit etre livree avec tests, verification manuelle ciblee et documentation mise a jour si le comportement change, et cocher checklist.

### Phase 0 - Cadrage technique et garde-fous

Objectif : figer le perimetre avant code.

La Phase 0 est suivie dans la section `5. Phase 0 - Cadrage technique et garde-fous`, avec une checklist unique en `5.4 Checklist opérationnelle (Phase 0)`.

Ne pas maintenir de checklist parallèle ici afin d'éviter les divergences de suivi.

### Phase 1 - Socle HTTP PrivatePortal

Objectif : creer les routes privees sans casser le front-office.

Checklist :

- [x] Ajouter les variables `PRIVATE_*` dans `backend/config/config.php` et `backend/.env.example`.
- [x] Creer `backend/src/PrivatePortal/Http/PrivateRouteResolver.php`.
- [x] Brancher le resolver prive dans `backend/src/Http/FrontController.php`.
- [x] Creer les templates `backend/templates/private/layout.php`, `login.php`, `dashboard.php`.
- [x] Ajouter `/private`, `/private/login`, `/private/dashboard`, `/private/logout`.
- [x] Ajouter `X-Robots-Tag` sur toutes les reponses privees.
- [x] Ajouter ou verifier `Disallow: /private/` dans `robots.txt` (servi par `FrontController`).
- [x] Tester la non-regression des routes publiques, RSS, sitemap, blog et admin (selon le protocole d’office, selon priorité).

Definition of Done :

- [x] `/private` non authentifie redirige vers le login prive.
- [x] Le dashboard ne s'affiche qu'avec une session privee valide.
- [x] Les routes publiques existantes gardent le meme comportement.
- [x] Les tests `FrontController` et `PrivatePortal` passent.

Validation et suivi phase 1 :

- `private route` : `PRIVATE_*` + routeur dédié reliés dans `FrontController` (points 1 à 5 ci-dessus).
- `anti-indexation` : `FrontController::robotsTxtResponse()` émet `Disallow: /private` quand le portail privé est activé.
- Vérification automatisée phase 1 :
  - `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalFrontControllerTest.php` : vert après harmonisation (hash Argon2id + logout POST + vérification logs via logger injecté).
  - `cd backend && phpunit --configuration phpunit.xml tests/FrontControllerHttpTest.php` : vert (46 tests, 1 point `image/structure` résolu via fixture de test pour `/images/structure/banniere.jpg`).
- Vérification manuelle ciblée (simulée via `FrontController`) :
  - `/private/login` => `200`
  - `/private` => `302 -> /private/login`
  - `/private/dashboard` => `302 -> /private/login`
  - `/private/logout` => `POST /private/logout + CSRF` => `302 -> /private/login`
  - `/robots.txt` => `Disallow: /private` présent.

### Phase 2 - Identite famille et sessions separees

Objectif : ne jamais reutiliser les comptes admin pour la famille.

Checklist :

- [x] Creer les migrations `private_users`, `private_user_invites`, `private_password_resets`, `private_sessions`.
- [x] Hasher les mots de passe avec `Argon2id`.
- [ ] Stocker les tokens invitation/reset sous forme hashee.
- [x] Implémenter le contrat auth/login/logout privé (authentification locale + `POST /private/logout` + CSRF + session dédiée).
- [ ] Implementer invitation, activation, reset mot de passe.
- [x] Creer une session privee dediee avec nom de cookie distinct.
- [x] Regenerer l'ID de session au login et logout.
- [x] Appliquer timeout d'inactivite.
- [x] Appliquer verrouillage apres 3 echecs pendant 24h.
- [ ] Ajouter le support MFA TOTP et codes de secours.
- [ ] Journaliser les connexions, echecs, verrouillages et resets.

Preuves de passage phase 2 (socle IAM initial) :

- `backend/sql/private/private_users.sql`
- `backend/sql/private/private_user_invites.sql`
- `backend/sql/private/private_password_resets.sql`
- `backend/sql/private/private_sessions.sql`

Definition of Done :

- [ ] Un compte invite n'est pas actif avant activation.
- [ ] Un email ne peut pas creer deux comptes famille.
- [ ] Les erreurs login/reset ne divulguent pas l'existence d'un compte.
- [ ] Les tests `PrivatePortalSecurity` couvrent succes, refus, expiration et lockout.

Tests à lancer / exécuter pour phase 2 :

- [x] Exécuter et archiver la suite privée sécurité :
  - `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalFrontControllerTest.php` ✅ OK (6/6), 2026-05-26.
  - `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalSecurityTest.php` ✅ OK (3/3), 2026-05-26.
- [x] Vérifier non-régression FO après chaque évolution privée :
  - `cd backend && phpunit --configuration phpunit.xml tests/FrontControllerHttpTest.php` ✅ OK (46/46), 2026-05-26.

Note d'exécution locale (environnement actuel) :
- Depuis la racine, `./vendor/bin/phpunit` n'existe pas; la commande échoue.
- Utiliser depuis `backend/` : `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalFrontControllerTest.php`, `cd backend && phpunit --configuration phpunit.xml tests/FrontControllerHttpTest.php` quand le binaire global est disponible.

- [ ] Contrôles manuels ciblés (ou curl équivalent) :
  - `/private/login` accessible
  - `/private/login` avec POST + CSRF invalide → refus
  - `/private/login` avec POST + CSRF valide → dashboard
  - `/private/dashboard` sans session valide → 302 vers login
  - `/private/logout` en POST + CSRF → retour login

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

Tests à lancer avant clôture phase 3 :

- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalMembersTest.php` (quand le test existe).
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalModuleAssignmentTest.php` (quand le test existe).
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalDashboardTest.php` (quand le test existe).
- [ ] Vérification manuelle :
  - `/private` et `/admin/parametres/espace-prive` selon profils ;
  - tentative d'activation/désactivation module en tant qu'utilisateur famille = refus ;
  - tentative d'accès direct à une route module non autorisée = `403`.

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

Tests à lancer avant clôture phase 4 :

- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalStorageTest.php` (quand le test existe).
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalFilesApiTest.php` (quand le test existe).
- [ ] Contrôles manuels ciblés :
  - accès à `/private/files/{documentId}` sans droit = refus ;
  - upload document en mode autorisé/rejeté selon extension, MIME, taille ;
  - vérification qu’aucun fichier privé n’est public via URL directe.

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

Tests à lancer avant clôture phase 5 :

- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivateApps/RealEstateRental`
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/RealEstateRental`
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalDashboardTest.php` (quand le portail module dépendant est répercuté dans ce test).
- [ ] Contrôles manuels :
  - filtrage des biens par droits utilisateur ;
  - création d’un lot avec données invalides (surface/statut/champ requis) ;
  - archivage d’un bien et visibilité cohérente dans la synthèse.

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

Tests à lancer avant clôture phase 6 :

- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivateApps/RealEstateRental`
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivateApps/RealEstateRental/Lifecycle`
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalTaxBridgeTest.php` (quand le test existe).
- [ ] Contrôles manuels :
  - parcours complet locatif (création locataire → bail → paiement/charge → export annualisé) ;
  - refus de synthèse avec données brouillon ;
  - protection des documents par permission.

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

Tests à lancer avant clôture phase 7 :

- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortal` (filtre de suite fiscale ciblé, à créer).
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/TaxDeclarationHelper`
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/RealEstateRental`
- [ ] Contrôles manuels :
  - agrégation annuelle cohérente entre source locative et données manuelles ;
  - rejet explicite en cas d’état brouillon/incohérence ;
  - traçabilité de la source sur au moins une ligne de synthèse.

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

Tests à lancer avant clôture phase 8 :

- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortal` (filtre `TaxDeclarationHelper`).
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/TaxDeclarationHelper`
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivateApps/TaxDeclarationHelper`
- [ ] Contrôles manuels :
  - parcours de création, édition, vérification puis verrouillage annuel ;
  - refus d'écriture après verrouillage ;
  - export CSV/PDF non inclusif de données hors périmètre.

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

Tests à lancer avant clôture phase 9 :

- [ ] `cd backend && phpunit --configuration phpunit.xml tests/PrivatePortal`
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/Security` (vérifier login/403/429/5xx privé)
- [ ] `cd backend && phpunit --configuration phpunit.xml tests/Logging`
- [ ] Contrôles manuels pré-production :
  - `GET /private` et `/private/login` en parcours navigateur réel ;
  - `POST /private/logout` CSRF invalide/valide ;
  - `robots.txt` et absence de régression FO (admin/blog/rss/sitemap/assets) ;
  - restauration privée documentée (données + fichiers) sur environnement test.

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

## 13. PVT-01 - Continuation d'implementation (Fondation)

Le lot PVT-01 reprend le socle minimal du portail famille en priorité. Les tâches ci-dessous sont à exécuter dans l’ordre pour limiter les risques de régression FO/BO.

### 13.1 Ordre cible d’implémentation

1. Variables et garde-fous de configuration (`PRIVATE_*`, activation, temps d’attente, rate limit).
2. Routage privé via `FrontController` avec résolveur dédié.
3. Guard session privée + stockage de session isolé.
4. Protection CSRF sur les actions mutatives.
5. Login / activation / reset / logout minimum sécurisé.
6. Dashboard privé minimal filtré par permissions.
7. Journal d’accès et refus avec audit append-only.
8. Headers anti-indexation + `robots.txt`.
9. Contrôle fichier : aucun point d’accès direct à `backend/public`.

### 13.2 Matrice de routes PVT-01 (MVP)

| Route | But | Methode | Protection | Sortie attendue |
|---|---|---|---|---|
| `/private` | Point d’entrée privé | GET | session privée | redirection vers `/private/dashboard` si authentifié, sinon `/private/login` |
| `/private/login` | Authentification | GET/POST | CSRF + rate limit | formulaire GET / création session + rotation ID |
| `/private/dashboard` | Accueil privé | GET | session + permissions | liste des modules autorisés |
| `/private/logout` | Fermeture session | POST | CSRF | invalidation session + redirection login |
| `/private/activate/{token}` | Activation invitation | GET/POST | token + hachage + audit | activation + choix mot de passe |
| `/private/password/forgot` | Reset auto-service | GET/POST | anti-abus + audit demande | retour neutre utilisateur |
| `/private/password/reset/{token}` | Réinitialisation mot de passe | GET/POST | token + contraintes mot de passe | remplacement sécurisé |
| `/private/files/{documentId}` | Téléchargement sécurisé | GET | permission ressource + audit | en-têtes téléchargement + flux contrôlé |

Règle métier clé : tout refus de permission (lecture/édition/téléchargement) doit être traçable et, côté API, renvoyer `401/403` selon le contexte.

### 13.3 Livrables PVT-01 attendus par étape

- Sprint 1 (routes + config) : endpoints privés fonctionnels, non-régression des routes publiques confirmée.
- Sprint 2 (session + login) : connexion privée isolée, lockout, gestion mots de passe et CSRF validés.
- Sprint 3 (dashboard + permissions) : affichage des modules autorisés seulement.
- Sprint 4 (audit + sécurité HTTP) : événements sensibles et headers anti-indexation vérifiés.
- Sprint 5 (pré-op) : documentation BO/BOU mise à jour + preuves d’exécution (curl / commandes test) archivées.

### 13.4 Critères de passage sprint PVT-01

1. `composer test -- --filter PrivatePortal` exécute sans erreur.
2. `composer test -- --filter FrontController` conserve le comportement FO.
3. Connexion privée : succès, refus silencieux, lockout et réinitialisation testés.
4. Une tentative d’accès privé non autorisé génère un event d’audit.
5. Aucune régression FO détectée sur `admin`, `blog`, `sitemap`, `rss`, et assets.

### 13.5 Protocole d’exécution d’office

Principe : poursuivre l’implémentation phase par phase, sans interruption systématique pour une validation minimale.

1. Exécuter des tests automatisés quand l’objectif de la tâche inclut explicitement une fonction testable de sécurité, de routage, de session ou d’audit.
2. Demander un test manuel quand le résultat dépend d’un parcours UX, d’un comportement navigateur, d’un flux email/token réel, ou d’une vérification infrastructure.
3. Si une tâche est documentaire ou structurelle (cadrage, checklists, matrice, migration de plan), poursuivre la rédaction jusqu’à la fin de la phase en cours avant toute pause.
4. En dehors de ces cas, continuer directement vers la phase suivante tant que les conditions de bascule sont remplies.
5. À chaque passage de phase, ajouter une entrée de preuve dans la section du README de la phase.
6. À la fin de chaque phase livrée, produire un commit dédié (message de phase) et pousser immédiatement la branche.

Référence d’arrêt : arrêt « naturel » au passage officiel d’une phase quand les critères de passage sont remplis et documentés ici.

### 13.6 Passe de phase en cours (cible)

Ce document suit la séquence : `Phase 0 -> Phase 1 -> Phase 2`.

Phase 1 est terminée.
Clôture phase 1 enregistrée dans : commit `6909548` (poussé sur `origin/chore/private-portal-docs-cleanup`).
Phase 2 (identité famille et sessions séparées) est clôturée en socle IAM minimal (phase suivante visée pour IAM complet) :
- [x] Contrat auth/logout aligné (`Argon2id` + `POST /private/logout`) ; suites `PrivatePortalFrontControllerTest` et `FrontControllerHttpTest` passées.
- [ ] Compléments IAM (invitation, activation, reset, tokens hashés, MFA, journalisation dédiée) reportés à la phase 3.
