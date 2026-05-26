# Configuration du bloc Instagram (accueil)

Ce guide explique comment récupérer les clés à saisir dans l’admin pour afficher les derniers posts Instagram.

## 1) Créer l’accès API Meta/Instagram

1. Connecte-toi sur <https://developers.facebook.com/>.
2. Crée une application Meta.
3. Ajoute le produit **Instagram Basic Display** (ou le flux Instagram Graph correspondant à ton type de compte).
4. Déclare une URL de redirection OAuth valide (même temporaire).

Tu obtiendras :
- `App ID`
- `App Secret`

## 2) Générer un token utilisateur Instagram

Après autorisation OAuth, récupère un **short-lived token**, puis échange-le en **long-lived token**.

Exemple d’échange short-lived -> long-lived :

```bash
curl -G "https://graph.instagram.com/access_token" \
  --data-urlencode "grant_type=ig_exchange_token" \
  --data-urlencode "client_secret=VOTRE_APP_SECRET" \
  --data-urlencode "access_token=VOTRE_SHORT_LIVED_TOKEN"
```

La réponse contient `access_token` (long-lived) et `expires_in`.

## 3) Récupérer l’identifiant utilisateur (optionnel mais recommandé)

```bash
curl -G "https://graph.instagram.com/me" \
  --data-urlencode "fields=id,username" \
  --data-urlencode "access_token=VOTRE_LONG_LIVED_TOKEN"
```

Tu obtiens :
- `id` (à mettre dans **User ID Instagram**)
- `username` (à mettre dans **Compte Instagram**, sans `@`)

## 4) Saisir les paramètres dans l’admin

Dans `Admin > Paramètres d’exploitation > Flux Instagram accueil` :

- Activer le bloc Instagram
- Compte Instagram (sans `@`)
- User ID Instagram (optionnel)
- Access Token Instagram
- Nombre de posts
- Rotation auto (ms)
- Durée de cache (secondes)
- Timeout API (secondes)

## 5) Renouveler le token avant expiration

Exemple de refresh d’un long-lived token :

```bash
curl -G "https://graph.instagram.com/refresh_access_token" \
  --data-urlencode "grant_type=ig_refresh_token" \
  --data-urlencode "access_token=VOTRE_LONG_LIVED_TOKEN"
```

## 6) Vérification rapide

Test direct de la récupération des posts :

```bash
curl -G "https://graph.instagram.com/me/media" \
  --data-urlencode "fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,username" \
  --data-urlencode "limit=5" \
  --data-urlencode "access_token=VOTRE_LONG_LIVED_TOKEN"
```

Si tu obtiens `data: [...]`, la configuration côté site est prête.

## 7) Verification V1 cote application

Depuis la racine du depot :

```bash
composer check-instagram-feed --working-dir=backend
```

Pour une verification bloquante pre-release (credentials obligatoires + probe API valide) :

```bash
composer check-instagram-feed --working-dir=backend -- --strict
```
