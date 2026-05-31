# Locations immobilieres privees

Date de mise a jour : 2026-05-31
Statut : plan d'amelioration du module `RealEstateRental`, phase L0 terminee, consolide depuis le brouillon `docs/private/amelioration locations immobil.txt` et aligne sur l'existant du depot.

Ce README sert de feuille de route fonctionnelle et technique pour rendre le module de locations immobilieres plus complet, sans casser le socle prive deja livre.

## 1. Etat actuel observe

Le module existe deja et doit rester dans l'architecture actuelle :

- code metier : `backend/src/PrivateApps/RealEstateRental/`;
- controle prive : `backend/src/PrivatePortal/Http/PrivatePortalController.php`;
- routes privees : `backend/src/PrivatePortal/Http/PrivateRouteResolver.php`;
- templates : `backend/templates/private/modules/real-estate-rental/`;
- tables SQL : `backend/sql/private/rental_*.sql`;
- stockage fichiers prives : `backend/private/storage/uploads/**`, servi uniquement par endpoint PHP apres verification des droits;
- tests : `backend/tests/PrivateApps/RealEstateRental/` et tests de garde du portail prive.

Fonctions deja presentes :

- tableau de bord locatif avec KPIs principaux;
- proprietes, biens locatifs, acces par membre;
- locataires, baux, loyers, paiements, charges et documents locatifs;
- generation manuelle d'un loyer depuis un bail, avec montant calcule cote serveur;
- paiement rattache a un loyer, y compris paiement partiel;
- telechargement et envoi email d'une quittance PDF depuis un paiement;
- synthese annuelle, export CSV/PDF et journalisation d'export;
- imports agence, classification, revue humaine et pont fiscal;
- bridge vers `TaxDeclarationHelper`.

Ecarts importants :

- les loyers attendus ne sont pas encore generes par un service d'echeancier idempotent;
- le statut `draft/validated/cancelled` sert aujourd'hui a la validation des donnees, pas au suivi `pending/partial/paid/late`;
- la table des loyers ne bloque pas encore explicitement le doublon `bail + mois`;
- les demandes de paiement et relances ne sont pas historisees dans une table dediee;
- les quittances existent comme reponse PDF/email, mais pas encore comme document genere immuable rattache au loyer;
- les charges n'ont pas encore de referentiel de categories complet, d'annee fiscale explicite ni de rattachement documentaire fin;
- la regularisation annuelle des charges reste a creer;
- le tableau de bord doit mieux remonter vacance, retards, documents manquants, echeances de bail et fiscalite provisoire.

## 2. Arbitrages retenus

1. Garder `backend/src/PrivateApps/RealEstateRental/`.
   Le brouillon proposait `backend/src/PrivatePortal/Modules/RentalManagement/`, mais le depot a deja convergé vers `PrivateApps/RealEstateRental`. Il ne faut pas renommer le module sans migration large.

2. Garder les fichiers hors webroot via le stockage prive existant.
   Le besoin `backend/private/storage/rental/documents/` est valide en intention, mais le projet utilise deja `backend/private/storage/uploads/**` avec scanner, hash, MIME et streaming controle. Une sous-arborescence dediee pourra etre ajoutee seulement si `PrivateDocumentStorage` la gere officiellement.

3. Ne pas mettre les documents, exports ou justificatifs sous `backend/public`.
   Aucun `backend/public/private/rental/`, aucun `backend/public/location.php`, aucun upload locatif public.

4. Separer le statut de validation et le statut de paiement.
   `draft/validated/cancelled` reste le statut de qualite des donnees. Le cycle de paiement doit etre calcule ou stocke a part : `pending`, `partial`, `paid`, `late`, `cancelled`.

5. Ne pas recalculer brutalement les loyers a chaque affichage.
   Un service genere les lignes mensuelles attendues une seule fois, les conserve en base et les complete sans doublon.

6. Garder la fiscalite comme preparation, pas comme declaration.
   Les montants locatifs alimentent le module fiscal, mais les cases declaratives et rulesets par annee restent configurables dans `TaxDeclarationHelper`.

