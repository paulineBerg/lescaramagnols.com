<?php
$menusView = is_array($menusView ?? null) ? $menusView : [];
$locations = is_array($menusView['locations'] ?? null) ? $menusView['locations'] : [];
$locationDefinitions = is_array($menusView['locationDefinitions'] ?? null) ? $menusView['locationDefinitions'] : [];
$activeLocation = is_string($menusView['activeLocation'] ?? null) ? $menusView['activeLocation'] : 'primary';
$selectedItem = is_array($menusView['selectedItem'] ?? null) ? $menusView['selectedItem'] : [];
$selectedItemPath = is_string($menusView['selectedItemPath'] ?? null) ? $menusView['selectedItemPath'] : null;
$selectedDescriptor = is_array($selectedItem['item'] ?? null) ? $selectedItem['item'] : null;
$selectedInputName = is_string($selectedItem['inputName'] ?? null) ? $selectedItem['inputName'] : '';
$pageOptions = is_array($menusView['pageOptions'] ?? null) ? $menusView['pageOptions'] : [];
$pageReferences = is_array($menusView['pageReferences'] ?? null) ? $menusView['pageReferences'] : [];
$preview = is_array($menusView['preview'] ?? null) ? $menusView['preview'] : [];
$banner = is_array($menusView['banner'] ?? null) ? $menusView['banner'] : [];
$backToTop = is_array($menusView['backToTop'] ?? null) ? $menusView['backToTop'] : [];
$footerNotice = is_array($menusView['footerNotice'] ?? null) ? $menusView['footerNotice'] : [];
$expertJson = is_string($menusView['expertJson'] ?? null) ? $menusView['expertJson'] : '{}';
$openContextualEditor = !empty($menusView['openContextualEditor']);
$activeLocationItems = is_array($locations[$activeLocation] ?? null) ? $locations[$activeLocation] : [];
$menuBuilderFormId = 'menu-builder-form';
$activeLocationDefinition = is_array($locationDefinitions[$activeLocation] ?? null)
    ? $locationDefinitions[$activeLocation]
    : [
        'label' => 'Menu principal',
        'summary' => '',
        'supportsChildren' => true,
        'editorKind' => 'navigation',
        'addKinds' => ['route', 'page', 'group', 'external'],
    ];

$kindLabels = [
    'route' => 'Lien interne',
    'page' => 'Page du registre',
    'external' => 'Lien externe',
    'group' => 'Groupe / sous-menu',
    'content_card' => 'Carte éditoriale',
];

$addKindLabels = [
    'route' => 'Ajouter un lien interne',
    'page' => 'Ajouter un lien page',
    'external' => 'Ajouter un lien externe',
    'group' => 'Ajouter un groupe',
    'content_card' => 'Ajouter une carte',
];

$stringOrNull = static function (mixed $value): ?string {
    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);

    return $value === '' ? null : $value;
};

$escape = static function (?string $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};

$normalizeLanguageCode = static function (mixed $language): ?string {
    if (!is_string($language)) {
        return null;
    }

    $normalized = strtolower(trim($language));
    if ($normalized === '' || preg_match('/^[a-z]{2,5}$/', $normalized) !== 1) {
        return null;
    }

    return $normalized;
};

$menuItemLabelLanguages = array_values(
    array_filter(
        array_map(
            static fn (mixed $language): string => is_string($language) ? strtolower(trim($language)) : '',
            site_available_languages()
        ),
        static fn (string $language): bool => $language !== ''
    )
);
if ($menuItemLabelLanguages === []) {
    $menuItemLabelLanguages = ['fr', 'de', 'en'];
}
if (!in_array('fr', $menuItemLabelLanguages, true)) {
    array_unshift($menuItemLabelLanguages, 'fr');
    $menuItemLabelLanguages = array_values(array_unique($menuItemLabelLanguages));
}

$menuItemLabelDefaultLanguage = $normalizeLanguageCode((string) app_config('default_lang', 'fr')) ?? 'fr';
if (!in_array($menuItemLabelDefaultLanguage, $menuItemLabelLanguages, true)) {
    $menuItemLabelDefaultLanguage = $menuItemLabelLanguages[0];
}

$currentLanguage = defined('CURRENT_LANG') ? $normalizeLanguageCode((string) CURRENT_LANG) : null;
if ($currentLanguage === null) {
    $currentLanguage = $menuItemLabelDefaultLanguage;
}

$translate = static function (string $key, string $fallback = ''): string {
    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === $key || $translated === '[[' . $key . ']]') {
        return $fallback;
    }

    return $translated;
};

$menuDeleteWarningTemplate = $translate(
    'TXT_ADMIN_MENUS_DELETE_WARNING_TEMPLATE',
    "ATTENTION : suppression definitive de l'element de menu \"%s\".\nCette action supprime aussi les sous-menus associes.\nConfirmer la suppression ?"
);

$localizedValueToString = static function (mixed $value) use ($stringOrNull, $normalizeLanguageCode, $menuItemLabelDefaultLanguage, $currentLanguage): ?string {
    if (!is_array($value)) {
        return $stringOrNull($value);
    }

    $translationsInput = is_array($value['translations'] ?? null) ? $value['translations'] : [];
    $translations = [];
    foreach ($translationsInput as $language => $labelText) {
        $normalizedLanguage = $normalizeLanguageCode($language);
        $normalizedText = $stringOrNull($labelText);
        if ($normalizedLanguage === null || $normalizedText === null) {
            continue;
        }

        $translations[$normalizedLanguage] = $normalizedText;
    }

    $defaultLanguage = $normalizeLanguageCode($value['defaultLanguage'] ?? null) ?? $menuItemLabelDefaultLanguage;
    if ($translations !== []) {
        if (is_string($translations[$currentLanguage] ?? null)) {
            return (string) $translations[$currentLanguage];
        }

        if (is_string($translations[$defaultLanguage] ?? null)) {
            return (string) $translations[$defaultLanguage];
        }

        $firstTranslation = array_values($translations)[0] ?? null;
        if (is_string($firstTranslation) && trim($firstTranslation) !== '') {
            return trim($firstTranslation);
        }
    }

    $text = $stringOrNull($value['text'] ?? null);
    if ($text !== null) {
        return $text;
    }

    $translationKey = $stringOrNull($value['translationKey'] ?? null);
    if ($translationKey === null) {
        return null;
    }

    if (function_exists('t')) {
        $translated = t($translationKey);
        if (is_string($translated) && $translated !== '' && $translated !== '[[' . $translationKey . ']]') {
            return $translated;
        }
    }

    return $translationKey;
};

$labelToString = static function (array $item) use ($localizedValueToString): ?string {
    $label = is_array($item['label'] ?? null) ? $item['label'] : [];

    return $localizedValueToString($label);
};

$presentationForItem = static function (array $item): array {
    return is_array($item['presentation'] ?? null) ? $item['presentation'] : [];
};

$featuredTargetMode = static function (array $item) use ($presentationForItem, $stringOrNull): string {
    $presentation = $presentationForItem($item);
    $featuredCard = is_array($presentation['featuredCard'] ?? null) ? $presentation['featuredCard'] : [];
    $target = is_array($featuredCard['target'] ?? null) ? $featuredCard['target'] : [];

    if ($stringOrNull($target['pageSlug'] ?? null) !== null) {
        return 'page';
    }

    if ($stringOrNull($target['url'] ?? null) !== null) {
        return 'external';
    }

    if ($stringOrNull($target['route'] ?? null) !== null) {
        return 'route';
    }

    return 'none';
};

$itemTargetMode = static function (array $item, string $location) use ($stringOrNull): string {
    $kind = strtolower(trim((string) ($item['kind'] ?? 'route')));

    if (in_array($location, ['sideLeft', 'sideRight'], true) || $kind === 'content_card') {
        $target = is_array($item['target'] ?? null) ? $item['target'] : [];

        if ($stringOrNull($target['pageSlug'] ?? null) !== null) {
            return 'page';
        }

        if ($stringOrNull($target['url'] ?? null) !== null) {
            return 'external';
        }

        if ($stringOrNull($target['route'] ?? null) !== null) {
            return 'route';
        }

        return 'none';
    }

    return $kind;
};

$encodePath = static function (string $location, array $indices): string {
    return $location . '|' . implode('|', $indices);
};

$inputNameForPath = static function (string $location, array $indices): string {
    $name = sprintf('locations[%s]', $location);
    $lastIndex = count($indices) - 1;

    foreach ($indices as $offset => $index) {
        $name .= sprintf('[%d]', $index);

        if ($offset < $lastIndex) {
            $name .= '[children]';
        }
    }

    return $name;
};

$parsePath = static function (?string $path): ?array {
    if (!is_string($path) || $path === '') {
        return null;
    }

    $segments = array_values(array_filter(explode('|', $path), static fn (string $part): bool => $part !== ''));
    if (count($segments) < 2) {
        return null;
    }

    $location = array_shift($segments);
    if (!is_string($location) || $location === '') {
        return null;
    }

    $indices = [];
    foreach ($segments as $segment) {
        if (!ctype_digit($segment)) {
            return null;
        }

        $indices[] = (int) $segment;
    }

    if ($indices === []) {
        return null;
    }

    return [
        'location' => $location,
        'indices' => $indices,
    ];
};

$selectedPathDescriptor = $parsePath($selectedItemPath);

$labelTranslationsForInput = static function (array $label) use ($stringOrNull, $normalizeLanguageCode, $menuItemLabelLanguages): array {
    $translationsInput = is_array($label['translations'] ?? null) ? $label['translations'] : [];
    $translations = [];

    foreach ($translationsInput as $language => $labelValue) {
        $normalizedLanguage = $normalizeLanguageCode($language);
        $normalizedValue = $stringOrNull($labelValue);
        if ($normalizedLanguage === null || $normalizedValue === null) {
            continue;
        }

        $translations[$normalizedLanguage] = $normalizedValue;
    }

    foreach ($menuItemLabelLanguages as $language) {
        if (!array_key_exists($language, $translations)) {
            $translations[$language] = null;
        }
    }

    return $translations;
};

