<?php
$mediaView = is_array($mediaView ?? null) ? $mediaView : [];
$currentFolder = is_string($mediaView['currentFolder'] ?? null) ? (string) $mediaView['currentFolder'] : '';
$parentFolder = is_string($mediaView['parentFolder'] ?? null) ? (string) $mediaView['parentFolder'] : '';
$breadcrumbs = is_array($mediaView['breadcrumbs'] ?? null) ? $mediaView['breadcrumbs'] : [];
$directories = is_array($mediaView['directories'] ?? null) ? $mediaView['directories'] : [];
$files = is_array($mediaView['files'] ?? null) ? $mediaView['files'] : [];
$hasGdWebp = !empty($mediaView['hasGdWebp']);
$maxUploadMb = max(1, (int) ($mediaView['maxUploadMegabytes'] ?? 60));
$maxArchiveMb = max(1, (int) ($mediaView['maxArchiveMegabytes'] ?? 200));
$allowedImageFormats = is_string($mediaView['allowedImageFormats'] ?? null) ? (string) $mediaView['allowedImageFormats'] : '';
$allowedVideoFormats = is_string($mediaView['allowedVideoFormats'] ?? null) ? (string) $mediaView['allowedVideoFormats'] : '';
$folderSizeLabel = is_string($mediaView['folderSizeLabel'] ?? null) ? (string) $mediaView['folderSizeLabel'] : '0 B';
$directoryCount = max(0, (int) ($mediaView['directoryCount'] ?? 0));
$fileCount = max(0, (int) ($mediaView['fileCount'] ?? 0));
$directoryCountTotal = max($directoryCount, (int) ($mediaView['directoryCountTotal'] ?? $directoryCount));
$fileCountTotal = max($fileCount, (int) ($mediaView['fileCountTotal'] ?? $fileCount));
$folderOptions = is_array($mediaView['folderOptions'] ?? null) ? $mediaView['folderOptions'] : [''];
$filters = is_array($mediaView['filters'] ?? null)
    ? $mediaView['filters']
    : [
        'q' => '',
        'type' => 'all',
        'min_size_kb' => null,
        'max_size_kb' => null,
        'date_from' => '',
        'date_to' => '',
        'sort' => 'name_asc',
        'hasActiveFilters' => false,
    ];
$hasActiveFilters = !empty($filters['hasActiveFilters']);
$filterQuery = is_string($filters['q'] ?? null) ? (string) $filters['q'] : '';
$filterType = is_string($filters['type'] ?? null) ? (string) $filters['type'] : 'all';
$filterMinSizeKb = is_numeric($filters['min_size_kb'] ?? null) ? (string) ((int) $filters['min_size_kb']) : '';
$filterMaxSizeKb = is_numeric($filters['max_size_kb'] ?? null) ? (string) ((int) $filters['max_size_kb']) : '';
$filterDateFrom = is_string($filters['date_from'] ?? null) ? (string) $filters['date_from'] : '';
$filterDateTo = is_string($filters['date_to'] ?? null) ? (string) $filters['date_to'] : '';
$filterSort = is_string($filters['sort'] ?? null) && (string) $filters['sort'] !== '' ? (string) $filters['sort'] : 'name_asc';
$mediaUrl = is_string($adminMediaUrl ?? null) && (string) $adminMediaUrl !== ''
    ? (string) $adminMediaUrl
    : admin_url('media');
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$filterFields = ['q', 'type', 'min_size_kb', 'max_size_kb', 'date_from', 'date_to', 'sort'];
$renderFilterHiddenInputs = static function (array $activeFilters) use ($escape, $filterFields): void {
    foreach ($filterFields as $name) {
        $value = $activeFilters[$name] ?? '';
        if ($value === null) {
            $value = '';
        }

        echo '<input type="hidden" name="filters[' . $escape($name) . ']" value="' . $escape((string) $value) . '" />';
    }
};
$folderLabel = static fn (string $folder): string => $folder === '' ? 'Racine' : $folder;
$mediaUrlWithFolder = static function (string $folder, bool $withFilters = true) use ($mediaUrl, $filters): string {
    $params = [];
    $normalizedFolder = trim($folder);
    if ($normalizedFolder !== '') {
        $params['folder'] = $normalizedFolder;
    }

    if ($withFilters) {
        foreach (['q', 'type', 'min_size_kb', 'max_size_kb', 'date_from', 'date_to', 'sort'] as $field) {
            $value = $filters[$field] ?? null;
            if ($value === null) {
                continue;
            }

            if ($field === 'sort') {
                $sortValue = is_string($value) ? trim($value) : '';
                if ($sortValue === '' || $sortValue === 'name_asc') {
                    continue;
                }

                $params[$field] = $sortValue;
                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    continue;
                }

                $params[$field] = $trimmed;
                continue;
            }

            if (is_numeric($value)) {
                $params[$field] = (string) $value;
            }
        }
    }

    if ($params === []) {
        return $mediaUrl;
    }

    return $mediaUrl . '?' . http_build_query($params);
};
$sortLabels = [
    'name_asc' => 'Nom (A-Z)',
    'name_desc' => 'Nom (Z-A)',
    'date_desc' => 'Date (plus recent)',
    'date_asc' => 'Date (plus ancien)',
    'size_desc' => 'Taille (plus grande)',
    'size_asc' => 'Taille (plus petite)',
    'type_asc' => 'Type (image/video/autre)',
];
?>