7. Traiter les demandes de paiement comme des courriers historises.
   Une demande n'est pas un texte libre : elle est liee au loyer, au bail, au locataire, au logement, au canal, au contenu envoye et a l'utilisateur emetteur.

8. Rendre les emails et courriers modifiables.
   Les textes par defaut doivent venir d'un modele configurable. L'utilisateur peut corriger l'objet, le destinataire et le corps avant envoi. Le contenu reellement envoye est conserve en snapshot.

9. Raccorder toute nouvelle table et tout nouveau fichier au registre RGPD.
   Les ajouts doivent etre declares dans `PrivateDataProtectionService`, dans les sauvegardes ZIP et dans les purges de compte.

## 3. Modele cible prioritaire

### 3.1 Loyers attendus

Regles :

- un loyer mensuel attendu est cree depuis un bail actif ou valide;
- le loyer existe avant paiement;
- le paiement est enregistre separement et s'impute sur le loyer;
- un bail ne peut pas avoir deux loyers pour la meme periode;
- le montant attendu est conserve, meme si le bail est modifie plus tard;
- toute correction doit etre auditee.

Statuts cibles :

| Statut paiement | Regle |
|---|---|
| `pending` | loyer attendu, aucun paiement valide, date d'echeance non depassee |
| `partial` | paiements valides inferieurs au total du |
| `paid` | paiements valides superieurs ou egaux au total du |
| `late` | solde restant apres date d'echeance |
| `cancelled` | loyer annule, exclu des relances, quittances et syntheses |

Implementation recommandee :

- ajouter une contrainte unique `rental_lease_id, period_year, period_month`;
- creer un `RentScheduleService` idempotent;
- ajouter un service `RentPaymentStatusService` qui calcule le statut depuis les paiements valides, le montant du et la date d'echeance;
- exposer l'etat operationnel dans la liste des loyers sans supprimer le statut de validation existant.

### 3.2 Paiements

Regles :

- un paiement peut solder un loyer complet, une partie de loyer, plusieurs paiements successifs ou un retard;
- un paiement annule ne compte jamais dans le total encaisse;
- le statut `paid` n'est atteint que si le total valide couvre le montant du;
- la suppression physique d'un paiement doit rester evitee lorsque l'historique fiscal ou locatif en depend.

Ameliorations recommandees :

- distinguer `payment_method`, `received_from`, `reference`, `is_caf_payment`;
- ajouter une correction auditee plutot qu'une suppression silencieuse;
- refuser un paiement qui depasse fortement le solde sans confirmation explicite;
- afficher `Attendu`, `Paye`, `Reste du`, `Date echeance`, `Statut paiement`.

### 3.3 Demandes de paiement et relances

La demande de paiement doit etre disponible sur chaque ligne de loyer si le statut paiement est `pending`, `partial` ou `late`.

Elle ne doit pas apparaitre pour :

- `paid`;
- `cancelled`.

Donnees obligatoires dans le message :

- nom du locataire;
- adresse du logement;
- mois concerne;
- montant du loyer;
- montant des charges;
- total a payer;
- montant deja paye si paiement partiel;
- reste du;
- date d'echeance;
- mode de paiement;
- reference bail, bien ou logement.

Table cible :

```text
rental_payment_requests
- id
- rental_rent_id
- rental_lease_id
- rental_tenant_id
- rental_property_id
- rental_unit_id
- channel
- subject
- body_snapshot
- amount_due
- amount_paid
- balance_due
- payment_status_at_send
- status
- sent_at
- sent_by_private_user_id
- recipient_email
- created_at
```

Canaux :

- email en premier, via la configuration SMTP privee existante;
- PDF exportable;
- copier-coller disponible;
- SMS et WhatsApp plus tard via adaptateur externe, jamais comme dependance du coeur metier.

Edition :

- le modele email/courrier est charge depuis un catalogue configurable;
- l'objet, le destinataire email et le corps peuvent etre modifies avant envoi;
- les variables autorisees sont explicites, par exemple `{{tenant_name}}`, `{{period}}`, `{{rent_amount}}`, `{{charges_amount}}`, `{{balance_due}}`, `{{due_date}}`, `{{payment_method}}`;
- le HTML email et le PDF courrier sont generes depuis le contenu valide, pas depuis un texte hardcode dans le controleur;
- le snapshot envoye reste conserve meme si le modele est modifie ensuite.

