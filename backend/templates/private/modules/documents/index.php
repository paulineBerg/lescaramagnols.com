<?php
$translate = static function (string $key, string $fallback): string {
    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
        return $fallback;
    }

    return $translated;
};

$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$privateDocumentsEnabled = is_bool($privateDocumentsEnabled ?? null) ? (bool) $privateDocumentsEnabled : false;
$privateDocuments = is_array($privateDocuments ?? null) ? $privateDocuments : [];
$privateDocumentCategories = is_array($privateDocumentCategories ?? null) ? $privateDocumentCategories : [];
$privateDocumentsUploadUrl = is_string($privateDocumentsUploadUrl ?? null) ? (string) $privateDocumentsUploadUrl : private_portal_url('files_upload');
$privateDocumentCategoriesUrl = is_string($privateDocumentCategoriesUrl ?? null) ? (string) $privateDocumentCategoriesUrl : private_portal_url('files_categories');
$privateDocumentUploadCsrfToken = is_string($privateDocumentUploadCsrfToken ?? null)
    ? (string) $privateDocumentUploadCsrfToken
    : '';
$privateDocumentCategoryColors = is_array($viewModel['privateDocumentCategoryColors'] ?? null)
    ? $viewModel['privateDocumentCategoryColors']
    : ['#ffffff', '#fff1d6', '#ffe0e0', '#e1f7d5', '#d6ecff', '#eadbff', '#ffdff3'];
$privateDocumentCategoryDefaultColor = is_string($viewModel['privateDocumentCategoryDefaultColor'] ?? null)
    ? (string) $viewModel['privateDocumentCategoryDefaultColor']
    : '#ffffff';
$privateDocumentColorClass = static function (mixed $value, string $default = '#ffffff'): string {
    $allowedColors = ['#ffffff', '#fff1d6', '#ffe0e0', '#e1f7d5', '#d6ecff', '#eadbff', '#ffdff3'];
    $normalized = strtolower(trim((string) $value));
    if (!in_array($normalized, $allowedColors, true)) {
        $normalized = strtolower(trim($default));
    }
    if (!in_array($normalized, $allowedColors, true)) {
        $normalized = '#ffffff';
    }

    return 'private-color-' . ltrim($normalized, '#');
};
$privateFilesBaseUrl = trim((string) ($privateFilesBaseUrl ?? ''));
if ($privateFilesBaseUrl === '') {
    $privateFilesBaseUrl = private_portal_url('files');
}