$labelDefaultLanguageForInput = static function (array $label) use ($stringOrNull, $normalizeLanguageCode, $menuItemLabelLanguages, $menuItemLabelDefaultLanguage): string {
    $defaultLanguage = $normalizeLanguageCode($label['defaultLanguage'] ?? null) ?? $menuItemLabelDefaultLanguage;
    if (!in_array($defaultLanguage, $menuItemLabelLanguages, true)) {
        $defaultLanguage = $menuItemLabelDefaultLanguage;
    }

    return $defaultLanguage;
};

$hiddenField = static function (string $name, ?string $value) use ($escape, $menuBuilderFormId): void {
    ?>
    <input
      type="hidden"
      name="<?php echo $escape($name); ?>"
      form="<?php echo $escape($menuBuilderFormId); ?>"
      value="<?php echo $escape($value); ?>"
    />
    <?php
};

$renderHiddenItemFields = null;
$renderHiddenItemFields = static function (array $item, string $baseName, string $location, array $indices = []) use (
    &$renderHiddenItemFields,
    $hiddenField,
    $itemTargetMode,
    $stringOrNull,
    $labelTranslationsForInput,
    $labelDefaultLanguageForInput,
    $menuItemLabelLanguages,
    $presentationForItem,
    $featuredTargetMode,
    $selectedPathDescriptor
): void {
    $indices = array_values(array_map('intval', $indices));
    $isSelectedItem = is_array($selectedPathDescriptor)
        && ($selectedPathDescriptor['location'] ?? null) === $location
        && ($selectedPathDescriptor['indices'] ?? null) === $indices;

    $target = is_array($item['target'] ?? null) ? $item['target'] : [];
    $accessibility = is_array($item['accessibility'] ?? null) ? $item['accessibility'] : [];
    $media = is_array($item['media'] ?? null) ? $item['media'] : [];
    $content = is_array($item['content'] ?? null) ? $item['content'] : [];
    $label = is_array($item['label'] ?? null) ? $item['label'] : [];
    $labelTranslations = $labelTranslationsForInput($label);
    $labelDefaultLanguage = $labelDefaultLanguageForInput($label);
    $presentation = $presentationForItem($item);
    $featuredCard = is_array($presentation['featuredCard'] ?? null) ? $presentation['featuredCard'] : [];
    $featuredTarget = is_array($featuredCard['target'] ?? null) ? $featuredCard['target'] : [];

    if (!$isSelectedItem) {
        $hiddenField($baseName . '[id]', $stringOrNull($item['id'] ?? null));
        $hiddenField($baseName . '[kind]', $stringOrNull($item['kind'] ?? null));
        $hiddenField($baseName . '[label_text]', $stringOrNull($label['text'] ?? null));
        $hiddenField($baseName . '[label_translation_key]', $stringOrNull($label['translationKey'] ?? null));
        $hiddenField($baseName . '[label_default_language]', $labelDefaultLanguage);
        foreach ($menuItemLabelLanguages as $language) {
            $hiddenField(
                $baseName . '[label_translations][' . $language . ']',
                $stringOrNull($labelTranslations[$language] ?? null)
            );
        }
        $hiddenField($baseName . '[target_mode]', $itemTargetMode($item, $location));
        $hiddenField($baseName . '[target_page_slug]', $stringOrNull($target['pageSlug'] ?? null));
        $hiddenField($baseName . '[target_route]', $stringOrNull($target['route'] ?? null));
        $hiddenField($baseName . '[target_url]', $stringOrNull($target['url'] ?? null));

        if (!empty($target['openInNewTab'])) {
            $hiddenField($baseName . '[open_in_new_tab]', '1');
        }

        $hiddenField($baseName . '[image]', $stringOrNull($media['image'] ?? null));
        $hiddenField($baseName . '[content_text]', $stringOrNull($content['text'] ?? null));
        $hiddenField($baseName . '[alt]', $stringOrNull($accessibility['alt'] ?? null));
        $hiddenField($baseName . '[title]', $stringOrNull($accessibility['title'] ?? null));
        $hiddenField($baseName . '[display_mode]', $stringOrNull($presentation['displayMode'] ?? null));
        $hiddenField($baseName . '[column_count]', $stringOrNull((string) ($presentation['columnCount'] ?? '')));
        $hiddenField($baseName . '[menu_template]', $stringOrNull($presentation['menuTemplate'] ?? null));

        if (!empty($presentation['isHighlight'])) {
            $hiddenField($baseName . '[is_highlight]', '1');
        }

        $hiddenField($baseName . '[featured_title]', $stringOrNull($featuredCard['title'] ?? null));
        $hiddenField($baseName . '[featured_text]', $stringOrNull($featuredCard['text'] ?? null));
        $hiddenField($baseName . '[featured_image]', $stringOrNull($featuredCard['image'] ?? null));
        $hiddenField($baseName . '[featured_cta_label]', $stringOrNull($featuredCard['ctaLabel'] ?? null));
        $hiddenField($baseName . '[featured_target_mode]', $featuredTargetMode($item));
        $hiddenField($baseName . '[featured_target_page_slug]', $stringOrNull($featuredTarget['pageSlug'] ?? null));
        $hiddenField($baseName . '[featured_target_route]', $stringOrNull($featuredTarget['route'] ?? null));
        $hiddenField($baseName . '[featured_target_url]', $stringOrNull($featuredTarget['url'] ?? null));

        if (!empty($featuredTarget['openInNewTab'])) {
            $hiddenField($baseName . '[featured_open_in_new_tab]', '1');
        }
    }

    $children = is_array($item['children'] ?? null) ? $item['children'] : [];

    foreach ($children as $childIndex => $childItem) {
        if (!is_array($childItem)) {
            continue;
        }

        $renderHiddenItemFields(
            $childItem,
            sprintf('%s[children][%d]', $baseName, $childIndex),
            $location,
            array_merge($indices, [$childIndex])
        );
    }
};

$renderTargetSummary = static function (array $item, string $location) use ($itemTargetMode, $escape, $stringOrNull): void {
    $targetMode = $itemTargetMode($item, $location);
    $target = is_array($item['target'] ?? null) ? $item['target'] : [];

    ?>
    <span class="menu-item-card__target">
      <?php if ($targetMode === 'page'): ?>
        Page : <code><?php echo $escape($stringOrNull($target['pageSlug'] ?? null)); ?></code>
      <?php elseif ($targetMode === 'route'): ?>
        Route : <code><?php echo $escape($stringOrNull($target['route'] ?? null)); ?></code>
      <?php elseif ($targetMode === 'external'): ?>
        Externe : <code><?php echo $escape($stringOrNull($target['url'] ?? null)); ?></code>
      <?php else: ?>
        Sans destination
      <?php endif; ?>
    </span>
    <?php
};