<section class="card">
  <h2>Bibliotheque medias</h2>
  <p>
    Gestion centralisee des contenus images et videos: import manuel, import ZIP, export de dossier,
    organisation en dossiers/sous-dossiers, controle des formats et conversion automatique en
    <code>WebP</code>.
  </p>
  <div class="actions-inline">
    <a class="button-link button-link-muted" href="<?php echo $escape($mediaUrlWithFolder('')); ?>">Racine</a>
    <?php if ($parentFolder !== ''): ?>
    <a class="button-link button-link-muted" href="<?php echo $escape($mediaUrlWithFolder($parentFolder)); ?>">Dossier parent</a>
    <?php endif; ?>
  </div>
</section>

<?php if (($message ?? null) !== null): ?>
<div class="notice notice-success"><?php echo $escape((string) $message); ?></div>
<?php endif; ?>

<?php if (($error ?? null) !== null): ?>
<div class="notice notice-error"><?php echo $escape((string) $error); ?></div>
<?php endif; ?>

<section class="card media-manager-breadcrumbs-card">
  <h2>Dossier courant</h2>
  <p class="media-manager-breadcrumbs">
    <?php foreach ($breadcrumbs as $index => $crumb): ?>
    <?php
    $crumb = is_array($crumb) ? $crumb : [];
    $crumbLabel = is_string($crumb['label'] ?? null) ? (string) $crumb['label'] : 'Dossier';
    $crumbFolder = is_string($crumb['folder'] ?? null) ? (string) $crumb['folder'] : '';
    $isLast = $index === (count($breadcrumbs) - 1);
    ?>
    <?php if ($isLast): ?>
    <strong><?php echo $escape($crumbLabel); ?></strong>
    <?php else: ?>
    <a href="<?php echo $escape($mediaUrlWithFolder($crumbFolder)); ?>"><?php echo $escape($crumbLabel); ?></a>
    <span>/</span>
    <?php endif; ?>
    <?php endforeach; ?>
  </p>
  <p class="notice-muted">
    <?php echo $directoryCount; ?> dossier(s) · <?php echo $fileCount; ?> fichier(s) · <?php echo $escape($folderSizeLabel); ?>
  </p>
</section>

