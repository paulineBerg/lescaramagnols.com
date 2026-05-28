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
?>
<section class="private-dashboard private-documents-module" data-private-documents-root>
  <style>
    .private-documents-module,
    .private-documents-module * {
      min-width: 0;
      overflow-wrap: anywhere;
    }

    .private-documents-grid {
      display: grid;
      gap: 1.1rem;
      grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr));
      margin: 1rem 0 1.4rem;
    }

    .private-documents-panel {
      background: #fff;
      border: 1px solid rgba(19, 41, 75, 0.08);
      border-radius: 18px;
      box-shadow: 0 14px 34px rgba(19, 41, 75, 0.08);
      padding: 1.2rem;
    }

    .private-documents-panel h3 {
      color: var(--private-primary-dark);
      margin: 0 0 0.85rem;
    }

    .private-document-category-list {
      display: grid;
      gap: 0.75rem;
    }

    .private-document-category-row {
      background: #fff;
      border: 1px solid rgba(19, 41, 75, 0.08);
      border-left: 0.45rem solid var(--document-category-color, #ffffff);
      border-radius: 14px;
      padding: 0.85rem;
    }

    .private-document-category-row h4 {
      color: var(--private-primary-dark);
      margin: 0 0 0.25rem;
    }

    .private-document-category-actions,
    .private-document-category-color-choices,
    .private-document-upload-actions {
      align-items: center;
      display: flex;
      flex-wrap: wrap;
      gap: 0.55rem;
    }

    .private-document-category-color-choice {
      align-items: center;
      border: 1px solid rgba(19, 41, 75, 0.16);
      border-radius: 999px;
      display: inline-flex;
      gap: 0.4rem;
      margin: 0;
      padding: 0.35rem 0.55rem;
    }

    .private-document-category-color-choice input {
      height: 1rem;
      margin: 0;
      min-height: auto;
      width: 1rem;
    }

    .private-document-category-swatch,
    .private-document-category-dot {
      border: 1px solid rgba(19, 41, 75, 0.22);
      border-radius: 999px;
      display: inline-block;
    }

    .private-document-category-swatch {
      height: 1.25rem;
      width: 1.25rem;
    }

    .private-document-category-dot {
      height: 0.95rem;
      margin-right: 0.35rem;
      vertical-align: -0.1rem;
      width: 0.95rem;
    }

    .private-document-button-secondary {
      background: rgba(19, 41, 75, 0.08);
      color: var(--private-primary-dark);
    }

    .private-document-button-danger {
      background: rgba(161, 26, 42, 0.14);
      color: var(--private-danger);
    }

    .private-document-button-secondary:hover,
    .private-document-button-danger:hover {
      box-shadow: none;
    }

    .private-documents-table-wrap {
      max-width: 100%;
      overflow-x: auto;
    }

    @media (max-width: 720px) {
      .private-document-category-actions,
      .private-document-upload-actions {
        align-items: stretch;
        flex-direction: column;
      }

      .private-document-category-actions button,
      .private-document-upload-actions button {
        width: 100%;
      }
    }
  </style>

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
                  <span class="private-document-category-swatch" style="background: <?php echo $h($color); ?>;"></span>
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
        <section class="private-documents-panel" style="margin-bottom: 1.4rem;">
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
              <article class="private-document-category-row" style="--document-category-color: <?php echo $h($categoryColor); ?>;">
                <h4><span class="private-document-category-dot" style="background: <?php echo $h($categoryColor); ?>;"></span><?php echo $h($categoryName); ?></h4>
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
                  <a href="<?php echo $h($downloadUrl); ?>">
                    <?php echo $h($originalName !== '' ? $originalName : $documentId); ?>
                  </a>
                </td>
                <td>
                  <?php if ($categoryName !== ''): ?>
                    <span class="tag" <?php echo $categoryColor !== '' ? 'style="border-color:' . $h($categoryColor) . ';color:' . $h($categoryColor) . '"' : ''; ?>>
                      <?php echo $h($categoryName); ?>
                    </span>
                  <?php else: ?>
                    <span class="muted"><?php echo $h($translate('TXT_PRIVATE_DOCUMENT_CATEGORY_NONE_SHORT', 'Sans catégorie')); ?></span>
                  <?php endif; ?>
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