$renderStructure = null;
$renderStructure = static function (array $items, string $location, array $indices = [], int $depth = 0) use (
    &$renderStructure,
    $renderHiddenItemFields,
    $encodePath,
    $inputNameForPath,
    $selectedItemPath,
    $labelToString,
    $itemTargetMode,
    $kindLabels,
    $renderTargetSummary,
    $escape,
    $activeLocationDefinition,
    $presentationForItem,
    $openContextualEditor,
    $menuDeleteWarningTemplate
): void {
    $total = count($items);

    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $currentIndices = array_merge($indices, [$index]);
        $path = $encodePath($location, $currentIndices);
        $baseName = $inputNameForPath($location, $currentIndices);
        $isSelected = $selectedItemPath === $path;
        $kind = strtolower(trim((string) ($item['kind'] ?? 'route')));
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
        $presentation = $presentationForItem($item);
        $label = $labelToString($item)
            ?? ($kind === 'content_card' ? 'Carte sans titre' : 'Item sans libellé');
        $deleteWarning = sprintf($menuDeleteWarningTemplate, $label);

        if (!$isSelected) {
            $renderHiddenItemFields($item, $baseName, $location, $currentIndices);
        }
        ?>
        <article
          class="menu-item-card menu-item-card-kind-<?php echo $escape($kind); ?><?php echo $isSelected ? ' menu-item-card-selected' : ''; ?>"
          style="--menu-depth: <?php echo (int) $depth; ?>;"
        >
          <div class="menu-item-card__header">
            <div>
              <div class="menu-item-card__eyebrow">
                <?php echo $escape($kindLabels[$kind] ?? strtoupper($kind)); ?>
              </div>
              <h3><?php echo $escape($label); ?></h3>
            </div>
            <?php if ($children !== []): ?>
              <span class="tag"><?php echo count($children); ?> sous-menu(x)</span>
            <?php endif; ?>
          </div>

          <div class="menu-item-card__meta">
            <?php $renderTargetSummary($item, $location); ?>
            <?php if (($presentation['displayMode'] ?? null) === 'mega' && !($kind === 'group' && $location === 'primary')): ?>
              <span class="menu-item-card__flag">Mega · <?php echo (int) ($presentation['columnCount'] ?? 3); ?> col.</span>
            <?php elseif (($presentation['displayMode'] ?? null) === 'dropdown' && !($kind === 'group' && $location === 'primary')): ?>
              <span class="menu-item-card__flag">Dropdown</span>
            <?php endif; ?>
            <?php if (!empty($presentation['isHighlight'])): ?>
              <span class="menu-item-card__flag">Mis en avant</span>
            <?php endif; ?>
            <?php if (!empty(($presentation['featuredCard']['title'] ?? null)) || !empty(($presentation['featuredCard']['image'] ?? null))): ?>
              <span class="menu-item-card__flag">Carte featured</span>
            <?php endif; ?>
            <?php if (!empty(($item['media']['image'] ?? null))): ?>
              <span class="menu-item-card__flag">Image</span>
            <?php endif; ?>
            <?php if (!empty(($item['content']['text'] ?? null))): ?>
              <span class="menu-item-card__flag">Texte</span>
            <?php endif; ?>
          </div>

          <?php if ($kind === 'group' && $location === 'primary'): ?>
          <?php
          $displayMode = strtolower(trim((string) ($presentation['displayMode'] ?? 'dropdown')));
          if (!in_array($displayMode, ['mega', 'dropdown'], true)) {
              $displayMode = 'dropdown';
          }
          $columnCount = max(2, min(4, (int) ($presentation['columnCount'] ?? 3)));
          $menuTemplate = strtolower(trim((string) ($presentation['menuTemplate'] ?? 'standard')));
          if (!in_array($menuTemplate, ['standard', 'editorial', 'brands'], true)) {
              $menuTemplate = 'standard';
          }
          $menuTemplateLabels = [
              'standard' => 'Standard',
              'editorial' => 'Editorial',
              'brands' => 'Marques / catalogue',
          ];
          $menuTemplateLabel = $menuTemplateLabels[$menuTemplate];
          $templateIsActive = $displayMode === 'mega' && $children !== [];
          ?>
          <div class="menu-item-card__presentation">
            <span class="menu-item-card__flag menu-item-card__flag-template menu-item-card__flag-template-<?php echo $escape($menuTemplate); ?>">
              Template <?php echo $templateIsActive ? 'actif' : 'préparé'; ?> : <?php echo $escape($menuTemplateLabel); ?>
            </span>
            <span class="menu-item-card__flag menu-item-card__flag-info">
              Desktop : <?php echo $displayMode === 'mega' ? 'Mega' : 'Dropdown'; ?><?php echo $displayMode === 'mega' ? ' · ' . $columnCount . ' col.' : ''; ?>
            </span>
            <?php if ($displayMode !== 'mega'): ?>
              <span class="menu-item-card__flag menu-item-card__flag-info">Passe en Mega pour appliquer le template</span>
            <?php elseif ($children === []): ?>
              <span class="menu-item-card__flag menu-item-card__flag-info">Ajoute un sous-menu pour afficher le panneau</span>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <div class="menu-item-card__actions">
            <?php if ($isSelected): ?>
            <button
              type="button"
              data-region-modal-open="menu-editor-dialog"
              <?php echo $openContextualEditor ? 'data-region-modal-autostart="true"' : ''; ?>
            >
              Ouvrir l’éditeur
            </button>
            <?php else: ?>
            <button type="submit" name="builder_action" value="<?php echo $escape('select@' . $path); ?>">
              Éditer
            </button>
            <?php endif; ?>
            <button type="submit" class="button-muted" name="builder_action" value="<?php echo $escape('duplicate@' . $path); ?>">
              Dupliquer
            </button>
            <button
              type="submit"
              class="button-muted"
              name="builder_action"
              value="<?php echo $escape('move_up@' . $path); ?>"
              <?php echo $index === 0 ? 'disabled' : ''; ?>
            >
              Monter
            </button>
            <button
              type="submit"
              class="button-muted"
              name="builder_action"
              value="<?php echo $escape('move_down@' . $path); ?>"
              <?php echo $index === ($total - 1) ? 'disabled' : ''; ?>
            >
              Descendre
            </button>
            <button
              type="submit"
              class="button-danger"
              name="builder_action"
              value="<?php echo $escape('remove@' . $path); ?>"
              data-menu-delete-warning="<?php echo $escape($deleteWarning); ?>"
            >
              Supprimer
            </button>

            <?php if (!empty($activeLocationDefinition['supportsChildren']) && $kind === 'group'): ?>
              <button type="submit" class="button-muted" name="builder_action" value="<?php echo $escape('append_child@' . $path . '@route'); ?>">
                + lien
              </button>
              <button type="submit" class="button-muted" name="builder_action" value="<?php echo $escape('append_child@' . $path . '@group'); ?>">
                + groupe
              </button>
            <?php endif; ?>
          </div>
        </article>

        <?php if ($children !== []): ?>
        <div class="menu-item-children">
          <?php $renderStructure($children, $location, $currentIndices, $depth + 1); ?>
        </div>
        <?php endif; ?>
        <?php
    }
};