<section class="card media-manager-filters-card">
  <h2>Recherche et filtres</h2>

  <form class="admin-form-grid media-manager-filters-grid" method="get" action="<?php echo $escape($mediaUrl); ?>">
    <input type="hidden" name="folder" value="<?php echo $escape($currentFolder); ?>" />

    <div class="field media-manager-filters-search">
      <label for="media-filter-q">Nom / chemin</label>
      <input id="media-filter-q" name="q" type="text" value="<?php echo $escape($filterQuery); ?>" placeholder="photo, video, evenement-2026" />
    </div>

    <div class="field">
      <label for="media-filter-type">Type</label>
      <select id="media-filter-type" name="type">
        <option value="all"<?php echo $filterType === 'all' ? ' selected' : ''; ?>>Tous</option>
        <option value="folder"<?php echo $filterType === 'folder' ? ' selected' : ''; ?>>Dossiers</option>
        <option value="image"<?php echo $filterType === 'image' ? ' selected' : ''; ?>>Images</option>
        <option value="video"<?php echo $filterType === 'video' ? ' selected' : ''; ?>>Videos</option>
        <option value="other"<?php echo $filterType === 'other' ? ' selected' : ''; ?>>Autres</option>
      </select>
    </div>

    <div class="field">
      <label for="media-filter-min-size">Taille min (KB)</label>
      <input id="media-filter-min-size" name="min_size_kb" type="number" min="0" step="1" value="<?php echo $escape($filterMinSizeKb); ?>" />
    </div>

    <div class="field">
      <label for="media-filter-max-size">Taille max (KB)</label>
      <input id="media-filter-max-size" name="max_size_kb" type="number" min="0" step="1" value="<?php echo $escape($filterMaxSizeKb); ?>" />
    </div>

    <div class="field">
      <label for="media-filter-date-from">Date debut</label>
      <input id="media-filter-date-from" name="date_from" type="date" value="<?php echo $escape($filterDateFrom); ?>" />
    </div>

    <div class="field">
      <label for="media-filter-date-to">Date fin</label>
      <input id="media-filter-date-to" name="date_to" type="date" value="<?php echo $escape($filterDateTo); ?>" />
    </div>

    <div class="field">
      <label for="media-filter-sort">Tri</label>
      <select id="media-filter-sort" name="sort">
        <?php foreach ($sortLabels as $sortValue => $sortLabel): ?>
        <option value="<?php echo $escape((string) $sortValue); ?>"<?php echo $filterSort === $sortValue ? ' selected' : ''; ?>>
          <?php echo $escape((string) $sortLabel); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="actions-inline media-manager-filters-actions">
      <a class="button-link button-link-muted" href="<?php echo $escape($mediaUrlWithFolder($currentFolder, false)); ?>">Reinitialiser</a>
      <button type="submit">Filtrer</button>
    </div>
  </form>

  <p class="notice-muted">
    Affichage: <?php echo $directoryCount; ?>/<?php echo $directoryCountTotal; ?> dossier(s) ·
    <?php echo $fileCount; ?>/<?php echo $fileCountTotal; ?> fichier(s)
    <?php if ($hasActiveFilters): ?>
    (filtres actifs)
    <?php endif; ?>
  </p>
</section>