### 3.4 Quittances, avis et documents generes

Regles :

- une quittance ne peut etre generee que si le statut paiement du loyer est `paid`;
- un paiement partiel produit au mieux un recu partiel, pas une quittance;
- les documents generes doivent etre stockes hors webroot;
- le PDF conserve un snapshot du modele et des montants au moment de la generation;
- chaque telechargement, envoi email et regeneration est audite.

Priorite :

1. avis d'echeance;
2. demande de paiement;
3. recu partiel;
4. quittance mensuelle;
5. recapitulatif annuel par locataire;
6. export ZIP des documents d'un bien.

### 3.5 Reglages email et courrier

Le module doit proposer une zone de reglages, ou s'appuyer sur le catalogue admin des emails prives, pour modifier les textes par defaut sans deploy.

Modeles a prevoir :

- demande de paiement simple;
- relance ferme pour retard;
- relance apres paiement partiel;
- avis d'echeance;
- recu partiel;
- quittance;
- regularisation des charges;
- recapitulatif annuel.

Regles :

- aucun nouveau texte email durable ne doit etre hardcode;
- chaque modele declare ses variables autorisees;
- l'interface affiche un apercu avant envoi;
- l'utilisateur peut modifier le contenu ponctuel avant envoi;
- l'email du locataire reste pre-rempli depuis sa fiche, mais peut etre corrige pour l'envoi en cours;
- les courriers PDF doivent reprendre le contenu valide dans la previsualisation;
- le snapshot envoye ne doit plus changer apres modification du modele source;
- la configuration SMTP privee doit proposer un bouton `Envoyer un message de test` vers une adresse saisie et validee;
- l'envoi de test SMTP doit journaliser succes ou echec avec adresse masquee, sans stocker mot de passe, token, DSN ni erreur brute sensible.

### 3.6 Charges et regularisations

Categories minimales :

```text
taxe_fonciere
assurance_pno
copropriete
travaux
entretien
agence
banque
eau
electricite
internet
mobilier
emprunt
autre
```

Regles :

- ne pas confondre charge payee, charge recuperable et charge fiscalement deductible;
- `recoverable` indique une recuperation possible aupres du locataire;
- `deductible_candidate` reste une aide de preparation fiscale, pas une validation officielle;
- `tax_year` doit etre explicite et modifiable;
- le justificatif doit etre rattache au bien, a la charge et, si utile, au bail ou au locataire.

Regularisation des charges :

- comparer provisions demandees au locataire et charges reelles;
- isoler la part recuperable;
- produire un solde a demander ou a rembourser;
- conserver un snapshot du calcul et des justificatifs.

### 3.7 Tableau de bord

KPIs a viser :

- nombre de proprietes;
- nombre de biens locatifs;
- biens loues;
- biens vacants ou indisponibles;
- loyers attendus du mois;
- loyers encaisses;
- loyers en retard;
- paiements partiels;
- charges payees;
- solde par bien;
- alertes importantes;
- documents manquants;
- echeances de bail;
- synthese fiscale provisoire.

Le tableau de bord doit rester dense, responsive et sans surcharge visuelle.

### 3.8 Contrat de champs confirme en L0

Ces champs forment le socle minimal pour les avis d'echeance, demandes de paiement, recus partiels, quittances et recapitulatif annuel. Ils ne declenchent pas de nouvelle route publique et ne doivent jamais etre compenses par du texte hardcode dans un controleur.

Champs indispensables pour les documents generes :

