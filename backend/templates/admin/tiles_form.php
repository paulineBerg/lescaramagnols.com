<?php
$formData = is_array($formData ?? null) ? $formData : [];
$tileItems = is_array($formData['items'] ?? null) ? array_values($formData['items']) : [];
$availableLanguages = array_values(array_filter(
    is_array($availableLanguages ?? null) ? $availableLanguages : [],
    static fn (mixed $language): bool => is_string($language) && trim($language) !== ''
));
$tileThemes = is_array($tileThemes ?? null) ? $tileThemes : [];
$tileSizes = is_array($tileSizes ?? null) ? $tileSizes : [];
$tileColors = is_array($tileColors ?? null) ? $tileColors : [];
$tilePageOptions = is_array($tilePageOptions ?? null) ? array_values($tilePageOptions) : [];
$contentMediaPicker = is_array($contentMediaPicker ?? null) ? $contentMediaPicker : [];
$contentMediaLibrary = is_array($contentMediaPicker['items'] ?? null) ? array_values($contentMediaPicker['items']) : [];
$tileItemIsVisible = static function (array $item): bool {
    return !array_key_exists('is_visible', $item) || !empty($item['is_visible']);
};
$mediaSourceOptions = [];
foreach ($tileItems as $tileItem) {
    if (!is_array($tileItem)) {
        continue;
    }

    $source = trim((string) ($tileItem['image_src'] ?? ''));
    if ($source !== '') {
        $mediaSourceOptions[$source] = true;
    }
}
foreach ($contentMediaLibrary as $mediaItem) {
    if (!is_array($mediaItem)) {
        continue;
    }

    $source = trim((string) ($mediaItem['src'] ?? ''));
    if ($source !== '') {
        $mediaSourceOptions[$source] = true;
    }
}
$languageLabels = [
    'fr' => 'Français',
    'en' => 'English',
    'de' => 'Deutsch',
];
$tileEditorFormId = 'tile-editor-form';
$resolvePreviewText = static function (array $item, string $field, array $availableLanguages, string $fallback = ''): string {
    $translations = is_array($item['translations'] ?? null) ? $item['translations'] : [];

    foreach (['fr', ...$availableLanguages] as $language) {
        if (!is_string($language) || trim($language) === '') {
            continue;
        }

        $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];
        $value = trim((string) ($translation[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    foreach ($translations as $translation) {
        if (!is_array($translation)) {
            continue;
        }

        $value = trim((string) ($translation[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return $fallback;
};
$renderAdminTilePreview = static function (array $item, string $context = 'editor') use ($availableLanguages, $resolvePreviewText): void {
    $size = \Caramagnols\Content\TileRepository::normalizeTileSizeValue((string) ($item['tile_size'] ?? \Caramagnols\Content\TileRepository::DEFAULT_SIZE));
    $color = \Caramagnols\Content\TileRepository::buttonColorToken($size, (string) ($item['color_token'] ?? 'bleu'));
    $imageSrc = trim((string) ($item['image_src'] ?? ''));
    $label = trim((string) ($item['preview_label'] ?? ''));
    if ($label === '') {
        $label = $resolvePreviewText($item, 'label', $availableLanguages, 'Tuile');
    }

    $summary = trim((string) ($item['preview_summary'] ?? ''));
    if ($summary === '') {
        $summary = $resolvePreviewText($item, 'title', $availableLanguages, '');
    }
    if ($summary !== '' && strcasecmp($summary, $label) === 0) {
        $summary = '';
    }

    $buttonImage = getTileButtonImage($size, $color, 'default');
    ?>
    <article
      class="admin-tile-preview admin-tile-preview--<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?> admin-tile-preview--<?php echo htmlspecialchars($context, ENT_QUOTES, 'UTF-8'); ?><?php echo $imageSrc !== '' ? ' is-with-media' : ''; ?> admin-tile-preview--color-<?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>"
      style="--admin-tile-bg:url('<?php echo htmlspecialchars($buttonImage, ENT_QUOTES, 'UTF-8'); ?>');"
      aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"
    >
      <div class="admin-tile-preview__inner">
        <?php if ($imageSrc !== ''): ?>
        <figure class="admin-tile-preview__media">
          <img src="<?php echo htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async" data-tile-preview-image />
        </figure>
        <?php endif; ?>
        <div class="admin-tile-preview__overlay"></div>
        <div class="admin-tile-preview__content">
          <span class="admin-tile-preview__label" data-tile-preview-label><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="admin-tile-preview__summary"<?php echo $summary === '' ? ' hidden' : ''; ?> data-tile-preview-summary><?php echo htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      </div>
    </article>
    <?php
};

$renderTileItemRow = static function (array $item, int $index) use ($availableLanguages, $languageLabels, $tileSizes, $tileColors, $tilePageOptions, $renderAdminTilePreview, $resolvePreviewText, $tileItemIsVisible): void {
    $targetType = trim((string) ($item['target_type'] ?? 'page'));
    $imageSrc = trim((string) ($item['image_src'] ?? ''));
    $isVisible = $tileItemIsVisible($item);
    $tileSize = \Caramagnols\Content\TileRepository::normalizeTileSizeValue((string) ($item['tile_size'] ?? \Caramagnols\Content\TileRepository::DEFAULT_SIZE));
    $selectedColorToken = \Caramagnols\Content\TileRepository::buttonColorToken($tileSize, (string) ($item['color_token'] ?? 'bleu'));
    $previewLabel = $resolvePreviewText($item, 'label', $availableLanguages, 'Tuile');
    $previewSummary = $resolvePreviewText($item, 'title', $availableLanguages, '');
    if ($previewSummary !== '' && strcasecmp($previewSummary, $previewLabel) === 0) {
        $previewSummary = '';
    }
    $tilePreviewItem = $item;
    $tilePreviewItem['tile_size'] = $tileSize;
    $tilePreviewItem['color_token'] = $selectedColorToken;
    $tilePreviewItem['preview_label'] = $previewLabel;
    $tilePreviewItem['preview_summary'] = $previewSummary;
    ?>
    <article class="nested-card tile-editor-item" data-tile-item data-tile-index="<?php echo (int) $index; ?>">
      <div class="page-editor-intro__header">
        <div>
          <strong>Tuile <?php echo (int) ($index + 1); ?></strong>
          <p class="notice-muted">Libellé, cible, image et couleurs par défaut du groupe.</p>
        </div>
        <div class="tile-editor-item__actions">
          <button type="button" class="button-muted button-small" data-tile-move-direction="up">Monter</button>
          <button type="button" class="button-muted button-small" data-tile-move-direction="down">Descendre</button>
          <button type="button" class="button-link button-link-muted button-small" data-tile-remove-item>Retirer cette tuile</button>
        </div>
      </div>

      <div class="tile-editor-item__preview-shell" data-tile-preview-shell>
        <div class="tile-editor-item__preview-meta">
          <span class="tag" data-tile-preview-size-badge><?php echo htmlspecialchars((string) ($tileSizes[$tileSize] ?? ucfirst($tileSize)), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="tag" data-tile-preview-color-badge><?php echo htmlspecialchars((string) ($tileColors[$selectedColorToken] ?? ucfirst($selectedColorToken)), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="tag"<?php echo $isVisible ? ' hidden' : ''; ?> data-tile-preview-visibility-badge>Masquée</span>
          <span class="notice-muted" data-tile-preview-target><?php echo htmlspecialchars($targetType === 'external' ? 'URL externe' : ($targetType === 'route' ? 'Route interne' : 'Page du site'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div data-tile-preview-card-wrapper>
          <?php $renderAdminTilePreview($tilePreviewItem, 'editor'); ?>
        </div>
      </div>

      <div class="admin-form-grid admin-form-grid-3">
        <div class="field">
          <label for="tile-item-uid-<?php echo (int) $index; ?>">UID interne</label>
          <input
            id="tile-item-uid-<?php echo (int) $index; ?>"
            name="items[<?php echo (int) $index; ?>][item_uid]"
            type="text"
            value="<?php echo htmlspecialchars((string) ($item['item_uid'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            placeholder="austin"
          />
        </div>

        <div class="field">
          <label for="tile-sort-order-<?php echo (int) $index; ?>">Position</label>
          <input
            id="tile-sort-order-<?php echo (int) $index; ?>"
            name="items[<?php echo (int) $index; ?>][sort_order]"
            type="number"
            min="0"
            step="10"
            value="<?php echo htmlspecialchars((string) ($item['sort_order'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            readonly
            aria-readonly="true"
            data-tile-sort-order
          />
          <p class="admin-form-help">Mise à jour via les boutons Monter / Descendre.</p>
        </div>

        <div class="field">
          <label for="tile-color-<?php echo (int) $index; ?>">Couleur Windows 10</label>
          <select
            id="tile-color-<?php echo (int) $index; ?>"
            name="items[<?php echo (int) $index; ?>][color_token]"
          >
            <?php foreach ($tileColors as $loopColorToken => $colorLabel): ?>
            <option value="<?php echo htmlspecialchars((string) $loopColorToken, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $selectedColorToken === $loopColorToken ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars((string) $colorLabel, ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="tile-size-<?php echo (int) $index; ?>">Format W10</label>
          <select
            id="tile-size-<?php echo (int) $index; ?>"
            name="items[<?php echo (int) $index; ?>][tile_size]"
          >
            <?php foreach ($tileSizes as $sizeKey => $sizeLabel): ?>
            <option value="<?php echo htmlspecialchars((string) $sizeKey, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $tileSize === $sizeKey ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars((string) $sizeLabel, ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field admin-form-span-2">
          <label for="tile-image-src-<?php echo (int) $index; ?>">Image éditoriale</label>
          <input
            id="tile-image-src-<?php echo (int) $index; ?>"
            name="items[<?php echo (int) $index; ?>][image_src]"
            type="text"
            value="<?php echo htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8'); ?>"
            list="tile-image-sources"
            placeholder="/assets/images/..."
            data-tile-image-src
          />
          <p class="admin-form-help">L image est posée dans la tuile ; le fond bouton W10 dépend du format et de la couleur.</p>
        </div>

        <div class="field">
          <label for="tile-target-type-<?php echo (int) $index; ?>">Type de cible</label>
          <select
            id="tile-target-type-<?php echo (int) $index; ?>"
            name="items[<?php echo (int) $index; ?>][target_type]"
            data-tile-target-type
          >
            <option value="page"<?php echo $targetType === 'page' ? ' selected' : ''; ?>>Page du site</option>
            <option value="route"<?php echo $targetType === 'route' ? ' selected' : ''; ?>>Route interne</option>
            <option value="external"<?php echo $targetType === 'external' ? ' selected' : ''; ?>>URL externe</option>
          </select>
        </div>

        <div class="field" data-tile-target-wrapper="page"<?php echo $targetType === 'page' ? '' : ' hidden'; ?>>
          <label for="tile-page-target-<?php echo (int) $index; ?>">Page cible</label>
          <select
            id="tile-page-target-<?php echo (int) $index; ?>"
            name="items[<?php echo (int) $index; ?>][target_page_slug]"
          >
            <option value="">Choisir une page</option>
            <?php foreach ($tilePageOptions as $pageOption): ?>
            <?php $pageSlug = trim((string) ($pageOption['slug'] ?? '')); ?>
            <?php if ($pageSlug === ''): continue; endif; ?>
            <option value="<?php echo htmlspecialchars($pageSlug, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($item['target_page_slug'] ?? '') === $pageSlug ? ' selected' : ''; ?>>
              <?php
              $pageLabel = trim((string) ($pageOption['title'] ?? $pageSlug));
              $pageRoute = trim((string) ($pageOption['route'] ?? ''));
              $pageStatus = trim((string) ($pageOption['status'] ?? ''));
              $pageParts = [$pageSlug];
              if ($pageLabel !== '' && strcasecmp($pageLabel, $pageSlug) !== 0) {
                  $pageParts[] = $pageLabel;
              }
              if ($pageRoute !== '') {
                  $pageParts[] = $pageRoute;
              }
              if ($pageStatus !== '') {
                  $pageParts[] = $pageStatus;
              }
              echo htmlspecialchars(implode(' · ', $pageParts), ENT_QUOTES, 'UTF-8');
              ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" data-tile-target-wrapper="route"<?php echo $targetType === 'route' ? '' : ' hidden'; ?>>
          <label for="tile-route-target-<?php echo (int) $index; ?>">Route interne</label>
          <input
            id="tile-route-target-<?php echo (int) $index; ?>"
            name="items[<?php echo (int) $index; ?>][target_route]"
            type="text"
            value="<?php echo htmlspecialchars((string) ($item['target_route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            placeholder="/fr/blog"
          />
        </div>

        <div class="field" data-tile-target-wrapper="external"<?php echo $targetType === 'external' ? '' : ' hidden'; ?>>
          <label for="tile-url-target-<?php echo (int) $index; ?>">URL externe</label>
          <input
            id="tile-url-target-<?php echo (int) $index; ?>"
            name="items[<?php echo (int) $index; ?>][target_url]"
            type="url"
            value="<?php echo htmlspecialchars((string) ($item['target_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            placeholder="https://..."
          />
        </div>

        <div class="field">
          <label for="tile-image-width-<?php echo (int) $index; ?>">Largeur image</label>
          <input
            id="tile-image-width-<?php echo (int) $index; ?>"
            name="items[<?php echo (int) $index; ?>][image_width]"
            type="number"
            min="1"
            max="8192"
            step="1"
            value="<?php echo htmlspecialchars((string) ($item['image_width'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
          />
        </div>

        <div class="field">
          <label for="tile-image-height-<?php echo (int) $index; ?>">Hauteur image</label>
          <input
            id="tile-image-height-<?php echo (int) $index; ?>"
            name="items[<?php echo (int) $index; ?>][image_height]"
            type="number"
            min="1"
            max="8192"
            step="1"
            value="<?php echo htmlspecialchars((string) ($item['image_height'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
          />
        </div>

        <div class="field">
          <label class="checkbox-inline" for="tile-visible-<?php echo (int) $index; ?>">
            <input
              id="tile-visible-<?php echo (int) $index; ?>"
              name="items[<?php echo (int) $index; ?>][is_visible]"
              type="checkbox"
              value="1"
              <?php echo $isVisible ? 'checked' : ''; ?>
              data-tile-visible-toggle
            />
            Afficher cette tuile
          </label>
        </div>

        <div class="field">
          <label class="checkbox-inline" for="tile-new-tab-<?php echo (int) $index; ?>">
            <input
              id="tile-new-tab-<?php echo (int) $index; ?>"
              name="items[<?php echo (int) $index; ?>][open_in_new_tab]"
              type="checkbox"
              value="1"
              <?php echo !empty($item['open_in_new_tab']) ? 'checked' : ''; ?>
            />
            Ouvrir dans un nouvel onglet
          </label>
        </div>
      </div>

      <details class="nested-card" open>
        <summary>Traductions</summary>
        <div class="admin-form-grid admin-form-grid-3">
          <?php foreach ($availableLanguages as $language): ?>
          <?php $translation = is_array($item['translations'][$language] ?? null) ? $item['translations'][$language] : []; ?>
          <div class="field">
            <label for="tile-label-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>-<?php echo (int) $index; ?>">
              <?php echo htmlspecialchars(($languageLabels[$language] ?? strtoupper((string) $language)) . ' · Libellé', ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <input
              id="tile-label-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>-<?php echo (int) $index; ?>"
              name="items[<?php echo (int) $index; ?>][translations][<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>][label]"
              type="text"
              value="<?php echo htmlspecialchars((string) ($translation['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            />
          </div>

          <div class="field">
            <label for="tile-alt-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>-<?php echo (int) $index; ?>">
              <?php echo htmlspecialchars(($languageLabels[$language] ?? strtoupper((string) $language)) . ' · Alt', ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <input
              id="tile-alt-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>-<?php echo (int) $index; ?>"
              name="items[<?php echo (int) $index; ?>][translations][<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>][alt]"
              type="text"
              value="<?php echo htmlspecialchars((string) ($translation['alt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            />
          </div>

          <div class="field">
            <label for="tile-title-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>-<?php echo (int) $index; ?>">
              <?php echo htmlspecialchars(($languageLabels[$language] ?? strtoupper((string) $language)) . ' · Title', ENT_QUOTES, 'UTF-8'); ?>
            </label>
            <input
              id="tile-title-<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>-<?php echo (int) $index; ?>"
              name="items[<?php echo (int) $index; ?>][translations][<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>][title]"
              type="text"
              value="<?php echo htmlspecialchars((string) ($translation['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            />
          </div>
          <?php endforeach; ?>
        </div>
      </details>
    </article>
    <?php
};
?>
<?php $visibleTileItems = array_values(array_filter($tileItems, static fn (mixed $tileItem): bool => is_array($tileItem) && $tileItemIsVisible($tileItem))); ?>

<section class="card page-editor-intro">
  <div class="page-editor-intro__header">
    <h2><?php echo ($isNewTileGroup ?? false) ? 'Créer un groupe de tuiles' : 'Éditer un groupe de tuiles'; ?></h2>
    <button
      type="submit"
      name="tile_action"
      value="save"
      form="<?php echo htmlspecialchars($tileEditorFormId, ENT_QUOTES, 'UTF-8'); ?>"
      class="page-editor-intro__save"
    >
      Enregistrer le groupe
    </button>
  </div>
  <p class="page-editor-intro__description">
    Le groupe définit les tuiles par défaut: format W10, couleur, image éditoriale, cible et textes traduits.
    Le rattachement à une page et les overrides se font ensuite dans l éditeur de page.
  </p>

  <?php if (($message ?? null) !== null): ?>
  <div class="notice notice-success"><?php echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <?php if (($error ?? null) !== null): ?>
  <div class="notice notice-error"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <div class="actions-inline page-editor-intro__actions">
    <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($tilesIndexUrl ?? $adminTilesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Retour à la liste</a>
  </div>
</section>

<form
  id="<?php echo htmlspecialchars($tileEditorFormId, ENT_QUOTES, 'UTF-8'); ?>"
  class="page-editor-form"
  method="post"
  action="<?php echo htmlspecialchars((string) ($currentTileUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
  <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) ($formData['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
  <input type="hidden" name="tile_state_json" id="tile-editor-state-json" value="" />

  <section class="cards-grid tile-editor-overview">
    <article class="card">
      <h2>Paramètres généraux</h2>

      <div class="admin-form-grid admin-form-grid-2">
        <div class="field">
          <label for="tile-group-name">Nom du groupe</label>
          <input
            id="tile-group-name"
            name="name"
            type="text"
            value="<?php echo htmlspecialchars((string) ($formData['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            required
          />
        </div>

        <div class="field">
          <label for="tile-group-theme">Thème visuel</label>
          <select id="tile-group-theme" name="theme">
            <?php foreach ($tileThemes as $themeKey => $themeLabel): ?>
            <option value="<?php echo htmlspecialchars((string) $themeKey, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($formData['theme'] ?? '') === $themeKey ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars((string) $themeLabel, ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </article>

    <article class="card tile-group-preview-card">
      <div class="page-editor-intro__header">
        <div>
          <h2>Aperçu du groupe</h2>
          <p class="notice-muted">Prévisualisation admin du rendu W10 du bas de page.</p>
        </div>
        <span class="tag" data-tile-group-count><?php echo count($visibleTileItems); ?> tuile(s)</span>
      </div>

      <h3 class="tile-group-preview-card__name" data-tile-group-name-preview>
        <?php echo htmlspecialchars(trim((string) ($formData['name'] ?? '')) !== '' ? (string) $formData['name'] : 'Groupe sans nom', ENT_QUOTES, 'UTF-8'); ?>
      </h3>

      <div class="admin-tile-mosaic admin-tile-mosaic--editor" data-tile-group-preview-list>
        <?php foreach ($visibleTileItems as $tileItem): ?>
        <?php if (!is_array($tileItem)) { continue; } ?>
        <?php $renderAdminTilePreview($tileItem, 'editor'); ?>
        <?php endforeach; ?>
      </div>
    </article>
  </section>

  <section class="card" data-tile-items-editor>
    <div class="page-editor-intro__header">
      <div>
        <h2>Tuiles du groupe</h2>
        <p class="page-editor-intro__description">
          Chaque tuile porte son gabarit Windows 10, sa couleur, son image éditoriale et sa cible par défaut.
          Les textes restent traduisibles. L ordre du groupe se règle directement avec Monter / Descendre.
        </p>
      </div>
      <button type="button" class="button-link button-link-muted" data-tile-add-item>Ajouter une tuile</button>
    </div>

    <datalist id="tile-image-sources">
      <?php foreach (array_keys($mediaSourceOptions) as $source): ?>
      <option value="<?php echo htmlspecialchars((string) $source, ENT_QUOTES, 'UTF-8'); ?>"></option>
      <?php endforeach; ?>
    </datalist>

    <div class="tile-editor-items" data-tile-items-list>
      <?php foreach ($tileItems as $index => $tileItem): ?>
      <?php $renderTileItemRow(is_array($tileItem) ? $tileItem : [], (int) $index); ?>
      <?php endforeach; ?>
    </div>

    <template id="tile-item-template">
      <?php $renderTileItemRow([
          'item_uid' => '',
          'sort_order' => '__SORT_ORDER__',
          'tile_size' => \Caramagnols\Content\TileRepository::DEFAULT_SIZE,
          'color_token' => 'bleu',
          'image_src' => '',
          'image_width' => '',
          'image_height' => '',
          'target_type' => 'page',
          'target_page_slug' => '',
          'target_route' => '',
          'target_url' => '',
          'is_visible' => '1',
          'open_in_new_tab' => '',
          'translations' => array_fill_keys($availableLanguages, ['label' => '', 'alt' => '', 'title' => '']),
      ], 9999); ?>
    </template>

    <?php if ($contentMediaLibrary !== []): ?>
    <details class="nested-card" open>
      <summary>Bibliothèque médias</summary>
      <p class="notice-muted">Les chemins ci-dessous peuvent être réutilisés directement dans le champ image de la tuile.</p>
      <ul class="shared-media-library-list">
        <?php foreach (array_slice($contentMediaLibrary, 0, 120) as $mediaItem): ?>
        <?php $source = trim((string) ($mediaItem['src'] ?? '')); ?>
        <?php if ($source === ''): continue; endif; ?>
        <li class="shared-media-library-item">
          <code><?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?></code>
          <button type="button" class="button-link button-link-muted" data-tile-copy-source="<?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?>">Utiliser ce chemin</button>
        </li>
        <?php endforeach; ?>
      </ul>
    </details>
    <?php endif; ?>
  </section>

  <div class="actions-inline actions-inline-end">
    <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($tilesIndexUrl ?? $adminTilesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Annuler</a>
    <button type="submit" name="tile_action" value="save">Enregistrer le groupe</button>
  </div>

  <?php if (!empty($formData['id'])): ?>
  <section class="card page-delete-card">
    <h2>Supprimer ce groupe</h2>
    <p class="notice-muted">Un groupe déjà rattaché à des pages ne peut pas être supprimé tant que ces pages n ont pas été détachées.</p>
    <div class="actions-inline actions-inline-end">
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($currentTileUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Non</a>
      <button class="button-danger" type="submit" name="tile_action" value="delete" onclick="return window.confirm('Supprimer définitivement ce groupe de tuiles ?');">Supprimer</button>
    </div>
    <input type="hidden" name="confirm_delete" value="1" />
  </section>
  <?php endif; ?>
</form>

<?php $cspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
<script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
  (() => {
    const form = document.getElementById('tile-editor-form');
    const stateField = document.getElementById('tile-editor-state-json');
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
      if (name === '' || name === 'csrf_token' || name === 'tile_action' || name === 'tile_state_json') {
        return false;
      }

      if (
        field instanceof HTMLButtonElement
        || (field instanceof HTMLInputElement && (field.type === 'submit' || field.type === 'button'))
      ) {
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
            || name === 'tile_state_json'
            || field === submitter;
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
    const editor = document.querySelector('[data-tile-items-editor]');
    if (!(editor instanceof HTMLElement)) {
      return;
    }

    const list = editor.querySelector('[data-tile-items-list]');
    const addButton = editor.querySelector('[data-tile-add-item]');
    const template = document.getElementById('tile-item-template');
    const groupPreviewList = document.querySelector('[data-tile-group-preview-list]');
    const groupNameInput = document.getElementById('tile-group-name');
    const groupNamePreview = document.querySelector('[data-tile-group-name-preview]');
    const groupCountPreview = document.querySelector('[data-tile-group-count]');
    if (!(list instanceof HTMLElement) || !(addButton instanceof HTMLButtonElement) || !(template instanceof HTMLTemplateElement)) {
      return;
    }

    const sizeFolders = {
      small: 'boutonpetit',
      medium: 'boutonmoyen',
      large: 'boutongrand',
      rectangle: 'boutonrectangle',
    };
    const sizePrefixes = {
      small: 'btptt_',
      medium: 'btmoy_',
      large: 'btgrd_',
      rectangle: 'btrect_',
    };
    const supportedColors = {
      small: ['blanc', 'bleu', 'bleufonce', 'bleuturquoise', 'bleuvert', 'gris', 'jaune', 'noir', 'orange', 'rouge', 'rougefonce', 'vertfonce', 'violet', 'violetfonce'],
      medium: ['blanc', 'bleu', 'bleufonce', 'bleuturquoise', 'bleuvert', 'gris', 'jaune', 'noir', 'orange', 'rouge', 'rougefonce', 'vertfonce', 'violet', 'violetfonce'],
      large: ['blanc', 'bleu', 'bleufonce', 'bleuturquoise', 'bleuvert', 'gris', 'jaune', 'noir', 'orange', 'rose', 'rouge', 'rougefonce', 'vertfonce', 'violet', 'violetfonce'],
      rectangle: ['blanc', 'bleu', 'bleufonce', 'bleuturquoise', 'bleuvert', 'gris', 'jaune', 'noir', 'orange', 'rouge', 'rougefonce', 'vertfonce', 'violet', 'violetfonce'],
    };

    const normalizeSize = (value) => {
      const normalized = String(value || '').trim().toLowerCase();
      return Object.prototype.hasOwnProperty.call(sizeFolders, normalized) ? normalized : '<?php echo \Caramagnols\Content\TileRepository::DEFAULT_SIZE; ?>';
    };

    const normalizeColor = (size, value) => {
      const normalizedSize = normalizeSize(size);
      const normalizedColor = String(value || '').trim().toLowerCase().replaceAll(' ', '').replaceAll('_', '');
      const sizeColors = supportedColors[normalizedSize] || [];
      return sizeColors.includes(normalizedColor) ? normalizedColor : 'bleu';
    };

    const buttonAssetPath = (size, color) => {
      const normalizedSize = normalizeSize(size);
      const normalizedColor = normalizeColor(normalizedSize, color);
      return `/assets/images/structure/menu/${sizeFolders[normalizedSize]}/${sizePrefixes[normalizedSize]}${normalizedColor}.png`;
    };

    const visibleRows = () => Array.from(list.querySelectorAll('[data-tile-item]')).filter((row) => row instanceof HTMLElement);

    const resolveNextIndex = () => {
      let maxIndex = -1;
      list.querySelectorAll('[data-tile-item]').forEach((row) => {
        if (!(row instanceof HTMLElement)) {
          return;
        }

        const value = Number.parseInt(row.getAttribute('data-tile-index') || '', 10);
        if (!Number.isNaN(value) && value > maxIndex) {
          maxIndex = value;
        }
      });

      return maxIndex + 1;
    };

    const renderTemplate = (index) => {
      const sortOrder = String((index + 1) * 10);
      return template.innerHTML
        .replaceAll('9999', String(index))
        .replaceAll('__SORT_ORDER__', sortOrder)
        .trim();
    };

    const pickPreviewText = (row, field) => {
      if (!(row instanceof HTMLElement)) {
        return '';
      }

      const selector = `input[name*="[translations]"][name$="[${field}]"]`;
      const inputs = Array.from(row.querySelectorAll(selector)).filter((element) => element instanceof HTMLInputElement);
      const preferred = inputs.find((input) => input.name.includes('[translations][fr]') && input.value.trim() !== '');
      if (preferred instanceof HTMLInputElement) {
        return preferred.value.trim();
      }

      const firstFilled = inputs.find((input) => input instanceof HTMLInputElement && input.value.trim() !== '');
      return firstFilled instanceof HTMLInputElement ? firstFilled.value.trim() : '';
    };

    const ensurePreviewMedia = (previewCard) => {
      if (!(previewCard instanceof HTMLElement)) {
        return null;
      }

      const inner = previewCard.querySelector('.admin-tile-preview__inner');
      if (!(inner instanceof HTMLElement)) {
        return null;
      }

      const existing = inner.querySelector('.admin-tile-preview__media');
      if (existing instanceof HTMLElement) {
        return existing;
      }

      const figure = document.createElement('figure');
      figure.className = 'admin-tile-preview__media';
      const image = document.createElement('img');
      image.alt = '';
      image.loading = 'lazy';
      image.decoding = 'async';
      image.setAttribute('data-tile-preview-image', '');
      figure.appendChild(image);
      inner.insertBefore(figure, inner.firstChild);

      return figure;
    };

    const syncPreviewCard = (previewCard, state) => {
      if (!(previewCard instanceof HTMLElement)) {
        return;
      }

      previewCard.className = `admin-tile-preview admin-tile-preview--${state.size} admin-tile-preview--editor admin-tile-preview--color-${state.color}${state.imageSrc !== '' ? ' is-with-media' : ''}`;
      previewCard.style.setProperty('--admin-tile-bg', `url('${buttonAssetPath(state.size, state.color)}')`);
      previewCard.style.opacity = state.isVisible ? '1' : '0.45';
      previewCard.setAttribute('aria-label', state.label);

      const label = previewCard.querySelector('[data-tile-preview-label]');
      if (label instanceof HTMLElement) {
        label.textContent = state.label;
      }

      const summary = previewCard.querySelector('[data-tile-preview-summary]');
      if (summary instanceof HTMLElement) {
        summary.textContent = state.summary;
        summary.hidden = state.summary === '';
      }

      const existingMedia = previewCard.querySelector('.admin-tile-preview__media');
      if (state.imageSrc === '') {
        if (existingMedia instanceof HTMLElement) {
          existingMedia.remove();
        }

        return;
      }

      const media = ensurePreviewMedia(previewCard);
      const image = media instanceof HTMLElement ? media.querySelector('[data-tile-preview-image]') : null;
      if (image instanceof HTMLImageElement) {
        image.src = state.imageSrc;
      }
    };

    const collectRowPreviewState = (row) => {
      if (!(row instanceof HTMLElement)) {
        return null;
      }

      const sizeField = row.querySelector('[name$="[tile_size]"]');
      const colorField = row.querySelector('[name$="[color_token]"]');
      const targetField = row.querySelector('[data-tile-target-type]');
      const imageField = row.querySelector('[data-tile-image-src]');
      const visibilityField = row.querySelector('[data-tile-visible-toggle]');
      const size = sizeField instanceof HTMLSelectElement ? normalizeSize(sizeField.value) : '<?php echo \Caramagnols\Content\TileRepository::DEFAULT_SIZE; ?>';
      const color = colorField instanceof HTMLSelectElement ? normalizeColor(size, colorField.value) : 'bleu';
      const targetLabel = targetField instanceof HTMLSelectElement && targetField.selectedOptions[0]
        ? targetField.selectedOptions[0].textContent.trim()
        : 'Page du site';
      const imageSrc = imageField instanceof HTMLInputElement ? imageField.value.trim() : '';
      const isVisible = !(visibilityField instanceof HTMLInputElement) || visibilityField.checked;
      const label = pickPreviewText(row, 'label') || 'Tuile';
      const summaryRaw = pickPreviewText(row, 'title');
      const summary = summaryRaw !== '' && summaryRaw.toLowerCase() !== label.toLowerCase() ? summaryRaw : '';
      const sizeLabel = sizeField instanceof HTMLSelectElement && sizeField.selectedOptions[0]
        ? sizeField.selectedOptions[0].textContent.trim()
        : size;
      const colorLabel = colorField instanceof HTMLSelectElement
        ? (() => {
          const matchingOption = Array.from(colorField.options).find((option) => option.value === color);
          return matchingOption instanceof HTMLOptionElement ? matchingOption.textContent.trim() : color;
        })()
        : color;

      return {
        size,
        color,
        imageSrc,
        isVisible,
        label,
        summary,
        targetLabel,
        sizeLabel,
        colorLabel,
      };
    };

    const syncRowPreview = (row) => {
      const state = collectRowPreviewState(row);
      if (state === null) {
        return;
      }

      const sizeBadge = row.querySelector('[data-tile-preview-size-badge]');
      if (sizeBadge instanceof HTMLElement) {
        sizeBadge.textContent = state.sizeLabel;
      }

      const colorBadge = row.querySelector('[data-tile-preview-color-badge]');
      if (colorBadge instanceof HTMLElement) {
        colorBadge.textContent = state.colorLabel;
      }

      const target = row.querySelector('[data-tile-preview-target]');
      if (target instanceof HTMLElement) {
        target.textContent = state.targetLabel;
      }

      const visibilityBadge = row.querySelector('[data-tile-preview-visibility-badge]');
      if (visibilityBadge instanceof HTMLElement) {
        visibilityBadge.hidden = state.isVisible;
      }

      const previewCard = row.querySelector('[data-tile-preview-card-wrapper] .admin-tile-preview');
      syncPreviewCard(previewCard, state);
    };

    const renderGroupPreview = () => {
      if (groupNamePreview instanceof HTMLElement) {
        const groupName = groupNameInput instanceof HTMLInputElement ? groupNameInput.value.trim() : '';
        groupNamePreview.textContent = groupName !== '' ? groupName : 'Groupe sans nom';
      }

      const rows = visibleRows();
      const previewRows = rows.filter((row) => {
        const state = collectRowPreviewState(row);
        return state !== null && state.isVisible;
      });
      if (groupCountPreview instanceof HTMLElement) {
        groupCountPreview.textContent = `${previewRows.length} tuile(s)`;
      }

      if (!(groupPreviewList instanceof HTMLElement)) {
        return;
      }

      groupPreviewList.innerHTML = '';
      if (previewRows.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'notice-muted tile-group-preview-card__empty';
        empty.textContent = 'Aucune tuile visible dans l’aperçu.';
        groupPreviewList.appendChild(empty);
        return;
      }

      previewRows.forEach((row) => {
        if (!(row instanceof HTMLElement)) {
          return;
        }

        const previewCard = row.querySelector('[data-tile-preview-card-wrapper] .admin-tile-preview');
        if (!(previewCard instanceof HTMLElement)) {
          return;
        }

        groupPreviewList.appendChild(previewCard.cloneNode(true));
      });
    };

    const refreshRowMetadata = () => {
      visibleRows().forEach((row, visibleIndex, rows) => {
        if (!(row instanceof HTMLElement)) {
          return;
        }

        const title = row.querySelector('.page-editor-intro__header strong');
        if (title instanceof HTMLElement) {
          title.textContent = `Tuile ${visibleIndex + 1}`;
        }

        const sortOrderField = row.querySelector('[data-tile-sort-order]');
        if (sortOrderField instanceof HTMLInputElement) {
          sortOrderField.value = String((visibleIndex + 1) * 10);
        }

        const moveUpButton = row.querySelector('[data-tile-move-direction="up"]');
        if (moveUpButton instanceof HTMLButtonElement) {
          moveUpButton.disabled = visibleIndex === 0;
        }

        const moveDownButton = row.querySelector('[data-tile-move-direction="down"]');
        if (moveDownButton instanceof HTMLButtonElement) {
          moveDownButton.disabled = visibleIndex === rows.length - 1;
        }
      });
    };

    const moveRow = (row, direction) => {
      if (!(row instanceof HTMLElement) || !(list instanceof HTMLElement)) {
        return;
      }

      if (direction === 'up') {
        const previousRow = row.previousElementSibling;
        if (!(previousRow instanceof HTMLElement)) {
          return;
        }

        list.insertBefore(row, previousRow);
      } else if (direction === 'down') {
        const nextRow = row.nextElementSibling;
        if (!(nextRow instanceof HTMLElement)) {
          return;
        }

        list.insertBefore(nextRow, row);
      } else {
        return;
      }

      refreshRowMetadata();
      renderGroupPreview();

      const focusButton = row.querySelector(`[data-tile-move-direction="${direction}"]`);
      if (focusButton instanceof HTMLButtonElement) {
        focusButton.focus();
      }
    };

    const updateTargetFields = (row) => {
      if (!(row instanceof HTMLElement)) {
        return;
      }

      const targetType = row.querySelector('[data-tile-target-type]');
      if (!(targetType instanceof HTMLSelectElement)) {
        return;
      }

      const currentType = targetType.value || 'page';
      row.querySelectorAll('[data-tile-target-wrapper]').forEach((wrapper) => {
        if (!(wrapper instanceof HTMLElement)) {
          return;
        }

        wrapper.hidden = wrapper.getAttribute('data-tile-target-wrapper') !== currentType;
      });
    };

    const bindRow = (row) => {
      if (!(row instanceof HTMLElement)) {
        return;
      }

      const removeButton = row.querySelector('[data-tile-remove-item]');
      if (removeButton instanceof HTMLButtonElement) {
        removeButton.addEventListener('click', () => {
          row.remove();
          refreshRowMetadata();
          renderGroupPreview();
        });
      }

      const moveUpButton = row.querySelector('[data-tile-move-direction="up"]');
      if (moveUpButton instanceof HTMLButtonElement) {
        moveUpButton.addEventListener('click', () => moveRow(row, 'up'));
      }

      const moveDownButton = row.querySelector('[data-tile-move-direction="down"]');
      if (moveDownButton instanceof HTMLButtonElement) {
        moveDownButton.addEventListener('click', () => moveRow(row, 'down'));
      }

      const targetType = row.querySelector('[data-tile-target-type]');
      if (targetType instanceof HTMLSelectElement) {
        targetType.addEventListener('change', () => updateTargetFields(row));
      }

      row.addEventListener('input', () => {
        syncRowPreview(row);
        renderGroupPreview();
      });
      row.addEventListener('change', () => {
        syncRowPreview(row);
        updateTargetFields(row);
        renderGroupPreview();
      });

      updateTargetFields(row);
      syncRowPreview(row);
    };

    list.querySelectorAll('[data-tile-item]').forEach(bindRow);
    refreshRowMetadata();

    let nextIndex = resolveNextIndex();
    addButton.addEventListener('click', () => {
      const wrapper = document.createElement('div');
      wrapper.innerHTML = renderTemplate(nextIndex);
      const row = wrapper.firstElementChild;
      if (!(row instanceof HTMLElement)) {
        return;
      }

      row.setAttribute('data-tile-index', String(nextIndex));
      list.appendChild(row);
      bindRow(row);
      refreshRowMetadata();
      renderGroupPreview();
      nextIndex += 1;
    });

    if (groupNameInput instanceof HTMLInputElement) {
      groupNameInput.addEventListener('input', renderGroupPreview);
      groupNameInput.addEventListener('change', renderGroupPreview);
    }

    document.querySelectorAll('[data-tile-copy-source]').forEach((button) => {
      if (!(button instanceof HTMLButtonElement)) {
        return;
      }

      button.addEventListener('click', () => {
        const source = button.getAttribute('data-tile-copy-source') || '';
        if (source === '') {
          return;
        }

        const lastRow = list.querySelector('[data-tile-item]:last-child');
        if (lastRow instanceof HTMLElement) {
          const input = lastRow.querySelector('[data-tile-image-src]');
          if (input instanceof HTMLInputElement) {
            input.value = source;
            input.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
      });
    });

    renderGroupPreview();
  })();
</script>
