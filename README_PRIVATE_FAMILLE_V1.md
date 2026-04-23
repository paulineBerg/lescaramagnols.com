# Portail Prive Famille V1

Date de mise a jour : 2026-03-20  
Statut : actif

Ce document decrit la solution recommandee pour ajouter, apres W1->W8, un espace prive "famille" dans `caramagnols` :

- dossier/logique `private` pour modules de travail
- acces reserve a des comptes clients famille
- securite forte, sans bloquer le Front-Office public

References :

- `README.md`
- `README_SECURITE_ADMIN_V1.md`
- `backend/README_INSTALLATION_HORS_WEBROOT.md`
- `README_PRIVATE_FAMILLE_BACKLOG_V1.md` (tickets techniques prets a execution)

## Decision produit validee (atelier 2026-03-20)

1. Super-admin unique pour invitation et affectation des modules prives.
2. Activation des comptes prives par l'utilisateur via lien email (expiration 7 jours).
3. Mode MVP retenu : authentification locale (`email` + mot de passe), migration OIDC conservee comme trajectoire.
4. MFA globale non obligatoire au demarrage, mais support obligatoire TOTP + codes de secours.
5. Verrouillage compte apres 3 echecs login, pendant 24h.
6. Aucune auto-gestion des droits/modules par l'utilisateur prive.
7. Audit visible uniquement super-admin, retention par defaut 1 an.
8. Notifications email sur evenements sensibles requises.
9. Exigences RGPD requises (export/suppression des donnees privees).
10. UX mobile-first avec installation Android type PWA.

## 1) Decision architecture

## Recommendation principale

Mettre en oeuvre une approche **Solution 2** (socle Symfony/OIDC progressif) avec possibilite d'ajouter des briques **Solution 3** (VPN/restrictions reseau) pour les modules sensibles.

Pourquoi :

1. Evite de coder une authentification critique "maison".
2. Permet MFA, RBAC, audit et gestion de session robustes.
3. Reste compatible avec la modernisation incremental du repo.

## Ce qu'il ne faut pas faire

1. Compter sur `robots.txt` comme protection.
2. Exposer des fichiers privees directement dans `backend/public`.
3. Reutiliser les comptes admin pour les membres famille.
4. Stocker des tokens/session dans `localStorage`.

## 2) Perimetre fonctionnel cible

Le portail prive doit couvrir :

1. Authentification "famille" dediee.
2. Portail d'entree des modules prives.
3. Gestion des autorisations par module.
4. Journal d'audit (connexions, acces, erreurs, actions sensibles).
5. Webapps privees independantes (ex: agenda, documents, suivi projets, etc.).

Modules metier cibles (backlog produit, non encore developpes) :

1. comptabilite,
2. messagerie entre membres,
3. gestion locative,
4. fabrication de posts/reels/stories,
5. aide bourse.

Hors perimetre initial V1 du portail :

1. Federation complexe multi-organisations.
2. SSO multi-domaines externes.
3. IAM complet entreprise.

## 3) Arborescence et isolation recommandees

## Regle absolue webroot

Le webroot reste `backend/public` uniquement.

## Structure proposee

```text
caramagnols/
  backend/
    public/                         # seul dossier expose HTTP
    src/
      PrivatePortal/
        Http/
        Security/
        ModuleRegistry/
        Audit/
    private/                        # NON expose HTTP
      modules/
        agenda/
        documents/
        ...
      storage/
        uploads/
        exports/
      config/
```

Principes :

1. Les fichiers de module et donnees prives sont hors webroot.
2. Les acces passent via un controleur backend avec verification d'identite et d'autorisation.
3. Les telechargements passent par endpoint controle (jamais lien fichier direct).

## 4) Modele de menace (minimum)

Menaces prioritaires :

1. Credential stuffing / brute force login.
2. Vol de session.
3. Escalade de privilege entre membres famille.
4. Exfiltration de fichiers prives.
5. XSS/CSRF sur modules prives.
6. Mauvaise configuration infra (webroot/proxy/headers).

Controles associes :

1. MFA + rate limit + lockout progressif.
2. Cookie session durci + rotation session ID + timeout inactivite.
3. RBAC strict + checks serveur sur chaque requete.
4. Stockage prive hors webroot + ACL applicative.
5. CSP + sanitization + CSRF + validation stricte.
6. Runbook de verification infra + tests automatises de securite.

## 5) Authentification et session

## Option A (recommandee) : OIDC

Utiliser un IdP (Keycloak/Auth0/Cognito) pour les comptes famille :