$formatBytes = static function (int $size): string {
    if ($size <= 0) {
        return '0 o';
    }

    $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
    $value = (float) $size;
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        ++$unitIndex;
    }

    if ($unitIndex === 0) {
        return (string) (int) $value . ' ' . $units[$unitIndex];
    }

    return number_format($value, 1, '.', ' ') . ' ' . $units[$unitIndex];
};
$privateDocumentScanLabel = static function (string $status) use ($translate): string {
    return match ($status) {
        'pending_scan' => $translate('TXT_PRIVATE_DOCUMENT_SCAN_PENDING', 'En attente de scan'),
        'infected' => $translate('TXT_PRIVATE_DOCUMENT_SCAN_INFECTED', 'Refusé par antivirus'),
        'scan_unavailable' => $translate('TXT_PRIVATE_DOCUMENT_SCAN_UNAVAILABLE_SHORT', 'Scan indisponible'),
        default => $translate('TXT_PRIVATE_DOCUMENT_SCAN_CLEAN', 'Validé'),
    };
};
$privateDocumentScanClass = static function (string $status): string {
    return match ($status) {
        'pending_scan' => 'private-document-scan-status--pending',
        'infected' => 'private-document-scan-status--infected',
        'scan_unavailable' => 'private-document-scan-status--unavailable',
        default => 'private-document-scan-status--clean',
    };
};
?>
<section class="private-dashboard private-documents-module" data-private-documents-root>

  <section class="card private-card-wide" id="private-documents">
    <span class="tag"><?php echo $h($translate('TXT_PRIVATE_DASHBOARD_FILES_TAG', 'Fichiers')); ?></span>
    <h2><?php echo $h($translate('TXT_PRIVATE_DASHBOARD_DOCUMENTS_TITLE', 'Documents')); ?></h2>

    <?php if ($privateDocumentsEnabled): ?>
      <div class="private-documents-grid">
        <section class="private-documents-panel" id="private-document-categories">
          <h3><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_CATEGORIES_TITLE', 'Catégories')); ?></h3>
          <form method="post" action="<?php echo $h($privateDocumentCategoriesUrl); ?>" id="private-document-category-form">
            <input type="hidden" name="csrf_token" value="<?php echo $h($privateDocumentUploadCsrfToken); ?>" />
            <input type="hidden" name="action" value="save_category" />
            <input type="hidden" name="category_id" value="0" data-private-document-category-id />

            <label for="private-document-category-name"><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_CATEGORY_LABEL', 'Nom de la catégorie')); ?></label>
            <input id="private-document-category-name" type="text" name="category_name" maxlength="80" required data-private-document-category-name />

            <label><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_CATEGORY_COLOR', 'Couleur')); ?></label>
            <div class="private-document-category-color-choices">
              <?php foreach ($privateDocumentCategoryColors as $color): ?>
                <?php $color = is_string($color) ? $color : $privateDocumentCategoryDefaultColor; ?>
                <label class="private-document-category-color-choice">
                  <input type="radio" name="category_color" value="<?php echo $h($color); ?>" <?php echo $color === $privateDocumentCategoryDefaultColor ? 'checked' : ''; ?> />
                  <span class="private-document-category-swatch <?php echo $h($privateDocumentColorClass($color, $privateDocumentCategoryDefaultColor)); ?>"></span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="private-document-upload-actions">
              <button type="submit"><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_CATEGORY_SUBMIT', 'Enregistrer la catégorie')); ?></button>
              <button type="button" class="private-document-button-secondary" data-private-document-category-reset>Nouvelle catégorie</button>
            </div>
          </form>
        </section>

        <section class="private-documents-panel">
          <h3><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_UPLOAD_PANEL_TITLE', 'Ajouter un document')); ?></h3>
          <form method="post" action="<?php echo $h($privateDocumentsUploadUrl); ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $h($privateDocumentUploadCsrfToken); ?>" />
            <label for="private-document-category"><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_CATEGORY_SELECT', 'Catégorie')); ?></label>
            <select id="private-document-category" name="category_id">
              <option value=""><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_CATEGORY_NONE', 'Sans catégorie')); ?></option>
              <?php foreach ($privateDocumentCategories as $category): ?>
                <?php if (!is_array($category) || !is_numeric($category['id'] ?? null)) { continue; } ?>
                <option value="<?php echo (int) $category['id']; ?>">
                  <?php echo $h($category['name'] ?? ''); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <label for="private-document-file"><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_FILE_LABEL', 'Ajouter un document')); ?></label>
            <input id="private-document-file" type="file" name="document_file" required />
            <button type="submit"><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_UPLOAD_SUBMIT', 'Envoyer')); ?></button>
          </form>
        </section>
      </div>

      <?php if ($privateDocumentCategories !== []): ?>
        <section class="private-documents-panel private-block-spaced">
          <h3><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_EXISTING_CATEGORIES', 'Catégories existantes')); ?></h3>
          <div class="private-document-category-list">
            <?php foreach ($privateDocumentCategories as $category): ?>
              <?php if (!is_array($category) || !is_numeric($category['id'] ?? null)) { continue; } ?>
              <?php
              $categoryId = (int) $category['id'];
              $categoryName = is_string($category['name'] ?? null) ? (string) $category['name'] : '';
              $categoryColor = is_string($category['color'] ?? null) && (string) $category['color'] !== '' ? (string) $category['color'] : $privateDocumentCategoryDefaultColor;
              $documentsCount = is_numeric($category['documentsCount'] ?? null) ? (int) $category['documentsCount'] : 0;
              if ($categoryName === '') {
                  continue;
              }
              ?>
              <article class="private-document-category-row <?php echo $h($privateDocumentColorClass($categoryColor, $privateDocumentCategoryDefaultColor)); ?>">
                <h4><span class="private-document-category-dot <?php echo $h($privateDocumentColorClass($categoryColor, $privateDocumentCategoryDefaultColor)); ?>"></span><?php echo $h($categoryName); ?></h4>
                <p class="muted"><?php echo $documentsCount; ?> document(s)</p>
                <div class="private-document-category-actions">
                  <button type="button"
                          class="private-document-button-secondary"
                          data-private-document-category-edit
                          data-category-id="<?php echo $categoryId; ?>"
                          data-category-name="<?php echo $h($categoryName); ?>"
                          data-category-color="<?php echo $h($categoryColor); ?>">Modifier</button>
                  <form method="post" action="<?php echo $h($privateDocumentCategoriesUrl); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $h($privateDocumentUploadCsrfToken); ?>" />
                    <input type="hidden" name="action" value="delete_category" />
                    <input type="hidden" name="category_id" value="<?php echo $categoryId; ?>" />
                    <button type="submit" class="private-document-button-danger">Supprimer</button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    <?php else: ?>
      <p class="muted">
        <?php echo $h($translate('TXT_PRIVATE_DOCUMENT_MODULE_DISABLED', 'Le module documents n’est pas activé pour votre compte.')); ?>
      </p>
    <?php endif; ?>

    <?php if (!$privateDocumentsEnabled || $privateDocuments === []): ?>
      <p class="muted">
        <?php echo $h($translate('TXT_PRIVATE_DASHBOARD_NO_DOCUMENTS', 'Aucun document pour le moment.')); ?>
      </p>
    <?php else: ?>
      <div class="private-documents-table-wrap">
        <table>
          <thead>
            <tr>
              <th><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_TABLE_NAME', 'Nom')); ?></th>
              <th><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_TABLE_CATEGORY', 'Catégorie')); ?></th>
              <th><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_TABLE_SCAN_STATUS', 'État')); ?></th>
              <th><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_TABLE_SIZE', 'Poids')); ?></th>
              <th><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_TABLE_DATE', 'Ajouté le')); ?></th>
              <th><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_TABLE_ACTIONS', 'Actions')); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($privateDocuments as $document): ?>
              <?php if (!is_array($document)) : ?>
                <?php continue; ?>
              <?php endif; ?>
              <?php
              $documentId = is_string($document['documentId'] ?? null) ? trim((string) $document['documentId']) : '';
              $originalName = is_string($document['originalName'] ?? null) ? trim((string) $document['originalName']) : '';
              $categoryName = is_string($document['categoryName'] ?? null) ? trim((string) $document['categoryName']) : '';
              $categoryColor = is_string($document['categoryColor'] ?? null) ? trim((string) $document['categoryColor']) : '';
              $sizeBytes = is_scalar($document['sizeBytes'] ?? null) ? (int) $document['sizeBytes'] : 0;
              $uploadedAtRaw = is_string($document['uploadedAt'] ?? null) ? trim((string) $document['uploadedAt']) : '';
              $scanStatus = is_string($document['scanStatus'] ?? null) ? trim((string) $document['scanStatus']) : 'clean';
              $scanDownloadable = $scanStatus === 'clean';

              if ($documentId === '') {
                  continue;
              }

              $uploadedAt = $uploadedAtRaw !== '' && strtotime($uploadedAtRaw) !== false
                  ? date('d/m/Y H:i', strtotime($uploadedAtRaw))
                  : $translate('TXT_PRIVATE_UNKNOWN', '—');

              $downloadUrl = rtrim($privateFilesBaseUrl, '/') . '/' . rawurlencode($documentId);
              $deleteUrl = rtrim($privateFilesBaseUrl, '/') . '/' . rawurlencode($documentId) . '/delete';
              ?>
              <tr>
                <td>
                  <?php if ($scanDownloadable): ?>
                    <a href="<?php echo $h($downloadUrl); ?>">
                      <?php echo $h($originalName !== '' ? $originalName : $documentId); ?>
                    </a>
                  <?php else: ?>
                    <span><?php echo $h($originalName !== '' ? $originalName : $documentId); ?></span>
                    <small class="muted"><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_SCAN_BLOCKED_HELP', 'Téléchargement bloqué.')); ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($categoryName !== ''): ?>
                    <span class="tag<?php echo $categoryColor !== '' ? ' private-color-tag ' . $h($privateDocumentColorClass($categoryColor, $privateDocumentCategoryDefaultColor)) : ''; ?>">
                      <?php echo $h($categoryName); ?>
                    </span>
                  <?php else: ?>
                    <span class="muted"><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_CATEGORY_NONE_SHORT', 'Sans catégorie')); ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="private-document-scan-status <?php echo $h($privateDocumentScanClass($scanStatus)); ?>">
                    <?php echo $h($privateDocumentScanLabel($scanStatus)); ?>
                  </span>
                </td>
                <td><?php echo $h($formatBytes(max(0, $sizeBytes))); ?></td>
                <td><?php echo $h($uploadedAt); ?></td>
                <td>
                  <form method="post" action="<?php echo $h($deleteUrl); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $h($privateDocumentUploadCsrfToken); ?>" />
                    <button type="submit"><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_DELETE', 'Supprimer')); ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php $privateDocumentCspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
  <script<?php echo $privateDocumentCspNonce !== '' ? ' nonce="' . $h($privateDocumentCspNonce) . '"' : ''; ?>>
    (() => {
      const root = document.querySelector('[data-private-documents-root]');
      if (!root) {
        return;
      }

      const categoryId = root.querySelector('[data-private-document-category-id]');
      const categoryName = root.querySelector('[data-private-document-category-name]');
      const resetButton = root.querySelector('[data-private-document-category-reset]');
      const selectColor = (color) => {
        root.querySelectorAll('input[name="category_color"]').forEach((input) => {
          if (input instanceof HTMLInputElement) {
            input.checked = input.value === color;
          }
        });
      };

      root.querySelectorAll('[data-private-document-category-edit]').forEach((button) => {
        button.addEventListener('click', () => {
          if (categoryId instanceof HTMLInputElement) {
            categoryId.value = button.getAttribute('data-category-id') || '0';
          }
          if (categoryName instanceof HTMLInputElement) {
            categoryName.value = button.getAttribute('data-category-name') || '';
            categoryName.focus();
          }
          selectColor(button.getAttribute('data-category-color') || '<?php echo $h($privateDocumentCategoryDefaultColor); ?>');
          document.getElementById('private-document-categories')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });

      resetButton?.addEventListener('click', () => {
        if (categoryId instanceof HTMLInputElement) {
          categoryId.value = '0';
        }
        if (categoryName instanceof HTMLInputElement) {
          categoryName.value = '';
          categoryName.focus();
        }
        selectColor('<?php echo $h($privateDocumentCategoryDefaultColor); ?>');
      });
    })();
  </script>
</section>