<section class="cards-grid media-manager-top-grid">
  <article class="card">
    <h2>Creer un dossier</h2>
    <form method="post" action="<?php echo $escape($mediaUrlWithFolder($currentFolder)); ?>" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
      <input type="hidden" name="media_action" value="create_folder" />
      <input type="hidden" name="folder" value="<?php echo $escape($currentFolder); ?>" />
      <?php $renderFilterHiddenInputs($filters); ?>

      <div class="field">
        <label for="media-new-folder-name">Nom du dossier</label>
        <input id="media-new-folder-name" name="new_folder_name" type="text" placeholder="evenements-2026" required />
      </div>

      <div class="actions-inline actions-inline-end">
        <button type="submit">Creer le dossier</button>
      </div>
    </form>
  </article>

  <article class="card">
    <h2>Importer des fichiers</h2>
    <form method="post" action="<?php echo $escape($mediaUrlWithFolder($currentFolder)); ?>" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
      <input type="hidden" name="media_action" value="upload" />
      <input type="hidden" name="folder" value="<?php echo $escape($currentFolder); ?>" />
      <?php $renderFilterHiddenInputs($filters); ?>

      <div class="field">
        <label for="media-files-input">Images / videos</label>
        <input
          id="media-files-input"
          name="media_files[]"
          type="file"
          multiple
          accept="image/jpeg,image/png,image/webp,image/gif,image/avif,video/mp4,video/webm,video/quicktime,video/ogg,video/x-m4v"
        />
      </div>

      <div class="admin-form-grid admin-form-grid-3">
        <div class="field">
          <label class="checkbox-field" for="media-upload-auto-webp">
            <input type="hidden" name="upload_auto_webp" value="0" />
            <input id="media-upload-auto-webp" name="upload_auto_webp" type="checkbox" value="1" checked />
            Conversion auto en WebP (images)
          </label>
        </div>
        <div class="field">
          <label for="media-upload-max-width">Largeur max</label>
          <input id="media-upload-max-width" name="upload_max_width" type="number" min="320" max="8192" value="2560" />
        </div>
        <div class="field">
          <label for="media-upload-max-height">Hauteur max</label>
          <input id="media-upload-max-height" name="upload_max_height" type="number" min="320" max="8192" value="2560" />
        </div>
        <div class="field field-compact">
          <label for="media-upload-quality">Qualite WebP</label>
          <input id="media-upload-quality" name="upload_quality" type="number" min="30" max="100" value="82" />
        </div>
      </div>

      <p class="notice-muted">
        Formats image: <?php echo $escape($allowedImageFormats); ?> ·
        formats video: <?php echo $escape($allowedVideoFormats); ?> ·
        taille max fichier: <?php echo $maxUploadMb; ?> Mo.
      </p>

      <div class="actions-inline actions-inline-end">
        <button type="submit">Importer les fichiers</button>
      </div>
    </form>
  </article>

  <article class="card">
    <h2>Import / Export ZIP</h2>
    <form method="post" action="<?php echo $escape($mediaUrlWithFolder($currentFolder)); ?>" enctype="multipart/form-data" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
      <input type="hidden" name="media_action" value="import_zip" />
      <input type="hidden" name="folder" value="<?php echo $escape($currentFolder); ?>" />
      <?php $renderFilterHiddenInputs($filters); ?>

      <div class="field">
        <label for="media-zip-input">Archive ZIP</label>
        <input id="media-zip-input" name="media_zip_file" type="file" accept=".zip,application/zip" />
      </div>

      <div class="admin-form-grid admin-form-grid-2">
        <div class="field">
          <label class="checkbox-field" for="media-import-auto-webp">
            <input type="hidden" name="upload_auto_webp" value="0" />
            <input id="media-import-auto-webp" name="upload_auto_webp" type="checkbox" value="1" checked />
            Conversion auto en WebP
          </label>
        </div>
        <div class="field field-compact">
          <label for="media-import-quality">Qualite WebP</label>
          <input id="media-import-quality" name="upload_quality" type="number" min="30" max="100" value="82" />
        </div>
      </div>
      <input type="hidden" name="upload_max_width" value="2560" />
      <input type="hidden" name="upload_max_height" value="2560" />

      <p class="notice-muted">Taille max archive: <?php echo $maxArchiveMb; ?> Mo.</p>

      <div class="actions-inline actions-inline-end">
        <button type="submit">Importer le ZIP</button>
      </div>
    </form>

    <form method="post" action="<?php echo $escape($mediaUrlWithFolder($currentFolder)); ?>">
      <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
      <input type="hidden" name="media_action" value="export_folder" />
      <input type="hidden" name="folder" value="<?php echo $escape($currentFolder); ?>" />
      <?php $renderFilterHiddenInputs($filters); ?>

      <div class="actions-inline actions-inline-end">
        <button type="submit" class="button-muted">Exporter ce dossier (ZIP)</button>
      </div>
    </form>
  </article>
</section>