1. Flow Authorization Code + PKCE.
2. MFA TOTP obligatoire.
3. Politique mot de passe geree cote IdP.
4. Re-auth possible pour actions sensibles.

## Option B (transitoire)

Authentification locale si IdP indisponible :

1. Hash `Argon2id`.
2. MFA configurable par compte, avec support TOTP + codes de secours.
3. Rate limit + lockout.
4. Migration planifiee vers OIDC.

## Politique session

1. Cookie `HttpOnly`, `Secure`, `SameSite=Strict`.
2. Timeout inactivite recommande : 30 a 60 min pour portail prive, 120 min max.
3. Rotation session sur login/logout et elevation de privilege.
4. Invalidation globale possible apres incident.

## 6) Autorisation (RBAC)

## Separation des populations

1. `admin_*` pour BO technique.
2. `family_*` pour espace prive.

Interdiction de melanger roles admin et famille dans les memes permissions.

## Roles minimaux proposes

1. `family_owner` : gestion membres famille + ACL modules.
2. `family_member` : acces modules autorises.
3. `family_viewer` : lecture seule sur certains modules.

## Table de droits (exemple)

1. `module_agenda.read`, `module_agenda.write`
2. `module_documents.read`, `module_documents.upload`, `module_documents.delete`
3. `module_tasks.read`, `module_tasks.write`

Chaque endpoint prive verifie :

1. identite valide
2. role utilisateur
3. permission explicite sur la ressource

## 7) Protection application

## Headers et policies

1. `X-Frame-Options: SAMEORIGIN`
2. `X-Content-Type-Options: nosniff`
3. `Referrer-Policy: strict-origin-when-cross-origin`
4. `Permissions-Policy` restrictive
5. `Content-Security-Policy` stricte (nonce scripts)
6. `Strict-Transport-Security` en production HTTPS

## CSRF / XSS / validation

1. CSRF obligatoire sur POST/PUT/PATCH/DELETE.
2. Encodage HTML par defaut dans templates.
3. Sanitization stricte des champs riches.
4. Validation serveur sur toutes entrees.

## Anti brute-force

1. Rate limiting par IP + identifiant.
2. Delais progressifs.
3. Journalisation des echecs.
4. Alerte sur pics d'echecs.

## 8) Robots, indexation, confidentialite

## Important

`robots.txt` est un signal de courtoisie, pas un controle d'acces.

Mesures a appliquer :

1. `robots.txt`: `Disallow: /private/` (ou route cible).
2. Header HTTP sur portail prive : `X-Robots-Tag: noindex, nofollow, noarchive`.
3. Meta robots en defense complementaire.
4. Acces authentifie obligatoire a tout contenu prive.

## 9) Donnees et stockage prive

## Regles

1. Fichiers prives hors webroot.
2. Noms de fichiers normalises (pas de caracteres exotiques).
3. Controle MIME + extension + taille.
4. Scan antivirus si flux fichier sensible.

## Telechargement securise

1. Endpoint backend `/private/files/{id}`.
2. Verification permission sur fichier.
3. Response stream apres check ACL.
4. Aucune URL directe vers disque.

## Sauvegarde et restauration

1. Backup chiffree des donnees privees.
2. Test de restauration regulier (mensuel minimum).
3. Conservation en coffre de secrets hors repo.

## 10) Observabilite et audit

Journaliser au minimum :

1. login succes/echec
2. MFA valide/invalide
3. acces module
4. tentative d'acces refusee
5. upload/download fichier
6. actions sensibles (suppression/export)

Champs d'audit recommandes :

1. `request_id`
2. `user_id`
3. `role`
4. `module`
5. `ip`
6. `user_agent`
7. `status`
8. `timestamp`

Alertes minimales :

1. pics de `login.failed`
2. pics de `403`
3. pics de `429`
4. erreurs serveur 5xx sur portail prive

## 11) Variables d'environnement recommandees

Exemple minimal (a adapter, hors Git) :