| Champ | Source retenue | Regle L0 |
|---|---|---|
| Bailleur | membre locatif `owner`, `co_owner` ou `manager`, rattache par `rental_property_members` et complete par `private_users.full_name`, `private_users.postal_address`, `private_users.email` | obligatoire pour un document final; si le nom ou l'adresse manque, afficher une alerte et autoriser seulement brouillon, apercu ou copie de travail |
| Mode de paiement | reglage futur de bail ou de propriete, avec snapshot dans le document envoye | indispensable pour une demande de paiement; ne pas inventer de mode par defaut, utiliser une valeur explicite validee avant envoi |
| Reference logement | composition lisible `propriete + bien locatif + bail`, avec les identifiants internes conserves pour audit | afficher un libelle humain; les ids SQL ne doivent pas etre la seule reference visible dans un courrier |
| Adresse exacte | `rental_units.address` si renseignee, sinon `rental_properties.address`, puis ajout possible de `building`, `floor`, `door` | obligatoire pour un document final; si l'adresse du bien differe de la propriete, l'adresse du bien prime |
| Civilite locataire | champ locataire futur controle, avec repli neutre sur `full_name` | ne jamais deduire `M.` ou `Mme` depuis le prenom; en absence de civilite, employer le nom complet sans civilite |

Champs modifiables avant envoi :

| Champ | Regle L0 |
|---|---|
| Email destinataire | pre-rempli depuis la fiche locataire quand disponible, modifiable pour l'envoi courant, valide par `FILTER_VALIDATE_EMAIL`, snapshot masque dans les logs |
| Objet | pre-rempli depuis le catalogue de modeles prives, modifiable avant envoi, longueur bornee |
| Corps email | issu du modele configurable, variables autorisees explicites, modifiable avant envoi, rendu HTML genere depuis le contenu valide |
| Texte courrier | commun a la previsualisation PDF et au snapshot envoye; aucune divergence entre email, PDF et historique |
| Signature | pre-remplie depuis le bailleur ou un reglage prive, modifiable avant envoi, conservee dans le snapshot |

Regles associees :

- tout modele durable doit venir du catalogue admin des emails prives ou d'un catalogue equivalent de courriers locatifs;
- le snapshot envoye conserve sujet, corps, texte courrier, signature, montants, destinataire, statut de paiement et date d'envoi;
- les journaux ne doivent exposer ni email complet, ni chemin serveur, ni token, ni secret SMTP;
- les phases L1 et L2 peuvent avancer sans ces champs si elles restent limitees a l'echeancier et aux statuts, mais L3 et L4 doivent bloquer les envois definitifs quand le contrat documentaire n'est pas satisfait.

## 4. Phases de realisation

### Phase L0 - Cadrage et alignement

- [x] Lire le brouillon de besoins.
- [x] Comparer avec le module existant.
- [x] Conserver l'architecture `PrivateApps/RealEstateRental`.
- [x] Remplacer le brouillon texte par ce README maintenu.
- [x] Confirmer les champs indispensables pour les prochains documents generes : bailleur, mode de paiement, reference logement, adresse exacte, civilite locataire.
- [x] Confirmer les champs modifiables avant envoi : email destinataire, objet, corps email, texte courrier, signature.
- [x] Ajouter un lien de navigation documentaire depuis `docs/private/README.md`.

Definition of Done :

- le plan ne demande aucun nouveau point d'entree public;
- les choix de stockage, statuts et fiscalite sont explicites;
- le brouillon non maintenu est supprime.

Validation L0 :

- le brouillon `docs/private/amelioration locations immobil.txt` n'est plus present dans le depot;
- aucune modification de schema SQL, route privee, endpoint public ou stockage fichier n'est introduite par L0;
- les ecarts de champs sont explicites pour guider L1 a L4 sans inventer de donnees dans les documents.

### Phase L1 - Echeancier durable des loyers

- [ ] Creer `RentScheduleService`.
- [ ] Ajouter une contrainte anti-doublon `bail + annee + mois`.
- [ ] Ajouter une action `Generer les loyers dus` par bail ou par mois.
- [ ] Refuser la generation si le bail est annule, termine hors periode ou si la periode existe deja.
- [ ] Conserver le montant du au moment de la generation.
- [ ] Auditer generation, correction et annulation.

Tests minimum :

- [ ] `RentScheduleServiceTest`;
- [ ] test doublon `bail + mois`;
- [ ] test bail hors periode;
- [ ] test generation depuis bail actif.

### Phase L2 - Statut de paiement et encaissements

