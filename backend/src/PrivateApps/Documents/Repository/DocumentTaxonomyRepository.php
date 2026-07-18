<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

/**
 * Taxonomie documentaire globale (2 niveaux maximum), partagée par toutes les
 * webapps. Les catégories système sont stables ; les catégories personnelles
 * sont globales, jamais par utilisateur. Aucune création automatique par le
 * classement : l'automatisme ne fait que sélectionner une catégorie existante.
 */
final class DocumentTaxonomyRepository
{
    public const INBOX_CODE = 'inbox';
    public const OTHER_CODE = 'other';

    /**
     * Catégories système : code => [parent, label, dossier d'export, ordre].
     *
     * @var array<string, array{0: ?string, 1: string, 2: string, 3: int}>
     */
    private const SYSTEM_CATEGORIES = [
        'property' => [null, 'Bien immobilier', 'Bien', 10],
        'tenants' => [null, 'Locataires', 'Locataires', 20],
        'leases' => [null, 'Baux', 'Baux', 30],
        'leases.contract' => ['leases', 'Contrats de bail', 'Baux', 31],
        'leases.amendment' => ['leases', 'Avenants et résiliations', 'Baux', 32],
        'inventory' => [null, 'États des lieux', 'Etats-des-lieux', 40],
        'inventory.entry' => ['inventory', 'États des lieux d\'entrée', 'Etats-des-lieux', 41],
        'inventory.exit' => ['inventory', 'États des lieux de sortie', 'Etats-des-lieux', 42],
        'rents' => [null, 'Loyers et paiements', 'Loyers-et-paiements', 50],
        'rents.receipt' => ['rents', 'Quittances', 'Loyers-et-paiements', 51],
        'rents.unpaid' => ['rents', 'Impayés', 'Loyers-et-paiements', 52],
        'rents.deposit' => ['rents', 'Dépôts de garantie', 'Loyers-et-paiements', 53],
        'charges' => [null, 'Charges', 'Charges', 60],
        'charges.water' => ['charges', 'Eau', 'Charges', 61],
        'charges.electricity' => ['charges', 'Électricité', 'Charges', 62],
        'charges.maintenance' => ['charges', 'Entretien', 'Charges', 63],
        'charges.service_calls' => ['charges', 'Appels de fonds', 'Charges', 64],
        'charges.regularization' => ['charges', 'Régularisations', 'Charges', 65],
        'works' => [null, 'Travaux et réparations', 'Travaux', 70],
        'works.quote' => ['works', 'Devis', 'Travaux', 71],
        'works.invoice' => ['works', 'Factures', 'Travaux', 72],
        'tax' => [null, 'Fiscalité', 'Fiscalite', 80],
        'tax.property_tax' => ['tax', 'Taxe foncière', 'Fiscalite', 81],
        'tax.cfe' => ['tax', 'CFE', 'Fiscalite', 82],
        'insurance' => [null, 'Assurances et sinistres', 'Assurances', 90],
        'insurance.contract' => ['insurance', 'Contrats', 'Assurances', 91],
        'insurance.claim' => ['insurance', 'Sinistres', 'Assurances', 92],
        'coownership' => [null, 'Copropriété', 'Copropriete', 100],
        'diagnostics' => [null, 'Diagnostics et conformité', 'Diagnostics', 110],
        'diagnostics.dpe' => ['diagnostics', 'DPE', 'Diagnostics', 111],
        'bank' => [null, 'Banque et comptabilité', 'Banque', 120],
        'mail' => [null, 'Courriers', 'Courriers', 130],
        'identity' => [null, 'Pièces et dossiers', 'Pieces', 140],
        'other' => [null, 'Autres', 'Autres', 900],
        'inbox' => [null, 'À classer', 'A-classer', 910],
    ];

