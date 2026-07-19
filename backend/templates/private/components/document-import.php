<?php
/**
 * Composant d'import documentaire unique, piloté par profil déclaratif.
 * Aucune logique métier en dur : l'onglet appelant fournit le contexte.
 *
 * Variables attendues avant inclusion :
 * - string $documentImportUrl          action POST (route documents_hub_import)
 * - string $documentImportCsrfToken    jeton CSRF « private_document_hub »
 * - string $documentImportProfileCode  code du profil d'import
 * - string $documentImportReturnRoute  nom de route de retour après import
 * - array  $documentImportContext     [['entity_type','entity_id','link_role'?], ...] (contexte prérempli, non redemandé)
 * - array  $documentImportCategories  lignes de taxonomie active (code, parent_code, label)
 * - string $documentImportDefaultCategory  code présélectionné ('' = laisser choisir/classement auto)
 * - array  $documentImportAllowedCategories  restriction éventuelle des codes ([] = toutes)
 * - bool   $documentImportAllowMultiple
 * - string $documentImportTitle        titre du bloc (optionnel)
 */

$h = $h ?? static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$documentImportUrl = is_string($documentImportUrl ?? null) ? (string) $documentImportUrl : '';
$documentImportCsrfToken = is_string($documentImportCsrfToken ?? null) ? (string) $documentImportCsrfToken : '';
$documentImportProfileCode = is_string($documentImportProfileCode ?? null) ? (string) $documentImportProfileCode : '';
$documentImportReturnRoute = is_string($documentImportReturnRoute ?? null) ? (string) $documentImportReturnRoute : 'documents_hub';
$documentImportContext = is_array($documentImportContext ?? null) ? $documentImportContext : [];
$documentImportCategories = is_array($documentImportCategories ?? null) ? $documentImportCategories : [];
$documentImportDefaultCategory = is_string($documentImportDefaultCategory ?? null) ? (string) $documentImportDefaultCategory : '';
$documentImportAllowedCategories = is_array($documentImportAllowedCategories ?? null) ? $documentImportAllowedCategories : [];
$documentImportAllowMultiple = is_bool($documentImportAllowMultiple ?? null) ? (bool) $documentImportAllowMultiple : true;
$documentImportTitle = is_string($documentImportTitle ?? null) && $documentImportTitle !== ''
    ? (string) $documentImportTitle
    : 'Ajouter des documents';

$documentImportComponentId = 'document-import-' . substr(md5($documentImportProfileCode . '|' . $documentImportReturnRoute), 0, 8);