- [ ] Creer `RentPaymentStatusService`.
- [ ] Afficher `pending`, `partial`, `paid`, `late`, `cancelled` sur les loyers.
- [ ] Recalculer le statut apres creation, correction ou annulation d'un paiement.
- [ ] Distinguer paiements locataire, CAF, remboursement et regularisation.
- [ ] Ajouter mode de paiement et reference.
- [ ] Bloquer ou confirmer les surpaiements.

Tests minimum :

- [ ] `PaymentStatusServiceTest`;
- [ ] paiement partiel;
- [ ] paiement complet;
- [ ] paiement annule;
- [ ] retard apres echeance;
- [ ] surpaiement controle.

### Phase L3 - Demandes de paiement et relances

- [ ] Creer `rental_payment_requests`.
- [ ] Ajouter un bouton `Demande de paiement` sur les loyers non soldes.
- [ ] Generer le message depuis les donnees du loyer, du bail, du locataire et du logement.
- [ ] Ajouter une previsualisation modifiable avant envoi : email, objet, courrier PDF et signature.
- [ ] Brancher les textes par defaut sur un catalogue de modeles configurables.
- [ ] Autoriser la modification ponctuelle du destinataire email.
- [ ] Ajouter un envoi de test depuis la configuration SMTP privee.
- [ ] Enregistrer le snapshot du message envoye.
- [ ] Envoyer par email via SMTP prive.
- [ ] Ajouter export PDF et copier-coller.
- [ ] Journaliser succes et echec d'envoi.

Tests minimum :

- [ ] `RentalPaymentRequestServiceTest`;
- [ ] bouton absent si loyer `paid` ou `cancelled`;
- [ ] email refuse sans destinataire valide;
- [ ] contenu genere sans chemin serveur ni secret;
- [ ] message SMTP de test avec succes/echec journalise et adresse masquee;
- [ ] audit succes/echec.

### Phase L4 - Quittances et documents generes

- [ ] Bloquer la quittance si le loyer n'est pas integralement paye.
- [ ] Ajouter recu partiel pour paiement incomplet.
- [ ] Stocker les PDF generes hors webroot avec hash, type MIME et taille.
- [ ] Rattacher quittance, recu et avis au loyer et au bail.
- [ ] Garder un snapshot immuable du document envoye.
- [ ] Ajouter purge ou retention documentee pour exports temporaires.

Tests minimum :

- [ ] `RentalReceiptServiceTest`;
- [ ] quittance interdite sur paiement partiel;
- [ ] PDF stocke hors webroot;
- [ ] telechargement refuse sans droit propriete;
- [ ] audit telechargement/email.

### Phase L5 - Charges, justificatifs et regularisations

- [ ] Ajouter categorie de charge normalisee.
- [ ] Ajouter `tax_year`.
- [ ] Rattacher un justificatif a une charge.
- [ ] Afficher charges recuperables, candidates deductibles et non deductibles separement.
- [ ] Creer l'ecran `Regularisations`.
- [ ] Calculer provisions, charges reelles, part recuperable et solde.
- [ ] Generer un document de regularisation verifiable.

Tests minimum :

- [ ] `RentalExpenseCategoryTest`;
- [ ] `ChargeRegularizationServiceTest`;
- [ ] charge recuperable non automatiquement deductible;
- [ ] regularisation avec solde a demander;
- [ ] regularisation avec remboursement.

### Phase L6 - Dashboard et synthese fiscale provisoire

- [ ] Ajouter biens vacants, biens indisponibles et baux proches de fin.
- [ ] Ajouter loyers du mois attendus, encaisses, partiels et en retard.
- [ ] Ajouter documents manquants par bail ou par bien.
- [ ] Ajouter solde annuel par bien.
- [ ] Afficher les donnees incertaines ou brouillon sans les integrer silencieusement.
- [ ] Garder la liaison fiscale configuree dans `TaxDeclarationHelper`.

Tests minimum :

- [ ] `RentalDashboardServiceTest`;
- [ ] `TaxSummaryServiceTest`;
- [ ] brouillons bloquants;
- [ ] charges non deductibles non additionnees aux deductions.

### Phase L7 - Exports et sauvegardes