    private bool $schemaReady = false;
    private bool $seeded = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function table(): string
    {
        return $this->database->table('private_document_taxonomy');
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $this->database->pdo()->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(96) NOT NULL,
                `parent_code` VARCHAR(96) NULL,
                `label` VARCHAR(120) NOT NULL,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `export_directory` VARCHAR(120) NOT NULL DEFAULT \'\',
                `retention_days` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_private_document_taxonomy_code` (`code`),
                KEY `idx_private_document_taxonomy_parent` (`parent_code`, `is_active`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->table()
        ));

        $this->schemaReady = true;
    }

    /**
     * Insère les catégories système manquantes (idempotent, ne touche jamais
     * aux libellés/ordres personnalisés d'une catégorie existante).
     */
    public function seedSystemCategories(): void
    {
        if ($this->seeded) {
            return;
        }

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $statement = $pdo->prepare(sprintf(
                'INSERT IGNORE INTO `%s`
                    (`code`, `parent_code`, `label`, `is_system`, `is_active`, `sort_order`, `export_directory`)
                 VALUES (:code, :parent_code, :label, 1, 1, :sort_order, :export_directory)',
                $this->table()
            ));
            foreach (self::SYSTEM_CATEGORIES as $code => [$parent, $label, $exportDirectory, $sortOrder]) {
                $statement->execute([
                    'code' => $code,
                    'parent_code' => $parent,
                    'label' => $label,
                    'sort_order' => $sortOrder,
                    'export_directory' => $exportDirectory,
                ]);
            }

            $this->seeded = true;
        } catch (\Throwable) {
            // Base indisponible : le seed sera retenté au prochain appel.
        }
    }

    /**
     * @return array<int, array<string, mixed>> catégories actives triées (parents puis enfants)
     */
    public function listActive(): array
    {
        try {
            $this->ensureSchema();
            $this->seedSystemCategories();
            $statement = $this->database->pdo()->query(sprintf(
                'SELECT * FROM `%s` WHERE `is_active` = 1 ORDER BY `sort_order` ASC, `label` ASC',
                $this->table()
            ));
            $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

            return is_array($rows) ? $rows : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function findByCode(string $code): ?array
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return null;
        }

        try {
            $this->ensureSchema();
            $this->seedSystemCategories();
            $statement = $this->database->pdo()->prepare(sprintf(
                'SELECT * FROM `%s` WHERE `code` = :code LIMIT 1',
                $this->table()
            ));
            $statement->execute(['code' => $code]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isActiveCategoryCode(string $code): bool
    {
        $category = $this->findByCode($code);

        return is_array($category) && (int) ($category['is_active'] ?? 0) === 1;
    }

    /**
     * Crée une catégorie personnelle globale (jamais appelé par le classement
     * automatique — uniquement par un administrateur).
     */
    public function createCustomCategory(string $code, ?string $parentCode, string $label, string $exportDirectory = ''): bool
    {
        $code = strtolower(trim($code));
        $label = trim($label);
        if (preg_match('/\A[a-z0-9_.]{2,96}\z/', $code) !== 1 || $label === '') {
            return false;
        }

        if ($parentCode !== null) {
            $parent = $this->findByCode($parentCode);
            if ($parent === null || $parent['parent_code'] !== null) {
                // Hiérarchie limitée à deux niveaux.
                return false;
            }
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'INSERT INTO `%s` (`code`, `parent_code`, `label`, `is_system`, `is_active`, `sort_order`, `export_directory`)
                 VALUES (:code, :parent_code, :label, 0, 1, 500, :export_directory)',
                $this->table()
            ));
            $statement->execute([
                'code' => $code,
                'parent_code' => $parentCode !== null ? strtolower(trim($parentCode)) : null,
                'label' => substr($label, 0, 120),
                'export_directory' => substr(trim($exportDirectory), 0, 120),
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Renomme une catégorie personnelle (les catégories système gardent leur code
     * et ne peuvent être que réordonnées/désactivées via renameAllowed=false).
     */
    public function renameCustomCategory(string $code, string $newLabel): bool
    {
        $category = $this->findByCode($code);
        if ($category === null || (int) ($category['is_system'] ?? 0) === 1) {
            return false;
        }

        return $this->updateFields($code, ['label' => substr(trim($newLabel), 0, 120)]);
    }

    public function setSortOrder(string $code, int $sortOrder): bool
    {
        return $this->updateFields($code, ['sort_order' => $sortOrder]);
    }

    public function setExportDirectory(string $code, string $exportDirectory): bool
    {
        return $this->updateFields($code, ['export_directory' => substr(trim($exportDirectory), 0, 120)]);
    }

    public function setRetentionDays(string $code, ?int $retentionDays): bool
    {
        return $this->updateFields($code, ['retention_days' => $retentionDays]);
    }

    /**
     * Désactive une catégorie après déplacement de ses documents (jamais de
     * suppression brutale : le service appelant doit d'abord re-catégoriser).
     */
    public function deactivate(string $code, DocumentHubRepository $hubRepository): bool
    {
        $category = $this->findByCode($code);
        if ($category === null || (int) ($category['is_system'] ?? 0) === 1) {
            return false;
        }

        $stillUsed = $hubRepository->countDocuments(['category_code' => $code]);
        if ($stillUsed > 0) {
            return false;
        }

        return $this->updateFields($code, ['is_active' => 0]);
    }

    /**
     * Fusionne une catégorie personnelle dans une autre catégorie active :
     * déplace tous les documents puis désactive la catégorie source.
     */
    public function mergeInto(string $sourceCode, string $targetCode, DocumentHubRepository $hubRepository): bool
    {
        $source = $this->findByCode($sourceCode);
        $target = $this->findByCode($targetCode);
        if (
            $source === null
            || $target === null
            || (int) ($source['is_system'] ?? 0) === 1
            || (int) ($target['is_active'] ?? 0) !== 1
            || $sourceCode === $targetCode
        ) {
            return false;
        }

        try {
            $statement = $this->database->pdo()->prepare(sprintf(
                'UPDATE `%s` SET `category_code` = :target, `updated_at` = NOW() WHERE `category_code` = :source',
                $hubRepository->documentsTable()
            ));
            $statement->execute(['target' => strtolower(trim($targetCode)), 'source' => strtolower(trim($sourceCode))]);
        } catch (\Throwable) {
            return false;
        }

        return $this->updateFields($sourceCode, ['is_active' => 0]);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function updateFields(string $code, array $fields): bool
    {
        if ($fields === []) {
            return false;
        }

        $assignments = [];
        $params = ['code' => strtolower(trim($code))];
        foreach ($fields as $column => $value) {
            if (preg_match('/\A[a-z_]+\z/', (string) $column) !== 1) {
                return false;
            }

            $assignments[] = sprintf('`%s` = :%s', $column, $column);
            $params[$column] = $value;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(sprintf(
                'UPDATE `%s` SET %s, `updated_at` = NOW() WHERE `code` = :code',
                $this->table(),
                implode(', ', $assignments)
            ));
            $statement->execute($params);

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
