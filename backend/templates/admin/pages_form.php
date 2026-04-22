<?php
$formData = is_array($formData ?? null) ? $formData : [];
$translations = is_array($formData['translations'] ?? null) ? $formData['translations'] : [];
$sharedMediaItems = is_array($formData['shared_media'] ?? null) ? array_values($formData['shared_media']) : [];
$sharedMediaLibrary = is_array($sharedMediaLibrary ?? null) ? array_values($sharedMediaLibrary) : [];
$contentMediaPicker = is_array($contentMediaPicker ?? null) ? $contentMediaPicker : [];
$contentMediaLibrary = is_array($contentMediaPicker['items'] ?? null)
    ? array_values($contentMediaPicker['items'])
    : (is_array($contentMediaLibrary ?? null) ? array_values($contentMediaLibrary) : []);
$contentMediaFolders = is_array($contentMediaPicker['folders'] ?? null) ? array_values($contentMediaPicker['folders']) : [''];
$contentMediaFavorites = is_array($contentMediaPicker['favorites'] ?? null) ? array_values($contentMediaPicker['favorites']) : [''];
$contentMediaPolicy = is_array($contentMediaPicker['policy'] ?? null) ? $contentMediaPicker['policy'] : [];
$contentMediaPolicyImageExtensions = array_values(
    array_filter(
        array_map(
            static fn (mixed $extension): string => is_string($extension) ? strtolower(trim($extension)) : '',
            is_array($contentMediaPolicy['imageExtensions'] ?? null) ? $contentMediaPolicy['imageExtensions'] : []
        ),
        static fn (string $extension): bool => $extension !== ''
    )
);
$contentMediaPolicyVideoExtensions = array_values(
    array_filter(
        array_map(
            static fn (mixed $extension): string => is_string($extension) ? strtolower(trim($extension)) : '',
            is_array($contentMediaPolicy['videoExtensions'] ?? null) ? $contentMediaPolicy['videoExtensions'] : []
        ),
        static fn (string $extension): bool => $extension !== ''
    )
);
$contentMediaPolicyImageMaxBytes = max(0, (int) ($contentMediaPolicy['imageMaxBytes'] ?? 0));
$contentMediaPolicyVideoMaxBytes = max(0, (int) ($contentMediaPolicy['videoMaxBytes'] ?? 0));
$contentMediaPolicyJson = json_encode(
    [
        'context' => (string) ($contentMediaPolicy['context'] ?? 'page'),
        'imageExtensions' => $contentMediaPolicyImageExtensions,
        'videoExtensions' => $contentMediaPolicyVideoExtensions,
        'imageMaxBytes' => $contentMediaPolicyImageMaxBytes,
        'videoMaxBytes' => $contentMediaPolicyVideoMaxBytes,
        'imageMaxLabel' => (string) ($contentMediaPolicy['imageMaxLabel'] ?? ''),
        'videoMaxLabel' => (string) ($contentMediaPolicy['videoMaxLabel'] ?? ''),
    ],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
if (!is_string($contentMediaPolicyJson) || $contentMediaPolicyJson === '') {
    $contentMediaPolicyJson = '{}';
}
$sharedMediaSourceOptions = [];
foreach ($sharedMediaItems as $sharedMediaItem) {
    if (!is_array($sharedMediaItem)) {
        continue;
    }

    $mediaSrc = trim((string) ($sharedMediaItem['src'] ?? ''));
    if ($mediaSrc !== '') {
        $sharedMediaSourceOptions[$mediaSrc] = true;
    }
}
foreach ($sharedMediaLibrary as $sharedMediaLibraryItem) {
    if (!is_array($sharedMediaLibraryItem)) {
        continue;
    }

    $mediaSrc = trim((string) ($sharedMediaLibraryItem['src'] ?? ''));
    if ($mediaSrc !== '') {
        $sharedMediaSourceOptions[$mediaSrc] = true;
    }
}
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
$statusLabels = [
    'draft' => 'Brouillon',
    'published' => 'Publié',
];
$languageLabels = [
    'fr' => 'Français',
    'en' => 'English',
    'de' => 'Deutsch',
];
$standardLayoutPlan = [
    [
        'key' => 'hero',
        'slot' => 'EditRegion1',
        'label' => 'Hero',
        'summary' => 'Zone centrale : titre, accroche, image',
        'area' => 'hero',
    ],
    [
        'key' => 'intro',
        'slot' => 'EditRegion8',
        'label' => 'Intro',
        'summary' => 'Colonne gauche : petite image d appel ou texte court uniquement',
        'area' => 'intro',
    ],
    [
        'key' => 'aside',
        'slot' => 'EditRegion2',
        'label' => 'Encart',
        'summary' => 'Colonne droite',
        'area' => 'aside',
    ],
    [
        'key' => 'body',
        'slot' => 'EditRegion3',
        'label' => 'Corps',
        'summary' => 'Contenu principal de la page',
        'area' => 'body',
    ],
    [
        'key' => 'after_body',
        'slot' => 'EditRegion4',
        'label' => 'Apres corps',
        'summary' => 'Bloc juste sous le contenu principal',
        'area' => 'after',
    ],
    [
        'key' => 'left',
        'slot' => 'EditRegion5',
        'label' => 'Bloc bas gauche',
        'summary' => 'Repere, faits, elements courts',
        'area' => 'left',
    ],
    [
        'key' => 'bottom',
        'slot' => 'EditRegion7',
        'label' => 'Bloc bas centre',
        'summary' => 'Zone centrale basse',
        'area' => 'bottom',
    ],
    [
        'key' => 'right',
        'slot' => 'EditRegion6',
        'label' => 'Bloc bas droit',
        'summary' => 'Colonne complementaire',
        'area' => 'right',
    ],
    [
        'key' => 'postscript',
        'slot' => 'EditRegion11',
        'label' => 'Post-scriptum',
        'summary' => 'Derniere zone visible du template',
        'area' => 'postscript',
    ],
    [
        'key' => 'footer',
        'slot' => 'EditRegion9',
        'label' => 'Footer editorial',
        'summary' => 'Zone rendue dans le pied de page du layout',
        'area' => 'footer',
    ],
];
$regionFieldMap = [
    'hero' => 'hero_html',
    'intro' => 'intro_html',
    'aside' => 'aside_html',
    'body' => 'body_html',
    'after_body' => 'after_body_html',
    'left' => 'left_html',
    'bottom' => 'bottom_html',
    'right' => 'right_html',
    'postscript' => 'postscript_html',
    'footer' => 'footer_html',
];
$regionRows = [
    'hero' => 10,
    'intro' => 8,
    'aside' => 8,
    'body' => 12,
    'after_body' => 8,
    'left' => 8,
    'bottom' => 8,
    'right' => 8,
    'postscript' => 8,
    'footer' => 8,
];
$hasStructuredRegionContent = static function (array $values, string $field): bool {
    return trim((string) ($values[$field] ?? '')) !== '';
};
$deleteInfo = is_array($deleteInfo ?? null) ? $deleteInfo : ['canDelete' => false, 'references' => []];
$deleteReferences = is_array($deleteInfo['references'] ?? null) ? $deleteInfo['references'] : [];
$pageEditorFormId = 'page-editor-form';
$translationLanguages = array_values(
    array_filter(
        is_array($availableLanguages ?? null) ? $availableLanguages : array_keys($translations),
        static fn (mixed $language): bool => is_string($language) && trim($language) !== ''
    )
);
$tileSupportEnabled = !empty($tileSupportEnabled);
$tilePlacements = is_array($formData['tile_placements'] ?? null) ? array_values($formData['tile_placements']) : [];
$tileGroupOptions = is_array($tileGroupOptions ?? null) ? array_values($tileGroupOptions) : [];
$tileGroupCatalog = is_array($tileGroupCatalog ?? null) ? array_values($tileGroupCatalog) : [];
$tilePageOptions = is_array($tilePageOptions ?? null) ? array_values($tilePageOptions) : [];
$tileEditorBootstrapJson = json_encode(
    [
        'enabled' => $tileSupportEnabled,
        'languages' => $translationLanguages,
        'placements' => $tilePlacements,
        'groups' => $tileGroupCatalog,
        'pageOptions' => $tilePageOptions,
    ],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
if (!is_string($tileEditorBootstrapJson) || $tileEditorBootstrapJson === '') {
    $tileEditorBootstrapJson = '{}';
}
?>

<section class="card page-editor-intro">
  <div class="page-editor-intro__header">
    <h2><?php echo ($isNewPage ?? false) ? 'Créer une page' : 'Éditer une page'; ?></h2>
    <button
      type="submit"
      name="page_action"
      value="save"
      form="<?php echo htmlspecialchars($pageEditorFormId, ENT_QUOTES, 'UTF-8'); ?>"
      class="page-editor-intro__save"
    >
      Enregistrer la page
    </button>
  </div>
  <p class="page-editor-intro__description">
    Le formulaire travaille sur le registre éditorial courant. Selon la configuration, il est stocké en
    <code>JSON</code>, <code>SQL</code> ou <code>double écriture</code>. Les pages structurées peuvent être éditées
    par langue et passées en <code>brouillon</code> ou <code>publié</code>.
  </p>

  <?php if (($message ?? null) !== null): ?>
  <div class="notice notice-success"><?php echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <?php if (($error ?? null) !== null): ?>
  <div class="notice notice-error"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <div class="actions-inline page-editor-intro__actions">
    <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($pagesIndexUrl ?? $adminPagesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Retour à la liste</a>
    <?php if (trim((string) ($formData['route'] ?? '')) !== ''): ?>
    <a class="button-link" href="<?php echo htmlspecialchars((string) ($formData['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Voir la route</a>
    <?php endif; ?>
  </div>
</section>

<form
  id="<?php echo htmlspecialchars($pageEditorFormId, ENT_QUOTES, 'UTF-8'); ?>"
  class="page-editor-form"
  method="post"
  enctype="multipart/form-data"
  action="<?php echo htmlspecialchars((string) ($currentPageUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
  <input
    type="hidden"
    name="page_state_json"
    id="page-editor-state-json"
    value=""
  />

  <section class="card">
    <h2>Paramètres généraux</h2>

    <div class="admin-form-grid admin-form-grid-2">
      <div class="field">
        <label for="page-slug">Slug</label>
        <input id="page-slug" name="slug" type="text" value="<?php echo htmlspecialchars((string) ($formData['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
      </div>

      <div class="field">
        <label for="page-route">Route publique</label>
        <input id="page-route" name="route" type="text" value="<?php echo htmlspecialchars((string) ($formData['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="/ma-page" />
      </div>

      <div class="field">
        <label for="page-status">Statut</label>
        <select id="page-status" name="status">
          <?php foreach (($supportedStatuses ?? []) as $status): ?>
          <option value="<?php echo htmlspecialchars((string) $status, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($formData['status'] ?? '') === $status ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($statusLabels[$status] ?? (string) $status, ENT_QUOTES, 'UTF-8'); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="page-layout">Layout structuré</label>
        <input id="page-layout" name="layout" type="text" value="<?php echo htmlspecialchars((string) ($formData['layout'] ?? 'standard_page'), ENT_QUOTES, 'UTF-8'); ?>" />
      </div>
    </div>
  </section>

  <?php if ($tileSupportEnabled): ?>
  <section class="card" data-page-tile-editor data-page-tile-bootstrap="<?php echo htmlspecialchars($tileEditorBootstrapJson, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="page-editor-intro__header">
      <div>
        <h2>Tuiles after_body</h2>
        <p class="page-editor-intro__description">
          Les groupes sélectionnés sont injectés à la fin de <code>EditRegion4 - Apres corps</code>, dans l ordre choisi.
          Depuis cette page, vous gérez seulement l affectation des groupes, leur ordre, la visibilité locale et, si besoin,
          une page cible locale. L édition complète des tuiles se fait dans <code>Tuiles</code>.
        </p>
      </div>
      <div class="actions-inline">
        <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($adminTilesUrl ?? admin_url('tiles')), ENT_QUOTES, 'UTF-8'); ?>">Gérer les groupes</a>
        <button type="button" class="button-link button-link-muted" data-page-tile-add-placement<?php echo $tileGroupOptions === [] ? ' disabled' : ''; ?>>Ajouter un groupe</button>
      </div>
    </div>

    <?php if ($tileGroupOptions === []): ?>
    <p class="notice-muted">Aucun groupe n est encore disponible. Crée d abord un groupe dans l administration Tuiles.</p>
    <?php endif; ?>

    <div class="page-tile-placement-list" data-page-tile-placement-list></div>
  </section>
  <?php endif; ?>

  <section class="card" data-shared-media-editor>
    <h2>
      <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_SECTION_TITLE', 'Medias partages (toutes langues)'), ENT_QUOTES, 'UTF-8'); ?>
    </h2>
    <p>
      <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_SECTION_HELP', 'Cette galerie est commune a la page, independante des traductions, et reutilisable dans d autres contenus.'), ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <div class="field">
      <label for="page-shared-media-files">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_UPLOAD_LABEL', 'Importer des images'), ENT_QUOTES, 'UTF-8'); ?>
      </label>
      <input
        id="page-shared-media-files"
        name="page_shared_media_files[]"
        type="file"
        multiple
        accept="image/jpeg,image/png,image/webp,image/gif,image/avif"
      />
      <p class="admin-form-help">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_UPLOAD_HELP', 'Chaque fichier est redimensionne automatiquement (max 2048px) puis converti en WebP dans /uploads/editorial/media/YYYY/MM.'), ENT_QUOTES, 'UTF-8'); ?>
      </p>
    </div>

    <div class="actions-inline">
      <button type="button" class="button-link button-link-muted" data-shared-media-add-row>
        <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_ADD_ROW', 'Ajouter une image manuellement'), ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>

    <datalist id="shared-media-library-sources">
      <?php foreach (array_keys($sharedMediaSourceOptions) as $sharedMediaSource): ?>
      <option value="<?php echo htmlspecialchars((string) $sharedMediaSource, ENT_QUOTES, 'UTF-8'); ?>"></option>
      <?php endforeach; ?>
    </datalist>

    <div class="shared-media-editor__list" data-shared-media-list>
      <?php foreach ($sharedMediaItems as $mediaIndex => $mediaItem): ?>
      <?php
      $mediaItem = is_array($mediaItem) ? $mediaItem : [];
      $mediaSrc = trim((string) ($mediaItem['src'] ?? ''));
      $mediaAlt = trim((string) ($mediaItem['alt'] ?? ''));
      $mediaTitle = trim((string) ($mediaItem['title'] ?? ''));
      $mediaCaption = trim((string) ($mediaItem['caption'] ?? ''));
      $mediaWidth = trim((string) ($mediaItem['width'] ?? ''));
      $mediaHeight = trim((string) ($mediaItem['height'] ?? ''));
      ?>
      <article class="nested-card shared-media-row" data-shared-media-row data-shared-media-index="<?php echo (int) $mediaIndex; ?>">
        <div class="admin-form-grid admin-form-grid-3">
          <div class="field admin-form-span-2">
            <label for="shared-media-src-<?php echo (int) $mediaIndex; ?>">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_SRC_LABEL', 'Chemin image'), ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <input
              id="shared-media-src-<?php echo (int) $mediaIndex; ?>"
              name="shared_media[<?php echo (int) $mediaIndex; ?>][src]"
              type="text"
              value="<?php echo htmlspecialchars($mediaSrc, ENT_QUOTES, 'UTF-8'); ?>"
              list="shared-media-library-sources"
              placeholder="/uploads/editorial/media/..."
              data-shared-media-src
            />
          </div>

          <div class="field">
            <label for="shared-media-alt-<?php echo (int) $mediaIndex; ?>">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_ALT_LABEL', 'Texte alternatif'), ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <input
              id="shared-media-alt-<?php echo (int) $mediaIndex; ?>"
              name="shared_media[<?php echo (int) $mediaIndex; ?>][alt]"
              type="text"
              value="<?php echo htmlspecialchars($mediaAlt, ENT_QUOTES, 'UTF-8'); ?>"
              data-shared-media-alt
            />
          </div>

          <div class="field">
            <label for="shared-media-title-<?php echo (int) $mediaIndex; ?>">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_TITLE_LABEL', 'Titre image'), ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <input
              id="shared-media-title-<?php echo (int) $mediaIndex; ?>"
              name="shared_media[<?php echo (int) $mediaIndex; ?>][title]"
              type="text"
              value="<?php echo htmlspecialchars($mediaTitle, ENT_QUOTES, 'UTF-8'); ?>"
            />
          </div>

          <div class="field">
            <label for="shared-media-width-<?php echo (int) $mediaIndex; ?>">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_WIDTH_LABEL', 'Largeur'), ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <input
              id="shared-media-width-<?php echo (int) $mediaIndex; ?>"
              name="shared_media[<?php echo (int) $mediaIndex; ?>][width]"
              type="number"
              min="1"
              max="8192"
              step="1"
              value="<?php echo htmlspecialchars($mediaWidth, ENT_QUOTES, 'UTF-8'); ?>"
            />
          </div>

          <div class="field">
            <label for="shared-media-height-<?php echo (int) $mediaIndex; ?>">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_HEIGHT_LABEL', 'Hauteur'), ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <input
              id="shared-media-height-<?php echo (int) $mediaIndex; ?>"
              name="shared_media[<?php echo (int) $mediaIndex; ?>][height]"
              type="number"
              min="1"
              max="8192"
              step="1"
              value="<?php echo htmlspecialchars($mediaHeight, ENT_QUOTES, 'UTF-8'); ?>"
            />
          </div>

          <div class="field admin-form-span-2">
            <label for="shared-media-caption-<?php echo (int) $mediaIndex; ?>">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_CAPTION_LABEL', 'Legende'), ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <textarea
              id="shared-media-caption-<?php echo (int) $mediaIndex; ?>"
              name="shared_media[<?php echo (int) $mediaIndex; ?>][caption]"
              rows="2"
            ><?php echo htmlspecialchars($mediaCaption, ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>

          <div class="field admin-form-span-2" data-shared-media-preview-wrapper<?php echo $mediaSrc === '' ? ' hidden' : ''; ?>>
            <label><?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_PREVIEW_LABEL', 'Apercu'), ENT_QUOTES, 'UTF-8'); ?></label>
            <img
              src="<?php echo htmlspecialchars($mediaSrc, ENT_QUOTES, 'UTF-8'); ?>"
              alt="<?php echo htmlspecialchars($mediaAlt !== '' ? $mediaAlt : $translate('TXT_ADMIN_PAGE_SHARED_MEDIA_PREVIEW_ALT', 'Apercu image partagee'), ENT_QUOTES, 'UTF-8'); ?>"
              width="<?php echo ((int) $mediaWidth > 0) ? (int) $mediaWidth : 320; ?>"
              height="<?php echo ((int) $mediaHeight > 0) ? (int) $mediaHeight : 180; ?>"
              loading="lazy"
              decoding="async"
              fetchpriority="low"
              style="max-width: 22rem; width: 100%; height: auto; border-radius: 0.75rem;"
              data-shared-media-preview
            />
          </div>
        </div>

        <div class="actions-inline actions-inline-end">
          <button type="button" class="button-link button-link-muted" data-shared-media-remove-row>
            <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_REMOVE', 'Retirer cette image'), ENT_QUOTES, 'UTF-8'); ?>
          </button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <template id="shared-media-row-template">
      <article class="nested-card shared-media-row" data-shared-media-row data-shared-media-index="__INDEX__">
        <div class="admin-form-grid admin-form-grid-3">
          <div class="field admin-form-span-2">
            <label for="shared-media-src-__INDEX__">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_SRC_LABEL', 'Chemin image'), ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <input
              id="shared-media-src-__INDEX__"
              name="shared_media[__INDEX__][src]"
              type="text"
              value=""
              list="shared-media-library-sources"
              placeholder="/uploads/editorial/media/..."
              data-shared-media-src
            />
          </div>

          <div class="field">
            <label for="shared-media-alt-__INDEX__">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_ALT_LABEL', 'Texte alternatif'), ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <input
              id="shared-media-alt-__INDEX__"
              name="shared_media[__INDEX__][alt]"
              type="text"
              value=""
              data-shared-media-alt
            />
          </div>

          <div class="field">
            <label for="shared-media-title-__INDEX__">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_TITLE_LABEL', 'Titre image'), ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <input
              id="shared-media-title-__INDEX__"
              name="shared_media[__INDEX__][title]"
              type="text"
              value=""
            />
          </div>

          <div class="field">
            <label for="shared-media-width-__INDEX__">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_WIDTH_LABEL', 'Largeur'), ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <input
              id="shared-media-width-__INDEX__"
              name="shared_media[__INDEX__][width]"
              type="number"
              min="1"
              max="8192"
              step="1"
              value=""
            />
          </div>

          <div class="field">
            <label for="shared-media-height-__INDEX__">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_HEIGHT_LABEL', 'Hauteur'), ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <input
              id="shared-media-height-__INDEX__"
              name="shared_media[__INDEX__][height]"
              type="number"
              min="1"
              max="8192"
              step="1"
              value=""
            />
          </div>

          <div class="field admin-form-span-2">
            <label for="shared-media-caption-__INDEX__">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_CAPTION_LABEL', 'Legende'), ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <textarea id="shared-media-caption-__INDEX__" name="shared_media[__INDEX__][caption]" rows="2"></textarea>
          </div>

          <div class="field admin-form-span-2" data-shared-media-preview-wrapper hidden>
            <label><?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_PREVIEW_LABEL', 'Apercu'), ENT_QUOTES, 'UTF-8'); ?></label>
            <img
              src=""
              alt="<?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_PREVIEW_ALT', 'Apercu image partagee'), ENT_QUOTES, 'UTF-8'); ?>"
              width="320"
              height="180"
              loading="lazy"
              decoding="async"
              fetchpriority="low"
              style="max-width: 22rem; width: 100%; height: auto; border-radius: 0.75rem;"
              data-shared-media-preview
            />
          </div>
        </div>

        <div class="actions-inline actions-inline-end">
          <button type="button" class="button-link button-link-muted" data-shared-media-remove-row>
            <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_REMOVE', 'Retirer cette image'), ENT_QUOTES, 'UTF-8'); ?>
          </button>
        </div>
      </article>
    </template>

    <?php if ($sharedMediaLibrary !== []): ?>
    <details class="nested-card" open>
      <summary><?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_LIBRARY_TITLE', 'Bibliotheque reutilisable'), ENT_QUOTES, 'UTF-8'); ?></summary>
      <p class="notice-muted">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_LIBRARY_HELP', 'Les images de /uploads/editorial/media peuvent etre reutilisees dans les pages et articles via leur chemin.'), ENT_QUOTES, 'UTF-8'); ?>
      </p>
      <ul class="shared-media-library-list">
        <?php foreach (array_slice($sharedMediaLibrary, 0, 80) as $libraryItem): ?>
        <?php
        $libraryItem = is_array($libraryItem) ? $libraryItem : [];
        $librarySrc = trim((string) ($libraryItem['src'] ?? ''));
        if ($librarySrc === '') {
            continue;
        }
        ?>
        <li class="shared-media-library-item">
          <code><?php echo htmlspecialchars($librarySrc, ENT_QUOTES, 'UTF-8'); ?></code>
          <button
            type="button"
            class="button-link button-link-muted"
            data-shared-media-library-use="<?php echo htmlspecialchars($librarySrc, ENT_QUOTES, 'UTF-8'); ?>"
          >
            <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_SHARED_MEDIA_COPY', 'Utiliser ce chemin'), ENT_QUOTES, 'UTF-8'); ?>
          </button>
        </li>
        <?php endforeach; ?>
      </ul>
    </details>
    <?php endif; ?>
  </section>

  <?php if ($translationLanguages !== []): ?>
  <section class="card">
    <div class="page-editor-intro__header">
      <div>
        <h2>Traductions</h2>
        <p class="page-editor-intro__description">
          Chaque langue ouvre maintenant son propre panneau. L enregistrement reste definitif sur la page courante, avec un bouton de sauvegarde disponible dans chaque onglet.
        </p>
      </div>
    </div>

    <div class="menu-builder-tabs" role="tablist" aria-label="Traductions de la page" data-translation-tabs>
      <?php foreach ($translationLanguages as $translationTabIndex => $language): ?>
      <?php
      $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];
      $translationTitle = trim((string) ($translation['title'] ?? ''));
      $isActiveTranslationTab = $translationTabIndex === 0;
      ?>
      <button
        type="button"
        id="page-translation-tab-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
        class="menu-builder-tab<?php echo $isActiveTranslationTab ? ' menu-builder-tab-active' : ''; ?>"
        role="tab"
        aria-selected="<?php echo $isActiveTranslationTab ? 'true' : 'false'; ?>"
        aria-controls="page-translation-panel-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
        tabindex="<?php echo $isActiveTranslationTab ? '0' : '-1'; ?>"
        data-translation-tab="<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
      >
        <strong><?php echo htmlspecialchars($languageLabels[$language] ?? strtoupper((string) $language), ENT_QUOTES, 'UTF-8'); ?></strong>
        <small><?php echo htmlspecialchars($translationTitle !== '' ? 'Titre renseigne' : 'Titre a renseigner', ENT_QUOTES, 'UTF-8'); ?></small>
      </button>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php foreach ($translationLanguages as $translationTabIndex => $language): ?>
  <?php
  $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];
  $regionValues = is_array($translation['regions'] ?? null) ? $translation['regions'] : [];
  $translationMetaImageSrc = trim((string) ($translation['meta_image_src'] ?? ''));
  $translationMetaImageAlt = trim((string) ($translation['meta_image_alt'] ?? ''));
  $isActiveTranslationTab = $translationTabIndex === 0;
  $regionStatuses = [];
  foreach ($regionFieldMap as $regionKey => $fieldKey) {
      $regionStatuses[$regionKey] = $hasStructuredRegionContent($regionValues, $fieldKey);
  }
  ?>
  <section
    class="card translation-card"
    id="page-translation-panel-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
    role="tabpanel"
    aria-labelledby="page-translation-tab-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
    data-translation-panel="<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
    <?php echo $isActiveTranslationTab ? '' : 'hidden'; ?>
  >
    <div class="page-editor-intro__header">
      <div class="actions-inline">
        <strong><?php echo htmlspecialchars($languageLabels[$language] ?? strtoupper((string) $language), ENT_QUOTES, 'UTF-8'); ?></strong>
        <span class="lang-badge"><?php echo strtoupper(htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8')); ?></span>
      </div>
      <button
        type="submit"
        name="page_action"
        value="save"
        form="<?php echo htmlspecialchars($pageEditorFormId, ENT_QUOTES, 'UTF-8'); ?>"
        data-translation-save="<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
      >
        Enregistrer la page
      </button>
    </div>

    <div class="admin-form-grid admin-form-grid-2">
      <div class="field">
        <label for="title-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>">Titre</label>
        <input
          id="title-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
          name="translations[<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>][title]"
          type="text"
          value="<?php echo htmlspecialchars((string) ($translation['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        />
      </div>

      <div class="field">
        <label for="meta-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>">Meta description</label>
        <input
          id="meta-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
          name="translations[<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>][meta_description]"
          type="text"
          value="<?php echo htmlspecialchars((string) ($translation['meta_description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        />
      </div>

      <div class="field admin-form-span-2">
        <label for="meta-image-src-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_META_IMAGE_LABEL', 'Image SEO (Open Graph / Twitter)'), ENT_QUOTES, 'UTF-8'); ?>
        </label>
        <input
          id="meta-image-src-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
          name="translations[<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>][meta_image_src]"
          type="text"
          value="<?php echo htmlspecialchars($translationMetaImageSrc, ENT_QUOTES, 'UTF-8'); ?>"
          placeholder="/uploads/editorial/page/..."
        />
        <p class="admin-form-help">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_META_IMAGE_HELP', 'Image utilisee pour les partages sociaux et metadonnees SEO de la page.'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
      </div>

      <div class="field">
        <label for="page-image-file-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_META_IMAGE_UPLOAD_LABEL', 'Upload image'), ENT_QUOTES, 'UTF-8'); ?>
        </label>
        <input
          id="page-image-file-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
          name="page_image_file_<?php echo htmlspecialchars((string) strtolower((string) $language), ENT_QUOTES, 'UTF-8'); ?>"
          type="file"
          accept="image/jpeg,image/png,image/webp,image/gif,image/avif"
        />
      </div>

      <div class="field">
        <label for="meta-image-alt-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_META_IMAGE_ALT_LABEL', 'Texte alternatif'), ENT_QUOTES, 'UTF-8'); ?>
        </label>
        <input
          id="meta-image-alt-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
          name="translations[<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>][meta_image_alt]"
          type="text"
          value="<?php echo htmlspecialchars($translationMetaImageAlt, ENT_QUOTES, 'UTF-8'); ?>"
        />
      </div>

      <div class="field">
        <label for="meta-image-title-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_META_IMAGE_TITLE_LABEL', 'Titre image'), ENT_QUOTES, 'UTF-8'); ?>
        </label>
        <input
          id="meta-image-title-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
          name="translations[<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>][meta_image_title]"
          type="text"
          value="<?php echo htmlspecialchars((string) ($translation['meta_image_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        />
      </div>

      <div class="field">
        <label for="meta-image-width-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_META_IMAGE_WIDTH_LABEL', 'Largeur'), ENT_QUOTES, 'UTF-8'); ?>
        </label>
        <input
          id="meta-image-width-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
          name="translations[<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>][meta_image_width]"
          type="number"
          min="1"
          max="8192"
          step="1"
          value="<?php echo htmlspecialchars((string) ($translation['meta_image_width'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        />
      </div>

      <div class="field">
        <label for="meta-image-height-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_META_IMAGE_HEIGHT_LABEL', 'Hauteur'), ENT_QUOTES, 'UTF-8'); ?>
        </label>
        <input
          id="meta-image-height-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"
          name="translations[<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>][meta_image_height]"
          type="number"
          min="1"
          max="8192"
          step="1"
          value="<?php echo htmlspecialchars((string) ($translation['meta_image_height'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        />
      </div>

      <?php if ($translationMetaImageSrc !== ''): ?>
      <div class="field admin-form-span-2">
        <label><?php echo htmlspecialchars($translate('TXT_ADMIN_PAGE_META_IMAGE_PREVIEW_LABEL', 'Apercu image'), ENT_QUOTES, 'UTF-8'); ?></label>
        <p>
          <img
            src="<?php echo htmlspecialchars($translationMetaImageSrc, ENT_QUOTES, 'UTF-8'); ?>"
            alt="<?php echo htmlspecialchars($translationMetaImageAlt !== '' ? $translationMetaImageAlt : $translate('TXT_ADMIN_PAGE_META_IMAGE_PREVIEW_ALT', 'Apercu image de page'), ENT_QUOTES, 'UTF-8'); ?>"
            width="<?php echo ((int) ($translation['meta_image_width'] ?? 0) > 0) ? (int) $translation['meta_image_width'] : 320; ?>"
            height="<?php echo ((int) ($translation['meta_image_height'] ?? 0) > 0) ? (int) $translation['meta_image_height'] : 180; ?>"
            loading="lazy"
            decoding="async"
            fetchpriority="low"
            style="max-width: 24rem; width: 100%; height: auto; border-radius: 0.75rem;"
          />
        </p>
      </div>
      <?php endif; ?>
    </div>

    <details class="nested-card" open>
      <summary>Régions structurées</summary>

      <div class="page-layout-plan">
        <div class="page-layout-plan__header">
          <div>
            <h3>Plan du template standard</h3>
            <p>Clique sur une zone pour ouvrir sa popup d’édition. Disposition de référence: <strong>Intro à gauche</strong>, <strong>Hero au centre</strong>, <strong>Encart à droite</strong>. Chaque région structurée remplace l’ancien bloc <code>EditRegion*</code> correspondant.</p>
          </div>
          <span class="tag">Mode structuré</span>
        </div>

        <div class="page-layout-plan__grid" aria-label="Plan visuel du template standard">
          <?php foreach ($standardLayoutPlan as $planItem): ?>
          <?php
          $planKey = (string) $planItem['key'];
          $modalId = 'region-modal-' . $language . '-' . $planKey;
          $isFilled = (bool) ($regionStatuses[$planKey] ?? false);
          ?>
          <button
            type="button"
            class="page-layout-plan__item page-layout-plan__item--<?php echo htmlspecialchars((string) $planItem['area'], ENT_QUOTES, 'UTF-8'); ?>"
            data-region-modal-open="<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>"
            aria-controls="<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>"
            aria-haspopup="dialog"
          >
            <span class="page-layout-plan__eyebrow"><?php echo htmlspecialchars((string) $planItem['slot'], ENT_QUOTES, 'UTF-8'); ?></span>
            <strong><?php echo htmlspecialchars((string) $planItem['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
            <span class="page-layout-plan__summary"><?php echo htmlspecialchars((string) $planItem['summary'], ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="page-layout-plan__meta">
              <span class="page-layout-plan__status page-layout-plan__status--<?php echo $isFilled ? 'filled' : 'empty'; ?>">
                <?php echo $isFilled ? 'Renseigné' : 'Vide'; ?>
              </span>
              <span class="page-layout-plan__action">Ouvrir</span>
            </span>
          </button>
          <?php endforeach; ?>
        </div>

        <p class="page-layout-plan__hint">
          Le plan couvre les zones du contenu principal puis la zone <code>Footer editorial</code> rendue dans
          <code>#piedpage</code> via <code>EditRegion9</code>.
        </p>
      </div>

      <div class="structured-region-dialogs">
        <?php foreach ($standardLayoutPlan as $planItem): ?>
        <?php
        $planKey = (string) $planItem['key'];
        $fieldKey = (string) ($regionFieldMap[$planKey] ?? ($planKey . '_html'));
        $modalId = 'region-modal-' . $language . '-' . $planKey;
        $textareaId = 'region-html-' . $language . '-' . $planKey;
        $calloutCheckboxId = 'region-callout-' . $language . '-' . $planKey;
        $rows = (int) ($regionRows[$planKey] ?? 8);
        ?>
        <dialog class="region-modal" id="<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="region-modal-title-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>-<?php echo htmlspecialchars($planKey, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="region-modal__surface">
            <div class="region-modal__header">
              <div>
                <p class="region-modal__eyebrow"><?php echo htmlspecialchars($languageLabels[$language] ?? strtoupper((string) $language), ENT_QUOTES, 'UTF-8'); ?> · Région structurée · <?php echo htmlspecialchars((string) $planItem['slot'], ENT_QUOTES, 'UTF-8'); ?></p>
                <h3 id="region-modal-title-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>-<?php echo htmlspecialchars($planKey, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $planItem['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars((string) $planItem['summary'], ENT_QUOTES, 'UTF-8'); ?></p>
              </div>
              <button type="button" class="button-link button-link-muted" data-region-modal-close>Fermer</button>
            </div>

            <div class="region-modal__body">
              <div class="field">
                <label for="<?php echo htmlspecialchars($textareaId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $planItem['label'], ENT_QUOTES, 'UTF-8'); ?> · contenu HTML</label>
                <?php if ($planKey === 'intro'): ?>
                <p class="notice-muted">Limite editoriale : petite image d appel ou texte court uniquement. Ne pas y mettre un second corps d article, un long developpement ou une grande image.</p>
                <?php endif; ?>
                <textarea id="<?php echo htmlspecialchars($textareaId, ENT_QUOTES, 'UTF-8'); ?>" name="translations[<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>][regions][<?php echo htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8'); ?>]" rows="<?php echo $rows; ?>"><?php echo htmlspecialchars((string) (($translation['regions'][$fieldKey] ?? '')), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <div class="actions-inline">
                  <button
                    type="button"
                    class="button-link button-link-muted"
                    data-content-media-open="page-media-insert-dialog"
                    data-content-media-target="<?php echo htmlspecialchars($textareaId, ENT_QUOTES, 'UTF-8'); ?>"
                  >
                    Inserer un media (image / video)
                  </button>
                </div>
                <?php if ($planKey === 'aside'): ?>
                <div class="region-callout-control" data-region-callout-root data-region-callout-target="<?php echo htmlspecialchars($textareaId, ENT_QUOTES, 'UTF-8'); ?>">
                  <label class="checkbox-field" for="<?php echo htmlspecialchars($calloutCheckboxId, ENT_QUOTES, 'UTF-8'); ?>">
                    <input
                      id="<?php echo htmlspecialchars($calloutCheckboxId, ENT_QUOTES, 'UTF-8'); ?>"
                      type="checkbox"
                      data-region-callout-toggle
                    />
                    Ajouter la bordure rose autour de l encart texte
                  </label>
                  <p class="notice-muted region-callout-control__status" data-region-callout-status>
                    Cette option suit uniquement la case a cocher, meme si l encart contient une image.
                  </p>
                </div>
                <?php endif; ?>
                <div class="region-image-check" data-image-check-root data-image-check-target="<?php echo htmlspecialchars($textareaId, ENT_QUOTES, 'UTF-8'); ?>">
                  <div class="actions-inline">
                    <button type="button" class="button-link button-link-muted" data-image-check-run>Vérifier les images</button>
                  </div>
                  <p class="notice-muted region-image-check__status" data-image-check-status>
                    Vérifie que chaque chemin <code>&lt;img src="..."&gt;</code> pointe vers une image accessible.
                  </p>
                  <ul class="region-image-check__results" data-image-check-results hidden></ul>
                </div>
              </div>
            </div>

            <div class="actions-inline actions-inline-end region-modal__actions">
              <button type="button" class="button-link button-link-muted" data-region-modal-close>Continuer</button>
              <button type="submit" form="<?php echo htmlspecialchars($pageEditorFormId, ENT_QUOTES, 'UTF-8'); ?>">Enregistrer la page</button>
            </div>
          </div>
        </dialog>
        <?php endforeach; ?>
      </div>
    </details>
  </section>
  <?php endforeach; ?>

  <dialog
    class="region-modal content-media-dialog"
    id="page-media-insert-dialog"
    data-content-media-dialog
    data-content-media-policy="<?php echo htmlspecialchars($contentMediaPolicyJson, ENT_QUOTES, 'UTF-8'); ?>"
  >
    <div class="region-modal__surface">
      <div class="region-modal__header">
        <div>
          <p class="region-modal__eyebrow">Bibliotheque medias</p>
          <h3>Inserer un media dans la region</h3>
          <p>Selectionne une image ou une video, puis insertion au curseur dans la zone HTML active.</p>
        </div>
        <button type="button" class="button-link button-link-muted" data-content-media-close>Fermer</button>
      </div>

      <div class="region-modal__body">
        <div class="admin-form-grid admin-form-grid-2 content-media-toolbar">
          <div class="field content-media-dialog__search">
            <label for="page-media-insert-search">Recherche</label>
            <input id="page-media-insert-search" type="text" placeholder="nom, chemin, format..." data-content-media-search />
          </div>
          <div class="field">
            <label for="page-media-insert-folder">Dossier</label>
            <select id="page-media-insert-folder" data-content-media-folder>
              <option value="">Tous les dossiers</option>
              <?php foreach ($contentMediaFolders as $folderOption): ?>
              <?php
              if (!is_string($folderOption)) {
                  continue;
              }
              $folderOption = trim($folderOption);
              if ($folderOption === '') {
                  continue;
              }
              ?>
              <option value="<?php echo htmlspecialchars($folderOption, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($folderOption, ENT_QUOTES, 'UTF-8'); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <?php if ($contentMediaFavorites !== []): ?>
        <div class="actions-inline content-media-favorites" data-content-media-favorites>
          <span class="tag">Favoris</span>
          <?php foreach ($contentMediaFavorites as $favoriteFolder): ?>
          <?php
          if (!is_string($favoriteFolder)) {
              continue;
          }
          $favoriteFolder = trim($favoriteFolder);
          ?>
          <button
            type="button"
            class="button-small button-muted"
            data-content-media-favorite-folder="<?php echo htmlspecialchars($favoriteFolder, ENT_QUOTES, 'UTF-8'); ?>"
          >
            <?php echo htmlspecialchars($favoriteFolder === '' ? 'Racine' : $favoriteFolder, ENT_QUOTES, 'UTF-8'); ?>
          </button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <section class="content-media-controls">
          <h4>Preset insertion</h4>
          <div class="admin-form-grid admin-form-grid-3">
            <div class="field">
              <label for="page-media-insert-preset">Preset</label>
              <select id="page-media-insert-preset" data-content-media-preset>
                <option value="figure-default">Figure standard</option>
                <option value="figure-wide">Figure pleine largeur</option>
                <option value="figure-left">Figure flottante gauche</option>
                <option value="figure-right">Figure flottante droite</option>
                <option value="raw">Balise simple (sans figure)</option>
              </select>
            </div>
            <div class="field">
              <label for="page-media-insert-classes">Classes CSS supplementaires</label>
              <input id="page-media-insert-classes" type="text" placeholder="ex: rounded shadow-lg" data-content-media-extra-classes />
            </div>
            <div class="field">
              <label for="page-media-insert-alt">Alt image par defaut</label>
              <input id="page-media-insert-alt" type="text" placeholder="description courte" data-content-media-alt />
            </div>
          </div>

          <div class="admin-form-grid admin-form-grid-3">
            <div class="field">
              <label class="checkbox-field" for="page-media-insert-lazy">
                <input id="page-media-insert-lazy" type="checkbox" data-content-media-lazy checked />
                Charger en lazy (images)
              </label>
            </div>
            <div class="field">
              <label class="checkbox-field" for="page-media-insert-dimensions">
                <input id="page-media-insert-dimensions" type="checkbox" data-content-media-include-dimensions checked />
                Injecter width/height (si disponibles)
              </label>
            </div>
            <div class="field">
              <label class="checkbox-field" for="page-media-insert-governance-strict">
                <input id="page-media-insert-governance-strict" type="checkbox" data-content-media-governance-strict checked />
                Bloquer les assets hors charte
              </label>
            </div>
          </div>

          <div class="admin-form-grid admin-form-grid-3">
            <div class="field">
              <label class="checkbox-field" for="page-media-insert-video-controls">
                <input id="page-media-insert-video-controls" type="checkbox" data-content-media-video-controls checked />
                Video: controls
              </label>
            </div>
            <div class="field">
              <label class="checkbox-field" for="page-media-insert-video-muted">
                <input id="page-media-insert-video-muted" type="checkbox" data-content-media-video-muted />
                Video: muted
              </label>
            </div>
            <div class="field">
              <label class="checkbox-field" for="page-media-insert-video-autoplay">
                <input id="page-media-insert-video-autoplay" type="checkbox" data-content-media-video-autoplay />
                Video: autoplay
              </label>
            </div>
            <div class="field">
              <label class="checkbox-field" for="page-media-insert-video-loop">
                <input id="page-media-insert-video-loop" type="checkbox" data-content-media-video-loop />
                Video: loop
              </label>
            </div>
            <div class="field admin-form-span-2">
              <label for="page-media-insert-video-poster">Video poster (optionnel)</label>
              <input id="page-media-insert-video-poster" type="text" placeholder="/uploads/editorial/library/.../poster.webp" data-content-media-video-poster />
            </div>
            <div class="field">
              <label class="checkbox-field" for="page-media-insert-filter-governance">
                <input id="page-media-insert-filter-governance" type="checkbox" data-content-media-filter-governance />
                Afficher seulement les assets conformes
              </label>
            </div>
          </div>

          <p class="notice-muted">
            Gouvernance PAGE:
            images <?php echo htmlspecialchars($contentMediaPolicyImageExtensions === [] ? 'tous formats' : strtoupper(implode(', ', $contentMediaPolicyImageExtensions)), ENT_QUOTES, 'UTF-8'); ?>
            (max <?php echo htmlspecialchars((string) ($contentMediaPolicy['imageMaxLabel'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?>),
            videos <?php echo htmlspecialchars($contentMediaPolicyVideoExtensions === [] ? 'tous formats' : strtoupper(implode(', ', $contentMediaPolicyVideoExtensions)), ENT_QUOTES, 'UTF-8'); ?>
            (max <?php echo htmlspecialchars((string) ($contentMediaPolicy['videoMaxLabel'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?>).
          </p>
          <div class="actions-inline">
            <button type="button" class="button-small button-link-muted" data-content-media-audit>Auditer le contenu cible</button>
          </div>
          <p class="notice-muted content-media-audit__status" data-content-media-audit-status>
            Lance un controle automatique (format, taille, source referencee) sur le champ cible.
          </p>
          <ul class="content-media-audit__results" data-content-media-audit-results hidden></ul>
          <p class="notice-muted content-media-dialog__status" data-content-media-status hidden></p>
        </section>

        <div class="content-media-library" data-content-media-list>
          <?php foreach ($contentMediaLibrary as $mediaItem): ?>
          <?php
          $mediaItem = is_array($mediaItem) ? $mediaItem : [];
          $mediaSrc = trim((string) ($mediaItem['src'] ?? ''));
          if ($mediaSrc === '') {
              continue;
          }

          $mediaKind = trim((string) ($mediaItem['kind'] ?? 'other'));
          if ($mediaKind !== 'image' && $mediaKind !== 'video') {
              continue;
          }

          $mediaName = trim((string) ($mediaItem['name'] ?? basename($mediaSrc)));
          $mediaFolder = trim((string) ($mediaItem['folder'] ?? ''));
          $mediaMime = trim((string) ($mediaItem['mime'] ?? 'application/octet-stream'));
          $mediaExtension = strtoupper(trim((string) ($mediaItem['extension'] ?? '')));
          $mediaExtensionLower = strtolower($mediaExtension);
          $mediaSizeBytes = max(0, (int) ($mediaItem['sizeBytes'] ?? 0));
          $mediaSizeLabel = trim((string) ($mediaItem['sizeLabel'] ?? '0 B'));
          $mediaDimensionsLabel = trim((string) ($mediaItem['dimensionsLabel'] ?? 'N/A'));
          $mediaWidth = is_int($mediaItem['width'] ?? null) ? (int) $mediaItem['width'] : 0;
          $mediaHeight = is_int($mediaItem['height'] ?? null) ? (int) $mediaItem['height'] : 0;
          $mediaSearch = strtolower(trim($mediaName . ' ' . $mediaSrc . ' ' . $mediaKind . ' ' . $mediaMime . ' ' . $mediaExtension . ' ' . $mediaFolder));
          $mediaPolicyCompliant = true;
          $mediaPolicyReasons = [];
          if ($mediaKind === 'image') {
              if ($contentMediaPolicyImageExtensions !== [] && !in_array($mediaExtensionLower, $contentMediaPolicyImageExtensions, true)) {
                  $mediaPolicyCompliant = false;
                  $mediaPolicyReasons[] = 'format hors charte';
              }
              if ($contentMediaPolicyImageMaxBytes > 0 && $mediaSizeBytes > $contentMediaPolicyImageMaxBytes) {
                  $mediaPolicyCompliant = false;
                  $mediaPolicyReasons[] = 'taille depassee';
              }
          } elseif ($mediaKind === 'video') {
              if ($contentMediaPolicyVideoExtensions !== [] && !in_array($mediaExtensionLower, $contentMediaPolicyVideoExtensions, true)) {
                  $mediaPolicyCompliant = false;
                  $mediaPolicyReasons[] = 'format hors charte';
              }
              if ($contentMediaPolicyVideoMaxBytes > 0 && $mediaSizeBytes > $contentMediaPolicyVideoMaxBytes) {
                  $mediaPolicyCompliant = false;
                  $mediaPolicyReasons[] = 'taille depassee';
              }
          }
          $mediaPolicyHint = implode(' · ', $mediaPolicyReasons);
          ?>
          <article
            class="content-media-item"
            data-content-media-item
            data-content-media-filter="<?php echo htmlspecialchars($mediaSearch, ENT_QUOTES, 'UTF-8'); ?>"
            data-content-media-folder="<?php echo htmlspecialchars($mediaFolder, ENT_QUOTES, 'UTF-8'); ?>"
            data-content-media-src="<?php echo htmlspecialchars($mediaSrc, ENT_QUOTES, 'UTF-8'); ?>"
            data-content-media-kind="<?php echo htmlspecialchars($mediaKind, ENT_QUOTES, 'UTF-8'); ?>"
            data-content-media-extension="<?php echo htmlspecialchars($mediaExtensionLower, ENT_QUOTES, 'UTF-8'); ?>"
            data-content-media-size-bytes="<?php echo $mediaSizeBytes; ?>"
            data-content-media-compliant="<?php echo $mediaPolicyCompliant ? '1' : '0'; ?>"
            data-content-media-policy-hint="<?php echo htmlspecialchars($mediaPolicyHint, ENT_QUOTES, 'UTF-8'); ?>"
          >
            <div class="content-media-item__preview">
              <?php if ($mediaKind === 'image'): ?>
              <img src="<?php echo htmlspecialchars($mediaSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($mediaName, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" />
              <?php else: ?>
              <video src="<?php echo htmlspecialchars($mediaSrc, ENT_QUOTES, 'UTF-8'); ?>" preload="metadata" muted></video>
              <?php endif; ?>
            </div>
            <div class="content-media-item__meta">
              <strong><?php echo htmlspecialchars($mediaName, ENT_QUOTES, 'UTF-8'); ?></strong>
              <code><?php echo htmlspecialchars($mediaSrc, ENT_QUOTES, 'UTF-8'); ?></code>
              <small>
                <?php echo htmlspecialchars(strtoupper($mediaKind), ENT_QUOTES, 'UTF-8'); ?>
                · <?php echo htmlspecialchars($mediaExtension, ENT_QUOTES, 'UTF-8'); ?>
                · <?php echo htmlspecialchars($mediaSizeLabel, ENT_QUOTES, 'UTF-8'); ?>
                · <?php echo htmlspecialchars($mediaDimensionsLabel, ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($mediaFolder !== ''): ?>
                · <?php echo htmlspecialchars($mediaFolder, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
              </small>
              <small data-content-media-policy-badge>
                <?php echo $mediaPolicyCompliant ? 'Conforme gouvernance' : ('Hors charte: ' . htmlspecialchars($mediaPolicyHint, ENT_QUOTES, 'UTF-8')); ?>
              </small>
            </div>
            <div class="actions-inline actions-inline-end">
              <button
                type="button"
                class="button-small"
                data-content-media-insert
                data-content-media-src="<?php echo htmlspecialchars($mediaSrc, ENT_QUOTES, 'UTF-8'); ?>"
                data-content-media-kind="<?php echo htmlspecialchars($mediaKind, ENT_QUOTES, 'UTF-8'); ?>"
                data-content-media-width="<?php echo $mediaWidth > 0 ? $mediaWidth : ''; ?>"
                data-content-media-height="<?php echo $mediaHeight > 0 ? $mediaHeight : ''; ?>"
                data-content-media-extension="<?php echo htmlspecialchars($mediaExtensionLower, ENT_QUOTES, 'UTF-8'); ?>"
                data-content-media-size-bytes="<?php echo $mediaSizeBytes; ?>"
                data-content-media-compliant="<?php echo $mediaPolicyCompliant ? '1' : '0'; ?>"
                data-content-media-policy-hint="<?php echo htmlspecialchars($mediaPolicyHint, ENT_QUOTES, 'UTF-8'); ?>"
                data-content-media-folder="<?php echo htmlspecialchars($mediaFolder, ENT_QUOTES, 'UTF-8'); ?>"
              >
                Inserer
              </button>
            </div>
          </article>
          <?php endforeach; ?>
        </div>

        <p class="notice-muted content-media-dialog__empty" data-content-media-empty hidden>Aucun media ne correspond a la recherche.</p>
      </div>

      <div class="actions-inline actions-inline-end region-modal__actions">
        <button type="button" class="button-link button-link-muted" data-content-media-close>Fermer</button>
      </div>
    </div>
  </dialog>

  <div class="actions-inline actions-inline-end">
    <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($pagesIndexUrl ?? $adminPagesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Annuler</a>
    <button type="submit" name="page_action" value="save" form="<?php echo htmlspecialchars($pageEditorFormId, ENT_QUOTES, 'UTF-8'); ?>">Enregistrer la page</button>
  </div>
</form>

<?php $cspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
<script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
  (() => {
    const tabList = document.querySelector('[data-translation-tabs]');
    const tabs = Array.from(document.querySelectorAll('[data-translation-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-translation-panel]'));
    if (!(tabList instanceof HTMLElement) || tabs.length === 0 || panels.length === 0) {
      return;
    }

    const storageKey = 'admin-page-editor-active-translation';
    const availableLanguages = tabs
      .map((tab) => tab.getAttribute('data-translation-tab') || '')
      .filter((language) => language !== '');

    if (availableLanguages.length === 0) {
      return;
    }

    const firstLanguage = availableLanguages[0];

    const setActiveLanguage = (requestedLanguage, options = {}) => {
      const language = availableLanguages.includes(requestedLanguage) ? requestedLanguage : firstLanguage;
      const shouldStore = options.store !== false;

      tabs.forEach((tab) => {
        const tabLanguage = tab.getAttribute('data-translation-tab') || '';
        const isActive = tabLanguage === language;
        tab.classList.toggle('menu-builder-tab-active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        tab.tabIndex = isActive ? 0 : -1;
      });

      panels.forEach((panel) => {
        const panelLanguage = panel.getAttribute('data-translation-panel') || '';
        panel.hidden = panelLanguage !== language;
      });

      if (shouldStore && typeof window.sessionStorage !== 'undefined') {
        window.sessionStorage.setItem(storageKey, language);
      }

      if (window.location.hash !== `#translation-${language}`) {
        window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#translation-${language}`);
      }

      return language;
    };

    const moveFocus = (currentIndex, delta) => {
      const nextIndex = (currentIndex + delta + tabs.length) % tabs.length;
      const nextTab = tabs[nextIndex];
      if (!(nextTab instanceof HTMLButtonElement)) {
        return;
      }

      const nextLanguage = nextTab.getAttribute('data-translation-tab') || firstLanguage;
      setActiveLanguage(nextLanguage);
      nextTab.focus();
    };

    tabs.forEach((tab, index) => {
      if (!(tab instanceof HTMLButtonElement)) {
        return;
      }

      tab.addEventListener('click', () => {
        setActiveLanguage(tab.getAttribute('data-translation-tab') || firstLanguage);
      });

      tab.addEventListener('keydown', (event) => {
        if (!(event instanceof KeyboardEvent)) {
          return;
        }

        if (event.key === 'ArrowRight') {
          event.preventDefault();
          moveFocus(index, 1);
          return;
        }

        if (event.key === 'ArrowLeft') {
          event.preventDefault();
          moveFocus(index, -1);
          return;
        }

        if (event.key === 'Home') {
          event.preventDefault();
          const firstTab = tabs[0];
          if (firstTab instanceof HTMLButtonElement) {
            setActiveLanguage(firstTab.getAttribute('data-translation-tab') || firstLanguage);
            firstTab.focus();
          }
          return;
        }

        if (event.key === 'End') {
          event.preventDefault();
          const lastTab = tabs[tabs.length - 1];
          if (lastTab instanceof HTMLButtonElement) {
            setActiveLanguage(lastTab.getAttribute('data-translation-tab') || firstLanguage);
            lastTab.focus();
          }
        }
      });
    });

    let preferredLanguage = '';
    const hashMatch = window.location.hash.match(/^#translation-([a-z]{2})$/i);
    if (Array.isArray(hashMatch) && typeof hashMatch[1] === 'string') {
      preferredLanguage = hashMatch[1].toLowerCase();
    }

    if (preferredLanguage === '' && typeof window.sessionStorage !== 'undefined') {
      preferredLanguage = window.sessionStorage.getItem(storageKey) || '';
    }

    setActiveLanguage(preferredLanguage, { store: preferredLanguage !== '' });
  })();
</script>
<script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
  (() => {
    const editor = document.querySelector('[data-page-tile-editor]');
    const addButton = document.querySelector('[data-page-tile-add-placement]');
    const list = document.querySelector('[data-page-tile-placement-list]');
    if (!(editor instanceof HTMLElement) || !(list instanceof HTMLElement)) {
      return;
    }

    let bootstrap = {};
    try {
      bootstrap = JSON.parse(editor.getAttribute('data-page-tile-bootstrap') || '{}');
    } catch (error) {
      bootstrap = {};
    }

    const languages = Array.isArray(bootstrap.languages) ? bootstrap.languages.filter((language) => typeof language === 'string' && language !== '') : [];
    const groups = Array.isArray(bootstrap.groups) ? bootstrap.groups : [];
    const pageOptions = Array.isArray(bootstrap.pageOptions) ? bootstrap.pageOptions : [];
    const groupMap = new Map(groups.map((group) => [String(group.id || ''), group]));

    const escapeHtml = (value) => String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');

    const defaultTranslations = () => {
      const translations = {};
      languages.forEach((language) => {
        translations[language] = { label: '', alt: '', title: '' };
      });

      return translations;
    };

    const defaultOverride = () => ({
      visibility_mode: 'default',
      target_mode: 'default',
      target_page_slug: '',
      target_route: '',
      target_url: '',
      translations: defaultTranslations(),
    });

    const normalizeOverride = (raw) => {
      const normalized = defaultOverride();
      const source = raw && typeof raw === 'object' ? raw : {};

      normalized.visibility_mode = typeof source.visibility_mode === 'string' && source.visibility_mode !== ''
        ? source.visibility_mode
        : 'default';
      normalized.target_mode = typeof source.target_mode === 'string' && source.target_mode !== ''
        ? source.target_mode
        : 'default';
      normalized.target_page_slug = typeof source.target_page_slug === 'string' ? source.target_page_slug : '';
      normalized.target_route = typeof source.target_route === 'string' ? source.target_route : '';
      normalized.target_url = typeof source.target_url === 'string' ? source.target_url : '';

      const rawTranslations = source.translations && typeof source.translations === 'object' ? source.translations : {};
      languages.forEach((language) => {
        const translation = rawTranslations[language] && typeof rawTranslations[language] === 'object'
          ? rawTranslations[language]
          : {};
        normalized.translations[language] = {
          label: typeof translation.label === 'string' ? translation.label : '',
          alt: typeof translation.alt === 'string' ? translation.alt : '',
          title: typeof translation.title === 'string' ? translation.title : '',
        };
      });

      return normalized;
    };

    const normalizePlacement = (raw, index) => {
      const source = raw && typeof raw === 'object' ? raw : {};
      const overrides = {};
      const rawOverrides = source.overrides && typeof source.overrides === 'object' ? source.overrides : {};
      Object.keys(rawOverrides).forEach((itemUid) => {
        overrides[itemUid] = normalizeOverride(rawOverrides[itemUid]);
      });

      return {
        placement_id: typeof source.placement_id === 'string' ? source.placement_id : '',
        group_id: source.group_id != null ? String(source.group_id) : '',
        sort_order: source.sort_order != null && String(source.sort_order) !== '' ? String(source.sort_order) : String((index + 1) * 10),
        overrides,
      };
    };

    const placements = Array.isArray(bootstrap.placements)
      ? bootstrap.placements.map((placement, index) => normalizePlacement(placement, index))
      : [];

    const defaultPlacement = () => ({
      placement_id: '',
      group_id: '',
      sort_order: String((placements.length + 1) * 10),
      overrides: {},
    });

    const ensureOverride = (placement, itemUid) => {
      if (!placement.overrides[itemUid]) {
        placement.overrides[itemUid] = defaultOverride();
      }

      return placement.overrides[itemUid];
    };

    const groupOptionsHtml = (selectedGroupId) => {
      const options = ['<option value="">Choisir un groupe</option>'];
      groups.forEach((group) => {
        const groupId = String(group.id || '');
        const selected = groupId === selectedGroupId ? ' selected' : '';
        const itemCount = Array.isArray(group.items) ? group.items.length : 0;
        options.push(`<option value="${escapeHtml(groupId)}"${selected}>${escapeHtml(group.name || groupId)} · ${itemCount} tuile(s)</option>`);
      });

      return options.join('');
    };

    const pageOptionLabel = (pageOption) => {
      const slug = String(pageOption.slug || '');
      const route = String(pageOption.route || '');
      const status = String(pageOption.status || '');

      return [String(pageOption.title || slug), route, status].filter((value) => value !== '').join(' · ');
    };

    const pageOptionLabels = new Map();
    pageOptions.forEach((pageOption) => {
      const slug = String(pageOption.slug || '');
      if (slug === '') {
        return;
      }

      pageOptionLabels.set(slug, pageOptionLabel(pageOption));
    });

    const pageOptionsHtml = (selectedSlug) => {
      const options = ['<option value="">Choisir une page</option>'];
      pageOptions.forEach((pageOption) => {
        const slug = String(pageOption.slug || '');
        if (slug === '') {
          return;
        }

        const label = pageOptionLabel(pageOption);
        const selected = slug === selectedSlug ? ' selected' : '';
        options.push(`<option value="${escapeHtml(slug)}"${selected}>${escapeHtml(label)}</option>`);
      });

      return options.join('');
    };

    const hasTileTranslationOverride = (override) => {
      const translations = override && typeof override === 'object' ? override.translations : null;
      if (!translations || typeof translations !== 'object') {
        return false;
      }

      return Object.values(translations).some((translation) => {
        if (!translation || typeof translation !== 'object') {
          return false;
        }

        return ['label', 'alt', 'title'].some((field) => {
          return typeof translation[field] === 'string' && translation[field].trim() !== '';
        });
      });
    };

    const hiddenOverrideInputsHtml = (placementIndex, itemUid, override) => {
      const prefix = `tile_placements[${placementIndex}][overrides][${itemUid}]`;
      const inputs = [
        `<input type="hidden" name="${escapeHtml(prefix)}[visibility_mode]" value="${escapeHtml(override.visibility_mode || 'default')}" />`,
        `<input type="hidden" name="${escapeHtml(prefix)}[target_mode]" value="${escapeHtml(override.target_mode || 'default')}" />`,
        `<input type="hidden" name="${escapeHtml(prefix)}[target_page_slug]" value="${escapeHtml(override.target_page_slug || '')}" />`,
        `<input type="hidden" name="${escapeHtml(prefix)}[target_route]" value="${escapeHtml(override.target_route || '')}" />`,
        `<input type="hidden" name="${escapeHtml(prefix)}[target_url]" value="${escapeHtml(override.target_url || '')}" />`,
      ];

      languages.forEach((language) => {
        const translation = override.translations[language] || { label: '', alt: '', title: '' };
        const translationPrefix = `${prefix}[translations][${language}]`;
        inputs.push(`<input type="hidden" name="${escapeHtml(translationPrefix)}[label]" value="${escapeHtml(translation.label || '')}" />`);
        inputs.push(`<input type="hidden" name="${escapeHtml(translationPrefix)}[alt]" value="${escapeHtml(translation.alt || '')}" />`);
        inputs.push(`<input type="hidden" name="${escapeHtml(translationPrefix)}[title]" value="${escapeHtml(translation.title || '')}" />`);
      });

      return inputs.join('');
    };

    const renderGroupItemsHtml = (placement, placementIndex) => {
      const group = groupMap.get(String(placement.group_id || ''));
      if (!group || !Array.isArray(group.items) || group.items.length === 0) {
        return '<p class="notice-muted">Choisissez un groupe pour afficher les tuiles et leurs overrides.</p>';
      }

        return group.items.map((item) => {
        const itemUid = String(item.item_uid || '');
        const override = ensureOverride(placement, itemUid);
        const targetMode = override.target_mode || 'default';
        const hasAdvancedTargetOverride = targetMode === 'route' || targetMode === 'external';
        const hasTranslationOverride = hasTileTranslationOverride(override);
        const visibleTargetMode = hasAdvancedTargetOverride
          ? 'advanced'
          : (targetMode === 'page' ? 'page' : 'default');
        const image = String(item.image_src || '');
        const tileSize = String(item.tile_size || '<?php echo \Caramagnols\Content\TileRepository::DEFAULT_SIZE; ?>');
        const imageHtml = image !== ''
          ? `<img src="${escapeHtml(image)}" alt="" loading="lazy" decoding="async" fetchpriority="low" style="max-width: 10rem; width: 100%; height: auto; border-radius: 0.75rem;" />`
          : '<span class="notice-muted">Sans image</span>';
        const localTargetSummary = (() => {
          if (targetMode === 'page' && String(override.target_page_slug || '') !== '') {
            return `Page locale : ${pageOptionLabels.get(String(override.target_page_slug || '')) || String(override.target_page_slug || '')}`;
          }

          if (targetMode === 'route') {
            return `Cible avancée locale conservée : ${String(override.target_route || 'route interne')}`;
          }

          if (targetMode === 'external') {
            return `Cible avancée locale conservée : ${String(override.target_url || 'URL externe')}`;
          }

          return 'Cible locale : défaut du groupe';
        })();
        const overrideNotes = [];
        if (hasAdvancedTargetOverride) {
          overrideNotes.push('Une cible locale avancée existe déjà. Elle est conservée tant que vous ne la remplacez pas ici.');
        }
        if (hasTranslationOverride) {
          overrideNotes.push('Des textes locaux personnalisés existent déjà. Ils sont conservés en arrière-plan depuis cet écran simplifié.');
        }

        return `
          <article class="nested-card page-tile-placement-item">
            <div class="page-editor-intro__header">
              <div>
                <strong>${escapeHtml(item.label || 'Tuile')}</strong>
                <p class="notice-muted">Cible par défaut : ${escapeHtml(item.target_summary || 'Aucune')}</p>
                <p class="notice-muted">${escapeHtml(localTargetSummary)}</p>
              </div>
              <div class="actions-inline">
                <span class="lang-badge">${escapeHtml(tileSize.toUpperCase())}</span>
                <span class="lang-badge">${escapeHtml(String(item.color_token || '').toUpperCase())}</span>
              </div>
            </div>

            ${hiddenOverrideInputsHtml(placementIndex, itemUid, override)}

            <div class="admin-form-grid admin-form-grid-3">
              <div class="field">
                <label>Visibilité</label>
                <select data-page-tile-ui-visibility data-item-uid="${escapeHtml(itemUid)}">
                  <option value="default"${override.visibility_mode === 'default' ? ' selected' : ''}>Défaut du groupe</option>
                  <option value="visible"${override.visibility_mode === 'visible' ? ' selected' : ''}>Forcer visible</option>
                  <option value="hidden"${override.visibility_mode === 'hidden' ? ' selected' : ''}>Masquer sur cette page</option>
                </select>
              </div>

              <div class="field">
                <label>Cible locale</label>
                <select data-page-tile-ui-target-mode data-item-uid="${escapeHtml(itemUid)}">
                  <option value="default"${visibleTargetMode === 'default' ? ' selected' : ''}>Défaut du groupe</option>
                  <option value="page"${visibleTargetMode === 'page' ? ' selected' : ''}>Remplacer par une page</option>
                  ${hasAdvancedTargetOverride ? '<option value="advanced" selected>Conserver la cible avancée</option>' : ''}
                </select>
              </div>

              <div class="field">
                <label>Aperçu</label>
                ${imageHtml}
              </div>

              <div class="field admin-form-span-2" data-page-tile-target-wrapper="page"${visibleTargetMode === 'page' ? '' : ' hidden'}>
                <label>Page cible locale</label>
                <select data-page-tile-ui-target-page data-item-uid="${escapeHtml(itemUid)}">
                  ${pageOptionsHtml(override.target_page_slug || '')}
                </select>
              </div>
            </div>

            ${overrideNotes.length > 0 ? `<p class="notice-muted">${overrideNotes.map((note) => escapeHtml(note)).join('<br>')}</p>` : ''}
          </article>
        `;
      }).join('');
    };

    const renderPlacements = () => {
      if (placements.length === 0) {
        list.innerHTML = '<p class="notice-muted">Aucun groupe de tuiles n est encore attaché à cette page.</p>';
        return;
      }

      list.innerHTML = placements.map((placement, index) => `
        <article class="nested-card page-tile-placement" data-page-tile-placement data-placement-index="${index}">
          <div class="page-editor-intro__header">
            <div>
              <strong>Groupe ${index + 1}</strong>
              <p class="notice-muted">Ordre d affichage dans after_body.</p>
            </div>
            <button type="button" class="button-link button-link-muted" data-page-tile-remove-placement>Retirer ce groupe</button>
          </div>

          <div class="admin-form-grid admin-form-grid-2">
            <div class="field">
              <label>Groupe</label>
              <select name="tile_placements[${index}][group_id]" data-page-tile-group>
                ${groupOptionsHtml(String(placement.group_id || ''))}
              </select>
            </div>

            <div class="field">
              <label>Ordre</label>
              <input type="number" min="0" step="10" name="tile_placements[${index}][sort_order]" value="${escapeHtml(placement.sort_order || '')}" data-page-tile-sort />
            </div>
          </div>

          <div class="page-tile-placement__items">
            ${renderGroupItemsHtml(placement, index)}
          </div>
        </article>
      `).join('');
    };

    const placementFromEvent = (eventTarget) => {
      if (!(eventTarget instanceof HTMLElement)) {
        return null;
      }

      const placementElement = eventTarget.closest('[data-page-tile-placement]');
      if (!(placementElement instanceof HTMLElement)) {
        return null;
      }

      const index = Number.parseInt(placementElement.getAttribute('data-placement-index') || '', 10);
      if (Number.isNaN(index) || !placements[index]) {
        return null;
      }

      return { placement: placements[index], index };
    };

    list.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      if (target.matches('[data-page-tile-remove-placement]')) {
        const resolved = placementFromEvent(target);
        if (!resolved) {
          return;
        }

        placements.splice(resolved.index, 1);
        renderPlacements();
      }
    });

    list.addEventListener('change', (event) => {
      const target = event.target;
      const resolved = placementFromEvent(target instanceof HTMLElement ? target : null);
      if (!resolved) {
        return;
      }

      const { placement } = resolved;
      const itemUid = target instanceof HTMLElement ? (target.getAttribute('data-item-uid') || '') : '';

      if (target instanceof HTMLSelectElement && target.matches('[data-page-tile-group]')) {
        placement.group_id = target.value;
        renderPlacements();
        return;
      }

      if (target instanceof HTMLSelectElement && target.matches('[data-page-tile-ui-visibility]') && itemUid !== '') {
        const override = ensureOverride(placement, itemUid);
        override.visibility_mode = target.value;
        renderPlacements();
        return;
      }

      if (target instanceof HTMLSelectElement && target.matches('[data-page-tile-ui-target-mode]') && itemUid !== '') {
        if (target.value === 'advanced') {
          return;
        }

        const override = ensureOverride(placement, itemUid);
        override.target_mode = target.value;
        renderPlacements();
        return;
      }

      if (target instanceof HTMLSelectElement && target.matches('[data-page-tile-ui-target-page]') && itemUid !== '') {
        const override = ensureOverride(placement, itemUid);
        override.target_page_slug = target.value;
        override.target_mode = target.value === '' ? 'default' : 'page';
        renderPlacements();
      }
    });

    list.addEventListener('input', (event) => {
      const target = event.target;
      const resolved = placementFromEvent(target instanceof HTMLElement ? target : null);
      if (!resolved) {
        return;
      }

      const { placement } = resolved;
      if (target instanceof HTMLInputElement && target.matches('[data-page-tile-sort]')) {
        placement.sort_order = target.value;
        return;
      }

      if (!(target instanceof HTMLInputElement)) {
        return;
      }
    });

    if (addButton instanceof HTMLButtonElement) {
      addButton.addEventListener('click', () => {
        placements.push(defaultPlacement());
        renderPlacements();
      });
    }

    renderPlacements();
  })();
</script>
<script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
  (() => {
    const form = document.getElementById('page-editor-form');
    const stateField = document.getElementById('page-editor-state-json');
    if (!(form instanceof HTMLFormElement) || !(stateField instanceof HTMLInputElement)) {
      return;
    }

    const tokenizeFieldName = (name) => {
      const matches = name.match(/[^[\]]+/g);
      return Array.isArray(matches) ? matches : [];
    };

    const assignFieldValue = (target, name, value) => {
      const tokens = tokenizeFieldName(name);
      if (tokens.length === 0) {
        return;
      }

      let current = target;
      tokens.forEach((token, index) => {
        const isLast = index === tokens.length - 1;
        if (isLast) {
          current[token] = value;
          return;
        }

        if (typeof current[token] !== 'object' || current[token] === null || Array.isArray(current[token])) {
          current[token] = {};
        }

        current = current[token];
      });
    };

    const isSerializableField = (field) => {
      if (!(field instanceof HTMLElement)) {
        return false;
      }

      const name = field.getAttribute('name') || '';
      if (name === '' || name === 'csrf_token' || name === 'page_action' || name === 'page_state_json') {
        return false;
      }

      if (
        field instanceof HTMLButtonElement
        || (field instanceof HTMLInputElement && (field.type === 'submit' || field.type === 'button'))
      ) {
        return false;
      }

      if (field instanceof HTMLInputElement && field.type === 'file') {
        return false;
      }

      if (
        field instanceof HTMLInputElement
        && (field.type === 'checkbox' || field.type === 'radio')
        && !field.checked
      ) {
        return false;
      }

      return true;
    };

    const freezeFieldsForSubmit = (submitter) => {
      Array.from(form.elements).forEach((field) => {
        if (
          field instanceof HTMLInputElement
          || field instanceof HTMLSelectElement
          || field instanceof HTMLTextAreaElement
          || field instanceof HTMLButtonElement
        ) {
          const name = field.getAttribute('name') || '';
          const shouldKeepEnabled = name === 'csrf_token'
            || name === 'page_state_json'
            || field === submitter
            || (field instanceof HTMLInputElement && field.type === 'file');
          field.disabled = !shouldKeepEnabled;
        }
      });
    };

    form.addEventListener('submit', (event) => {
      const payload = {};
      Array.from(form.elements).forEach((field) => {
        if (!isSerializableField(field)) {
          return;
        }

        if (
          field instanceof HTMLInputElement
          || field instanceof HTMLSelectElement
          || field instanceof HTMLTextAreaElement
        ) {
          assignFieldValue(payload, field.name, field.value);
        }
      });

      stateField.value = JSON.stringify(payload);
      freezeFieldsForSubmit(event.submitter instanceof HTMLElement ? event.submitter : null);
    });
  })();
</script>
<script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
  (() => {
    const editor = document.querySelector('[data-shared-media-editor]');
    if (!(editor instanceof HTMLElement)) {
      return;
    }

    const list = editor.querySelector('[data-shared-media-list]');
    const addButton = editor.querySelector('[data-shared-media-add-row]');
    const template = document.getElementById('shared-media-row-template');
    if (!(list instanceof HTMLElement) || !(addButton instanceof HTMLButtonElement) || !(template instanceof HTMLTemplateElement)) {
      return;
    }

    const resolveNextIndex = () => {
      let maxIndex = -1;
      list.querySelectorAll('[data-shared-media-row]').forEach((row) => {
        if (!(row instanceof HTMLElement)) {
          return;
        }

        const value = Number.parseInt(row.getAttribute('data-shared-media-index') || '', 10);
        if (!Number.isNaN(value) && value > maxIndex) {
          maxIndex = value;
        }
      });

      return maxIndex + 1;
    };

    let nextIndex = resolveNextIndex();

    const renderTemplate = (index) => template.innerHTML.replaceAll('__INDEX__', String(index)).trim();

    const updateRowPreview = (row) => {
      if (!(row instanceof HTMLElement)) {
        return;
      }

      const srcInput = row.querySelector('[data-shared-media-src]');
      const altInput = row.querySelector('[data-shared-media-alt]');
      const previewWrapper = row.querySelector('[data-shared-media-preview-wrapper]');
      const previewImage = row.querySelector('[data-shared-media-preview]');
      if (!(srcInput instanceof HTMLInputElement) || !(previewWrapper instanceof HTMLElement) || !(previewImage instanceof HTMLImageElement)) {
        return;
      }

      const src = srcInput.value.trim();
      const alt = altInput instanceof HTMLInputElement ? altInput.value.trim() : '';
      if (src === '') {
        previewImage.removeAttribute('src');
        previewWrapper.hidden = true;
        return;
      }

      previewImage.src = src;
      previewImage.alt = alt !== '' ? alt : previewImage.alt;
      previewWrapper.hidden = false;
    };

    const bindRow = (row) => {
      if (!(row instanceof HTMLElement)) {
        return;
      }

      const removeButton = row.querySelector('[data-shared-media-remove-row]');
      if (removeButton instanceof HTMLButtonElement) {
        removeButton.addEventListener('click', () => {
          row.remove();
        });
      }

      const srcInput = row.querySelector('[data-shared-media-src]');
      if (srcInput instanceof HTMLInputElement) {
        srcInput.addEventListener('input', () => updateRowPreview(row));
        srcInput.addEventListener('change', () => updateRowPreview(row));
      }

      const altInput = row.querySelector('[data-shared-media-alt]');
      if (altInput instanceof HTMLInputElement) {
        altInput.addEventListener('input', () => updateRowPreview(row));
      }

      updateRowPreview(row);
    };

    const appendRow = (initialSrc = '') => {
      const index = nextIndex;
      nextIndex += 1;

      const wrapper = document.createElement('div');
      wrapper.innerHTML = renderTemplate(index);
      const row = wrapper.firstElementChild;
      if (!(row instanceof HTMLElement)) {
        return;
      }

      list.appendChild(row);
      bindRow(row);

      const srcInput = row.querySelector('[data-shared-media-src]');
      if (srcInput instanceof HTMLInputElement) {
        if (initialSrc !== '') {
          srcInput.value = initialSrc;
        }
        srcInput.focus();
        srcInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }

      updateRowPreview(row);
    };

    list.querySelectorAll('[data-shared-media-row]').forEach((row) => bindRow(row));

    addButton.addEventListener('click', () => {
      appendRow('');
    });

    editor.querySelectorAll('[data-shared-media-library-use]').forEach((button) => {
      if (!(button instanceof HTMLButtonElement)) {
        return;
      }

      button.addEventListener('click', () => {
        const src = button.getAttribute('data-shared-media-library-use') || '';
        appendRow(src);
      });
    });
  })();
</script>
<script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
  (() => {
    const escapeAttribute = (value) => String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('"', '&quot;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');

    const normalizeTokenList = (value) => {
      if (typeof value !== 'string' || value.trim() === '') {
        return [];
      }

      const unique = new Set();
      value.split(/\s+/).forEach((token) => {
        const normalized = token.trim();
        if (normalized === '' || /[^a-z0-9_-]/i.test(normalized)) {
          return;
        }
        unique.add(normalized);
      });

      return Array.from(unique);
    };

    const bytesToLabel = (bytes) => {
      const units = ['B', 'KB', 'MB', 'GB', 'TB'];
      let value = Number.isFinite(bytes) ? Math.max(0, Number(bytes)) : 0;
      let unitIndex = 0;
      while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
      }
      return unitIndex === 0 ? `${Math.round(value)} ${units[unitIndex]}` : `${value.toFixed(1)} ${units[unitIndex]}`;
    };

    const insertAtCursor = (textarea, snippet) => {
      if (!(textarea instanceof HTMLTextAreaElement)) {
        return;
      }

      const start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
      const end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : textarea.value.length;
      textarea.value = `${textarea.value.slice(0, start)}${snippet}${textarea.value.slice(end)}`;
      const nextCursor = start + snippet.length;
      textarea.setSelectionRange(nextCursor, nextCursor);
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
      textarea.focus();
    };

    const dialog = document.getElementById('page-media-insert-dialog');
    if (!(dialog instanceof HTMLDialogElement)) {
      return;
    }

    const closeButtons = dialog.querySelectorAll('[data-content-media-close]');
    const searchInput = dialog.querySelector('[data-content-media-search]');
    const folderSelect = dialog.querySelector('[data-content-media-folder]');
    const favoriteButtons = dialog.querySelectorAll('[data-content-media-favorite-folder]');
    const listRoot = dialog.querySelector('[data-content-media-list]');
    const emptyState = dialog.querySelector('[data-content-media-empty]');
    const presetSelect = dialog.querySelector('[data-content-media-preset]');
    const extraClassesInput = dialog.querySelector('[data-content-media-extra-classes]');
    const altInput = dialog.querySelector('[data-content-media-alt]');
    const lazyCheckbox = dialog.querySelector('[data-content-media-lazy]');
    const includeDimensionsCheckbox = dialog.querySelector('[data-content-media-include-dimensions]');
    const governanceStrictCheckbox = dialog.querySelector('[data-content-media-governance-strict]');
    const governanceFilterCheckbox = dialog.querySelector('[data-content-media-filter-governance]');
    const videoControlsCheckbox = dialog.querySelector('[data-content-media-video-controls]');
    const videoMutedCheckbox = dialog.querySelector('[data-content-media-video-muted]');
    const videoAutoplayCheckbox = dialog.querySelector('[data-content-media-video-autoplay]');
    const videoLoopCheckbox = dialog.querySelector('[data-content-media-video-loop]');
    const videoPosterInput = dialog.querySelector('[data-content-media-video-poster]');
    const auditButton = dialog.querySelector('[data-content-media-audit]');
    const auditStatus = dialog.querySelector('[data-content-media-audit-status]');
    const auditResults = dialog.querySelector('[data-content-media-audit-results]');
    const inlineStatus = dialog.querySelector('[data-content-media-status]');

    const parsePolicy = () => {
      const raw = dialog.getAttribute('data-content-media-policy') || '{}';
      let parsed = {};
      try {
        parsed = JSON.parse(raw);
      } catch (_error) {
        parsed = {};
      }

      const normalizeExtensions = (value) => (Array.isArray(value) ? value : [])
        .map((entry) => String(entry).trim().toLowerCase())
        .filter((entry) => entry !== '');

      const imageMaxBytes = Number.parseInt(String(parsed.imageMaxBytes ?? ''), 10);
      const videoMaxBytes = Number.parseInt(String(parsed.videoMaxBytes ?? ''), 10);

      return {
        context: String(parsed.context || 'page'),
        imageExtensions: normalizeExtensions(parsed.imageExtensions),
        videoExtensions: normalizeExtensions(parsed.videoExtensions),
        imageMaxBytes: Number.isNaN(imageMaxBytes) ? 0 : Math.max(0, imageMaxBytes),
        videoMaxBytes: Number.isNaN(videoMaxBytes) ? 0 : Math.max(0, videoMaxBytes),
        imageMaxLabel: String(parsed.imageMaxLabel || ''),
        videoMaxLabel: String(parsed.videoMaxLabel || ''),
      };
    };

    const policy = parsePolicy();

    const canonicalSource = (value) => {
      const normalized = String(value || '').trim();
      if (normalized === '') {
        return '';
      }

      return normalized.split('#')[0].split('?')[0];
    };

    const complianceForAsset = ({ kind, extension, sizeBytes }) => {
      const normalizedKind = String(kind || '').toLowerCase() === 'video' ? 'video' : 'image';
      const normalizedExtension = String(extension || '').toLowerCase().replace(/^\./, '');
      const normalizedSize = Number.isFinite(sizeBytes) ? Math.max(0, Number(sizeBytes)) : 0;
      const reasons = [];

      if (normalizedKind === 'image') {
        if (policy.imageExtensions.length > 0 && !policy.imageExtensions.includes(normalizedExtension)) {
          reasons.push(`format ${normalizedExtension || 'inconnu'} non autorise`);
        }
        if (policy.imageMaxBytes > 0 && normalizedSize > policy.imageMaxBytes) {
          reasons.push(`taille ${bytesToLabel(normalizedSize)} > ${policy.imageMaxLabel || bytesToLabel(policy.imageMaxBytes)}`);
        }
      } else {
        if (policy.videoExtensions.length > 0 && !policy.videoExtensions.includes(normalizedExtension)) {
          reasons.push(`format ${normalizedExtension || 'inconnu'} non autorise`);
        }
        if (policy.videoMaxBytes > 0 && normalizedSize > policy.videoMaxBytes) {
          reasons.push(`taille ${bytesToLabel(normalizedSize)} > ${policy.videoMaxLabel || bytesToLabel(policy.videoMaxBytes)}`);
        }
      }

      return {
        compliant: reasons.length === 0,
        reasons,
      };
    };

    const setInlineStatus = (message, isError = false) => {
      if (!(inlineStatus instanceof HTMLElement)) {
        return;
      }
      const normalized = String(message || '').trim();
      inlineStatus.hidden = normalized === '';
      inlineStatus.textContent = normalized;
      inlineStatus.classList.toggle('notice-error', isError && normalized !== '');
      inlineStatus.classList.toggle('notice-success', !isError && normalized !== '');
    };

    const clearAuditState = () => {
      if (auditStatus instanceof HTMLElement) {
        auditStatus.textContent = 'Lance un controle automatique (format, taille, source referencee) sur le champ cible.';
      }
      if (auditResults instanceof HTMLElement) {
        auditResults.innerHTML = '';
        auditResults.hidden = true;
      }
    };

    const mediaIndex = new Map();
    dialog.querySelectorAll('[data-content-media-item]').forEach((item) => {
      if (!(item instanceof HTMLElement)) {
        return;
      }
      const src = canonicalSource(item.getAttribute('data-content-media-src') || '');
      if (src === '' || mediaIndex.has(src)) {
        return;
      }
      const kind = (item.getAttribute('data-content-media-kind') || 'image').toLowerCase();
      const extension = (item.getAttribute('data-content-media-extension') || '').toLowerCase();
      const sizeBytes = Number.parseInt(item.getAttribute('data-content-media-size-bytes') || '0', 10);
      mediaIndex.set(src, {
        kind: kind === 'video' ? 'video' : 'image',
        extension,
        sizeBytes: Number.isNaN(sizeBytes) ? 0 : sizeBytes,
      });
    });

    const applyFilter = () => {
      if (!(listRoot instanceof HTMLElement)) {
        return;
      }

      const query = searchInput instanceof HTMLInputElement
        ? searchInput.value.trim().toLowerCase()
        : '';
      const selectedFolder = folderSelect instanceof HTMLSelectElement
        ? folderSelect.value.trim()
        : '';
      const onlyGovernedAssets = governanceFilterCheckbox instanceof HTMLInputElement && governanceFilterCheckbox.checked;
      let visibleCount = 0;

      listRoot.querySelectorAll('[data-content-media-item]').forEach((node) => {
        if (!(node instanceof HTMLElement)) {
          return;
        }

        const haystack = (node.getAttribute('data-content-media-filter') || '').toLowerCase();
        const folder = (node.getAttribute('data-content-media-folder') || '').trim();
        const extension = (node.getAttribute('data-content-media-extension') || '').trim().toLowerCase();
        const kind = (node.getAttribute('data-content-media-kind') || 'image').toLowerCase();
        const sizeBytes = Number.parseInt(node.getAttribute('data-content-media-size-bytes') || '0', 10);
        const governance = complianceForAsset({
          kind,
          extension,
          sizeBytes: Number.isNaN(sizeBytes) ? 0 : sizeBytes,
        });
        const searchMatch = query === '' || haystack.includes(query);
        const folderMatch = selectedFolder === '' || folder === selectedFolder || folder.startsWith(`${selectedFolder}/`);
        const governanceMatch = !onlyGovernedAssets || governance.compliant;
        const match = searchMatch && folderMatch && governanceMatch;
        node.hidden = !match;
        node.setAttribute('data-content-media-compliant', governance.compliant ? '1' : '0');
        node.setAttribute('data-content-media-policy-hint', governance.reasons.join(' · '));

        const badge = node.querySelector('[data-content-media-policy-badge]');
        if (badge instanceof HTMLElement) {
          badge.textContent = governance.compliant
            ? 'Conforme gouvernance'
            : `Hors charte: ${governance.reasons.join(' · ')}`;
        }

        if (match) {
          visibleCount += 1;
        }
      });

      if (emptyState instanceof HTMLElement) {
        emptyState.hidden = visibleCount > 0;
      }
    };

    const buildClassAttribute = (presetClasses, customClasses) => {
      const classes = [...normalizeTokenList(presetClasses), ...normalizeTokenList(customClasses)];
      if (classes.length === 0) {
        return '';
      }
      return ` class="${escapeAttribute(classes.join(' '))}"`;
    };

    const buildMediaSnippet = ({ src, kind, width, height }) => {
      const safeSrc = escapeAttribute(src);
      const preset = presetSelect instanceof HTMLSelectElement ? presetSelect.value : 'figure-default';
      const extraClasses = extraClassesInput instanceof HTMLInputElement ? extraClassesInput.value : '';
      const presetConfig = {
        'figure-default': { figureClass: 'media-figure', mediaClass: 'media-asset' },
        'figure-wide': { figureClass: 'media-figure media-figure--wide', mediaClass: 'media-asset media-asset--wide' },
        'figure-left': { figureClass: 'media-figure media-figure--left', mediaClass: 'media-asset media-asset--left' },
        'figure-right': { figureClass: 'media-figure media-figure--right', mediaClass: 'media-asset media-asset--right' },
        'raw': { figureClass: '', mediaClass: '' },
      }[preset] || { figureClass: 'media-figure', mediaClass: 'media-asset' };
      const mediaClassAttribute = buildClassAttribute(presetConfig.mediaClass, extraClasses);
      const figureClassAttribute = preset === 'raw' ? '' : buildClassAttribute(presetConfig.figureClass, '');

      if (kind === 'video') {
        const controls = !(videoControlsCheckbox instanceof HTMLInputElement) || videoControlsCheckbox.checked;
        const muted = videoMutedCheckbox instanceof HTMLInputElement && videoMutedCheckbox.checked;
        const autoplay = videoAutoplayCheckbox instanceof HTMLInputElement && videoAutoplayCheckbox.checked;
        const loop = videoLoopCheckbox instanceof HTMLInputElement && videoLoopCheckbox.checked;
        const poster = videoPosterInput instanceof HTMLInputElement ? videoPosterInput.value.trim() : '';
        const attributes = [];
        if (controls) {
          attributes.push('controls');
        }
        attributes.push('preload="metadata"');
        attributes.push('playsinline');
        if (autoplay || muted) {
          attributes.push('muted');
        }
        if (autoplay) {
          attributes.push('autoplay');
        }
        if (loop) {
          attributes.push('loop');
        }
        if (poster !== '') {
          attributes.push(`poster="${escapeAttribute(poster)}"`);
        }
        attributes.push(`src="${safeSrc}"`);
        if (mediaClassAttribute !== '') {
          attributes.push(mediaClassAttribute.trim());
        }

        const videoTag = `<video ${attributes.join(' ')}></video>`;
        return preset === 'raw'
          ? `\n${videoTag}\n`
          : `\n<figure${figureClassAttribute}>\n  ${videoTag}\n</figure>\n`;
      }

      const altValue = altInput instanceof HTMLInputElement ? altInput.value.trim() : '';
      const useLazy = !(lazyCheckbox instanceof HTMLInputElement) || lazyCheckbox.checked;
      const includeDimensions = !(includeDimensionsCheckbox instanceof HTMLInputElement) || includeDimensionsCheckbox.checked;
      const widthAttr = includeDimensions && Number.isInteger(width) && width > 0 ? ` width="${width}"` : '';
      const heightAttr = includeDimensions && Number.isInteger(height) && height > 0 ? ` height="${height}"` : '';
      const loadingAttributes = useLazy ? ' loading="lazy" decoding="async" fetchpriority="low"' : '';
      const classAttribute = mediaClassAttribute;
      const imageTag = `<img src="${safeSrc}" alt="${escapeAttribute(altValue)}"${classAttribute}${loadingAttributes}${widthAttr}${heightAttr} />`;
      return preset === 'raw'
        ? `\n${imageTag}\n`
        : `\n<figure${figureClassAttribute}>\n  ${imageTag}\n</figure>\n`;
    };

    const resolveTargetTextarea = () => {
      const textareaId = dialog.getAttribute('data-content-media-target') || '';
      if (textareaId === '') {
        return null;
      }
      const textarea = document.getElementById(textareaId);
      return textarea instanceof HTMLTextAreaElement ? textarea : null;
    };

    const inferKindFromSource = (source, typeHint = '') => {
      const normalizedType = String(typeHint || '').toLowerCase();
      if (normalizedType.startsWith('video/')) {
        return 'video';
      }
      if (normalizedType.startsWith('image/')) {
        return 'image';
      }

      const normalizedSource = canonicalSource(source).toLowerCase();
      const extension = normalizedSource.includes('.')
        ? normalizedSource.split('.').pop() || ''
        : '';
      return ['mp4', 'webm', 'ogv', 'ogg', 'mov', 'm4v'].includes(extension) ? 'video' : 'image';
    };

    const extractMediaSources = (rawHtml) => {
      if (typeof rawHtml !== 'string' || rawHtml.trim() === '') {
        return [];
      }

      const template = document.createElement('template');
      template.innerHTML = rawHtml;

      const found = [];
      const seen = new Set();
      const pushSource = (kind, src) => {
        const normalized = canonicalSource(src);
        if (normalized === '') {
          return;
        }
        const key = `${kind}::${normalized}`;
        if (seen.has(key)) {
          return;
        }
        seen.add(key);
        found.push({ kind, src: normalized });
      };

      template.content.querySelectorAll('img[src]').forEach((node) => {
        pushSource('image', node.getAttribute('src') || '');
      });
      template.content.querySelectorAll('video[src]').forEach((node) => {
        pushSource('video', node.getAttribute('src') || '');
      });
      template.content.querySelectorAll('video source[src], source[src]').forEach((node) => {
        pushSource(inferKindFromSource(node.getAttribute('src') || '', node.getAttribute('type') || ''), node.getAttribute('src') || '');
      });

      return found;
    };

    document.querySelectorAll('[data-content-media-open="page-media-insert-dialog"]').forEach((button) => {
      if (!(button instanceof HTMLElement)) {
        return;
      }

      button.addEventListener('click', () => {
        const targetId = button.getAttribute('data-content-media-target') || '';
        dialog.setAttribute('data-content-media-target', targetId);
        setInlineStatus('', false);
        clearAuditState();
        dialog.showModal();
        if (searchInput instanceof HTMLInputElement) {
          searchInput.value = '';
        }
        if (folderSelect instanceof HTMLSelectElement) {
          folderSelect.value = '';
        }
        applyFilter();
        if (searchInput instanceof HTMLInputElement) {
          searchInput.focus();
        }
      });
    });

    closeButtons.forEach((button) => {
      if (!(button instanceof HTMLElement)) {
        return;
      }

      button.addEventListener('click', () => dialog.close());
    });

    if (searchInput instanceof HTMLInputElement) {
      searchInput.addEventListener('input', applyFilter);
    }
    if (folderSelect instanceof HTMLSelectElement) {
      folderSelect.addEventListener('change', applyFilter);
    }
    if (governanceFilterCheckbox instanceof HTMLInputElement) {
      governanceFilterCheckbox.addEventListener('change', applyFilter);
    }

    favoriteButtons.forEach((button) => {
      if (!(button instanceof HTMLElement)) {
        return;
      }
      button.addEventListener('click', () => {
        if (!(folderSelect instanceof HTMLSelectElement)) {
          return;
        }
        const favoriteFolder = button.getAttribute('data-content-media-favorite-folder') || '';
        folderSelect.value = favoriteFolder;
        applyFilter();
      });
    });

    if (auditButton instanceof HTMLElement) {
      auditButton.addEventListener('click', () => {
        const textarea = resolveTargetTextarea();
        if (!(textarea instanceof HTMLTextAreaElement)) {
          if (auditStatus instanceof HTMLElement) {
            auditStatus.textContent = 'Aucun champ cible actif pour l audit.';
          }
          return;
        }

        const references = extractMediaSources(textarea.value);
        if (references.length === 0) {
          if (auditStatus instanceof HTMLElement) {
            auditStatus.textContent = 'Audit termine: aucun media detecte dans le contenu.';
          }
          if (auditResults instanceof HTMLElement) {
            auditResults.innerHTML = '';
            auditResults.hidden = true;
          }
          return;
        }

        const issues = [];
        references.forEach((reference) => {
          const indexed = mediaIndex.get(reference.src);
          if (!indexed) {
            issues.push(`Source non referencee dans la bibliotheque: ${reference.src}`);
            return;
          }

          const governance = complianceForAsset({
            kind: reference.kind === 'video' ? 'video' : indexed.kind,
            extension: indexed.extension,
            sizeBytes: indexed.sizeBytes,
          });
          if (!governance.compliant) {
            issues.push(`${reference.src} -> ${governance.reasons.join(' · ')}`);
          }
        });

        if (auditStatus instanceof HTMLElement) {
          auditStatus.textContent = issues.length === 0
            ? `Audit termine: ${references.length} media conforme(s).`
            : `Audit termine: ${issues.length} anomalie(s) sur ${references.length} media.`;
        }

        if (auditResults instanceof HTMLElement) {
          auditResults.innerHTML = '';
          if (issues.length === 0) {
            auditResults.hidden = true;
          } else {
            issues.forEach((issue) => {
              const item = document.createElement('li');
              item.textContent = issue;
              auditResults.appendChild(item);
            });
            auditResults.hidden = false;
          }
        }
      });
    }

    if (listRoot instanceof HTMLElement) {
      listRoot.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const insertButton = target.closest('[data-content-media-insert]');
        if (!(insertButton instanceof HTMLElement)) {
          return;
        }

        const textarea = resolveTargetTextarea();
        if (!(textarea instanceof HTMLTextAreaElement)) {
          return;
        }

        const src = insertButton.getAttribute('data-content-media-src') || '';
        const kind = (insertButton.getAttribute('data-content-media-kind') || 'image').toLowerCase();
        const extension = (insertButton.getAttribute('data-content-media-extension') || '').toLowerCase();
        const sizeBytes = Number.parseInt(insertButton.getAttribute('data-content-media-size-bytes') || '0', 10);
        const width = Number.parseInt(insertButton.getAttribute('data-content-media-width') || '', 10);
        const height = Number.parseInt(insertButton.getAttribute('data-content-media-height') || '', 10);
        if (src.trim() === '') {
          return;
        }

        const governance = complianceForAsset({
          kind: kind === 'video' ? 'video' : 'image',
          extension,
          sizeBytes: Number.isNaN(sizeBytes) ? 0 : sizeBytes,
        });
        const strictGovernance = !(governanceStrictCheckbox instanceof HTMLInputElement) || governanceStrictCheckbox.checked;
        if (strictGovernance && !governance.compliant) {
          setInlineStatus(`Insertion bloquee: ${governance.reasons.join(' · ')}`, true);
          return;
        }

        const snippet = buildMediaSnippet({
          src,
          kind: kind === 'video' ? 'video' : 'image',
          width: Number.isNaN(width) ? 0 : width,
          height: Number.isNaN(height) ? 0 : height,
        });

        insertAtCursor(textarea, snippet);
        setInlineStatus('Media insere dans la region.', false);
        dialog.close();
      });
    }

    if (videoAutoplayCheckbox instanceof HTMLInputElement && videoMutedCheckbox instanceof HTMLInputElement) {
      videoAutoplayCheckbox.addEventListener('change', () => {
        if (videoAutoplayCheckbox.checked) {
          videoMutedCheckbox.checked = true;
        }
      });
    }

    applyFilter();
  })();
</script>
<script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
  (() => {
    const checkRoots = document.querySelectorAll('[data-image-check-root]');
    if (checkRoots.length === 0) {
      return;
    }

    const IMAGE_CHECK_TIMEOUT_MS = 7000;

    const extractImageSources = (rawHtml) => {
      if (typeof rawHtml !== 'string' || rawHtml.trim() === '') {
        return [];
      }

      const template = document.createElement('template');
      template.innerHTML = rawHtml;

      const uniqueSources = [];
      const seen = new Set();

      template.content.querySelectorAll('img[src]').forEach((imageNode) => {
        const rawSource = (imageNode.getAttribute('src') || '').trim();
        if (rawSource === '' || seen.has(rawSource)) {
          return;
        }

        seen.add(rawSource);
        uniqueSources.push(rawSource);
      });

      return uniqueSources;
    };

    const sourceToAbsoluteUrl = (source) => {
      const normalized = source.trim();
      if (normalized === '') {
        return '';
      }

      if (/^https?:\/\//i.test(normalized)) {
        return normalized;
      }

      if (normalized.startsWith('//')) {
        return `${window.location.protocol}${normalized}`;
      }

      if (normalized.startsWith('data:') || normalized.startsWith('blob:')) {
        return normalized;
      }

      if (normalized.startsWith('/')) {
        return `${window.location.origin}${normalized}`;
      }

      return `${window.location.origin}/${normalized.replace(/^\.\/+/, '').replace(/^\/+/, '')}`;
    };

    const probeImage = (absoluteUrl) => {
      if (absoluteUrl.startsWith('data:')) {
        return Promise.resolve({ ok: true, reason: 'inline' });
      }

      return new Promise((resolve) => {
        const image = new Image();
        let settled = false;
        const timeoutId = window.setTimeout(() => {
          if (settled) {
            return;
          }
          settled = true;
          resolve({ ok: false, reason: 'timeout' });
        }, IMAGE_CHECK_TIMEOUT_MS);

        image.onload = () => {
          if (settled) {
            return;
          }
          settled = true;
          window.clearTimeout(timeoutId);
          resolve({ ok: true, reason: 'loaded' });
        };

        image.onerror = () => {
          if (settled) {
            return;
          }
          settled = true;
          window.clearTimeout(timeoutId);
          resolve({ ok: false, reason: 'error' });
        };

        image.decoding = 'async';
        image.referrerPolicy = 'no-referrer';
        image.src = absoluteUrl;
      });
    };

    const setStatusText = (node, text) => {
      if (!(node instanceof HTMLElement)) {
        return;
      }

      node.textContent = text;
    };

    const clearResults = (list) => {
      if (!(list instanceof HTMLElement)) {
        return;
      }

      while (list.firstChild) {
        list.removeChild(list.firstChild);
      }
    };

    checkRoots.forEach((root) => {
      if (!(root instanceof HTMLElement)) {
        return;
      }

      const targetId = root.getAttribute('data-image-check-target') || '';
      const textarea = targetId !== '' ? document.getElementById(targetId) : null;
      if (!(textarea instanceof HTMLTextAreaElement)) {
        return;
      }

      const runButton = root.querySelector('[data-image-check-run]');
      const statusNode = root.querySelector('[data-image-check-status]');
      const resultList = root.querySelector('[data-image-check-results]');
      if (!(runButton instanceof HTMLButtonElement) || !(statusNode instanceof HTMLElement) || !(resultList instanceof HTMLElement)) {
        return;
      }

      runButton.addEventListener('click', async () => {
        runButton.disabled = true;
        setStatusText(statusNode, 'Analyse des images en cours…');
        clearResults(resultList);
        resultList.hidden = true;

        const sources = extractImageSources(textarea.value);
        if (sources.length === 0) {
          setStatusText(statusNode, 'Aucune balise image détectée dans ce bloc HTML.');
          runButton.disabled = false;
          return;
        }

        const checks = await Promise.all(
          sources.map(async (source) => {
            const absoluteUrl = sourceToAbsoluteUrl(source);
            if (absoluteUrl === '') {
              return { source, absoluteUrl, ok: false, reason: 'empty' };
            }

            const result = await probeImage(absoluteUrl);

            return { source, absoluteUrl, ok: result.ok, reason: result.reason };
          })
        );

        let okCount = 0;
        checks.forEach((entry) => {
          if (entry.ok) {
            okCount += 1;
          }

          const item = document.createElement('li');
          item.className = `region-image-check__item ${entry.ok ? 'is-ok' : 'is-error'}`;

          const badge = document.createElement('strong');
          badge.className = 'region-image-check__badge';
          badge.textContent = entry.ok ? 'OK' : 'Erreur';
          item.appendChild(badge);

          const sourceCode = document.createElement('code');
          sourceCode.textContent = entry.source;
          item.appendChild(sourceCode);

          if (entry.absoluteUrl !== '') {
            const link = document.createElement('a');
            link.href = entry.absoluteUrl;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = 'ouvrir';
            item.appendChild(link);
          }

          if (!entry.ok) {
            const reason = document.createElement('small');
            reason.textContent = entry.reason === 'timeout'
              ? 'L’image ne répond pas (timeout).'
              : 'Fichier introuvable ou format non image.';
            item.appendChild(reason);
          }

          resultList.appendChild(item);
        });

        setStatusText(
          statusNode,
          `${sources.length} image(s) détectée(s) · ${okCount} OK · ${sources.length - okCount} en erreur`
        );
        resultList.hidden = false;
        runButton.disabled = false;
      });
    });
  })();
</script>
<script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
  (() => {
    const calloutRoots = document.querySelectorAll('[data-region-callout-root]');
    if (calloutRoots.length === 0) {
      return;
    }

    const WRAPPER_CLASS = 'content-region-bordered-callout';
    const BORDER_CLASS = 'border';
    const LEGACY_WRAPPER_ID = 'bloc-haut';

    const parseHtml = (rawHtml) => {
      const template = document.createElement('template');
      template.innerHTML = typeof rawHtml === 'string' ? rawHtml : '';
      return template;
    };

    const significantNodes = (fragment) => Array.from(fragment.childNodes).filter((node) => {
      if (node.nodeType !== Node.TEXT_NODE) {
        return true;
      }

      return (node.textContent || '').trim() !== '';
    });

    const resolveWrapper = (rawHtml) => {
      const template = parseHtml(rawHtml);
      const nodes = significantNodes(template.content);
      if (nodes.length !== 1) {
        return null;
      }

      const firstNode = nodes[0];
      if (!(firstNode instanceof HTMLDivElement)) {
        return null;
      }

      if (firstNode.id === LEGACY_WRAPPER_ID) {
        return firstNode;
      }

      if (firstNode.classList.contains(BORDER_CLASS) || firstNode.classList.contains(WRAPPER_CLASS)) {
        return firstNode;
      }

      return null;
    };

    const unwrapCalloutHtml = (rawHtml) => {
      const wrapper = resolveWrapper(rawHtml);
      return wrapper instanceof HTMLDivElement ? wrapper.innerHTML.trim() : String(rawHtml || '').trim();
    };

    const visibleText = (rawHtml) => String(parseHtml(rawHtml).content.textContent || '').replace(/\s+/g, ' ').trim();

    const wrapCalloutHtml = (rawHtml) => {
      const normalized = String(rawHtml || '').trim();
      if (normalized === '') {
        return '';
      }

      if (resolveWrapper(normalized) instanceof HTMLDivElement) {
        return normalized;
      }

      return `<div class="${WRAPPER_CLASS} ${BORDER_CLASS}">${normalized}</div>`;
    };

    const updateTextareaValue = (textarea, value) => {
      const normalized = String(value || '').trim();
      if (textarea.value.trim() === normalized) {
        return;
      }

      textarea.value = normalized;
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const setStatus = (node, message) => {
      if (!(node instanceof HTMLElement)) {
        return;
      }

      node.textContent = message;
    };

    const syncControl = (root, normalizeContent = false) => {
      if (!(root instanceof HTMLElement)) {
        return;
      }

      const targetId = root.getAttribute('data-region-callout-target') || '';
      const textarea = targetId !== '' ? document.getElementById(targetId) : null;
      const checkbox = root.querySelector('[data-region-callout-toggle]');
      const status = root.querySelector('[data-region-callout-status]');
      if (!(textarea instanceof HTMLTextAreaElement) || !(checkbox instanceof HTMLInputElement)) {
        return;
      }

      let currentHtml = textarea.value.trim();
      const isWrapped = resolveWrapper(currentHtml) instanceof HTMLDivElement;
      const hasContent = unwrapCalloutHtml(currentHtml) !== '';

      checkbox.checked = isWrapped;
      checkbox.disabled = false;

      if (!hasContent) {
        setStatus(status, 'Ajoute du contenu dans l encart, puis coche cette option si tu veux afficher la bordure.');
        return;
      }

      setStatus(
        status,
        isWrapped
          ? 'La bordure rose sera affichee autour de cet encart.'
          : 'Coche cette option pour afficher la bordure rose autour de cet encart.'
      );
    };

    calloutRoots.forEach((root) => {
      if (!(root instanceof HTMLElement)) {
        return;
      }

      const targetId = root.getAttribute('data-region-callout-target') || '';
      const textarea = targetId !== '' ? document.getElementById(targetId) : null;
      const checkbox = root.querySelector('[data-region-callout-toggle]');
      if (!(textarea instanceof HTMLTextAreaElement) || !(checkbox instanceof HTMLInputElement)) {
        return;
      }

      checkbox.addEventListener('change', () => {
        if (checkbox.checked) {
          updateTextareaValue(textarea, wrapCalloutHtml(textarea.value));
        } else {
          updateTextareaValue(textarea, unwrapCalloutHtml(textarea.value));
        }

        syncControl(root, true);
      });

      textarea.addEventListener('input', () => {
        syncControl(root, true);
      });

      syncControl(root, true);
    });
  })();
</script>

<?php if (!($isNewPage ?? false)): ?>
<section class="card">
  <h2>Suppression</h2>
  <p>
    Cette action supprime définitivement la page <code><?php echo htmlspecialchars((string) ($currentSlug ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
    et toutes ses traductions.
  </p>

  <?php if ($deleteReferences !== []): ?>
  <div class="notice notice-error">
    Suppression bloquée : la page est encore utilisée par la navigation ou par des tuiles.
  </div>
  <ul>
    <?php foreach ($deleteReferences as $reference): ?>
    <li>
      <?php echo htmlspecialchars((string) ($reference['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
      · <?php echo htmlspecialchars((string) ($reference['context'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
      · <?php echo htmlspecialchars((string) ($reference['path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <p class="notice-muted">Retire d abord ces références dans les menus ou les tuiles avant de supprimer la page.</p>
  <?php else: ?>
  <details class="danger-confirmation">
    <summary>Supprimer définitivement</summary>

    <div class="danger-confirmation__body">
      <p class="danger-confirmation__question">Êtes-vous sûr de vouloir supprimer cette page ?</p>
      <p class="notice notice-error">
        Cette suppression est définitive et enlèvera aussi toutes les traductions liées.
      </p>

      <form method="post" action="<?php echo htmlspecialchars((string) ($currentPageUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="confirm_delete" value="1" />

        <div class="actions-inline actions-inline-end">
          <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($currentPageUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Non</a>
          <button type="submit" name="page_action" value="delete" class="button-danger">Oui, supprimer définitivement</button>
        </div>
      </form>
    </div>
  </details>
  <?php endif; ?>
</section>
<?php endif; ?>