$renderEditorFields = static function (
    array $item,
    string $location,
    string $baseName
) use (
    $escape,
    $stringOrNull,
    $itemTargetMode,
    $labelToString,
    $labelTranslationsForInput,
    $labelDefaultLanguageForInput,
    $menuItemLabelLanguages,
    $translate,
    $kindLabels,
    $pageOptions,
    $activeLocationDefinition,
    $presentationForItem,
    $featuredTargetMode,
    $menuBuilderFormId
): void {
    $kind = strtolower(trim((string) ($item['kind'] ?? 'route')));
    $label = $labelToString($item);
    $targetMode = $itemTargetMode($item, $location);
    $target = is_array($item['target'] ?? null) ? $item['target'] : [];
    $children = is_array($item['children'] ?? null) ? $item['children'] : [];
    $media = is_array($item['media'] ?? null) ? $item['media'] : [];
    $content = is_array($item['content'] ?? null) ? $item['content'] : [];
    $accessibility = is_array($item['accessibility'] ?? null) ? $item['accessibility'] : [];
    $presentation = $presentationForItem($item);
    $featuredCard = is_array($presentation['featuredCard'] ?? null) ? $presentation['featuredCard'] : [];
    $featuredTarget = is_array($featuredCard['target'] ?? null) ? $featuredCard['target'] : [];
    $displayMode = is_string($presentation['displayMode'] ?? null) ? (string) $presentation['displayMode'] : 'dropdown';
    if (!in_array($displayMode, ['mega', 'dropdown'], true)) {
        $displayMode = 'dropdown';
    }
    $menuTemplate = strtolower(trim((string) ($presentation['menuTemplate'] ?? 'standard')));
    if (!in_array($menuTemplate, ['standard', 'editorial', 'brands'], true)) {
        $menuTemplate = 'standard';
    }
    $menuTemplateLabels = [
        'standard' => 'Standard',
        'editorial' => 'Editorial',
        'brands' => 'Marques / catalogue',
    ];
    $menuTemplateLabel = $menuTemplateLabels[$menuTemplate];
    $columnCount = max(2, min(4, (int) ($presentation['columnCount'] ?? 3)));
    $templateIsActive = $displayMode === 'mega' && $children !== [];
    $featuredMode = $featuredTargetMode($item);
    $supportsChildren = !empty($activeLocationDefinition['supportsChildren']);
    $labelData = is_array($item['label'] ?? null) ? $item['label'] : [];
    $labelTranslations = $labelTranslationsForInput($labelData);
    $labelDefaultLanguage = $labelDefaultLanguageForInput($labelData);
    ?>
    <input
      type="hidden"
      name="<?php echo $escape($baseName . '[id]'); ?>"
      form="<?php echo $escape($menuBuilderFormId); ?>"
      value="<?php echo $escape($stringOrNull($item['id'] ?? null)); ?>"
    />
    <input
      type="hidden"
      name="<?php echo $escape($baseName . '[label_translation_key]'); ?>"
      form="<?php echo $escape($menuBuilderFormId); ?>"
      value="<?php echo $escape($stringOrNull(($item['label']['translationKey'] ?? null))); ?>"
    />

    <?php if (in_array($location, ['sideLeft', 'sideRight'], true)): ?>
    <input
      type="hidden"
      name="<?php echo $escape($baseName . '[kind]'); ?>"
      form="<?php echo $escape($menuBuilderFormId); ?>"
      value="content_card"
    />
    <?php else: ?>
    <div class="field">
      <label for="selected_kind">Type d’item</label>
      <select
        id="selected_kind"
        name="<?php echo $escape($baseName . '[kind]'); ?>"
        form="<?php echo $escape($menuBuilderFormId); ?>"
      >
        <option value="route" <?php echo $kind === 'route' ? 'selected' : ''; ?>>Lien interne</option>
        <option value="page" <?php echo $kind === 'page' ? 'selected' : ''; ?>>Page du registre</option>
        <option value="external" <?php echo $kind === 'external' ? 'selected' : ''; ?>>Lien externe</option>
        <?php if ($supportsChildren): ?>
        <option value="group" <?php echo $kind === 'group' ? 'selected' : ''; ?>>Groupe / sous-menu</option>
        <?php endif; ?>
      </select>
      <div class="notice-muted">
        Le type est appliqué à la sauvegarde. Si des enfants existent, l’item restera un groupe.
      </div>
    </div>
    <?php endif; ?>

    <div class="admin-form-grid admin-form-grid-2">
      <div class="field">
        <label for="selected_label_text"><?php echo $escape($translate('TXT_ADMIN_MENUS_LABEL_TEXT', 'Libellé')); ?></label>
        <input
          id="selected_label_text"
          type="text"
          name="<?php echo $escape($baseName . '[label_text]'); ?>"
          form="<?php echo $escape($menuBuilderFormId); ?>"
          value="<?php echo $escape($label); ?>"
        />
      </div>

      <div class="field">
        <label for="selected_label_default_language"><?php echo $escape($translate('TXT_ADMIN_MENUS_LABEL_DEFAULT_LANGUAGE', 'Langue par défaut')); ?></label>
        <select
          id="selected_label_default_language"
          name="<?php echo $escape($baseName . '[label_default_language]'); ?>"
          form="<?php echo $escape($menuBuilderFormId); ?>"
        >
          <?php foreach ($menuItemLabelLanguages as $language): ?>
          <option value="<?php echo $escape($language); ?>" <?php echo $labelDefaultLanguage === $language ? 'selected' : ''; ?>>
            <?php echo $escape(strtoupper($language)); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="admin-form-grid admin-form-grid-3">
      <?php foreach ($menuItemLabelLanguages as $language): ?>
      <div class="field">
        <label for="selected_label_translation_<?php echo $escape($language); ?>">
          <?php echo $escape(sprintf('%s (%s)', $translate('TXT_ADMIN_MENUS_LABEL_TRANSLATION', 'Traduction du libellé'), strtoupper($language))); ?>
        </label>
        <input
          id="selected_label_translation_<?php echo $escape($language); ?>"
          type="text"
          name="<?php echo $escape($baseName . '[label_translations][' . $language . ']'); ?>"
          form="<?php echo $escape($menuBuilderFormId); ?>"
          value="<?php echo $escape($stringOrNull($labelTranslations[$language] ?? null)); ?>"
        />
      </div>
      <?php endforeach; ?>
    </div>

    <p class="notice-muted">
      <?php echo $escape($translate('TXT_ADMIN_MENUS_LABEL_TRANSLATIONS_HELP', 'Les traductions par langue sont prioritaires, puis la langue par défaut, puis le libellé principal.')); ?>
    </p>

    <div class="admin-form-grid admin-form-grid-2">
      <div class="field">
        <label for="selected_image">Image</label>
        <input
          id="selected_image"
          type="text"
          name="<?php echo $escape($baseName . '[image]'); ?>"
          form="<?php echo $escape($menuBuilderFormId); ?>"
          value="<?php echo $escape($stringOrNull($media['image'] ?? null)); ?>"
          placeholder="/assets/images/..."
        />
      </div>

      <div class="field">
        <label for="selected_alt">Alt</label>
        <input
          id="selected_alt"
          type="text"
          name="<?php echo $escape($baseName . '[alt]'); ?>"
          form="<?php echo $escape($menuBuilderFormId); ?>"
          value="<?php echo $escape($stringOrNull($accessibility['alt'] ?? null)); ?>"
        />
      </div>

      <div class="field">
        <label for="selected_title">Title</label>
        <input
          id="selected_title"
          type="text"
          name="<?php echo $escape($baseName . '[title]'); ?>"
          form="<?php echo $escape($menuBuilderFormId); ?>"
          value="<?php echo $escape($stringOrNull($accessibility['title'] ?? null)); ?>"
        />
      </div>
    </div>

    <?php if (!in_array($location, ['sideLeft', 'sideRight'], true)): ?>
    <label class="checkbox-field">
        <input
          type="checkbox"
          name="<?php echo $escape($baseName . '[is_highlight]'); ?>"
          form="<?php echo $escape($menuBuilderFormId); ?>"
          value="1"
          <?php echo !empty($presentation['isHighlight']) ? 'checked' : ''; ?>
        />
      Marquer ce lien comme mis en avant dans un mega menu
    </label>
    <?php endif; ?>

    <?php if (in_array($location, ['sideLeft', 'sideRight'], true)): ?>
    <div class="field">
      <label for="selected_content_text">Texte éditorial</label>
        <textarea
          id="selected_content_text"
          name="<?php echo $escape($baseName . '[content_text]'); ?>"
          form="<?php echo $escape($menuBuilderFormId); ?>"
          rows="5"
        ><?php echo $escape($stringOrNull($content['text'] ?? null)); ?></textarea>
      </div>

      <div class="field">
        <label for="selected_target_mode">Lien de la carte</label>
      <select
        id="selected_target_mode"
        name="<?php echo $escape($baseName . '[target_mode]'); ?>"
        form="<?php echo $escape($menuBuilderFormId); ?>"
      >
        <option value="none" <?php echo $targetMode === 'none' ? 'selected' : ''; ?>>Sans lien</option>
        <option value="page" <?php echo $targetMode === 'page' ? 'selected' : ''; ?>>Vers une page publiée</option>
        <option value="route" <?php echo $targetMode === 'route' ? 'selected' : ''; ?>>Vers une route interne</option>
        <option value="external" <?php echo $targetMode === 'external' ? 'selected' : ''; ?>>Vers une URL externe</option>
      </select>
    </div>
    <?php else: ?>
    <input
      type="hidden"
      name="<?php echo $escape($baseName . '[target_mode]'); ?>"
      form="<?php echo $escape($menuBuilderFormId); ?>"
      value="<?php echo $escape($kind); ?>"
    />
    <?php endif; ?>

    <div class="menu-editor-targets">
      <div class="menu-editor-target<?php echo ($targetMode === 'page' || $kind === 'page') ? ' menu-editor-target-active' : ''; ?>">
        <h4>Page publiée</h4>
        <div class="field">
          <label for="selected_target_page">Page du registre</label>
          <select
            id="selected_target_page"
            name="<?php echo $escape($baseName . '[target_page_slug]'); ?>"
            form="<?php echo $escape($menuBuilderFormId); ?>"
          >
            <option value="">Sélectionner une page</option>
            <?php foreach ($pageOptions as $pageOption): ?>
            <?php $pageSlug = is_string($pageOption['slug'] ?? null) ? $pageOption['slug'] : ''; ?>
            <option
              value="<?php echo $escape($pageSlug); ?>"
              <?php echo $stringOrNull($target['pageSlug'] ?? null) === $pageSlug ? 'selected' : ''; ?>
            >
              <?php
              echo $escape(
                  sprintf(
                      '%s (%s)',
                      (string) ($pageOption['title'] ?? $pageSlug),
                      (string) ($pageOption['route'] ?? '')
                  )
              );
              ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="menu-editor-target<?php echo ($targetMode === 'route' || $kind === 'route') ? ' menu-editor-target-active' : ''; ?>">
        <h4>Route interne</h4>
        <div class="field">
          <label for="selected_target_route">Chemin interne</label>
          <input
            id="selected_target_route"
            type="text"
            name="<?php echo $escape($baseName . '[target_route]'); ?>"
            form="<?php echo $escape($menuBuilderFormId); ?>"
            value="<?php echo $escape($stringOrNull($target['route'] ?? null)); ?>"
            placeholder="/..."
          />
        </div>
      </div>

      <div class="menu-editor-target<?php echo ($targetMode === 'external' || $kind === 'external') ? ' menu-editor-target-active' : ''; ?>">
        <h4>URL externe</h4>
        <div class="field">
          <label for="selected_target_url">Adresse externe</label>
          <input
            id="selected_target_url"
            type="text"
            name="<?php echo $escape($baseName . '[target_url]'); ?>"
            form="<?php echo $escape($menuBuilderFormId); ?>"
            value="<?php echo $escape($stringOrNull($target['url'] ?? null)); ?>"
            placeholder="https://example.com"
          />
        </div>

        <label class="checkbox-field">
          <input
            type="checkbox"
            name="<?php echo $escape($baseName . '[open_in_new_tab]'); ?>"
            form="<?php echo $escape($menuBuilderFormId); ?>"
            value="1"
            <?php echo !empty($target['openInNewTab']) ? 'checked' : ''; ?>
          />
          Ouvrir dans un nouvel onglet
        </label>
      </div>
    </div>

    <?php if ($kind === 'group' && $location === 'primary'): ?>
    <section class="menu-editor-mega">
      <h4>Présentation desktop</h4>
      <div class="menu-editor-presentation-hint">
        <span class="menu-item-card__flag menu-item-card__flag-template menu-item-card__flag-template-<?php echo $escape($menuTemplate); ?>">
          Template <?php echo $templateIsActive ? 'actif' : 'préparé'; ?> : <?php echo $escape($menuTemplateLabel); ?>
        </span>
        <span class="menu-item-card__flag menu-item-card__flag-info">
          Desktop : <?php echo $displayMode === 'mega' ? 'Mega' : 'Dropdown'; ?><?php echo $displayMode === 'mega' ? ' · ' . $columnCount . ' col.' : ''; ?>
        </span>
        <?php if ($displayMode !== 'mega'): ?>
          <span class="menu-item-card__flag menu-item-card__flag-info">Passe en Mega pour appliquer le template</span>
        <?php elseif ($children === []): ?>
          <span class="menu-item-card__flag menu-item-card__flag-info">Ajoute un sous-menu pour afficher le panneau</span>
        <?php endif; ?>
      </div>
      <div class="admin-form-grid admin-form-grid-3">
        <div class="field">
          <label for="selected_display_mode">Mode d’ouverture</label>
          <select
            id="selected_display_mode"
            name="<?php echo $escape($baseName . '[display_mode]'); ?>"
            form="<?php echo $escape($menuBuilderFormId); ?>"
          >
            <option value="dropdown" <?php echo $displayMode === 'dropdown' ? 'selected' : ''; ?>>Dropdown compact</option>
            <option value="mega" <?php echo $displayMode === 'mega' ? 'selected' : ''; ?>>Mega menu</option>
          </select>
        </div>

        <div class="field">
          <label for="selected_column_count">Colonnes</label>
          <select
            id="selected_column_count"
            name="<?php echo $escape($baseName . '[column_count]'); ?>"
            form="<?php echo $escape($menuBuilderFormId); ?>"
          >
            <?php foreach ([2, 3, 4] as $columnOption): ?>
            <option value="<?php echo $columnOption; ?>" <?php echo $columnCount === $columnOption ? 'selected' : ''; ?>>
              <?php echo $columnOption; ?> colonnes
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="selected_menu_template">Template</label>
          <select
            id="selected_menu_template"
            name="<?php echo $escape($baseName . '[menu_template]'); ?>"
            form="<?php echo $escape($menuBuilderFormId); ?>"
          >
            <option value="standard" <?php echo $menuTemplate === 'standard' ? 'selected' : ''; ?>>Standard</option>
            <option value="editorial" <?php echo $menuTemplate === 'editorial' ? 'selected' : ''; ?>>Editorial</option>
            <option value="brands" <?php echo $menuTemplate === 'brands' ? 'selected' : ''; ?>>Marques / catalogue</option>
          </select>
        </div>
      </div>

      <p class="notice-muted">
        En mode mega, les groupes enfants deviennent des sections ou colonnes. Les liens enfants simples sont répartis automatiquement.
      </p>
      <p class="notice-muted">
        Les options <strong>Mode</strong>, <strong>Colonnes</strong> et <strong>Template</strong> impactent le rendu desktop uniquement
        et seulement si ce groupe possède des enfants. Sans enfant, le front affiche un lien simple (pas de panneau).
      </p>
      <p class="notice-muted">
        Aide rapide: <code>docs/ADMIN_MENU_PRESENTATION_HELP.md</code>
      </p>

      <div class="admin-form-grid admin-form-grid-2">
        <div class="field">
          <label for="featured_title">Titre carte mise en avant</label>
          <input
            id="featured_title"
            type="text"
            name="<?php echo $escape($baseName . '[featured_title]'); ?>"
            form="<?php echo $escape($menuBuilderFormId); ?>"
            value="<?php echo $escape($stringOrNull($featuredCard['title'] ?? null)); ?>"
          />
        </div>

        <div class="field">
          <label for="featured_cta_label">Label CTA</label>
          <input
            id="featured_cta_label"
            type="text"
            name="<?php echo $escape($baseName . '[featured_cta_label]'); ?>"
            form="<?php echo $escape($menuBuilderFormId); ?>"
            value="<?php echo $escape($stringOrNull($featuredCard['ctaLabel'] ?? null)); ?>"
          />
        </div>

        <div class="field">
          <label for="featured_image">Image carte mise en avant</label>
          <input
            id="featured_image"
            type="text"
            name="<?php echo $escape($baseName . '[featured_image]'); ?>"
            form="<?php echo $escape($menuBuilderFormId); ?>"
            value="<?php echo $escape($stringOrNull($featuredCard['image'] ?? null)); ?>"
            placeholder="/assets/images/..."
          />
        </div>

        <div class="field">
          <label for="featured_target_mode">Destination CTA</label>
          <select
            id="featured_target_mode"
            name="<?php echo $escape($baseName . '[featured_target_mode]'); ?>"
            form="<?php echo $escape($menuBuilderFormId); ?>"
          >
            <option value="none" <?php echo $featuredMode === 'none' ? 'selected' : ''; ?>>Sans lien</option>
            <option value="page" <?php echo $featuredMode === 'page' ? 'selected' : ''; ?>>Vers une page publiée</option>
            <option value="route" <?php echo $featuredMode === 'route' ? 'selected' : ''; ?>>Vers une route interne</option>
            <option value="external" <?php echo $featuredMode === 'external' ? 'selected' : ''; ?>>Vers une URL externe</option>
          </select>
        </div>
      </div>

      <div class="field">
        <label for="featured_text">Texte carte mise en avant</label>
        <textarea
          id="featured_text"
          name="<?php echo $escape($baseName . '[featured_text]'); ?>"
          form="<?php echo $escape($menuBuilderFormId); ?>"
          rows="4"
        ><?php echo $escape($stringOrNull($featuredCard['text'] ?? null)); ?></textarea>
      </div>

      <div class="menu-editor-targets">
        <div class="menu-editor-target<?php echo $featuredMode === 'page' ? ' menu-editor-target-active' : ''; ?>">
          <h4>CTA vers page</h4>
          <div class="field">
            <label for="featured_target_page_slug">Page du registre</label>
            <select
              id="featured_target_page_slug"
              name="<?php echo $escape($baseName . '[featured_target_page_slug]'); ?>"
              form="<?php echo $escape($menuBuilderFormId); ?>"
            >
              <option value="">Sélectionner une page</option>
              <?php foreach ($pageOptions as $pageOption): ?>
              <?php $pageSlug = is_string($pageOption['slug'] ?? null) ? $pageOption['slug'] : ''; ?>
              <option value="<?php echo $escape($pageSlug); ?>" <?php echo $stringOrNull($featuredTarget['pageSlug'] ?? null) === $pageSlug ? 'selected' : ''; ?>>
                <?php echo $escape((string) ($pageOption['title'] ?? $pageSlug)); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="menu-editor-target<?php echo $featuredMode === 'route' ? ' menu-editor-target-active' : ''; ?>">
          <h4>CTA vers route</h4>
          <div class="field">
            <label for="featured_target_route">Chemin interne</label>
            <input
              id="featured_target_route"
              type="text"
              name="<?php echo $escape($baseName . '[featured_target_route]'); ?>"
              form="<?php echo $escape($menuBuilderFormId); ?>"
              value="<?php echo $escape($stringOrNull($featuredTarget['route'] ?? null)); ?>"
              placeholder="/..."
            />
          </div>
        </div>

        <div class="menu-editor-target<?php echo $featuredMode === 'external' ? ' menu-editor-target-active' : ''; ?>">
          <h4>CTA externe</h4>
          <div class="field">
            <label for="featured_target_url">Adresse externe</label>
            <input
              id="featured_target_url"
              type="text"
              name="<?php echo $escape($baseName . '[featured_target_url]'); ?>"
              form="<?php echo $escape($menuBuilderFormId); ?>"
              value="<?php echo $escape($stringOrNull($featuredTarget['url'] ?? null)); ?>"
              placeholder="https://example.com"
            />
          </div>

          <label class="checkbox-field">
            <input
              type="checkbox"
              name="<?php echo $escape($baseName . '[featured_open_in_new_tab]'); ?>"
              form="<?php echo $escape($menuBuilderFormId); ?>"
              value="1"
              <?php echo !empty($featuredTarget['openInNewTab']) ? 'checked' : ''; ?>
            />
            Ouvrir dans un nouvel onglet
          </label>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($kind === 'group'): ?>
    <div class="notice notice-success">
      Cet item agit comme conteneur de sous-menu. Ajoute ou réorganise ses enfants depuis la colonne structure.
    </div>
    <?php endif; ?>

    <div class="notice-muted">
      Contexte : <strong><?php echo $escape($kindLabels[$kind] ?? $kind); ?></strong>
      <?php if (in_array($location, ['sideLeft', 'sideRight'], true)): ?>
      · Carte éditoriale latérale avec image, texte et lien optionnel.
      <?php endif; ?>
    </div>
    <?php
};

