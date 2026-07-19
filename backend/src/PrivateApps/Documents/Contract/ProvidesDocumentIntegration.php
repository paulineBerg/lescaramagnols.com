<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Contract;

/**
 * Interface optionnelle des manifestes PrivateAppManifest.
 * Un module qui l'implémente est automatiquement pris en compte par le
 * DocumentIntegrationRegistry — aucune modification du hub n'est nécessaire
 * pour brancher une webapp future.
 */
interface ProvidesDocumentIntegration
{
    public function documentIntegration(): DocumentIntegration;
}