<section class="cards-grid media-manager-content-grid">
  <article class="card">
    <h2>Sous-dossiers</h2>

    <?php if ($directories === []): ?>
    <p class="notice-muted">Aucun sous-dossier dans cet emplacement.</p>
    <?php else: ?>
    <ul class="media-manager-folder-list">
      <?php foreach ($directories as $directory): ?>
      <?php
      $directory = is_array($directory) ? $directory : [];
      $directoryName = is_string($directory['name'] ?? null) ? (string) $directory['name'] : '';
      $directoryFolder = is_string($directory['folder'] ?? null) ? (string) $directory['folder'] : '';
      $directoryCountItems = max(0, (int) ($directory['itemCount'] ?? 0));
      $directoryMtime = is_numeric($directory['mtime'] ?? null) ? (int) $directory['mtime'] : 0;
      $directoryModifiedLabel = $directoryMtime > 0 ? date('d/m/Y H:i', $directoryMtime) : 'N/A';
      $destinationFolders = [];
      foreach ($folderOptions as $folderOption) {
          if (!is_string($folderOption)) {
              continue;
          }

          $folderOption = trim($folderOption);
          $isDescendantOption = $folderOption !== '' && str_starts_with($folderOption . '/', $directoryFolder . '/');
          if ($folderOption === $directoryFolder || $isDescendantOption) {
              continue;
          }

          $destinationFolders[] = $folderOption;
      }
      ?>
      <li class="media-manager-folder-item">
        <div class="media-manager-folder-head">
          <a href="<?php echo $escape($mediaUrlWithFolder($directoryFolder)); ?>">
            <strong><?php echo $escape($directoryName); ?></strong>
            <span><?php echo $directoryCountItems; ?> element(s) · modifie le <?php echo $escape($directoryModifiedLabel); ?></span>
          </a>
        </div>

        <div class="media-manager-item-forms">
          <form method="post" action="<?php echo $escape($mediaUrlWithFolder($currentFolder)); ?>" class="media-manager-inline-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
            <input type="hidden" name="media_action" value="rename_folder" />
            <input type="hidden" name="folder" value="<?php echo $escape($currentFolder); ?>" />
            <input type="hidden" name="target_folder" value="<?php echo $escape($directoryFolder); ?>" />
            <?php $renderFilterHiddenInputs($filters); ?>
            <input name="new_folder_name" type="text" value="<?php echo $escape($directoryName); ?>" required />
            <button type="submit" class="button-small">Renommer</button>
          </form>

          <?php if ($destinationFolders !== []): ?>
          <form method="post" action="<?php echo $escape($mediaUrlWithFolder($currentFolder)); ?>" class="media-manager-inline-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
            <input type="hidden" name="media_action" value="move_folder" />
            <input type="hidden" name="folder" value="<?php echo $escape($currentFolder); ?>" />
            <input type="hidden" name="target_folder" value="<?php echo $escape($directoryFolder); ?>" />
            <?php $renderFilterHiddenInputs($filters); ?>
            <select name="destination_folder">
              <?php foreach ($destinationFolders as $destinationFolder): ?>
              <option value="<?php echo $escape($destinationFolder); ?>"<?php echo $destinationFolder === $currentFolder ? ' selected' : ''; ?>>
                <?php echo $escape($folderLabel($destinationFolder)); ?>
              </option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="button-small button-muted">Deplacer</button>
          </form>
          <?php endif; ?>

          <form method="post" action="<?php echo $escape($mediaUrlWithFolder($currentFolder)); ?>" class="media-manager-inline-form">
            <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
            <input type="hidden" name="media_action" value="delete_folder" />
            <input type="hidden" name="folder" value="<?php echo $escape($currentFolder); ?>" />
            <input type="hidden" name="target_folder" value="<?php echo $escape($directoryFolder); ?>" />
            <?php $renderFilterHiddenInputs($filters); ?>
            <button type="submit" class="button-danger button-small" onclick="return confirm('Supprimer ce dossier et tout son contenu ?');">Supprimer</button>
          </form>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </article>

  <article class="card">
    <h2>Fichiers</h2>

    <?php if ($files === []): ?>
    <p class="notice-muted">Aucun fichier dans ce dossier.</p>
    <?php else: ?>
    <div class="table-shell">
      <table class="admin-table media-manager-table">
        <thead>
          <tr>
            <th>Apercu</th>
            <th>Fichier</th>
            <th>Format</th>
            <th>Taille</th>
            <th>Dimensions</th>
            <th>Modifie le</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($files as $file): ?>
          <?php
          $file = is_array($file) ? $file : [];
          $name = is_string($file['name'] ?? null) ? (string) $file['name'] : '';
          $path = is_string($file['path'] ?? null) ? (string) $file['path'] : '';
          $src = is_string($file['src'] ?? null) ? (string) $file['src'] : '';
          $mime = is_string($file['mime'] ?? null) ? (string) $file['mime'] : '';
          $extension = is_string($file['extension'] ?? null) ? (string) $file['extension'] : '';
          $sizeLabel = is_string($file['sizeLabel'] ?? null) ? (string) $file['sizeLabel'] : '0 B';
          $dimensionsLabel = is_string($file['dimensionsLabel'] ?? null) ? (string) $file['dimensionsLabel'] : 'N/A';
          $kind = is_string($file['kind'] ?? null) ? (string) $file['kind'] : 'other';
          $canConvertToWebp = !empty($file['canConvertToWebp']);
          $mtime = is_numeric($file['mtime'] ?? null) ? (int) $file['mtime'] : 0;
          $modifiedLabel = $mtime > 0 ? date('d/m/Y H:i', $mtime) : 'N/A';
          ?>
          <tr>
            <td>
              <?php if ($kind === 'image'): ?>
              <img class="media-manager-thumb" src="<?php echo $escape($src); ?>" alt="<?php echo $escape($name); ?>" loading="lazy" />
              <?php elseif ($kind === 'video'): ?>
              <video class="media-manager-thumb" src="<?php echo $escape($src); ?>" preload="metadata" muted></video>
              <?php else: ?>
              <span class="tag">Fichier</span>
              <?php endif; ?>
            </td>
            <td>
              <strong><?php echo $escape($name); ?></strong><br />
              <code><?php echo $escape($src); ?></code>
            </td>
            <td>
              <?php echo $escape(strtoupper($extension)); ?><br />
              <small><?php echo $escape($mime); ?></small>
            </td>
            <td><?php echo $escape($sizeLabel); ?></td>
            <td><?php echo $escape($dimensionsLabel); ?></td>
            <td><?php echo $escape($modifiedLabel); ?></td>
            <td>
              <div class="actions-inline media-manager-row-actions">
                <a class="button-link button-link-muted button-small" href="<?php echo $escape($src); ?>" target="_blank" rel="noopener noreferrer">Ouvrir</a>

                <form method="post" action="<?php echo $escape($mediaUrlWithFolder($currentFolder)); ?>" class="media-manager-inline-form" autocomplete="off">
                  <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
                  <input type="hidden" name="media_action" value="rename_file" />
                  <input type="hidden" name="folder" value="<?php echo $escape($currentFolder); ?>" />
                  <input type="hidden" name="target_file" value="<?php echo $escape($path); ?>" />
                  <?php $renderFilterHiddenInputs($filters); ?>
                  <input name="new_file_name" type="text" value="<?php echo $escape($name); ?>" required />
                  <button type="submit" class="button-small">Renommer</button>
                </form>

                <form method="post" action="<?php echo $escape($mediaUrlWithFolder($currentFolder)); ?>" class="media-manager-inline-form" autocomplete="off">
                  <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
                  <input type="hidden" name="media_action" value="move_file" />
                  <input type="hidden" name="folder" value="<?php echo $escape($currentFolder); ?>" />
                  <input type="hidden" name="target_file" value="<?php echo $escape($path); ?>" />
                  <?php $renderFilterHiddenInputs($filters); ?>
                  <select name="destination_folder">
                    <?php foreach ($folderOptions as $destinationFolder): ?>
                    <?php if (!is_string($destinationFolder)): ?>
                    <?php continue; ?>
                    <?php endif; ?>
                    <?php $destinationFolder = trim($destinationFolder); ?>
                    <option value="<?php echo $escape($destinationFolder); ?>"<?php echo $destinationFolder === $currentFolder ? ' selected' : ''; ?>>
                      <?php echo $escape($folderLabel($destinationFolder)); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="button-small button-muted">Deplacer</button>
                </form>

                <?php if ($canConvertToWebp && $hasGdWebp): ?>
                <form method="post" action="<?php echo $escape($mediaUrlWithFolder($currentFolder)); ?>">
                  <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
                  <input type="hidden" name="media_action" value="convert_file_webp" />
                  <input type="hidden" name="folder" value="<?php echo $escape($currentFolder); ?>" />
                  <input type="hidden" name="target_file" value="<?php echo $escape($path); ?>" />
                  <input type="hidden" name="upload_max_width" value="2560" />
                  <input type="hidden" name="upload_max_height" value="2560" />
                  <input type="hidden" name="upload_quality" value="82" />
                  <?php $renderFilterHiddenInputs($filters); ?>
                  <button type="submit" class="button-small">Convertir WebP</button>
                </form>
                <?php endif; ?>

                <form method="post" action="<?php echo $escape($mediaUrlWithFolder($currentFolder)); ?>">
                  <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
                  <input type="hidden" name="media_action" value="delete_file" />
                  <input type="hidden" name="folder" value="<?php echo $escape($currentFolder); ?>" />
                  <input type="hidden" name="target_file" value="<?php echo $escape($path); ?>" />
                  <?php $renderFilterHiddenInputs($filters); ?>
                  <button type="submit" class="button-danger button-small" onclick="return confirm('Supprimer ce fichier ?');">Supprimer</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </article>
</section>