$documentImportCategoryOptions = [];
foreach ($documentImportCategories as $categoryRow) {
    $code = is_string($categoryRow['code'] ?? null) ? (string) $categoryRow['code'] : '';
    if ($code === '') {
        continue;
    }
    if ($documentImportAllowedCategories !== [] && !in_array($code, $documentImportAllowedCategories, true) && $code !== 'inbox') {
        continue;
    }
    $label = is_string($categoryRow['label'] ?? null) ? (string) $categoryRow['label'] : $code;
    $parent = is_string($categoryRow['parent_code'] ?? null) ? (string) $categoryRow['parent_code'] : '';
    $documentImportCategoryOptions[] = [
        'code' => $code,
        'label' => ($parent !== '' ? '— ' : '') . $label,
    ];
}
?>
<section class="private-dashboard-panel document-import-panel" id="<?php echo $h($documentImportComponentId); ?>">
  <h3><?php echo $h($documentImportTitle); ?></h3>
  <form method="post"
        action="<?php echo $h($documentImportUrl); ?>"
        enctype="multipart/form-data"
        class="document-import-form"
        data-document-import>
    <input type="hidden" name="csrf_token" value="<?php echo $h($documentImportCsrfToken); ?>" />
    <input type="hidden" name="profile_code" value="<?php echo $h($documentImportProfileCode); ?>" />
    <input type="hidden" name="return_route" value="<?php echo $h($documentImportReturnRoute); ?>" />
    <?php foreach ($documentImportContext as $contextRef): ?>
      <?php
        $refType = is_string($contextRef['entity_type'] ?? null) ? (string) $contextRef['entity_type'] : '';
        $refId = is_string($contextRef['entity_id'] ?? null) || is_numeric($contextRef['entity_id'] ?? null)
            ? (string) $contextRef['entity_id']
            : '';
        $refRole = is_string($contextRef['link_role'] ?? null) ? (string) $contextRef['link_role'] : 'attachment';
        if ($refType === '' || $refId === '') {
            continue;
        }
      ?>
      <input type="hidden" name="entity_type[]" value="<?php echo $h($refType); ?>" />
      <input type="hidden" name="entity_id[]" value="<?php echo $h($refId); ?>" />
      <input type="hidden" name="link_role[]" value="<?php echo $h($refRole); ?>" />
    <?php endforeach; ?>

    <div class="document-import-dropzone" data-import-dropzone>
      <p><strong>Glissez-déposez</strong> vos fichiers ici, ou</p>
      <label class="private-button-secondary" for="<?php echo $h($documentImportComponentId); ?>-files">
        Parcourir les fichiers
      </label>
      <input type="file"
             id="<?php echo $h($documentImportComponentId); ?>-files"
             name="hub_files[]"
             <?php echo $documentImportAllowMultiple ? 'multiple' : ''; ?>
             accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,.tif,.tiff,.docx,.odt,.xlsx,.ods,.csv,.txt,image/*,application/pdf"
             data-import-input
             class="document-import-input" />
      <p class="muted">
        Formats acceptés : PDF, images (JPG, PNG, WebP, HEIC, TIFF), DOCX, ODT, XLSX, ODS, CSV, TXT.
        Sur mobile, la prise de photo est proposée par le sélecteur de fichiers.
      </p>
      <ul class="document-import-file-list" data-import-file-list aria-live="polite"></ul>
    </div>

    <div class="private-list-filter-grid">
      <label>
        Catégorie proposée
        <select name="category_code" data-import-category>
          <option value="">Classement automatique</option>
          <?php foreach ($documentImportCategoryOptions as $option): ?>
            <option value="<?php echo $h($option['code']); ?>"
              <?php echo $option['code'] === $documentImportDefaultCategory ? 'selected' : ''; ?>>
              <?php echo $h($option['label']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Titre (optionnel)
        <input type="text" name="title" maxlength="255" autocomplete="off" />
      </label>
      <label>
        Date du document
        <input type="date" name="document_date" />
      </label>
      <label>
        Description (optionnelle)
        <input type="text" name="description" maxlength="1000" autocomplete="off" />
      </label>
    </div>

    <div class="private-list-filter-actions document-import-actions">
      <button type="submit" class="private-create-button" data-import-submit>Importer</button>
      <button type="button" class="private-button-secondary" data-import-cancel hidden>Annuler l'envoi</button>
      <progress value="0" max="100" data-import-progress hidden aria-label="Progression de l'envoi"></progress>
      <span class="muted" data-import-status role="status"></span>
    </div>
  </form>
</section>
<script>
(function () {
  'use strict';
  var root = document.getElementById(<?php echo json_encode($documentImportComponentId); ?>);
  if (!root) { return; }
  var form = root.querySelector('[data-document-import]');
  var dropzone = root.querySelector('[data-import-dropzone]');
  var input = root.querySelector('[data-import-input]');
  var fileList = root.querySelector('[data-import-file-list]');
  var progress = root.querySelector('[data-import-progress]');
  var statusEl = root.querySelector('[data-import-status]');
  var cancelBtn = root.querySelector('[data-import-cancel]');
  var submitBtn = root.querySelector('[data-import-submit]');
  var currentXhr = null;

  function renderFileList() {
    fileList.innerHTML = '';
    if (!input.files) { return; }
    Array.prototype.forEach.call(input.files, function (file) {
      var item = document.createElement('li');
      item.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' Ko)';
      fileList.appendChild(item);
    });
  }

  input.addEventListener('change', renderFileList);

  ['dragover', 'dragenter'].forEach(function (eventName) {
    dropzone.addEventListener(eventName, function (event) {
      event.preventDefault();
      dropzone.classList.add('document-import-dropzone-active');
    });
  });
  ['dragleave', 'drop'].forEach(function (eventName) {
    dropzone.addEventListener(eventName, function (event) {
      event.preventDefault();
      dropzone.classList.remove('document-import-dropzone-active');
    });
  });
  dropzone.addEventListener('drop', function (event) {
    if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length > 0) {
      input.files = event.dataTransfer.files;
      renderFileList();
    }
  });
  form.addEventListener('submit', function (event) {
    if (!window.XMLHttpRequest || !window.FormData) { return; }
    if (!input.files || input.files.length === 0) {
      event.preventDefault();
      statusEl.textContent = 'Aucun fichier sélectionné.';
      return;
    }

    event.preventDefault();
    var xhr = new XMLHttpRequest();
    currentXhr = xhr;
    xhr.open('POST', form.action, true);
    progress.hidden = false;
    cancelBtn.hidden = false;
    submitBtn.disabled = true;
    statusEl.textContent = 'Envoi en cours…';

    xhr.upload.addEventListener('progress', function (e) {
      if (e.lengthComputable) {
        progress.value = Math.round((e.loaded / e.total) * 100);
      }
    });
    xhr.addEventListener('load', function () {
      // La réponse suit la redirection : recharger la page de destination.
      window.location.href = xhr.responseURL || window.location.href;
    });
    xhr.addEventListener('error', function () {
      submitBtn.disabled = false;
      cancelBtn.hidden = true;
      progress.hidden = true;
      statusEl.textContent = 'L\'envoi a échoué. Réessayez.';
    });
    xhr.addEventListener('abort', function () {
      submitBtn.disabled = false;
      cancelBtn.hidden = true;
      progress.hidden = true;
      statusEl.textContent = 'Envoi annulé. Aucun fichier n\'a été conservé.';
    });
    xhr.send(new FormData(form));
  });

  cancelBtn.addEventListener('click', function () {
    if (currentXhr) { currentXhr.abort(); }
  });
})();
</script>
