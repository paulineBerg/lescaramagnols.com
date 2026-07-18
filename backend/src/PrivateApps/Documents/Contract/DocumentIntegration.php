<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Contract;

use Caramagnols\Database\EditorialDatabase;

/**
 * Intégration documentaire d'un module PrivateApps.
 * Un module (existant ou futur) rejoint le hub documentaire en exposant
 * cette intégration depuis son manifeste via ProvidesDocumentIntegration.
 */
interface DocumentIntegration
{
    public function moduleCode(): string;

    /**
     * Types d'entités du module pouvant recevoir des documents.
     *
     * @return array<int, DocumentEntityType>
     */
    public function entityTypes(): array;

    /**
     * Profils d'import déclaratifs du module.
     *
     * @return array<int, DocumentImportProfile>
     */
    public function importProfiles(): array;

    /**
     * Fabrique le resolver d'entités (existence, autorisation, libellés).
     */
    public function createEntityResolver(EditorialDatabase $database): DocumentEntityResolver;
}
