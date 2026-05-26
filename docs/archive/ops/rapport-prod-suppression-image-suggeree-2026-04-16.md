# Rapport prod OVH - suppression des phrases "Image suggérée"

Date d'intervention : `2026-04-16`

## Objet

Suppression en production OVH, dans la base SQL éditoriale, des phrases de contenu qui commencent par :

- `Image suggérée` en `fr`
- `Vorgeschlagenes Bild` en `de`

## Périmètre réellement touché

Une seule page était concernée en production :

- slug : `auto-retro-mercedes-histoire-de-mercedes`
- route : `/auto-retro/mercedes/histoire-de-mercedes.php`
- section traduite : `regions/body`

## Suppressions effectuées

### Français

4 paragraphes supprimés :

- `Image suggérée : tableau de bord avec voyants ABS/ESP et photo d’un airbag déployé.`
- `Image suggérée : 300 SEL « Red Pig » en course et plaque « One Man, One Engine ».`
- `Image suggérée : monoplace moderne avec mise en avant de l’unité hybride.`
- `Image suggérée : intérieur avec grand écran MBUX et vue d’un modèle EQ en charge.`

### Allemand

4 paragraphes supprimés :

- `Vorgeschlagenes Bild: Armaturenbrett mit ABS/ESP-Anzeigen und ein ausgelöster Airbag.`
- `Vorgeschlagenes Bild: 300 SEL „Red Pig“ im Rennen und Plakette „One Man, One Engine“.`
- `Vorgeschlagenes Bild: modernes Formel-1-Auto mit Fokus auf die Hybridtechnik.`
- `Vorgeschlagenes Bild: Innenraum mit großem MBUX-Bildschirm und ein ladendes EQ-Modell.`

## Exécution

Intervention exécutée directement sur l'environnement OVH via SSH, avec mise à jour SQL ciblée de `car_page_translation_sections.payload_json` pour les traductions `fr` et `de` de la page Mercedes.

La connexion MySQL directe depuis le poste courant a été refusée par OVH ; l'opération a donc été menée depuis l'hôte de production, ce qui correspond au mode d'exploitation documenté du projet.

## Vérifications de clôture

Vérifications effectuées après mise à jour :

- contrôle SQL global : aucune occurrence restante de `Image suggérée` en `fr`
- contrôle SQL global : aucune occurrence restante de `Vorgeschlagenes Bild` en `de`
- purge du cache runtime applicatif : `cache_cleared`
- contrôle HTTP prod `fr` sur `/fr/auto-retro/mercedes/histoire-de-mercedes.php` : plus aucune occurrence
- contrôle HTTP prod `de` sur `/de/auto-retro/mercedes/histoire-de-mercedes.php` : plus aucune occurrence

## Résultat

Le nettoyage demandé est appliqué en production OVH.
