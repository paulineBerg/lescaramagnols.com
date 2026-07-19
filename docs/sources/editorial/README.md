# Sources Editoriales

Date de mise a jour : 2026-04-30

Ce dossier rassemble les materiaux bruts reutilisables qui servent de base a la redaction de pages et d'articles publics :
- transcriptions audio
- notes factuelles internes
- releves ou syntheses de terrain
- photographies sources de travail editorial

## Perimetre

Ce dossier n'est pas une documentation projet au sens classique.
La racine `docs/` reste reservee aux `README`, rapports, audits, runbooks et notes d'architecture maintenues.

`docs/sources/editorial/` sert uniquement a ranger des sources de travail editorial reutilisables.
Les photos sources reutilisables se rangent dans `docs/sources/editorial/images/`.

## Regles de rangement

- `1` fichier par sujet editorial reel
- si un meme enregistrement couvre plusieurs sujets, le decouper en plusieurs fichiers dedies
- noms de fichiers en ASCII simple, `kebab-case`
- suffixe recommande : `-transcription.txt`
- eviter les noms fourre-tout du type `fichier audio`, `notes diverses`, `conversation 1`
- pour les photos, creer `1` sous-dossier par sujet editorial reel dans `images/`, en ASCII simple et `kebab-case`
- pour les photos, utiliser des noms de fichiers descriptifs et stables, sans espaces, underscores ni majuscules

Exemples valides :
- `slk230-kompressor-transcription.txt`
- `2cv4-etat-des-lieux-transcription.txt`
- `2cv4-restauration-transcription.txt`
- `images/2cv4-etat-des-lieux/2cv4-etat-des-lieux-avant.jpg`
- `images/2cv4-restauration/2cv4-restauration-sava.jpg`

## Regles d'usage editorial

- utiliser ces fichiers comme sources factuelles internes, pas comme texte publiable brut
- ne pas recopier mot a mot de longs passages dans les pages ou articles publics
- transformer la matiere orale en texte redige, clair et verifiable
- si la source est citee publiquement, la mentionner sobrement dans `EditRegion11 - Sources`, par exemple : `Transcription audio interne`
- ne pas raconter dans le corps de l'article le travail de recherche ou la presence du fichier source; integrer directement les faits utiles

## Hygiene du depot

- si une source brute est temporaire, sensible ou sans valeur de reutilisation durable, la garder hors du depot
- les fichiers audio binaires (`.m4a`, `.mp3`, etc.) ne doivent pas etre versionnes ici par defaut
- si une note brute devient une vraie documentation d'exploitation ou de projet, la deplacer vers `docs/` ou vers un `README` de domaine adapte
