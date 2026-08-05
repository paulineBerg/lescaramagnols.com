# WebDevelopment

Le module `WebDevelopment` donne a un membre autorise de l'espace prive acces
a une previsualisation confidentielle de son projet web. Le site de travail
n'est pas publie directement dans le document root public.

## Architecture de production

Le stockage servi par le module se trouve dans l'espace prive du backend :

```text
/home/lescaramgl-ssh/caramagnols/backend/private/web-development/deployments/
  <project-key>/releases/current/public/index.html
```

Configuration de production attendue :

```dotenv
WEB_DEVELOPMENT_PREVIEW_HOST=www.lescaramagnols.com
WEB_DEVELOPMENT_DEPLOYMENTS_ROOT=private/web-development/deployments
```

`WEB_DEVELOPMENT_DEPLOYMENTS_ROOT` est relatif a `ROOT_PATH`. Ce stockage reste
hors du webroot, mais dans l'arborescence du backend visible par PHP-FPM. Les
versions statiques de previsualisation doivent etre lisibles par PHP-FPM.

Le repertoire historique suivant ne doit plus etre utilise pour ces projets :

```text
/home/lescaramgl-ssh/caramagnols-runtime/private-storage/web-development/
```

## Chemins en base de donnees

Les champs `current_public_path` du projet et `public_path` de sa version sont
relatifs a `WEB_DEVELOPMENT_DEPLOYMENTS_ROOT`. Exemple pour `lordelaroche` :

```text
lordelaroche/releases/current/public
```

Ne pas enregistrer un chemin absolu ni repeter le prefixe
`web-development/deployments` dans ces champs.

## Controle d'acces

Le parcours normal est le suivant :

1. Le membre se connecte a l'espace prive.
2. L'administrateur lui attribue le module `web_development` et le projet.
3. Le bouton de previsualisation effectue une requete POST protegee par CSRF.
4. Le backend cree un ticket a usage unique puis une session de
   previsualisation privee.
5. Le projet s'ouvre dans un nouvel onglet sous `/p/<project-key>/`.

Un acces direct a `/p/<project-key>/` sans session valide doit retourner `404`.
Les reponses de previsualisation imposent notamment `noindex`, `nofollow`,
`noarchive` et `noimageindex`, ainsi qu'une politique de cache privee.

## Deploiement d'un projet

Publier les fichiers statiques dans :

```text
backend/private/web-development/deployments/<project-key>/releases/current/public/
```

Le deploiement du backend doit conserver `backend/private` de maniere additive.
Les repertoires parents doivent etre traversables et les fichiers de la version
courante lisibles par l'utilisateur PHP-FPM, sans exposer ce stockage dans le
document root du serveur web.

## Validation

Les tests cibles du module sont executes depuis `backend/` :

```bash
vendor/bin/phpunit tests/PrivateApps/WebDevelopment
```

Le controle de production doit suivre le parcours ticket puis cookie. Un simple
appel non authentifie de `/p/<project-key>/` n'est pas un smoke test valide,
puisque la reponse `404` est alors le comportement de securite attendu.
