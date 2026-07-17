# Caramagnols Real Estate Rental

Module autonome de gestion locative privée pour le portail Caramagnols.

## Installation Composer

```bash
composer config repositories.caramagnols-real-estate-rental vcs git@github.com:paulineBerg/caramagnols-real-estate-rental.git
composer require --prefer-source caramagnols/real-estate-rental:dev-main
```

Dans `lescaramagnols.com`, le dépôt est monté comme sous-module sous `backend/src/PrivateApps/RealEstateRental` et résolu par un dépôt Composer local de type `path`.

Le module ne fournit aucun point d’entrée public autonome. Les documents restent hors webroot et l’authentification, les permissions par bien, CSRF, la journalisation et la protection des données sont fournies par le portail privé hôte.
