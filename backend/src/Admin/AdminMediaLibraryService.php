<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

final class AdminMediaLibraryService
{
    private const ROOT_RELATIVE_PATH = '/uploads/editorial/library';

    private const ALLOWED_IMAGE_MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
    ];

    private const ALLOWED_VIDEO_MIME_TO_EXTENSION = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/ogg' => 'ogv',
        'video/x-m4v' => 'm4v',
    ];

    private const ALLOWED_ARCHIVE_MIME = [
        'application/zip',
        'application/x-zip',
        'application/x-zip-compressed',
        'multipart/x-zip',
    ];

    /**
     * @var array<int, string>
     */
    private const MEDIA_PICKER_DEFAULT_FAVORITES = [
        '',
        'images',
        'videos',
        'articles',
        'pages',
        'shared',
    ];

    /**
     * @var array<string, array{
     *   imageExtensions: array<int, string>,
     *   videoExtensions: array<int, string>,
     *   imageMaxBytes: int,
     *   videoMaxBytes: int
     * }>
     */
    private const MEDIA_GOVERNANCE_POLICIES = [
        'page' => [
            'imageExtensions' => ['webp', 'avif', 'jpg', 'jpeg', 'png'],
            'videoExtensions' => ['mp4', 'webm'],
            'imageMaxBytes' => 6291456,
            'videoMaxBytes' => 52428800,
        ],
        'article' => [
            'imageExtensions' => ['webp', 'avif', 'jpg', 'jpeg', 'png'],
            'videoExtensions' => ['mp4', 'webm'],
            'imageMaxBytes' => 4194304,
            'videoMaxBytes' => 26214400,
        ],
    ];

    public function __construct(
        private readonly string $publicDirectory,
        private readonly int $maxUploadBytes = 62914560,
        private readonly int $maxArchiveBytes = 209715200
    ) {
        $this->ensureRootDirectoryExists();
    }

    public function normalizeFolderPath(?string $rawFolder): string
    {
        if (!is_string($rawFolder)) {
            return '';
        }

        $normalized = str_replace('\\', '/', trim($rawFolder));
        if ($normalized === '') {
            return '';
        }

        $segments = [];
        foreach (explode('/', $normalized) as $segment) {
            $segment = trim((string) $segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }

            $safeSegment = $this->sanitizePathSegment($segment);
            if ($safeSegment === '') {
                continue;
            }

            $segments[] = $safeSegment;
        }

        return implode('/', $segments);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   q: string,
     *   type: string,
     *   min_size_kb: int|null,
     *   max_size_kb: int|null,
     *   date_from: string,
     *   date_to: string,
     *   sort: string,
     *   hasActiveFilters: bool
     * }
     */
    public function normalizeFilters(array $input): array
    {
        $query = is_string($input['q'] ?? null) ? trim((string) $input['q']) : '';
        if ($query !== '') {
            $query = function_exists('mb_substr') ? (string) mb_substr($query, 0, 120) : substr($query, 0, 120);
        }

        $type = is_string($input['type'] ?? null) ? strtolower(trim((string) $input['type'])) : 'all';
        $allowedTypes = ['all', 'folder', 'image', 'video', 'other'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'all';
        }

        $minSizeKb = is_numeric($input['min_size_kb'] ?? null) ? max(0, (int) $input['min_size_kb']) : null;
        $maxSizeKb = is_numeric($input['max_size_kb'] ?? null) ? max(0, (int) $input['max_size_kb']) : null;

        if ($minSizeKb !== null && $maxSizeKb !== null && $maxSizeKb < $minSizeKb) {
            [$minSizeKb, $maxSizeKb] = [$maxSizeKb, $minSizeKb];
        }

        $dateFrom = $this->normalizeDateString(is_string($input['date_from'] ?? null) ? (string) $input['date_from'] : '');
        $dateTo = $this->normalizeDateString(is_string($input['date_to'] ?? null) ? (string) $input['date_to'] : '');

        $sort = is_string($input['sort'] ?? null) ? strtolower(trim((string) $input['sort'])) : 'name_asc';
        $allowedSorts = ['name_asc', 'name_desc', 'date_desc', 'date_asc', 'size_desc', 'size_asc', 'type_asc'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'name_asc';
        }

        $hasActiveFilters = $query !== ''
            || $type !== 'all'
            || $minSizeKb !== null
            || $maxSizeKb !== null
            || $dateFrom !== ''
            || $dateTo !== ''
            || $sort !== 'name_asc';

        return [
            'q' => $query,
            'type' => $type,
            'min_size_kb' => $minSizeKb,
            'max_size_kb' => $maxSizeKb,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sort' => $sort,
            'hasActiveFilters' => $hasActiveFilters,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function viewModel(string $currentFolder = '', array $filters = []): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $folder = $this->normalizeFolderPath($currentFolder);
        $absoluteFolderPath = $this->absoluteFolderPath($folder);

        if (!is_dir($absoluteFolderPath)) {
            $folder = '';
            $absoluteFolderPath = $this->absoluteFolderPath($folder);
        }

        $directories = [];
        $files = [];
        $folderSizeBytes = 0;

        foreach (scandir($absoluteFolderPath) ?: [] as $name) {
            if (!is_string($name) || $name === '.' || $name === '..') {
                continue;
            }

            $absolutePath = $absoluteFolderPath . DIRECTORY_SEPARATOR . $name;
            if (is_dir($absolutePath)) {
                $childFolder = $folder === '' ? $name : $folder . '/' . $name;
                $directories[] = [
                    'name' => $name,
                    'folder' => $childFolder,
                    'itemCount' => $this->countDirectoryItems($absolutePath),
                    'mtime' => @filemtime($absolutePath) ?: 0,
                ];
                continue;
            }

            if (!is_file($absolutePath)) {
                continue;
            }

            $fileMetadata = $this->buildFileMetadata($absolutePath, $folder, $name);
            $files[] = $fileMetadata;
            $folderSizeBytes += (int) ($fileMetadata['sizeBytes'] ?? 0);
        }

        $directoryCountTotal = count($directories);
        $fileCountTotal = count($files);

        $directories = array_values(
            array_filter(
                $directories,
                fn (array $directory): bool => $this->directoryMatchesFilters($directory, $normalizedFilters)
            )
        );
        $files = array_values(
            array_filter(
                $files,
                fn (array $file): bool => $this->fileMatchesFilters($file, $normalizedFilters)
            )
        );

        $this->sortDirectories($directories, (string) ($normalizedFilters['sort'] ?? 'name_asc'));
        $this->sortFiles($files, (string) ($normalizedFilters['sort'] ?? 'name_asc'));

        return [
            'currentFolder' => $folder,
            'currentFolderAbsolutePath' => $absoluteFolderPath,
            'parentFolder' => $this->parentFolder($folder),
            'breadcrumbs' => $this->breadcrumbs($folder),
            'filters' => $normalizedFilters,
            'directories' => $directories,
            'files' => $files,
            'directoryCountTotal' => $directoryCountTotal,
            'fileCountTotal' => $fileCountTotal,
            'directoryCount' => count($directories),
            'fileCount' => count($files),
            'folderSizeBytes' => $folderSizeBytes,
            'folderSizeLabel' => $this->bytesToLabel($folderSizeBytes),
            'hasGdWebp' => $this->canEncodeWebp(),
            'folderOptions' => $this->folderPathOptions(),
            'maxUploadMegabytes' => max(1, (int) floor($this->maxUploadBytes / 1048576)),
            'maxArchiveMegabytes' => max(1, (int) floor($this->maxArchiveBytes / 1048576)),
            'allowedImageFormats' => implode(', ', array_map('strtoupper', array_values(self::ALLOWED_IMAGE_MIME_TO_EXTENSION))),
            'allowedVideoFormats' => implode(', ', array_map('strtoupper', array_values(self::ALLOWED_VIDEO_MIME_TO_EXTENSION))),
        ];
    }

    /**
     * @return array<int, array{
     *   name: string,
     *   folder: string,
     *   path: string,
     *   src: string,
     *   kind: string,
     *   mime: string,
     *   extension: string,
     *   sizeBytes: int,
     *   sizeLabel: string,
     *   width: int|null,
     *   height: int|null,
     *   dimensionsLabel: string,
     *   mtime: int
     * }>
     */
    public function mediaPickerItems(int $limit = 240): array
    {
        $limit = max(0, min(1000, $limit));
        if ($limit === 0) {
            return [];
        }

        $root = $this->absoluteRootPath();
        if (!is_dir($root)) {
            return [];
        }

        $items = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $normalizedRoot = str_replace('\\', '/', rtrim($root, '/\\'));
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $absolutePath = str_replace('\\', '/', $fileInfo->getPathname());
            if (!str_starts_with($absolutePath, $normalizedRoot . '/')) {
                continue;
            }

            $relativePath = ltrim(substr($absolutePath, strlen($normalizedRoot)), '/');
            $normalizedFile = $this->normalizeFilePath($relativePath);
            if ($normalizedFile === '') {
                continue;
            }

            $folder = $this->parentFolder($normalizedFile);
            $name = (string) pathinfo($normalizedFile, PATHINFO_BASENAME);
            $metadata = $this->buildFileMetadata($fileInfo->getPathname(), $folder, $name);
            $kind = (string) ($metadata['kind'] ?? 'other');
            if ($kind !== 'image' && $kind !== 'video') {
                continue;
            }

            $items[] = [
                'name' => (string) ($metadata['name'] ?? ''),
                'folder' => $folder,
                'path' => (string) ($metadata['path'] ?? ''),
                'src' => (string) ($metadata['src'] ?? ''),
                'kind' => $kind,
                'mime' => (string) ($metadata['mime'] ?? 'application/octet-stream'),
                'extension' => (string) ($metadata['extension'] ?? ''),
                'sizeBytes' => (int) ($metadata['sizeBytes'] ?? 0),
                'sizeLabel' => (string) ($metadata['sizeLabel'] ?? '0 B'),
                'width' => isset($metadata['width']) && is_int($metadata['width']) ? $metadata['width'] : null,
                'height' => isset($metadata['height']) && is_int($metadata['height']) ? $metadata['height'] : null,
                'dimensionsLabel' => (string) ($metadata['dimensionsLabel'] ?? 'N/A'),
                'mtime' => (int) ($metadata['mtime'] ?? 0),
            ];
        }

        usort(
            $items,
            static function (array $left, array $right): int {
                $byDate = ((int) $right['mtime']) <=> ((int) $left['mtime']);
                if ($byDate !== 0) {
                    return $byDate;
                }

                return strcmp((string) $left['name'], (string) $right['name']);
            }
        );

        return array_slice($items, 0, $limit);
    }

    /**
     * @return array{
     *   context: string,
     *   items: array<int, array{
     *     name: string,
     *     folder: string,
     *     path: string,
     *     src: string,
     *     kind: string,
     *     mime: string,
     *     extension: string,
     *     sizeBytes: int,
     *     sizeLabel: string,
     *     width: int|null,
     *     height: int|null,
     *     dimensionsLabel: string,
     *     mtime: int
     *   }>,
     *   folders: array<int, string>,
     *   favorites: array<int, string>,
     *   policy: array{
     *     context: string,
     *     imageExtensions: array<int, string>,
     *     videoExtensions: array<int, string>,
     *     imageMaxBytes: int,
     *     videoMaxBytes: int,
     *     imageMaxLabel: string,
     *     videoMaxLabel: string
     *   }
     * }
     */
    public function mediaPickerViewModel(string $context = 'page', int $limit = 240): array
    {
        $normalizedContext = $this->normalizeMediaPickerContext($context);
        $folders = $this->folderPathOptions();

        return [
            'context' => $normalizedContext,
            'items' => $this->mediaPickerItems($limit),
            'folders' => $folders,
            'favorites' => $this->mediaPickerFavoriteFolders($folders),
            'policy' => $this->mediaGovernancePolicy($normalizedContext),
        ];
    }

    /**
     * @return array{success: bool, error: string|null, folder: string}
     */
    public function createFolder(string $parentFolder, string $folderName): array
    {
        $parent = $this->normalizeFolderPath($parentFolder);
        $safeFolderName = $this->sanitizePathSegment($folderName);
        if ($safeFolderName === '') {
            return [
                'success' => false,
                'error' => 'Nom de dossier invalide.',
                'folder' => $parent,
            ];
        }

        $targetFolder = $parent === '' ? $safeFolderName : $parent . '/' . $safeFolderName;
        $absolutePath = $this->absoluteFolderPath($targetFolder);

        if (is_dir($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Ce dossier existe deja.',
                'folder' => $parent,
            ];
        }

        if (!mkdir($absolutePath, 0775, true) && !is_dir($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Impossible de creer le dossier.',
                'folder' => $parent,
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'folder' => $targetFolder,
        ];
    }

    /**
     * @return array{success: bool, error: string|null, folder: string}
     */
    public function renameFile(string $targetFilePath, string $newFileName): array
    {
        $relativeFilePath = $this->normalizeFilePath($targetFilePath);
        if ($relativeFilePath === '') {
            return [
                'success' => false,
                'error' => 'Fichier cible invalide.',
                'folder' => '',
            ];
        }

        $absoluteSourcePath = $this->absoluteFilePath($relativeFilePath);
        if (!is_file($absoluteSourcePath)) {
            return [
                'success' => false,
                'error' => 'Fichier introuvable.',
                'folder' => $this->parentFolder($relativeFilePath),
            ];
        }

        $parentFolder = $this->parentFolder($relativeFilePath);
        $currentFilename = (string) pathinfo($relativeFilePath, PATHINFO_BASENAME);
        $requestedFilename = trim($newFileName);
        if ($requestedFilename === '') {
            return [
                'success' => false,
                'error' => 'Nouveau nom de fichier requis.',
                'folder' => $parentFolder,
            ];
        }

        $sanitizedFilename = $this->sanitizeFilename($requestedFilename);
        if ($sanitizedFilename === '') {
            return [
                'success' => false,
                'error' => 'Nom de fichier invalide.',
                'folder' => $parentFolder,
            ];
        }

        $sourceExtension = strtolower((string) pathinfo($currentFilename, PATHINFO_EXTENSION));
        $newBase = (string) pathinfo($sanitizedFilename, PATHINFO_FILENAME);
        $newExtension = strtolower((string) pathinfo($sanitizedFilename, PATHINFO_EXTENSION));
        if ($newBase === '') {
            $newBase = 'fichier';
        }

        if ($newExtension === '' && $sourceExtension !== '') {
            $newExtension = $sourceExtension;
        }

        $targetFilename = $newExtension !== '' ? ($newBase . '.' . $newExtension) : $newBase;
        if (strcasecmp($targetFilename, $currentFilename) === 0) {
            return [
                'success' => true,
                'error' => null,
                'folder' => $parentFolder,
            ];
        }

        $sourceDirectory = dirname($absoluteSourcePath);
        $absoluteTargetPath = rtrim($sourceDirectory, '/\\') . DIRECTORY_SEPARATOR . $targetFilename;
        if (file_exists($absoluteTargetPath)) {
            return [
                'success' => false,
                'error' => 'Un fichier avec ce nom existe deja.',
                'folder' => $parentFolder,
            ];
        }

        if (!@rename($absoluteSourcePath, $absoluteTargetPath)) {
            return [
                'success' => false,
                'error' => 'Renommage du fichier impossible.',
                'folder' => $parentFolder,
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'folder' => $parentFolder,
        ];
    }

    /**
     * @return array{success: bool, error: string|null, folder: string}
     */
    public function moveFile(string $targetFilePath, string $destinationFolder): array
    {
        $relativeFilePath = $this->normalizeFilePath($targetFilePath);
        if ($relativeFilePath === '') {
            return [
                'success' => false,
                'error' => 'Fichier cible invalide.',
                'folder' => '',
            ];
        }

        $absoluteSourcePath = $this->absoluteFilePath($relativeFilePath);
        if (!is_file($absoluteSourcePath)) {
            return [
                'success' => false,
                'error' => 'Fichier introuvable.',
                'folder' => $this->parentFolder($relativeFilePath),
            ];
        }

        $targetFolder = $this->normalizeFolderPath($destinationFolder);
        $absoluteTargetFolder = $this->absoluteFolderPath($targetFolder);
        if (!is_dir($absoluteTargetFolder) && !mkdir($absoluteTargetFolder, 0775, true) && !is_dir($absoluteTargetFolder)) {
            return [
                'success' => false,
                'error' => 'Dossier de destination introuvable.',
                'folder' => $this->parentFolder($relativeFilePath),
            ];
        }

        $filename = (string) pathinfo($relativeFilePath, PATHINFO_BASENAME);
        $sourceFolder = $this->parentFolder($relativeFilePath);
        if ($targetFolder === $sourceFolder) {
            return [
                'success' => true,
                'error' => null,
                'folder' => $targetFolder,
            ];
        }

        $absoluteTargetPath = rtrim($absoluteTargetFolder, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($absoluteTargetPath)) {
            return [
                'success' => false,
                'error' => 'Un fichier avec ce nom existe deja dans le dossier cible.',
                'folder' => $sourceFolder,
            ];
        }

        if (!@rename($absoluteSourcePath, $absoluteTargetPath)) {
            return [
                'success' => false,
                'error' => 'Deplacement du fichier impossible.',
                'folder' => $sourceFolder,
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'folder' => $targetFolder,
        ];
    }

    /**
     * @return array{success: bool, error: string|null, parentFolder: string, folder: string}
     */
    public function renameFolder(string $targetFolder, string $newFolderName): array
    {
        $sourceFolder = $this->normalizeFolderPath($targetFolder);
        if ($sourceFolder === '') {
            return [
                'success' => false,
                'error' => 'Le dossier racine ne peut pas etre renomme.',
                'parentFolder' => '',
                'folder' => '',
            ];
        }

        $safeFolderName = $this->sanitizePathSegment($newFolderName);
        if ($safeFolderName === '') {
            return [
                'success' => false,
                'error' => 'Nom de dossier invalide.',
                'parentFolder' => $this->parentFolder($sourceFolder),
                'folder' => $sourceFolder,
            ];
        }

        $absoluteSourcePath = $this->absoluteFolderPath($sourceFolder);
        if (!is_dir($absoluteSourcePath)) {
            return [
                'success' => false,
                'error' => 'Dossier introuvable.',
                'parentFolder' => $this->parentFolder($sourceFolder),
                'folder' => $sourceFolder,
            ];
        }

        $parentFolder = $this->parentFolder($sourceFolder);
        $targetRelativeFolder = $parentFolder === '' ? $safeFolderName : ($parentFolder . '/' . $safeFolderName);
        if ($targetRelativeFolder === $sourceFolder) {
            return [
                'success' => true,
                'error' => null,
                'parentFolder' => $parentFolder,
                'folder' => $targetRelativeFolder,
            ];
        }

        $absoluteTargetPath = $this->absoluteFolderPath($targetRelativeFolder);
        if (is_dir($absoluteTargetPath) || is_file($absoluteTargetPath)) {
            return [
                'success' => false,
                'error' => 'Un dossier avec ce nom existe deja.',
                'parentFolder' => $parentFolder,
                'folder' => $sourceFolder,
            ];
        }

        if (!@rename($absoluteSourcePath, $absoluteTargetPath)) {
            return [
                'success' => false,
                'error' => 'Renommage du dossier impossible.',
                'parentFolder' => $parentFolder,
                'folder' => $sourceFolder,
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'parentFolder' => $parentFolder,
            'folder' => $targetRelativeFolder,
        ];
    }

    /**
     * @return array{success: bool, error: string|null, parentFolder: string, folder: string}
     */
    public function moveFolder(string $targetFolder, string $destinationFolder): array
    {
        $sourceFolder = $this->normalizeFolderPath($targetFolder);
        if ($sourceFolder === '') {
            return [
                'success' => false,
                'error' => 'Le dossier racine ne peut pas etre deplace.',
                'parentFolder' => '',
                'folder' => '',
            ];
        }

        $absoluteSourcePath = $this->absoluteFolderPath($sourceFolder);
        if (!is_dir($absoluteSourcePath)) {
            return [
                'success' => false,
                'error' => 'Dossier introuvable.',
                'parentFolder' => $this->parentFolder($sourceFolder),
                'folder' => $sourceFolder,
            ];
        }

        $targetParentFolder = $this->normalizeFolderPath($destinationFolder);
        if ($targetParentFolder !== '' && str_starts_with($targetParentFolder . '/', $sourceFolder . '/')) {
            return [
                'success' => false,
                'error' => 'Le dossier de destination est invalide.',
                'parentFolder' => $this->parentFolder($sourceFolder),
                'folder' => $sourceFolder,
            ];
        }

        $absoluteTargetParentPath = $this->absoluteFolderPath($targetParentFolder);
        if (!is_dir($absoluteTargetParentPath)) {
            return [
                'success' => false,
                'error' => 'Dossier de destination introuvable.',
                'parentFolder' => $this->parentFolder($sourceFolder),
                'folder' => $sourceFolder,
            ];
        }

        $folderName = (string) pathinfo($sourceFolder, PATHINFO_BASENAME);
        $targetRelativeFolder = $targetParentFolder === '' ? $folderName : ($targetParentFolder . '/' . $folderName);
        if ($targetRelativeFolder === $sourceFolder) {
            return [
                'success' => true,
                'error' => null,
                'parentFolder' => $targetParentFolder,
                'folder' => $sourceFolder,
            ];
        }

        if (str_starts_with($targetRelativeFolder . '/', $sourceFolder . '/')) {
            return [
                'success' => false,
                'error' => 'Impossible de deplacer un dossier dans un sous-dossier de lui-meme.',
                'parentFolder' => $this->parentFolder($sourceFolder),
                'folder' => $sourceFolder,
            ];
        }

        $absoluteTargetPath = $this->absoluteFolderPath($targetRelativeFolder);
        if (is_dir($absoluteTargetPath) || is_file($absoluteTargetPath)) {
            return [
                'success' => false,
                'error' => 'Un dossier avec ce nom existe deja dans la destination.',
                'parentFolder' => $this->parentFolder($sourceFolder),
                'folder' => $sourceFolder,
            ];
        }

        if (!@rename($absoluteSourcePath, $absoluteTargetPath)) {
            return [
                'success' => false,
                'error' => 'Deplacement du dossier impossible.',
                'parentFolder' => $this->parentFolder($sourceFolder),
                'folder' => $sourceFolder,
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'parentFolder' => $targetParentFolder,
            'folder' => $targetRelativeFolder,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $files
     * @return array{success: bool, error: string|null, uploadedCount: int, convertedCount: int, skippedCount: int}
     */
    public function uploadFiles(
        string $folder,
        array $files,
        bool $autoWebp = true,
        int $maxWidth = 2560,
        int $maxHeight = 2560,
        int $quality = 82
    ): array {
        $targetFolder = $this->normalizeFolderPath($folder);
        $absoluteFolderPath = $this->absoluteFolderPath($targetFolder);

        if (!is_dir($absoluteFolderPath) && !mkdir($absoluteFolderPath, 0775, true) && !is_dir($absoluteFolderPath)) {
            return [
                'success' => false,
                'error' => 'Impossible de creer le dossier de destination.',
                'uploadedCount' => 0,
                'convertedCount' => 0,
                'skippedCount' => 0,
            ];
        }

        $uploadedCount = 0;
        $convertedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($files as $file) {
            $result = $this->persistUploadedFile(
                $file,
                $absoluteFolderPath,
                $autoWebp,
                $maxWidth,
                $maxHeight,
                $quality
            );

            if (($result['status'] ?? '') === 'stored') {
                $uploadedCount++;
                continue;
            }

            if (($result['status'] ?? '') === 'converted') {
                $uploadedCount++;
                $convertedCount++;
                continue;
            }

            if (($result['status'] ?? '') === 'skipped') {
                $skippedCount++;
                continue;
            }

            $errors[] = (string) ($result['error'] ?? 'Erreur upload');
        }

        return [
            'success' => $errors === [],
            'error' => $errors === [] ? null : implode(' ', $errors),
            'uploadedCount' => $uploadedCount,
            'convertedCount' => $convertedCount,
            'skippedCount' => $skippedCount,
        ];
    }

    /**
     * @param array<string, mixed> $archiveFile
     * @return array{success: bool, error: string|null, importedCount: int, convertedCount: int, skippedCount: int}
     */
    public function importArchive(
        string $folder,
        array $archiveFile,
        bool $autoWebp = true,
        int $maxWidth = 2560,
        int $maxHeight = 2560,
        int $quality = 82
    ): array {
        if (!class_exists(\ZipArchive::class)) {
            return [
                'success' => false,
                'error' => 'ZipArchive nest pas disponible sur le serveur.',
                'importedCount' => 0,
                'convertedCount' => 0,
                'skippedCount' => 0,
            ];
        }

        $archiveError = isset($archiveFile['error']) ? (int) $archiveFile['error'] : UPLOAD_ERR_NO_FILE;
        if ($archiveError === UPLOAD_ERR_NO_FILE) {
            return [
                'success' => false,
                'error' => 'Aucune archive ZIP transmise.',
                'importedCount' => 0,
                'convertedCount' => 0,
                'skippedCount' => 0,
            ];
        }

        if ($archiveError !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'error' => $this->uploadErrorMessage($archiveError),
                'importedCount' => 0,
                'convertedCount' => 0,
                'skippedCount' => 0,
            ];
        }

        $tmpName = is_string($archiveFile['tmp_name'] ?? null) ? trim((string) $archiveFile['tmp_name']) : '';
        if ($tmpName === '' || !is_file($tmpName)) {
            return [
                'success' => false,
                'error' => 'Archive temporaire introuvable.',
                'importedCount' => 0,
                'convertedCount' => 0,
                'skippedCount' => 0,
            ];
        }

        $archiveSize = is_numeric($archiveFile['size'] ?? null) ? (int) $archiveFile['size'] : (int) filesize($tmpName);
        if ($archiveSize <= 0) {
            return [
                'success' => false,
                'error' => 'Archive ZIP vide.',
                'importedCount' => 0,
                'convertedCount' => 0,
                'skippedCount' => 0,
            ];
        }

        if ($archiveSize > $this->maxArchiveBytes) {
            return [
                'success' => false,
                'error' => sprintf(
                    'Archive trop volumineuse (max %d Mo).',
                    max(1, (int) floor($this->maxArchiveBytes / 1048576))
                ),
                'importedCount' => 0,
                'convertedCount' => 0,
                'skippedCount' => 0,
            ];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $archiveMime = strtolower((string) $finfo->file($tmpName));
        if (!in_array($archiveMime, self::ALLOWED_ARCHIVE_MIME, true)) {
            $extension = strtolower((string) pathinfo((string) ($archiveFile['name'] ?? ''), PATHINFO_EXTENSION));
            if ($extension !== 'zip') {
                return [
                    'success' => false,
                    'error' => 'Format d archive non supporte. Utilisez un fichier .zip.',
                    'importedCount' => 0,
                    'convertedCount' => 0,
                    'skippedCount' => 0,
                ];
            }
        }

        $baseFolder = $this->normalizeFolderPath($folder);
        $baseAbsolutePath = $this->absoluteFolderPath($baseFolder);
        if (!is_dir($baseAbsolutePath) && !mkdir($baseAbsolutePath, 0775, true) && !is_dir($baseAbsolutePath)) {
            return [
                'success' => false,
                'error' => 'Impossible de creer le dossier de destination.',
                'importedCount' => 0,
                'convertedCount' => 0,
                'skippedCount' => 0,
            ];
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($tmpName);
        if ($opened !== true) {
            return [
                'success' => false,
                'error' => 'Impossible de lire l archive ZIP.',
                'importedCount' => 0,
                'convertedCount' => 0,
                'skippedCount' => 0,
            ];
        }

        $importedCount = 0;
        $convertedCount = 0;
        $skippedCount = 0;
        $errors = [];
        $totalExtractedBytes = 0;
        $maxExtractedBytes = 419430400;
        $maxEntries = 400;
        $entriesToScan = min($zip->numFiles, $maxEntries);

        for ($index = 0; $index < $entriesToScan; $index++) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat)) {
                $skippedCount++;
                continue;
            }

            $entryName = is_string($stat['name'] ?? null) ? (string) $stat['name'] : '';
            $entrySize = is_numeric($stat['size'] ?? null) ? (int) $stat['size'] : 0;

            if ($entryName === '' || str_ends_with($entryName, '/')) {
                continue;
            }

            if ($entrySize < 0) {
                $skippedCount++;
                continue;
            }

            $totalExtractedBytes += $entrySize;
            if ($totalExtractedBytes > $maxExtractedBytes) {
                $errors[] = 'Archive trop volumineuse apres extraction.';
                break;
            }

            $sanitizedEntry = $this->sanitizeArchiveEntryPath($entryName);
            if ($sanitizedEntry === null) {
                $skippedCount++;
                continue;
            }

            $entryDirectory = (string) pathinfo($sanitizedEntry, PATHINFO_DIRNAME);
            $entryFilename = (string) pathinfo($sanitizedEntry, PATHINFO_BASENAME);

            $relativeTargetFolder = $baseFolder;
            if ($entryDirectory !== '' && $entryDirectory !== '.') {
                $relativeTargetFolder = $baseFolder === ''
                    ? $entryDirectory
                    : $baseFolder . '/' . $entryDirectory;
            }

            $absoluteTargetFolder = $this->absoluteFolderPath($relativeTargetFolder);
            if (!is_dir($absoluteTargetFolder) && !mkdir($absoluteTargetFolder, 0775, true) && !is_dir($absoluteTargetFolder)) {
                $errors[] = sprintf('Impossible de creer le dossier cible pour %s.', $entryName);
                continue;
            }

            $stream = $zip->getStream($entryName);
            if (!is_resource($stream)) {
                $skippedCount++;
                continue;
            }

            $temporaryPath = tempnam(sys_get_temp_dir(), 'cara-media-zip-');
            if (!is_string($temporaryPath)) {
                fclose($stream);
                $errors[] = 'Impossible de creer un fichier temporaire pour import ZIP.';
                continue;
            }

            $temporaryHandle = fopen($temporaryPath, 'wb');
            if (!is_resource($temporaryHandle)) {
                fclose($stream);
                @unlink($temporaryPath);
                $errors[] = 'Impossible de preparer un fichier temporaire pour import ZIP.';
                continue;
            }

            stream_copy_to_stream($stream, $temporaryHandle);
            fclose($temporaryHandle);
            fclose($stream);

            $result = $this->persistExtractedFile(
                $temporaryPath,
                $entryFilename,
                $absoluteTargetFolder,
                $autoWebp,
                $maxWidth,
                $maxHeight,
                $quality
            );
            @unlink($temporaryPath);

            if (($result['status'] ?? '') === 'stored') {
                $importedCount++;
                continue;
            }

            if (($result['status'] ?? '') === 'converted') {
                $importedCount++;
                $convertedCount++;
                continue;
            }

            if (($result['status'] ?? '') === 'skipped') {
                $skippedCount++;
                continue;
            }

            $errors[] = (string) ($result['error'] ?? 'Erreur import ZIP');
        }

        $zip->close();

        return [
            'success' => $errors === [],
            'error' => $errors === [] ? null : implode(' ', $errors),
            'importedCount' => $importedCount,
            'convertedCount' => $convertedCount,
            'skippedCount' => $skippedCount,
        ];
    }

    /**
     * @return array{success: bool, error: string|null, parentFolder: string}
     */
    public function deleteFolder(string $targetFolder): array
    {
        $folder = $this->normalizeFolderPath($targetFolder);
        if ($folder === '') {
            return [
                'success' => false,
                'error' => 'La racine ne peut pas etre supprimee.',
                'parentFolder' => '',
            ];
        }

        $absolutePath = $this->absoluteFolderPath($folder);
        if (!is_dir($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Dossier introuvable.',
                'parentFolder' => $this->parentFolder($folder),
            ];
        }

        if (!$this->removeDirectoryRecursively($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Impossible de supprimer ce dossier.',
                'parentFolder' => $this->parentFolder($folder),
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'parentFolder' => $this->parentFolder($folder),
        ];
    }

    /**
     * @return array{success: bool, error: string|null, folder: string}
     */
    public function deleteFile(string $filePath): array
    {
        $relativeFilePath = $this->normalizeFilePath($filePath);
        if ($relativeFilePath === '') {
            return [
                'success' => false,
                'error' => 'Fichier cible invalide.',
                'folder' => '',
            ];
        }

        $absolutePath = $this->absoluteFilePath($relativeFilePath);
        if (!is_file($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Fichier introuvable.',
                'folder' => $this->parentFolder($relativeFilePath),
            ];
        }

        if (!@unlink($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Suppression du fichier impossible.',
                'folder' => $this->parentFolder($relativeFilePath),
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'folder' => $this->parentFolder($relativeFilePath),
        ];
    }

    /**
     * @return array{success: bool, error: string|null, outputSrc: string|null, folder: string}
     */
    public function convertFileToWebp(
        string $filePath,
        int $maxWidth = 2560,
        int $maxHeight = 2560,
        int $quality = 82
    ): array {
        $relativeFilePath = $this->normalizeFilePath($filePath);
        if ($relativeFilePath === '') {
            return [
                'success' => false,
                'error' => 'Fichier cible invalide.',
                'outputSrc' => null,
                'folder' => '',
            ];
        }

        if (!$this->canEncodeWebp()) {
            return [
                'success' => false,
                'error' => 'Conversion WebP indisponible (extension GD).',
                'outputSrc' => null,
                'folder' => $this->parentFolder($relativeFilePath),
            ];
        }

        $absolutePath = $this->absoluteFilePath($relativeFilePath);
        if (!is_file($absolutePath)) {
            return [
                'success' => false,
                'error' => 'Fichier introuvable.',
                'outputSrc' => null,
                'folder' => $this->parentFolder($relativeFilePath),
            ];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = strtolower((string) $finfo->file($absolutePath));
        if (!isset(self::ALLOWED_IMAGE_MIME_TO_EXTENSION[$mimeType]) || $mimeType === 'image/webp') {
            return [
                'success' => false,
                'error' => 'Seules les images JPG, PNG, GIF ou AVIF peuvent etre converties en WebP.',
                'outputSrc' => null,
                'folder' => $this->parentFolder($relativeFilePath),
            ];
        }

        $filename = (string) pathinfo($relativeFilePath, PATHINFO_FILENAME);
        $baseName = $this->sanitizePathSegment($filename !== '' ? $filename : 'image');
        $absoluteDirectory = dirname($absolutePath);
        $targetFilename = $this->buildUniqueFilename($baseName, 'webp');
        $targetAbsolutePath = rtrim($absoluteDirectory, '/\\') . DIRECTORY_SEPARATOR . $targetFilename;

        $writeResult = $this->convertImageFileToWebp(
            $absolutePath,
            $mimeType,
            $targetAbsolutePath,
            $maxWidth,
            $maxHeight,
            $quality
        );

        if (!$writeResult['success']) {
            return [
                'success' => false,
                'error' => (string) ($writeResult['error'] ?? 'Conversion impossible.'),
                'outputSrc' => null,
                'folder' => $this->parentFolder($relativeFilePath),
            ];
        }

        $outputFolder = $this->parentFolder($relativeFilePath);
        $outputRelativePath = $outputFolder === '' ? $targetFilename : ($outputFolder . '/' . $targetFilename);

        return [
            'success' => true,
            'error' => null,
            'outputSrc' => $this->publicUrlForRelativePath($outputRelativePath),
            'folder' => $outputFolder,
        ];
    }

    /**
     * @return array{success: bool, error: string|null, filename: string|null, content: string|null}
     */
    public function exportFolderArchive(string $folder): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return [
                'success' => false,
                'error' => 'ZipArchive nest pas disponible sur le serveur.',
                'filename' => null,
                'content' => null,
            ];
        }

        $relativeFolder = $this->normalizeFolderPath($folder);
        $absoluteFolder = $this->absoluteFolderPath($relativeFolder);
        if (!is_dir($absoluteFolder)) {
            return [
                'success' => false,
                'error' => 'Dossier introuvable.',
                'filename' => null,
                'content' => null,
            ];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absoluteFolder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $files[] = $fileInfo->getPathname();
        }

        if ($files === []) {
            return [
                'success' => false,
                'error' => 'Aucun fichier a exporter dans ce dossier.',
                'filename' => null,
                'content' => null,
            ];
        }

        $archivePath = tempnam(sys_get_temp_dir(), 'cara-media-export-');
        if (!is_string($archivePath)) {
            return [
                'success' => false,
                'error' => 'Impossible de preparer l archive dexport.',
                'filename' => null,
                'content' => null,
            ];
        }

        @unlink($archivePath);
        $archivePath .= '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return [
                'success' => false,
                'error' => 'Impossible de creer l archive ZIP.',
                'filename' => null,
                'content' => null,
            ];
        }

        foreach ($files as $absolutePath) {
            $relativePath = ltrim(str_replace('\\', '/', substr($absolutePath, strlen(rtrim($absoluteFolder, '/\\')))), '/');
            if ($relativePath === '') {
                continue;
            }

            $zip->addFile($absolutePath, $relativePath);
        }

        $zip->close();

        $content = @file_get_contents($archivePath);
        @unlink($archivePath);

        if (!is_string($content) || $content === '') {
            return [
                'success' => false,
                'error' => 'Impossible de lire l archive generee.',
                'filename' => null,
                'content' => null,
            ];
        }

        $folderToken = $relativeFolder === '' ? 'racine' : str_replace('/', '-', $relativeFolder);
        $filename = sprintf('media-%s-%s.zip', $folderToken, date('Ymd-His'));

        return [
            'success' => true,
            'error' => null,
            'filename' => $filename,
            'content' => $content,
        ];
    }

    /**
     * @return array<int, array{label: string, folder: string}>
     */
    private function breadcrumbs(string $folder): array
    {
        $normalized = $this->normalizeFolderPath($folder);
        $breadcrumbs = [
            [
                'label' => 'Racine',
                'folder' => '',
            ],
        ];

        if ($normalized === '') {
            return $breadcrumbs;
        }

        $parts = explode('/', $normalized);
        $path = '';
        foreach ($parts as $part) {
            $path = $path === '' ? $part : ($path . '/' . $part);
            $breadcrumbs[] = [
                'label' => $part,
                'folder' => $path,
            ];
        }

        return $breadcrumbs;
    }

    private function parentFolder(string $folder): string
    {
        $normalized = $this->normalizeFolderPath($folder);
        if ($normalized === '') {
            return '';
        }

        $parent = dirname($normalized);

        return $parent === '.' ? '' : $parent;
    }

    private function normalizeDateString(string $rawDate): string
    {
        $value = trim($rawDate);
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return '';
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (!checkdate($month, $day, $year)) {
            return '';
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function normalizeMediaPickerContext(string $context): string
    {
        $normalized = strtolower(trim($context));

        return array_key_exists($normalized, self::MEDIA_GOVERNANCE_POLICIES)
            ? $normalized
            : 'page';
    }

    /**
     * @param array<int, string> $folderOptions
     * @return array<int, string>
     */
    private function mediaPickerFavoriteFolders(array $folderOptions): array
    {
        $available = [];
        foreach ($folderOptions as $folderOption) {
            if (!is_string($folderOption)) {
                continue;
            }

            $normalized = $this->normalizeFolderPath($folderOption);
            $available[$normalized] = true;
        }
        $available[''] = true;

        $favorites = [];
        foreach (self::MEDIA_PICKER_DEFAULT_FAVORITES as $defaultFavorite) {
            $normalized = $this->normalizeFolderPath($defaultFavorite);
            if (array_key_exists($normalized, $available)) {
                $favorites[$normalized] = true;
            }
        }

        if (count($favorites) < 4) {
            foreach ($folderOptions as $folderOption) {
                if (!is_string($folderOption)) {
                    continue;
                }

                $normalized = $this->normalizeFolderPath($folderOption);
                if ($normalized === '' || array_key_exists($normalized, $favorites)) {
                    continue;
                }

                $favorites[$normalized] = true;
                if (count($favorites) >= 6) {
                    break;
                }
            }
        }

        if (!array_key_exists('', $favorites)) {
            $favorites = ['' => true] + $favorites;
        }

        return array_values(array_keys($favorites));
    }

    /**
     * @return array{
     *   context: string,
     *   imageExtensions: array<int, string>,
     *   videoExtensions: array<int, string>,
     *   imageMaxBytes: int,
     *   videoMaxBytes: int,
     *   imageMaxLabel: string,
     *   videoMaxLabel: string
     * }
     */
    private function mediaGovernancePolicy(string $context): array
    {
        $normalizedContext = $this->normalizeMediaPickerContext($context);
        $policy = self::MEDIA_GOVERNANCE_POLICIES[$normalizedContext] ?? self::MEDIA_GOVERNANCE_POLICIES['page'];
        $imageExtensions = array_values(array_unique(array_map(static fn (string $ext): string => strtolower(trim($ext)), $policy['imageExtensions'])));
        $videoExtensions = array_values(array_unique(array_map(static fn (string $ext): string => strtolower(trim($ext)), $policy['videoExtensions'])));
        $imageMaxBytes = max(0, (int) $policy['imageMaxBytes']);
        $videoMaxBytes = max(0, (int) $policy['videoMaxBytes']);

        return [
            'context' => $normalizedContext,
            'imageExtensions' => array_values(array_filter($imageExtensions, static fn (string $ext): bool => $ext !== '')),
            'videoExtensions' => array_values(array_filter($videoExtensions, static fn (string $ext): bool => $ext !== '')),
            'imageMaxBytes' => $imageMaxBytes,
            'videoMaxBytes' => $videoMaxBytes,
            'imageMaxLabel' => $this->bytesToLabel($imageMaxBytes),
            'videoMaxLabel' => $this->bytesToLabel($videoMaxBytes),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function folderPathOptions(): array
    {
        $root = $this->absoluteRootPath();
        $options = [''];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $normalizedRoot = str_replace('\\', '/', rtrim($root, '/\\'));
        foreach ($iterator as $info) {
            if (!$info instanceof \SplFileInfo || !$info->isDir()) {
                continue;
            }

            $absolutePath = str_replace('\\', '/', $info->getPathname());
            if (!str_starts_with($absolutePath, $normalizedRoot . '/')) {
                continue;
            }

            $relativePath = ltrim(substr($absolutePath, strlen($normalizedRoot)), '/');
            $normalized = $this->normalizeFolderPath($relativePath);
            if ($normalized === '') {
                continue;
            }

            $options[] = $normalized;
        }

        $options = array_values(array_unique($options));
        sort($options, SORT_NATURAL | SORT_FLAG_CASE);
        $rootIndex = array_search('', $options, true);
        if ($rootIndex !== false) {
            unset($options[$rootIndex]);
        }

        array_unshift($options, '');

        return array_values($options);
    }

    /**
     * @param array<string, mixed> $directory
     * @param array<string, mixed> $filters
     */
    private function directoryMatchesFilters(array $directory, array $filters): bool
    {
        $type = (string) ($filters['type'] ?? 'all');
        if ($type !== 'all' && $type !== 'folder') {
            return false;
        }

        if (($filters['min_size_kb'] ?? null) !== null || ($filters['max_size_kb'] ?? null) !== null) {
            return false;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        $directoryName = (string) ($directory['name'] ?? '');
        if ($query !== '' && !$this->containsIgnoreCase($directoryName, $query)) {
            return false;
        }

        $mtime = is_numeric($directory['mtime'] ?? null) ? (int) $directory['mtime'] : 0;

        return $this->timestampMatchesDateRange(
            $mtime,
            (string) ($filters['date_from'] ?? ''),
            (string) ($filters['date_to'] ?? '')
        );
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $filters
     */
    private function fileMatchesFilters(array $file, array $filters): bool
    {
        $type = (string) ($filters['type'] ?? 'all');
        if ($type === 'folder') {
            return false;
        }

        $fileKind = (string) ($file['kind'] ?? 'other');
        if ($type !== 'all' && $fileKind !== $type) {
            return false;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $name = (string) ($file['name'] ?? '');
            $path = (string) ($file['path'] ?? '');
            if (!$this->containsIgnoreCase($name, $query) && !$this->containsIgnoreCase($path, $query)) {
                return false;
            }
        }

        $sizeBytes = is_numeric($file['sizeBytes'] ?? null) ? (int) $file['sizeBytes'] : 0;
        $sizeKb = $sizeBytes / 1024;
        $minSizeKb = is_numeric($filters['min_size_kb'] ?? null) ? (int) $filters['min_size_kb'] : null;
        $maxSizeKb = is_numeric($filters['max_size_kb'] ?? null) ? (int) $filters['max_size_kb'] : null;

        if ($minSizeKb !== null && $sizeKb < $minSizeKb) {
            return false;
        }

        if ($maxSizeKb !== null && $sizeKb > $maxSizeKb) {
            return false;
        }

        $mtime = is_numeric($file['mtime'] ?? null) ? (int) $file['mtime'] : 0;

        return $this->timestampMatchesDateRange(
            $mtime,
            (string) ($filters['date_from'] ?? ''),
            (string) ($filters['date_to'] ?? '')
        );
    }

    /**
     * @param array<int, array<string, mixed>> $directories
     */
    private function sortDirectories(array &$directories, string $sort): void
    {
        $direction = str_ends_with($sort, '_desc') ? -1 : 1;

        usort(
            $directories,
            function (array $left, array $right) use ($sort, $direction): int {
                $leftName = strtolower((string) ($left['name'] ?? ''));
                $rightName = strtolower((string) ($right['name'] ?? ''));

                $compare = match ($sort) {
                    'date_desc', 'date_asc' => ((int) ($left['mtime'] ?? 0)) <=> ((int) ($right['mtime'] ?? 0)),
                    'size_desc', 'size_asc' => ((int) ($left['itemCount'] ?? 0)) <=> ((int) ($right['itemCount'] ?? 0)),
                    default => $leftName <=> $rightName,
                };

                if ($compare === 0) {
                    $compare = $leftName <=> $rightName;
                }

                return $compare * $direction;
            }
        );
    }

    /**
     * @param array<int, array<string, mixed>> $files
     */
    private function sortFiles(array &$files, string $sort): void
    {
        $direction = str_ends_with($sort, '_desc') ? -1 : 1;

        usort(
            $files,
            function (array $left, array $right) use ($sort, $direction): int {
                $leftName = strtolower((string) ($left['name'] ?? ''));
                $rightName = strtolower((string) ($right['name'] ?? ''));
                $leftKind = strtolower((string) ($left['kind'] ?? 'other'));
                $rightKind = strtolower((string) ($right['kind'] ?? 'other'));
                $leftExtension = strtolower((string) ($left['extension'] ?? ''));
                $rightExtension = strtolower((string) ($right['extension'] ?? ''));

                $compare = match ($sort) {
                    'date_desc', 'date_asc' => ((int) ($left['mtime'] ?? 0)) <=> ((int) ($right['mtime'] ?? 0)),
                    'size_desc', 'size_asc' => ((int) ($left['sizeBytes'] ?? 0)) <=> ((int) ($right['sizeBytes'] ?? 0)),
                    'type_asc' => ($leftKind <=> $rightKind) !== 0
                        ? ($leftKind <=> $rightKind)
                        : ($leftExtension <=> $rightExtension),
                    default => $leftName <=> $rightName,
                };

                if ($compare === 0) {
                    $compare = $leftName <=> $rightName;
                }

                return $compare * $direction;
            }
        );
    }

    private function containsIgnoreCase(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
        }

        return stripos($haystack, $needle) !== false;
    }

    private function timestampMatchesDateRange(int $timestamp, string $dateFrom, string $dateTo): bool
    {
        if ($timestamp <= 0) {
            return $dateFrom === '' && $dateTo === '';
        }

        if ($dateFrom !== '') {
            $fromTimestamp = strtotime($dateFrom . ' 00:00:00');
            if ($fromTimestamp !== false && $timestamp < $fromTimestamp) {
                return false;
            }
        }

        if ($dateTo !== '') {
            $toTimestamp = strtotime($dateTo . ' 23:59:59');
            if ($toTimestamp !== false && $timestamp > $toTimestamp) {
                return false;
            }
        }

        return true;
    }

    private function ensureRootDirectoryExists(): void
    {
        $absoluteRoot = $this->absoluteRootPath();
        if (!is_dir($absoluteRoot) && !mkdir($absoluteRoot, 0775, true) && !is_dir($absoluteRoot)) {
            throw new \RuntimeException('Impossible de creer le dossier racine de la bibliotheque medias.');
        }
    }

    private function absoluteRootPath(): string
    {
        return rtrim($this->publicDirectory, '/\\') . self::ROOT_RELATIVE_PATH;
    }

    private function absoluteFolderPath(string $relativeFolder): string
    {
        $normalized = $this->normalizeFolderPath($relativeFolder);
        if ($normalized === '') {
            return $this->absoluteRootPath();
        }

        return rtrim($this->absoluteRootPath(), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    }

    private function absoluteFilePath(string $relativeFilePath): string
    {
        $normalized = $this->normalizeFilePath($relativeFilePath);

        return rtrim($this->absoluteRootPath(), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    }

    private function sanitizePathSegment(string $segment): string
    {
        $normalized = strtolower(trim($segment));
        $normalized = preg_replace('/[^a-z0-9._-]+/i', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-_.');

        return $normalized;
    }

    private function normalizeFilePath(string $filePath): string
    {
        $normalized = str_replace('\\', '/', trim($filePath));
        if ($normalized === '') {
            return '';
        }

        $segments = [];
        foreach (explode('/', $normalized) as $segment) {
            $segment = trim((string) $segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }

            if (preg_match('/^[A-Za-z0-9._ -]+$/', $segment) !== 1) {
                return '';
            }

            $segments[] = $segment;
        }

        return implode('/', array_filter($segments, static fn (string $segment): bool => $segment !== ''));
    }

    private function sanitizeFilename(string $filename): string
    {
        $base = (string) pathinfo($filename, PATHINFO_FILENAME);
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $safeBase = $this->sanitizePathSegment($base);
        if ($safeBase === '') {
            $safeBase = 'fichier';
        }

        $safeExt = preg_replace('/[^a-z0-9]+/i', '', $ext) ?? '';

        return $safeExt !== '' ? ($safeBase . '.' . strtolower($safeExt)) : $safeBase;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFileMetadata(string $absolutePath, string $folder, string $name): array
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = strtolower((string) $finfo->file($absolutePath));
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $sizeBytes = is_numeric(filesize($absolutePath)) ? (int) filesize($absolutePath) : 0;
        $relativePath = $folder === '' ? $name : ($folder . '/' . $name);

        $width = null;
        $height = null;
        if (str_starts_with($mimeType, 'image/')) {
            $dimensions = @getimagesize($absolutePath);
            if (is_array($dimensions)) {
                $width = is_numeric($dimensions[0]) ? (int) $dimensions[0] : null;
                $height = is_numeric($dimensions[1]) ? (int) $dimensions[1] : null;
            }
        }

        $kind = 'other';
        if (str_starts_with($mimeType, 'image/')) {
            $kind = 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $kind = 'video';
        }

        return [
            'name' => $name,
            'path' => $relativePath,
            'src' => $this->publicUrlForRelativePath($relativePath),
            'mime' => $mimeType,
            'extension' => $extension,
            'sizeBytes' => $sizeBytes,
            'sizeLabel' => $this->bytesToLabel($sizeBytes),
            'mtime' => @filemtime($absolutePath) ?: 0,
            'width' => $width,
            'height' => $height,
            'dimensionsLabel' => is_int($width) && is_int($height) ? sprintf('%dx%d', $width, $height) : 'N/A',
            'kind' => $kind,
            'canConvertToWebp' => $kind === 'image' && $mimeType !== 'image/webp' && $this->canEncodeWebp(),
        ];
    }

    /**
     * @param array<string, mixed> $file
     * @return array{status: string, error?: string}
     */
    private function persistUploadedFile(
        array $file,
        string $targetDirectory,
        bool $autoWebp,
        int $maxWidth,
        int $maxHeight,
        int $quality
    ): array {
        $errorCode = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return ['status' => 'skipped'];
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            return ['status' => 'error', 'error' => $this->uploadErrorMessage($errorCode)];
        }

        $tmpName = is_string($file['tmp_name'] ?? null) ? trim((string) $file['tmp_name']) : '';
        if ($tmpName === '' || !is_file($tmpName)) {
            return ['status' => 'error', 'error' => 'Fichier temporaire introuvable.'];
        }

        $size = is_numeric($file['size'] ?? null) ? (int) $file['size'] : (int) filesize($tmpName);
        if ($size <= 0) {
            return ['status' => 'error', 'error' => 'Fichier vide.'];
        }

        if ($size > $this->maxUploadBytes) {
            return [
                'status' => 'error',
                'error' => sprintf('Fichier trop volumineux (max %d Mo).', max(1, (int) floor($this->maxUploadBytes / 1048576))),
            ];
        }

        return $this->persistBinaryFile($tmpName, (string) ($file['name'] ?? ''), $targetDirectory, $autoWebp, $maxWidth, $maxHeight, $quality);
    }

    /**
     * @return array{status: string, error?: string}
     */
    private function persistExtractedFile(
        string $temporaryPath,
        string $originalName,
        string $targetDirectory,
        bool $autoWebp,
        int $maxWidth,
        int $maxHeight,
        int $quality
    ): array {
        if (!is_file($temporaryPath)) {
            return ['status' => 'error', 'error' => 'Fichier extrait introuvable.'];
        }

        return $this->persistBinaryFile($temporaryPath, $originalName, $targetDirectory, $autoWebp, $maxWidth, $maxHeight, $quality);
    }

    /**
     * @return array{status: string, error?: string}
     */
    private function persistBinaryFile(
        string $sourcePath,
        string $originalName,
        string $targetDirectory,
        bool $autoWebp,
        int $maxWidth,
        int $maxHeight,
        int $quality
    ): array {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = strtolower((string) $finfo->file($sourcePath));
        $filenameBase = $this->sanitizePathSegment((string) pathinfo($originalName, PATHINFO_FILENAME));
        if ($filenameBase === '') {
            $filenameBase = 'media';
        }

        if (isset(self::ALLOWED_IMAGE_MIME_TO_EXTENSION[$mimeType])) {
            if ($autoWebp && $this->canEncodeWebp()) {
                $targetFilename = $this->buildUniqueFilename($filenameBase, 'webp');
                $targetPath = rtrim($targetDirectory, '/\\') . DIRECTORY_SEPARATOR . $targetFilename;

                $converted = $this->convertImageFileToWebp(
                    $sourcePath,
                    $mimeType,
                    $targetPath,
                    $maxWidth,
                    $maxHeight,
                    $quality
                );
                if (!$converted['success']) {
                    return ['status' => 'error', 'error' => (string) ($converted['error'] ?? 'Conversion WebP impossible.')];
                }

                return ['status' => 'converted'];
            }

            $extension = (string) self::ALLOWED_IMAGE_MIME_TO_EXTENSION[$mimeType];
            $targetFilename = $this->buildUniqueFilename($filenameBase, $extension);
            $targetPath = rtrim($targetDirectory, '/\\') . DIRECTORY_SEPARATOR . $targetFilename;
            $copied = @copy($sourcePath, $targetPath);
            if (!$copied) {
                return ['status' => 'error', 'error' => 'Impossible de copier limage importee.'];
            }

            @chmod($targetPath, 0644);

            return ['status' => 'stored'];
        }

        if (isset(self::ALLOWED_VIDEO_MIME_TO_EXTENSION[$mimeType])) {
            $extension = (string) self::ALLOWED_VIDEO_MIME_TO_EXTENSION[$mimeType];
            $targetFilename = $this->buildUniqueFilename($filenameBase, $extension);
            $targetPath = rtrim($targetDirectory, '/\\') . DIRECTORY_SEPARATOR . $targetFilename;
            $copied = @copy($sourcePath, $targetPath);
            if (!$copied) {
                return ['status' => 'error', 'error' => 'Impossible de copier la video importee.'];
            }

            @chmod($targetPath, 0644);

            return ['status' => 'stored'];
        }

        return ['status' => 'skipped'];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private function convertImageFileToWebp(
        string $sourcePath,
        string $mimeType,
        string $targetPath,
        int $maxWidth,
        int $maxHeight,
        int $quality
    ): array {
        $imageResource = $this->createImageResourceFromFile($sourcePath, $mimeType);
        if (!$this->isGdImageResource($imageResource)) {
            return [
                'success' => false,
                'error' => 'Image source illisible pour conversion WebP.',
            ];
        }

        $sourceWidth = max(1, (int) imagesx($imageResource));
        $sourceHeight = max(1, (int) imagesy($imageResource));
        [$targetWidth, $targetHeight] = $this->fitInsideBox($sourceWidth, $sourceHeight, $maxWidth, $maxHeight);

        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($targetImage === false) {
            $this->destroyGdImage($imageResource);

            return [
                'success' => false,
                'error' => 'Preparation image impossible.',
            ];
        }

        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        $resampled = imagecopyresampled(
            $targetImage,
            $imageResource,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        if (!$resampled) {
            imagedestroy($targetImage);
            $this->destroyGdImage($imageResource);

            return [
                'success' => false,
                'error' => 'Redimensionnement impossible.',
            ];
        }

        $normalizedQuality = max(30, min(100, $quality));
        $written = imagewebp($targetImage, $targetPath, $normalizedQuality);
        imagedestroy($targetImage);
        $this->destroyGdImage($imageResource);

        if (!$written) {
            return [
                'success' => false,
                'error' => 'Ecriture du WebP impossible.',
            ];
        }

        @chmod($targetPath, 0644);

        return ['success' => true];
    }

    private function canEncodeWebp(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagewebp');
    }

    /**
     * @return \GdImage|false|null
     */
    private function createImageResourceFromFile(string $filePath, string $mimeType): mixed
    {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png' => @imagecreatefrompng($filePath),
            'image/gif' => @imagecreatefromgif($filePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : null,
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($filePath) : null,
            default => null,
        };
    }

    private function isGdImageResource(mixed $value): bool
    {
        return $value instanceof \GdImage || is_resource($value);
    }

    private function destroyGdImage(mixed $resource): void
    {
        if ($this->isGdImageResource($resource)) {
            imagedestroy($resource);
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fitInsideBox(int $sourceWidth, int $sourceHeight, int $maxWidth, int $maxHeight): array
    {
        $sourceWidth = max(1, $sourceWidth);
        $sourceHeight = max(1, $sourceHeight);
        $maxWidth = max(1, min(8192, $maxWidth));
        $maxHeight = max(1, min(8192, $maxHeight));

        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1.0);

        return [
            max(1, (int) round($sourceWidth * $ratio)),
            max(1, (int) round($sourceHeight * $ratio)),
        ];
    }

    private function buildUniqueFilename(string $baseName, string $extension): string
    {
        return sprintf(
            '%s-%s-%s.%s',
            $baseName,
            date('Ymd-His'),
            bin2hex(random_bytes(4)),
            strtolower($extension)
        );
    }

    private function sanitizeArchiveEntryPath(string $entryPath): ?string
    {
        $normalized = str_replace('\\', '/', trim($entryPath));
        if ($normalized === '') {
            return null;
        }

        $parts = [];
        $segments = explode('/', $normalized);
        $lastIndex = count($segments) - 1;
        foreach ($segments as $index => $segment) {
            $segment = trim((string) $segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }

            if ($index === $lastIndex) {
                $safe = $this->sanitizeFilename($segment);
            } else {
                $safe = $this->sanitizePathSegment($segment);
            }

            if ($safe === '') {
                continue;
            }

            $parts[] = $safe;
        }

        if ($parts === []) {
            return null;
        }

        return implode('/', $parts);
    }

    private function countDirectoryItems(string $absoluteDirectory): int
    {
        $count = 0;
        foreach (scandir($absoluteDirectory) ?: [] as $name) {
            if (!is_string($name) || $name === '.' || $name === '..') {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function bytesToLabel(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = max(0, (float) $bytes);
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        if ($unitIndex === 0) {
            return (string) ((int) $value) . ' ' . $units[$unitIndex];
        }

        return number_format($value, 1, '.', ' ') . ' ' . $units[$unitIndex];
    }

    private function publicUrlForRelativePath(string $relativePath): string
    {
        $normalized = trim(str_replace('\\', '/', $relativePath), '/');
        if ($normalized === '') {
            return self::ROOT_RELATIVE_PATH;
        }

        return self::ROOT_RELATIVE_PATH . '/' . $normalized;
    }

    private function removeDirectoryRecursively(string $directory): bool
    {
        if (!is_dir($directory)) {
            return true;
        }

        $entries = scandir($directory);
        if (!is_array($entries)) {
            return false;
        }

        foreach ($entries as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                if (!$this->removeDirectoryRecursively($path)) {
                    return false;
                }
                continue;
            }

            if (!@unlink($path)) {
                return false;
            }
        }

        return @rmdir($directory);
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier depasse la taille autorisee.',
            UPLOAD_ERR_PARTIAL => 'Le fichier na ete transfere que partiellement.',
            UPLOAD_ERR_NO_TMP_DIR => 'Le serveur ne dispose pas de dossier temporaire.',
            UPLOAD_ERR_CANT_WRITE => 'Le serveur ne peut pas ecrire le fichier.',
            UPLOAD_ERR_EXTENSION => 'Le transfert a ete bloque par une extension PHP.',
            default => 'Erreur technique pendant le transfert du fichier.',
        };
    }
}