- [ ] Export CSV loyers.
- [ ] Export CSV charges.
- [ ] Export PDF annuel par bien.
- [ ] Export PDF recapitulatif par locataire.
- [ ] Export ZIP des documents d'un bien.
- [ ] Stocker les exports temporaires hors webroot.
- [ ] Journaliser chaque export.
- [ ] Raccorder nouvelles tables et fichiers a `PrivateDataProtectionService`.

Tests minimum :

- [ ] `RentalExportServiceTest`;
- [ ] ZIP sans chemin serveur expose;
- [ ] export refuse sans permission;
- [ ] backup ZIP contient tables et fichiers attendus;
- [ ] purge compte traite les nouvelles donnees.

### Phase L8 - Imports agence et rapprochements avances

- [ ] Conserver l'import agence comme sous-domaine de `RealEstateRental`.
- [ ] Renforcer le mapping des lignes agence vers loyers, charges, honoraires, GLI et reversements.
- [ ] Ajouter rapprochement avec virements et justificatifs.
- [ ] Ajouter file OCR/saisie manuelle pour scans sans texte.
- [ ] Signaler doublons, periodes manquantes et lignes non classees.
- [ ] Ne jamais valider automatiquement une categorie fiscale sensible.

Tests minimum :

- [ ] parseurs ASG/ICS existants;
- [ ] mapping fiscal avec revue humaine;
- [ ] donnees sensibles masquees;
- [ ] document source conserve.

## 5. Optimisations possibles

- Index SQL sur les requetes frequentes : `rental_property_id`, periode, statut de validation, statut paiement, echeance.
- Vue ou service de lecture pour le tableau de bord, afin d'eviter de recalculer tous les totaux dans les templates.
- Generation batch mensuelle avec dry-run JSON avant ecriture.
- Idempotence stricte sur imports, generation de loyers, relances et exports.
- Files d'attente futures pour OCR, envoi email volumineux et generation ZIP.
- Cache court des KPIs par utilisateur et annee, invalide apres ecriture locative.
- Composants UI plus compacts pour les tableaux longs, filtres persistants et actions groupées.
- Versionnement des modeles de courriers et quittances.
- Historique de corrections plutot que suppression sur les objets financiers.
- API privee future uniquement apres stabilisation du rendu serveur, avec les memes controles CSRF, permissions et audit.

## 6. Interdits et controles de securite

Interdits :

- creer `backend/public/private/rental/`;
- creer `backend/public/location.php`;
- stocker documents, exports ou justificatifs sous `backend/public/uploads/`;
- coder du SQL dans les templates;
- coder les regles fiscales definitives dans les templates;
- exposer chemin serveur, token, mot de passe, IBAN ou numero fiscal complet dans le HTML ou les logs;
- supprimer physiquement un locataire lie a un bail sans politique de purge explicite.

Controles obligatoires pour chaque phase :

- validation stricte des entrees;
- CSRF sur toutes les ecritures;
- verification des permissions par propriete;
- audit des actions sensibles;
- documents hors webroot;
- raccordement sauvegarde ZIP et purge RGPD si nouvelles donnees;
- tests unitaires ou fonctionnels adaptes au risque;
- controle UI responsive si template modifie.

Commandes de validation a adapter au perimetre :

```bash
cd backend && phpunit --configuration phpunit.xml tests/PrivateApps/RealEstateRental
cd backend && phpunit --configuration phpunit.xml tests/PrivatePortal/PrivateTemplateGuardTest.php tests/PrivatePortal/PrivateUiGuardTest.php
cd backend && phpunit --configuration phpunit.xml tests/PrivatePortalTaxBridgeTest.php
cd backend && vendor/bin/phpstan analyse
cd backend && vendor/bin/phpcs
git diff --check
```

## 7. Ordre de priorite recommande

1. securiser les loyers attendus avec echeancier idempotent et anti-doublon;
2. separer statut de validation et statut de paiement;
3. ajouter demandes de paiement historisees;
4. rendre quittances et recus immuables dans le stockage prive;
5. structurer charges, justificatifs et regularisations;
6. enrichir tableau de bord et synthese annuelle;
7. etendre exports et sauvegardes;
8. poursuivre import agence, OCR et rapprochements.

Cette progression donne rapidement un module plus utile au quotidien, tout en gardant une base saine pour les declarations fiscales et les evolutions avancees.
