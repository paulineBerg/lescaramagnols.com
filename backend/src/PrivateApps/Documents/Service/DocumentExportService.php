<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Service;

use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;
use Caramagnols\PrivateApps\Documents\Repository\DocumentTaxonomyRepository;

/**
 * Export lisible et déterministe d'une sélection de documents : arborescence
 * <Entité>/<Année>/<Dossier-catégorie>/, noms de fichiers normalisés
 * multi-OS, un seul exemplaire par objet physique, manifeste + documents.csv
 * + SHA256SUMS + LISEZ-MOI.txt. L'archive est produite dans exports-temp
 * (durée de vie courte, purgée par le garbage collector).
 */
final class DocumentExportService
{
    private const MAX_SEGMENT_LENGTH = 80;

    public function __construct(
        private readonly DocumentHubRepository $repository,
        private readonly DocumentTaxonomyRepository $taxonomy,
        private readonly DocumentStorageService $storage,
        private readonly DocumentLinkService $links
    ) {
    }

    /**
     * Construit une archive ZIP pour les documents accessibles par l'utilisateur.
     *
     * @param array<string, mixed> $filters mêmes filtres que DocumentHubRepository::listDocuments
     * @return array{ok: bool, error_code: string, zip_path: string, file_count: int, manifest: array<string, mixed>}
     */
    public function exportToZip(int $privateUserId, array $filters, string $exportLabel = 'documents'): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return $this->failure('zip_unavailable');
        }

        $documents = [];
        $offset = 0;
        do {
            $page = $this->repository->listDocuments($filters, 200, $offset);
            foreach ($page as $document) {
                if ($this->links->userCanAccessDocument($document, $privateUserId)) {
                    $documents[] = $document;
                }
            }
            $offset += 200;
        } while ($page !== [] && $offset < 10000);

        if ($documents === []) {
            return $this->failure('no_documents');
        }

        $exportDirectoryByCategory = [];
        foreach ($this->taxonomy->listActive() as $category) {
            $code = (string) ($category['code'] ?? '');
            $directory = trim((string) ($category['export_directory'] ?? ''));
            if ($code !== '') {
                $exportDirectoryByCategory[$code] = $directory !== '' ? $directory : $this->slugSegment((string) ($category['label'] ?? $code));
            }
        }

        $zipName = sprintf(
            'export-%s-%s.zip',
            $this->slugSegment($exportLabel),
            date('Ymd-His')
        );
        $zipPath = $this->storage->exportsTempDirectory() . '/' . $zipName;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return $this->failure('zip_create_failed');
        }

        $manifestEntries = [];
        $csvLines = ['document_uid;fichier_archive;nom_original;titre;categorie;date;annee;sha256;taille;rattachements'];
        $checksums = [];
        $usedPaths = [];
        $addedObjects = [];
        $fileCount = 0;

        foreach ($documents as $document) {
            $storageKey = (string) ($document['storage_key'] ?? '');
            $absolutePath = $this->storage->absolutePathForKey($storageKey);
            if ($absolutePath === null || !is_file($absolutePath)) {
                continue;
            }

            $sha256 = (string) ($document['sha256'] ?? '');
            $linksDescribed = $this->links->describeLinks(is_array($document['links'] ?? null) ? $document['links'] : []);
            $linksSummary = implode(', ', array_map(
                static fn (array $link): string => $link['label'] !== ''
                    ? $link['label']
                    : $link['entity_type'] . '#' . $link['entity_id'],
                $linksDescribed
            ));

            if (isset($addedObjects[$sha256])) {
                // Document multi-rattaché ou dédupliqué : une seule copie physique,
                // les liens sont détaillés dans le manifeste et documents.csv.
                $archivePath = $addedObjects[$sha256];
            } else {
                $archivePath = $this->deterministicArchivePath(
                    $document,
                    $linksDescribed,
                    $exportDirectoryByCategory,
                    $usedPaths
                );
                if (!$zip->addFile($absolutePath, $archivePath)) {
                    continue;
                }
                $addedObjects[$sha256] = $archivePath;
                $checksums[] = $sha256 . '  ' . $archivePath;
                $fileCount++;
            }

            $manifestEntries[] = [
                'document_uid' => (string) ($document['document_uid'] ?? ''),
                'archive_path' => $archivePath,
                'original_filename' => (string) ($document['original_filename'] ?? ''),
                'title' => (string) ($document['title'] ?? ''),
                'category_code' => (string) ($document['category_code'] ?? ''),
                'document_date' => (string) ($document['document_date'] ?? ''),
                'fiscal_year' => is_numeric($document['fiscal_year'] ?? null) ? (int) $document['fiscal_year'] : null,
                'sha256' => $sha256,
                'size_bytes' => (int) ($document['stored_size'] ?? 0),
                'links' => $linksDescribed,
            ];

            $csvLines[] = implode(';', array_map(
                static fn (string $value): string => '"' . str_replace('"', '""', $value) . '"',
                [
                    (string) ($document['document_uid'] ?? ''),
                    $archivePath,
                    (string) ($document['original_filename'] ?? ''),
                    (string) ($document['title'] ?? ''),
                    (string) ($document['category_code'] ?? ''),
                    (string) ($document['document_date'] ?? ''),
                    (string) ($document['fiscal_year'] ?? ''),
                    $sha256,
                    (string) (int) ($document['stored_size'] ?? 0),
                    $linksSummary,
                ]
            ));
        }

        $manifest = [
            'format' => 'caramagnols-document-export',
            'version' => 1,
            'generated_at' => date('c'),
            'timezone' => date_default_timezone_get(),
            'document_count' => count($manifestEntries),
            'unique_file_count' => $fileCount,
            'filters' => $filters,
            'documents' => $manifestEntries,
        ];

        $zip->addFromString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('documents.csv', "\xEF\xBB\xBF" . implode("\n", $csvLines) . "\n");
        $zip->addFromString('SHA256SUMS', implode("\n", $checksums) . "\n");
        $zip->addFromString(
            'LISEZ-MOI.txt',
            "Export de documents — Les Caramagnols\n\n"
            . "Arborescence : <Entité>/<Année>/<Catégorie>/AAAA-MM-JJ_categorie_description_<uid>.<ext>\n"
            . "Un document rattaché à plusieurs entités n'est présent qu'une seule fois ;\n"
            . "tous ses rattachements sont détaillés dans manifest.json et documents.csv.\n"
            . "Vérification d'intégrité : sha256sum -c SHA256SUMS\n"
        );

        if (!$zip->close()) {
            @unlink($zipPath);

            return $this->failure('zip_close_failed');
        }

        return [
            'ok' => true,
            'error_code' => '',
            'zip_path' => $zipPath,
            'file_count' => $fileCount,
            'manifest' => $manifest,
        ];
    }

    /**
     * Chemin déterministe et sûr dans l'archive :
     * <Entité>/<Année>/<Dossier-catégorie>/AAAA-MM-JJ_categorie_description_<uid8>.<ext>
     *
     * @param array<string, mixed> $document
     * @param array<int, array{entity_type: string, entity_id: string, link_role: string, label: string}> $linksDescribed
     * @param array<string, string> $exportDirectoryByCategory
     * @param array<string, bool> $usedPaths
     */
    public function deterministicArchivePath(
        array $document,
        array $linksDescribed,
        array $exportDirectoryByCategory,
        array &$usedPaths
    ): string {
        $entityLabel = 'Sans-rattachement';
        foreach ($linksDescribed as $link) {
            if ($link['label'] !== '') {
                $entityLabel = $link['label'];
                break;
            }
        }

        $fiscalYear = is_numeric($document['fiscal_year'] ?? null) && (int) $document['fiscal_year'] > 0
            ? (string) (int) $document['fiscal_year']
            : 'Sans-annee';

        $categoryCode = (string) ($document['category_code'] ?? 'inbox');
        $categoryDirectory = $exportDirectoryByCategory[$categoryCode] ?? $this->slugSegment($categoryCode);

        $date = (string) ($document['document_date'] ?? '');
        $datePrefix = preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) === 1
            ? $date
            : substr((string) ($document['created_at'] ?? date('Y-m-d')), 0, 10);

        $description = trim((string) ($document['title'] ?? ''));
        if ($description === '') {
            $description = (string) pathinfo((string) ($document['original_filename'] ?? 'document'), PATHINFO_FILENAME);
        }

        $uid = substr((string) ($document['document_uid'] ?? md5((string) ($document['sha256'] ?? ''))), 0, 8);
        $extension = strtolower((string) ($document['extension'] ?? 'bin'));

        $fileName = sprintf(
            '%s_%s_%s_%s.%s',
            $datePrefix,
            $this->slugSegment(str_replace('.', '-', $categoryCode)),
            $this->slugSegment($description),
            $uid,
            preg_match('/\A[a-z0-9]{1,16}\z/', $extension) === 1 ? $extension : 'bin'
        );

        $path = $this->slugSegment($entityLabel) . '/' . $fiscalYear . '/' . $this->slugSegment($categoryDirectory) . '/' . $fileName;

        // Doublon de chemin improbable (uid inclus) mais géré par suffixe.
        $candidate = $path;
        $suffix = 2;
        while (isset($usedPaths[$candidate])) {
            $candidate = preg_replace('/(\.[a-z0-9]{1,16})\z/', '-' . $suffix . '$1', $path) ?? $path . '-' . $suffix;
            $suffix++;
        }
        $usedPaths[$candidate] = true;

        return $candidate;
    }

    /**
     * Segment de chemin sûr pour Windows/macOS/Linux : translittération des
     * accents, caractères interdits remplacés, longueur bornée.
     */
    public function slugSegment(string $value): string
    {
        $value = trim($value);
        $transliterated = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : $value;
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = (string) preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]+/', '-', $value);
        $value = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $value);
        $value = trim((string) preg_replace('/-{2,}/', '-', $value), '.-');

        if ($value === '' || preg_match('/\A(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])\z/i', $value) === 1) {
            $value = 'element';
        }

        return substr($value, 0, self::MAX_SEGMENT_LENGTH);
    }

    /**
     * @return array{ok: bool, error_code: string, zip_path: string, file_count: int, manifest: array<string, mixed>}
     */
    private function failure(string $errorCode): array
    {
        return ['ok' => false, 'error_code' => $errorCode, 'zip_path' => '', 'file_count' => 0, 'manifest' => []];
    }
}