```env
# Portail prive
PRIVATE_PORTAL_ENABLED=true
PRIVATE_PORTAL_BASE_PATH=/private

# Session privee
PRIVATE_SESSION_COOKIE=caramagnols_private_session
PRIVATE_INACTIVITY_TIMEOUT_SECONDS=3600
PRIVATE_REAUTH_TIMEOUT_SECONDS=1800

# Auth locale MVP (trajectoire OIDC conservee)
PRIVATE_AUTH_MODE=local
PRIVATE_INVITE_TOKEN_TTL_HOURS=168
PRIVATE_ACCOUNT_LOCKOUT_ATTEMPTS=3
PRIVATE_ACCOUNT_LOCKOUT_SECONDS=86400
PRIVATE_MFA_TOTP_ENABLED=true
PRIVATE_MFA_BACKUP_CODES_ENABLED=true
PRIVATE_PASSWORD_MIN_LENGTH=14
PRIVATE_PASSWORD_COMPLEXITY_ENABLED=true
PRIVATE_AUDIT_RETENTION_DAYS=365
PRIVATE_SECURITY_ALERT_EMAILS=admin@example.com

# Auth OIDC (phase ulterieure)
OIDC_ISSUER_URL=
OIDC_CLIENT_ID=
OIDC_CLIENT_SECRET=
OIDC_REDIRECT_URI=https://www.lescaramagnols.com/private/auth/callback
OIDC_LOGOUT_REDIRECT_URI=https://www.lescaramagnols.com/private

# MFA locale (si fallback non-OIDC)
PRIVATE_TOTP_ENABLED=true

# Rate limit
PRIVATE_LOGIN_RATE_LIMIT_ATTEMPTS=5
PRIVATE_LOGIN_RATE_LIMIT_WINDOW=900
```

## 12) Plan d'implementation (post W1->W8)

Le detail ticketise du lot foundation est maintenu dans :

- `README_PRIVATE_FAMILLE_BACKLOG_V1.md`

## PVT-01 - Foundation (1 sprint)

1. Creer route groupe `/private`.
2. Middleware auth + permission + CSRF.
3. Portail vide avec dashboard minimal.
4. Journal d'audit de base.

Critere :

1. route privee inaccessible sans login
2. headers securite presentes
3. logs audit generees

## PVT-02 - Auth forte (1 sprint)

1. Invitation email + auth locale + MFA TOTP/codes secours.
2. Gestion sessions privees.
3. Ecran gestion membres famille (roles).

Critere :

1. invitation/activation/login prive OK
2. MFA enforcee
3. roles appliques en back

## PVT-03 - Module 1 pilote (1 sprint)

1. Integrer une premiere webapp privee (ex: agenda).
2. ACL complete lecture/ecriture.
3. Tests e2e de parcours prives.

Critere :

1. module accessible selon role
2. refus 403 correct si droit absent

## PVT-04 - Fichiers prives + exploitation (1 sprint)

1. Upload/download via controleur securise.
2. Backup/restore documente.
3. Alerting ops active.

Critere :

1. aucune fuite de fichier hors auth
2. restauration testee

## 13) Checklist go-live du portail prive

## A. Securite technique

- [ ] HTTPS force
- [ ] HSTS actif
- [ ] cookies prives durcis
- [ ] CSRF actif
- [ ] MFA active
- [ ] rate limiting actif
- [ ] RBAC verifie sur endpoints
- [ ] fichiers prives hors webroot
- [ ] `X-Robots-Tag` actif sur `/private/*`

## B. Exploitation

- [ ] logs audit exploitable
- [ ] alertes actives
- [ ] backup/restoration testes
- [ ] runbook incident disponible

## C. Recette

- [ ] login valide + MFA valide
- [ ] login invalide (mdp/TOTP faux)
- [ ] acces refuse role insuffisant
- [ ] upload/download role valide
- [ ] test mobile + desktop

## 14) Integration avec la trajectoire W1->W8

1. Ne pas bloquer W1->W8.
2. Demarrer le portail prive en chantier parallele controle apres stabilisation S2/S3.
3. Faire la bascule progressive module par module.

Regle de gouvernance :

1. toute evolution portail prive met a jour ce README
2. toute evolution securite met a jour aussi `README_SECURITE_ADMIN_V1.md` (ou son equivalent futur "securite globale")

## 15) TODO

- [ ] Definir les valeurs finales de politique mot de passe (`min`, regles exactes, historique, rotation).
- [ ] Definir le workflow support de recuperation MFA (perte TOTP + codes secours epuises).
- [ ] Definir le template d'emails transactionnels (invitation, alerte securite, reset mot de passe).
- [ ] Definir l'IdP retenu (Keycloak/Auth0/Cognito) et le mode d'hebergement pour la phase post-MVP.
- [ ] Choisir la premiere webapp privee pilote.
- [ ] Definir les roles finaux famille et la matrice de permissions.
- [ ] Ecrire les tests e2e prives dans la CI.
- [ ] Documenter le runbook d'incident portail prive.