$renderPreviewItems = null;
$renderPreviewItems = static function (array $items, string $listClass = 'menu-preview-list') use (&$renderPreviewItems, $escape): void {
    if ($items === []) {
        ?>
        <div class="menu-preview-empty">Aucun item</div>
        <?php
        return;
    }
    ?>
    <ul class="<?php echo $escape($listClass); ?>">
      <?php foreach ($items as $item): ?>
      <?php if (!is_array($item)) { continue; } ?>
      <li>
        <span>
          <?php echo $escape((string) (($item['label'] ?? null) ?? 'Sans titre')); ?>
          <?php $presentation = is_array($item['presentation'] ?? null) ? $item['presentation'] : []; ?>
          <?php if (($item['panelKind'] ?? null) === 'mega'): ?>
          <small>(mega)</small>
          <?php elseif (($item['panelKind'] ?? null) === 'dropdown'): ?>
          <small>(dropdown)</small>
          <?php endif; ?>
          <?php if (!empty($presentation['isHighlight'])): ?>
          <small>(highlight)</small>
          <?php endif; ?>
          <?php if (!empty($item['external'])): ?>
          <small>(externe)</small>
          <?php endif; ?>
        </span>
        <?php if (is_array($item['children'] ?? null) && $item['children'] !== []): ?>
        <?php $renderPreviewItems($item['children'], 'menu-preview-sublist'); ?>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php
};

$bannerHeadlineLabel = is_array($banner['headline'] ?? null) ? $banner['headline'] : [];
$bannerHeadlineTranslations = $labelTranslationsForInput($bannerHeadlineLabel);
$bannerHeadlineDefaultLanguage = $labelDefaultLanguageForInput($bannerHeadlineLabel);
$bannerHeadlineTranslationKey = $stringOrNull($bannerHeadlineLabel['translationKey'] ?? null);
$bannerHeadline = $localizedValueToString($bannerHeadlineLabel) ?? 'Texte défilant vide';
$bannerHeadlinePreview = $stringOrNull($bannerHeadlineTranslations[$bannerHeadlineDefaultLanguage] ?? null) ?? $bannerHeadline;
$bannerImage = $stringOrNull($banner['image'] ?? null) ?? 'Image non définie';
$bannerAlt = $stringOrNull($banner['accessibility']['alt'] ?? null) ?? 'Alt vide';
$bannerTitle = $stringOrNull($banner['accessibility']['title'] ?? null) ?? 'Title vide';
$backToTopLabelValue = is_array($backToTop['label'] ?? null) ? $backToTop['label'] : [];
$backToTopLabelTranslations = $labelTranslationsForInput($backToTopLabelValue);
$backToTopLabelDefaultLanguage = $labelDefaultLanguageForInput($backToTopLabelValue);
$backToTopLabelTranslationKey = $stringOrNull($backToTopLabelValue['translationKey'] ?? null);
$backToTopLabel = $localizedValueToString($backToTopLabelValue) ?? 'Libellé vide';
$backToTopLabelPreview = $stringOrNull($backToTopLabelTranslations[$backToTopLabelDefaultLanguage] ?? null) ?? $backToTopLabel;
$backToTopAlt = $stringOrNull($backToTop['accessibility']['alt'] ?? null) ?? 'Alt vide';
$backToTopTitle = $stringOrNull($backToTop['accessibility']['title'] ?? null) ?? 'Title vide';
$footerNoticeTranslations = is_array($footerNotice['translations'] ?? null) ? $footerNotice['translations'] : [];
$footerNoticeLanguages = $menuItemLabelLanguages;
$footerNoticeDefaultLanguage = $normalizeLanguageCode($footerNotice['defaultLanguage'] ?? null) ?? $menuItemLabelDefaultLanguage;
if (!in_array($footerNoticeDefaultLanguage, $footerNoticeLanguages, true)) {
    $footerNoticeDefaultLanguage = $menuItemLabelDefaultLanguage;
}
$footerNoticeTranslationKey = $stringOrNull($footerNotice['translationKey'] ?? null) ?? 'TXT_PiedPageModele';
$footerNoticePreview = $stringOrNull($footerNoticeTranslations[$footerNoticeDefaultLanguage] ?? null) ?? 'Texte vide';
if (function_exists('mb_strimwidth')) {
    $footerNoticePreview = mb_strimwidth($footerNoticePreview, 0, 180, '…', 'UTF-8');
} elseif (strlen($footerNoticePreview) > 180) {
    $footerNoticePreview = substr($footerNoticePreview, 0, 180) . '...';
}
?>

