# Règles du module RealEstateRental

- Le module reste installable comme paquet Composer `caramagnols/real-estate-rental`.
- Aucun point d’entrée public, secret, override local, document locatif ou donnée runtime ne doit être ajouté au dépôt.
- Les documents, exports et justificatifs restent hors webroot et utilisent le stockage protégé du portail hôte.
- Toute écriture sensible vérifie authentification, permissions, validation stricte, CSRF et journalisation.
- Le code PHP utilise `strict_types`, des types explicites et le namespace `Caramagnols\PrivateApps\RealEstateRental`.
- Les tests d’intégration sont exécutés depuis le dépôt hôte `lescaramagnols.com`.