<section class="card">
  <h2>Builder des menus</h2>
  <p>
    Edition visuelle par emplacement. Le stockage éditorial est piloté par configuration
    (<code>json</code>, <code>sql</code>, <code>dual-write</code>). Le mode expert expose le JSON canonique normalisé
    sans en faire le workflow principal.
  </p>

  <?php if (($message ?? null) !== null): ?>
  <div class="notice notice-success"><?php echo $escape((string) $message); ?></div>
  <?php endif; ?>

  <?php if (($error ?? null) !== null): ?>
  <div class="notice notice-error"><?php echo $escape((string) $error); ?></div>
  <?php endif; ?>

  <form
    method="post"
    action="<?php echo $escape((string) ($adminMenusUrl ?? admin_url('menus'))); ?>"
    class="menu-builder-form"
    id="<?php echo $escape($menuBuilderFormId); ?>"
  >
    <input
      type="hidden"
      name="csrf_token"
      form="<?php echo $escape($menuBuilderFormId); ?>"
      value="<?php echo $escape((string) ($csrfToken ?? '')); ?>"
    />
    <input
      type="hidden"
      name="active_location"
      form="<?php echo $escape($menuBuilderFormId); ?>"
      value="<?php echo $escape($activeLocation); ?>"
    />
    <input
      type="hidden"
      name="selected_item"
      form="<?php echo $escape($menuBuilderFormId); ?>"
      value="<?php echo $escape($selectedItemPath); ?>"
    />
    <input
      type="hidden"
      name="builder_state_json"
      id="menu-builder-state-json"
      form="<?php echo $escape($menuBuilderFormId); ?>"
      value=""
    />

    <section class="menu-builder-meta-grid">
      <article class="card menu-builder-card">
        <div class="menu-builder-card__header">
          <div>
            <h3>Zone système · bannière</h3>
            <p>Image et texte défilant du header public.</p>
          </div>
          <button type="button" data-region-modal-open="menu-system-banner-dialog">Configurer</button>
        </div>
        <div class="menu-system-card__summary">
          <div class="menu-system-card__row">
            <strong>Langue par défaut</strong>
            <span class="menu-system-card__value"><?php echo $escape(strtoupper($bannerHeadlineDefaultLanguage)); ?></span>
          </div>
          <div class="menu-system-card__row">
            <strong>Image</strong>
            <span class="menu-system-card__value"><?php echo $escape($bannerImage); ?></span>
          </div>
          <div class="menu-system-card__row">
            <strong>Texte</strong>
            <span class="menu-system-card__value"><?php echo $escape($bannerHeadlinePreview); ?></span>
          </div>
          <div class="menu-system-card__row">
            <strong>Accessibilité</strong>
            <span class="menu-system-card__value">Alt: <?php echo $escape($bannerAlt); ?></span>
            <span class="menu-system-card__value">Title: <?php echo $escape($bannerTitle); ?></span>
          </div>
        </div>
      </article>

      <article class="card menu-builder-card">
        <div class="menu-builder-card__header">
          <div>
            <h3>Zone système · remonter</h3>
            <p>Libellé et attributs d’accessibilité du bouton de retour en haut.</p>
          </div>
          <button type="button" data-region-modal-open="menu-system-backtotop-dialog">Configurer</button>
        </div>
        <div class="menu-system-card__summary">
          <div class="menu-system-card__row">
            <strong>Langue par défaut</strong>
            <span class="menu-system-card__value"><?php echo $escape(strtoupper($backToTopLabelDefaultLanguage)); ?></span>
          </div>
          <div class="menu-system-card__row">
            <strong>Libellé</strong>
            <span class="menu-system-card__value"><?php echo $escape($backToTopLabelPreview); ?></span>
          </div>
          <div class="menu-system-card__row">
            <strong>Accessibilité</strong>
            <span class="menu-system-card__value">Alt: <?php echo $escape($backToTopAlt); ?></span>
            <span class="menu-system-card__value">Title: <?php echo $escape($backToTopTitle); ?></span>
          </div>
        </div>
      </article>

      <article class="card menu-builder-card">
        <div class="menu-builder-card__header">
          <div>
            <h3>Zone système · pied de page</h3>
            <p>Texte descriptif affiché sous le menu footer, avec traductions et fallback FR.</p>
          </div>
          <button type="button" data-region-modal-open="menu-system-footer-notice-dialog">Configurer</button>
        </div>
        <div class="menu-system-card__summary">
          <div class="menu-system-card__row">
            <strong>Langue par défaut</strong>
            <span class="menu-system-card__value"><?php echo $escape(strtoupper($footerNoticeDefaultLanguage)); ?></span>
          </div>
          <div class="menu-system-card__row">
            <strong>Clé fallback</strong>
            <span class="menu-system-card__value"><code><?php echo $escape($footerNoticeTranslationKey); ?></code></span>
          </div>
          <div class="menu-system-card__row">
            <strong>Aperçu</strong>
            <span class="menu-system-card__value"><?php echo $escape($footerNoticePreview); ?></span>
          </div>
        </div>
      </article>
    </section>

    <?php foreach ($locationDefinitions as $locationKey => $definition): ?>
    <?php if ($locationKey === $activeLocation) { continue; } ?>
    <?php $inactiveItems = is_array($locations[$locationKey] ?? null) ? $locations[$locationKey] : []; ?>
    <?php foreach ($inactiveItems as $inactiveIndex => $inactiveItem): ?>
    <?php if (!is_array($inactiveItem)) { continue; } ?>
    <?php
    $renderHiddenItemFields(
        $inactiveItem,
        sprintf('locations[%s][%d]', $locationKey, $inactiveIndex),
        $locationKey,
        [$inactiveIndex]
    );
    ?>
    <?php endforeach; ?>
    <?php endforeach; ?>

    <div class="menu-builder-tabs" role="tablist" aria-label="Emplacements du menu">
      <?php foreach ($locationDefinitions as $locationKey => $definition): ?>
      <button
        type="submit"
        name="builder_action"
        value="<?php echo $escape('switch_location@' . $locationKey); ?>"
        class="menu-builder-tab<?php echo $locationKey === $activeLocation ? ' menu-builder-tab-active' : ''; ?>"
      >
        <span><?php echo $escape((string) ($definition['label'] ?? $locationKey)); ?></span>
        <small><?php echo $escape((string) ($definition['summary'] ?? '')); ?></small>
      </button>
      <?php endforeach; ?>
    </div>

    <section class="menu-builder-grid">
      <article class="card menu-builder-card">
        <div class="menu-builder-card__header">
          <div>
            <h3><?php echo $escape((string) ($activeLocationDefinition['label'] ?? $activeLocation)); ?></h3>
            <p><?php echo $escape((string) ($activeLocationDefinition['summary'] ?? '')); ?></p>
          </div>
          <div class="menu-builder-toolbar">
            <?php foreach ((array) ($activeLocationDefinition['addKinds'] ?? []) as $kind): ?>
            <button
              type="submit"
              class="button-muted"
              name="builder_action"
              value="<?php echo $escape('append@' . $activeLocation . '@' . $kind); ?>"
            >
              <?php echo $escape((string) ($addKindLabels[$kind] ?? 'Ajouter')); ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if ($activeLocationItems === []): ?>
        <div class="menu-structure-empty">
          Aucun item pour cet emplacement. Utilise les actions ci-dessus pour commencer.
        </div>
        <?php else: ?>
        <div class="menu-structure-list">
          <?php $renderStructure($activeLocationItems, $activeLocation); ?>
        </div>
        <?php endif; ?>
      </article>
    </section>

    <?php if ($selectedDescriptor !== null && $selectedInputName !== ''): ?>
    <dialog class="region-modal menu-editor-dialog" id="menu-editor-dialog" aria-labelledby="menu-editor-title">
      <div class="region-modal__surface">
        <div class="region-modal__header">
          <div>
            <p class="region-modal__eyebrow">Édition contextuelle</p>
            <h3 id="menu-editor-title">Modifier l’item sélectionné</h3>
            <p>
              Le formulaire s’adapte à l’item sélectionné.
              Les cartes latérales restent distinctes des items de navigation.
            </p>
          </div>

          <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
        </div>

        <div class="region-modal__body menu-editor-dialog__body">
          <?php $renderEditorFields($selectedDescriptor, $activeLocation, $selectedInputName); ?>
        </div>

        <div class="region-modal__actions actions-inline actions-inline-end">
          <button type="button" class="button-muted" data-region-modal-close>Annuler</button>
          <button type="submit" name="builder_action" value="save" form="<?php echo $escape($menuBuilderFormId); ?>">Sauvegarder et fermer</button>
        </div>
      </div>
    </dialog>
    <?php endif; ?>

    <dialog class="region-modal menu-system-dialog" id="menu-system-banner-dialog" aria-labelledby="menu-system-banner-title">
      <div class="region-modal__surface">
        <div class="region-modal__header">
          <div>
            <p class="region-modal__eyebrow">Zone système</p>
            <h3 id="menu-system-banner-title">Modifier la bannière</h3>
            <p>Règle l’image du header, le texte défilant et les attributs d’accessibilité associés.</p>
          </div>
          <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
        </div>

        <div class="region-modal__body">
          <div class="admin-form-grid">
            <div class="field">
              <label for="banner_image">Image</label>
              <input
                id="banner_image"
                type="text"
                name="banner[image]"
                form="<?php echo $escape($menuBuilderFormId); ?>"
                value="<?php echo $escape((string) ($banner['image'] ?? '')); ?>"
                placeholder="/assets/images/structure/banniere.jpg"
              />
            </div>
            <div class="field">
              <label for="banner_headline_default_language">Langue par défaut du texte défilant</label>
              <input
                type="hidden"
                name="banner[headline_translation_key]"
                form="<?php echo $escape($menuBuilderFormId); ?>"
                value="<?php echo $escape($bannerHeadlineTranslationKey); ?>"
              />
              <select
                id="banner_headline_default_language"
                name="banner[headline_default_language]"
                form="<?php echo $escape($menuBuilderFormId); ?>"
              >
                <?php foreach ($menuItemLabelLanguages as $language): ?>
                <option value="<?php echo $escape($language); ?>" <?php echo $bannerHeadlineDefaultLanguage === $language ? 'selected' : ''; ?>>
                  <?php echo $escape(strtoupper($language)); ?>
                </option>
                <?php endforeach; ?>
              </select>
              <small>Si une traduction manque, cette langue est utilisée en repli.</small>
            </div>

            <?php foreach ($menuItemLabelLanguages as $language): ?>
            <div class="field">
              <label for="banner_headline_translation_<?php echo $escape($language); ?>">
                Texte <?php echo $escape(strtoupper($language)); ?>
              </label>
              <input
                id="banner_headline_translation_<?php echo $escape($language); ?>"
                type="text"
                name="banner[headline_translations][<?php echo $escape($language); ?>]"
                form="<?php echo $escape($menuBuilderFormId); ?>"
                value="<?php echo $escape($stringOrNull($bannerHeadlineTranslations[$language] ?? null)); ?>"
              />
            </div>
            <?php endforeach; ?>

            <div class="field">
              <label for="banner_headline">Texte de secours (optionnel)</label>
              <input
                id="banner_headline"
                type="text"
                name="banner[headline]"
                form="<?php echo $escape($menuBuilderFormId); ?>"
                value="<?php echo $escape($localizedValueToString($bannerHeadlineLabel)); ?>"
              />
            </div>
            <div class="admin-form-grid admin-form-grid-2">
              <div class="field">
                <label for="banner_alt">Alt</label>
                <input
                  id="banner_alt"
                  type="text"
                  name="banner[alt]"
                  form="<?php echo $escape($menuBuilderFormId); ?>"
                  value="<?php echo $escape((string) (($banner['accessibility']['alt'] ?? null) ?? '')); ?>"
                />
              </div>
              <div class="field">
                <label for="banner_title">Title</label>
                <input
                  id="banner_title"
                  type="text"
                  name="banner[title]"
                  form="<?php echo $escape($menuBuilderFormId); ?>"
                  value="<?php echo $escape((string) (($banner['accessibility']['title'] ?? null) ?? '')); ?>"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="region-modal__actions actions-inline actions-inline-end">
          <button type="button" class="button-muted" data-region-modal-close>Annuler</button>
          <button type="submit" name="builder_action" value="save" form="<?php echo $escape($menuBuilderFormId); ?>">Appliquer et sauvegarder</button>
        </div>
      </div>
    </dialog>

    <dialog class="region-modal menu-system-dialog" id="menu-system-backtotop-dialog" aria-labelledby="menu-system-backtotop-title">
      <div class="region-modal__surface">
        <div class="region-modal__header">
          <div>
            <p class="region-modal__eyebrow">Zone système</p>
            <h3 id="menu-system-backtotop-title">Modifier “Remonter”</h3>
            <p>Règle le libellé et les attributs d’accessibilité du bouton de retour en haut.</p>
          </div>
          <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
        </div>

        <div class="region-modal__body">
          <div class="admin-form-grid">
            <div class="field">
              <label for="back_to_top_label_default_language">Langue par défaut du libellé</label>
              <input
                type="hidden"
                name="remonter[label_translation_key]"
                form="<?php echo $escape($menuBuilderFormId); ?>"
                value="<?php echo $escape($backToTopLabelTranslationKey); ?>"
              />
              <select
                id="back_to_top_label_default_language"
                name="remonter[label_default_language]"
                form="<?php echo $escape($menuBuilderFormId); ?>"
              >
                <?php foreach ($menuItemLabelLanguages as $language): ?>
                <option value="<?php echo $escape($language); ?>" <?php echo $backToTopLabelDefaultLanguage === $language ? 'selected' : ''; ?>>
                  <?php echo $escape(strtoupper($language)); ?>
                </option>
                <?php endforeach; ?>
              </select>
              <small>Si une traduction manque, cette langue est utilisée en repli.</small>
            </div>

            <?php foreach ($menuItemLabelLanguages as $language): ?>
            <div class="field">
              <label for="back_to_top_label_translation_<?php echo $escape($language); ?>">
                Libellé <?php echo $escape(strtoupper($language)); ?>
              </label>
              <input
                id="back_to_top_label_translation_<?php echo $escape($language); ?>"
                type="text"
                name="remonter[label_translations][<?php echo $escape($language); ?>]"
                form="<?php echo $escape($menuBuilderFormId); ?>"
                value="<?php echo $escape($stringOrNull($backToTopLabelTranslations[$language] ?? null)); ?>"
              />
            </div>
            <?php endforeach; ?>

            <div class="field">
              <label for="back_to_top_label">Libellé de secours (optionnel)</label>
              <input
                id="back_to_top_label"
                type="text"
                name="remonter[label]"
                form="<?php echo $escape($menuBuilderFormId); ?>"
                value="<?php echo $escape($localizedValueToString($backToTopLabelValue)); ?>"
              />
            </div>
            <div class="admin-form-grid admin-form-grid-2">
              <div class="field">
                <label for="back_to_top_alt">Alt</label>
                <input
                  id="back_to_top_alt"
                  type="text"
                  name="remonter[alt]"
                  form="<?php echo $escape($menuBuilderFormId); ?>"
                  value="<?php echo $escape((string) (($backToTop['accessibility']['alt'] ?? null) ?? '')); ?>"
                />
              </div>
              <div class="field">
                <label for="back_to_top_title">Title</label>
                <input
                  id="back_to_top_title"
                  type="text"
                  name="remonter[title]"
                  form="<?php echo $escape($menuBuilderFormId); ?>"
                  value="<?php echo $escape((string) (($backToTop['accessibility']['title'] ?? null) ?? '')); ?>"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="region-modal__actions actions-inline actions-inline-end">
          <button type="button" class="button-muted" data-region-modal-close>Annuler</button>
          <button type="submit" name="builder_action" value="save" form="<?php echo $escape($menuBuilderFormId); ?>">Appliquer et sauvegarder</button>
        </div>
      </div>
    </dialog>

    <dialog class="region-modal menu-system-dialog" id="menu-system-footer-notice-dialog" aria-labelledby="menu-system-footer-notice-title">
      <div class="region-modal__surface">
        <div class="region-modal__header">
          <div>
            <p class="region-modal__eyebrow">Zone système</p>
            <h3 id="menu-system-footer-notice-title">Modifier le texte de pied de page</h3>
            <p>Tu peux saisir les traductions et garder le français comme fallback par défaut.</p>
          </div>
          <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
        </div>

        <div class="region-modal__body">
          <div class="admin-form-grid">
            <input
              type="hidden"
              name="footer_notice[translation_key]"
              form="<?php echo $escape($menuBuilderFormId); ?>"
              value="<?php echo $escape($footerNoticeTranslationKey); ?>"
            />

            <div class="field">
              <label for="footer_notice_default_language">Langue par défaut</label>
              <select
                id="footer_notice_default_language"
                name="footer_notice[default_language]"
                form="<?php echo $escape($menuBuilderFormId); ?>"
              >
                <?php foreach ($footerNoticeLanguages as $language): ?>
                <option value="<?php echo $escape($language); ?>" <?php echo $footerNoticeDefaultLanguage === $language ? 'selected' : ''; ?>>
                  <?php echo $escape(strtoupper($language)); ?>
                </option>
                <?php endforeach; ?>
              </select>
              <small>Si une traduction manque, le front utilise cette langue en repli (FR recommandé).</small>
            </div>

            <?php foreach ($footerNoticeLanguages as $language): ?>
            <div class="field">
              <label for="footer_notice_translation_<?php echo $escape($language); ?>">
                Texte <?php echo $escape(strtoupper($language)); ?>
              </label>
              <textarea
                id="footer_notice_translation_<?php echo $escape($language); ?>"
                name="footer_notice[translations][<?php echo $escape($language); ?>]"
                form="<?php echo $escape($menuBuilderFormId); ?>"
                rows="4"
              ><?php echo $escape($stringOrNull($footerNoticeTranslations[$language] ?? null)); ?></textarea>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="region-modal__actions actions-inline actions-inline-end">
          <button type="button" class="button-muted" data-region-modal-close>Annuler</button>
          <button type="submit" name="builder_action" value="save" form="<?php echo $escape($menuBuilderFormId); ?>">Appliquer et sauvegarder</button>
        </div>
      </div>
    </dialog>

    <section class="menu-preview-grid">
      <article class="card menu-builder-card">
        <h3>Aperçu simplifié · header desktop</h3>
        <div class="menu-preview-header">
          <div class="menu-preview-banner">
            <strong><?php echo $escape((string) (($preview['brand']['label'] ?? null) ?? 'Les Caramagnols')); ?></strong>
            <span><?php echo $escape((string) (($preview['banner']['headline'] ?? null) ?? 'Bannière vide')); ?></span>
          </div>
          <div class="menu-preview-utility">
            <?php foreach ((array) ($preview['utility'] ?? []) as $utilityItem): ?>
            <?php if (!is_array($utilityItem)) { continue; } ?>
            <span class="menu-preview-chip">
              <?php echo $escape((string) (($utilityItem['label'] ?? null) ?? ($utilityItem['title'] ?? 'Lien'))); ?>
            </span>
            <?php endforeach; ?>
          </div>
          <div class="menu-preview-primary">
            <?php $renderPreviewItems((array) ($preview['primary'] ?? [])); ?>
          </div>
        </div>
      </article>

      <article class="card menu-builder-card">
        <h3>Aperçu simplifié · header mobile</h3>
        <div class="menu-preview-mobile">
          <div class="menu-preview-mobile__top">
            <span class="menu-preview-hamburger" aria-hidden="true">☰</span>
            <span><?php echo $escape((string) (($preview['brand']['label'] ?? null) ?? 'Les Caramagnols')); ?></span>
          </div>
          <p><?php echo $escape((string) (($preview['banner']['headline'] ?? null) ?? 'Bannière vide')); ?></p>
          <?php $renderPreviewItems((array) ($preview['primary'] ?? [])); ?>
        </div>
      </article>

      <article class="card menu-builder-card">
        <h3>Aperçu des blocs latéraux</h3>
        <div class="menu-preview-sides">
          <section>
            <h4>Gauche</h4>
            <?php foreach ((array) ($preview['sideLeft'] ?? []) as $leftItem): ?>
            <?php if (!is_array($leftItem)) { continue; } ?>
            <article class="menu-preview-side-card">
              <?php if (!empty($leftItem['image'])): ?>
              <div class="menu-preview-side-card__image"><?php echo $escape((string) $leftItem['image']); ?></div>
              <?php endif; ?>
              <strong><?php echo $escape((string) (($leftItem['label'] ?? null) ?? 'Sans titre')); ?></strong>
              <?php if (!empty($leftItem['text'])): ?>
              <p><?php echo $escape((string) $leftItem['text']); ?></p>
              <?php endif; ?>
              <?php if (!empty($leftItem['href'])): ?>
              <code><?php echo $escape((string) $leftItem['href']); ?></code>
              <?php endif; ?>
            </article>
            <?php endforeach; ?>
          </section>

          <section>
            <h4>Droite</h4>
            <?php foreach ((array) ($preview['sideRight'] ?? []) as $rightItem): ?>
            <?php if (!is_array($rightItem)) { continue; } ?>
            <article class="menu-preview-side-card">
              <?php if (!empty($rightItem['image'])): ?>
              <div class="menu-preview-side-card__image"><?php echo $escape((string) $rightItem['image']); ?></div>
              <?php endif; ?>
              <strong><?php echo $escape((string) (($rightItem['label'] ?? null) ?? 'Sans titre')); ?></strong>
              <?php if (!empty($rightItem['text'])): ?>
              <p><?php echo $escape((string) $rightItem['text']); ?></p>
              <?php endif; ?>
              <?php if (!empty($rightItem['href'])): ?>
              <code><?php echo $escape((string) $rightItem['href']); ?></code>
              <?php endif; ?>
            </article>
            <?php endforeach; ?>
          </section>
        </div>
      </article>
    </section>

    <details class="card expert-mode">
      <summary>Mode expert · JSON canonique</summary>
      <p class="notice-muted">
        Lecture seule. Le JSON reste visible pour audit ou dépannage, mais n’est plus le workflow principal.
      </p>
      <div class="field">
        <label for="expert_json">JSON canonique</label>
        <textarea id="expert_json" rows="22" readonly><?php echo $escape($expertJson); ?></textarea>
      </div>
    </details>

    <div class="actions-inline actions-inline-end">
      <button type="submit" name="builder_action" value="save">Sauvegarder les menus</button>
    </div>
  </form>
</section>

<?php $cspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
<script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
  (() => {
    const form = document.querySelector('.menu-builder-form');
    if (form instanceof HTMLFormElement) {
      const stateField = document.getElementById('menu-builder-state-json');
      const associatedSelector = form.id !== ''
        ? `input[form="${form.id}"], textarea[form="${form.id}"], select[form="${form.id}"], button[form="${form.id}"]`
        : '';
      const tokenizeFieldName = (name) => {
        const matches = name.match(/[^[\]]+/g);
        return Array.isArray(matches) ? matches : [];
      };
      const collectFormFields = () => {
        const fields = [];
        const seen = new Set();
        const pushField = (field) => {
          if (
            !(field instanceof HTMLInputElement)
            && !(field instanceof HTMLSelectElement)
            && !(field instanceof HTMLTextAreaElement)
            && !(field instanceof HTMLButtonElement)
          ) {
            return;
          }

          if (seen.has(field)) {
            return;
          }

          seen.add(field);
          fields.push(field);
        };

        Array.from(form.elements).forEach(pushField);
        if (associatedSelector !== '') {
          Array.from(document.querySelectorAll(associatedSelector)).forEach(pushField);
        }

        return fields;
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
        if (name === '' || name === 'csrf_token' || name === 'builder_action' || name === 'builder_state_json') {
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
        collectFormFields().forEach((field) => {
          const name = field.getAttribute('name') || '';
          const shouldKeepEnabled = name === 'csrf_token'
            || name === 'builder_state_json'
            || field === submitter;
          field.disabled = !shouldKeepEnabled;
        });
      };
      const serializeBuilderState = (submitter) => {
        if (!(stateField instanceof HTMLInputElement)) {
          return;
        }

        const payload = {};
        collectFormFields().forEach((field) => {
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
        freezeFieldsForSubmit(submitter);
      };

      const shouldConfirmDeleteAction = (submitter) => {
        if (!(submitter instanceof HTMLButtonElement)) {
          return false;
        }

        return submitter.name === 'builder_action' && submitter.value.startsWith('remove@');
      };
      const resolveDeleteWarning = (submitter) => {
        if (submitter instanceof HTMLButtonElement) {
          const submitterWarning = submitter.getAttribute('data-menu-delete-warning');
          if (submitterWarning && submitterWarning.trim() !== '') {
            return submitterWarning.trim();
          }
        }

        return 'ATTENTION : suppression definitive de cet element de menu.';
      };
      const confirmDeleteAction = (warning) => {
        if (typeof window.confirm !== 'function') {
          return false;
        }

        return window.confirm(warning);
      };

      form.addEventListener('submit', (event) => {
        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        if (shouldConfirmDeleteAction(submitter) && !confirmDeleteAction(resolveDeleteWarning(submitter))) {
          event.preventDefault();
          return;
        }

        serializeBuilderState(submitter);
      });
    }

    if (form instanceof HTMLFormElement && typeof window.sessionStorage !== 'undefined') {
      const scrollStorageKey = `admin-menus-scroll:${window.location.pathname}`;
      const persistScrollPosition = () => {
        window.sessionStorage.setItem(scrollStorageKey, String(window.scrollY));
      };
      const restoreScrollPosition = () => {
        const rawValue = window.sessionStorage.getItem(scrollStorageKey);
        if (rawValue === null) {
          return;
        }

        window.sessionStorage.removeItem(scrollStorageKey);
        const top = Number.parseInt(rawValue, 10);
        if (Number.isNaN(top) || top < 0) {
          return;
        }

        window.requestAnimationFrame(() => {
          window.scrollTo({ top, left: 0, behavior: 'auto' });
        });
      };

      form.addEventListener('submit', persistScrollPosition);

      if (document.readyState === 'complete') {
        restoreScrollPosition();
      } else {
        window.addEventListener('load', restoreScrollPosition, { once: true });
      }
    }

    const bindDialogSnapshotReset = (dialogId) => {
      const dialog = document.getElementById(dialogId);
      if (!(dialog instanceof HTMLDialogElement)) {
        return;
      }

      const controls = Array.from(dialog.querySelectorAll('input, textarea, select'));
      const snapshot = controls.map((control) => {
        if (control instanceof HTMLInputElement && (control.type === 'checkbox' || control.type === 'radio')) {
          return { checked: control.checked, value: control.value };
        }

        return { checked: false, value: control.value };
      });

      dialog.addEventListener('close', () => {
        controls.forEach((control, index) => {
          const state = snapshot[index];
          if (state === undefined) {
            return;
          }

          if (control instanceof HTMLInputElement && (control.type === 'checkbox' || control.type === 'radio')) {
            control.checked = state.checked;
            return;
          }

          control.value = state.value;
        });
      });
    };

    ['menu-editor-dialog', 'menu-system-banner-dialog', 'menu-system-backtotop-dialog', 'menu-system-footer-notice-dialog'].forEach(bindDialogSnapshotReset);
  })();
</script>

<section class="card">
  <h2>Références pages publiées</h2>
  <p class="notice-muted">
    Les items de type <code>page</code> et les cartes latérales liées à une page ne peuvent cibler qu’une page publiée.
  </p>

  <?php if ($pageReferences === []): ?>
  <p class="notice-muted">Aucune page enregistrée dans le registre éditorial.</p>
  <?php else: ?>
  <div class="table-shell">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Slug</th>
          <th>Titre</th>
          <th>Statut</th>
          <th>Route</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pageReferences as $pageReference): ?>
        <tr>
          <td><code><?php echo $escape((string) ($pageReference['slug'] ?? '')); ?></code></td>
          <td><?php echo $escape((string) ($pageReference['title'] ?? '')); ?></td>
          <td>
            <span class="status-pill status-<?php echo $escape((string) ($pageReference['status'] ?? '')); ?>">
              <?php echo $escape((string) ($pageReference['status'] ?? '')); ?>
            </span>
          </td>
          <td><code><?php echo $escape((string) ($pageReference['route'] ?? '')); ?></code></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
