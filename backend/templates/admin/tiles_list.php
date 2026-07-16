<?php
$groups = is_array($groups ?? null) ? $groups : [];
$tilesEnabled = !empty($tilesEnabled);
$translate = static function (string $key, string $fallback): string {
    if (function_exists('admin_translate')) {
        return admin_translate($key, $fallback);
    }

    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
        return $fallback;
    }

    return $translated;
};
$groupCount = count($groups);
$placementCount = array_sum(array_map(
    static fn (array $group): int => max(0, (int) ($group['placementCount'] ?? 0)),
    $groups
));
$renderAdminTilePreview = static function (array $tile, string $context = 'catalog'): void {
    $size = \Caramagnols\Content\TileRepository::normalizeTileSizeValue((string) ($tile['tile_size'] ?? \Caramagnols\Content\TileRepository::DEFAULT_SIZE));
    $color = \Caramagnols\Content\TileRepository::buttonColorToken($size, (string) ($tile['color_token'] ?? 'bleu'));
    $imageSrc = trim((string) ($tile['image_src'] ?? ''));
    $label = trim((string) ($tile['label'] ?? ''));
    if ($label === '') {
        $label = $translate('TXT_ADMIN_TILES_TILE_LABEL', 'Tuile');
    }

    $title = trim((string) ($tile['title'] ?? ''));
    $summary = $title !== '' && strcasecmp($title, $label) !== 0 ? $title : '';
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
          <img src="<?php echo htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async" />
        </figure>
        <?php endif; ?>
        <div class="admin-tile-preview__overlay"></div>
        <div class="admin-tile-preview__content">
          <span class="admin-tile-preview__label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
          <?php if ($summary !== ''): ?>
          <span class="admin-tile-preview__summary"><?php echo htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endif; ?>
        </div>
      </div>
    </article>
    <?php
};
?>

<section class="cards-grid">
  <article class="card">
    <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_TILES_GROUPS_TITLE', 'Groupes de tuiles'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p>
      <?php echo htmlspecialchars($translate('TXT_ADMIN_TILES_GROUPS_BODY', 'Ce module centralise les menus visuels de type Windows 10 rendus en after_body. Un groupe est réutilisable sur plusieurs pages, puis ajustable page par page.'), ENT_QUOTES, 'UTF-8'); ?>
    </p>
    <p class="actions-inline">
      <a class="button-link" href="<?php echo htmlspecialchars((string) ($createTileUrl ?? $adminTileCreateUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_TILES_CREATE_GROUP', 'Créer un groupe'), ENT_QUOTES, 'UTF-8'); ?></a>
    </p>
  </article>

  <article class="card">
    <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_QUICK_VIEW', 'Vue rapide'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <ul>
      <li><span class="tag"><?php echo htmlspecialchars($translate('TXT_ADMIN_TILES_GROUPS_TAG', 'Groupes'), ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_TILES_GROUPS_COUNT', '%d groupe(s) disponible(s).'), $groupCount), ENT_QUOTES, 'UTF-8'); ?></li>
      <li><span class="tag"><?php echo htmlspecialchars($translate('TXT_ADMIN_TILES_PLACEMENTS_TAG', 'Placements'), ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_TILES_PLACEMENTS_COUNT', '%d rattachement(s) de page.'), $placementCount), ENT_QUOTES, 'UTF-8'); ?></li>
    </ul>
  </article>
</section>

<?php if (($message ?? null) !== null): ?>
<div class="notice notice-success"><?php echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if (($error ?? null) !== null): ?>
<div class="notice notice-error"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<section class="card">
  <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_TILES_CATALOG_TITLE', 'Catalogue'), ENT_QUOTES, 'UTF-8'); ?></h2>

  <?php if (!$tilesEnabled): ?>
  <p class="notice-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_TILES_SQL_ONLY', 'Le module Tuiles est disponible uniquement quand le stockage éditorial SQL est actif.'), ENT_QUOTES, 'UTF-8'); ?></p>
  <?php elseif ($groups === []): ?>
  <p class="notice-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_TILES_NO_GROUPS', 'Aucun groupe de tuiles n est encore enregistré.'), ENT_QUOTES, 'UTF-8'); ?></p>
  <?php else: ?>
  <div class="cards-grid tile-admin-catalog">
    <?php foreach ($groups as $group): ?>
    <?php $groupId = (int) ($group['id'] ?? 0); ?>
    <?php $previewItems = is_array($group['previewItems'] ?? null) ? array_values($group['previewItems']) : []; ?>
    <article class="card tile-admin-group-card">
      <div class="page-editor-intro__header">
        <div>
          <h3><?php echo htmlspecialchars((string) ($group['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
          <p class="notice-muted">
            <code>#<?php echo $groupId; ?></code>
            · <?php echo htmlspecialchars($translate('TXT_ADMIN_TILES_THEME_LABEL', 'Thème'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) ($group['theme'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
          </p>
        </div>
        <div class="tile-admin-group-card__actions">
          <a class="button-link" href="<?php echo htmlspecialchars((string) (($adminTilesUrl ?? admin_url('tiles')) . '/' . $groupId), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_EDIT', 'Éditer'), ENT_QUOTES, 'UTF-8'); ?></a>
          <form
            class="tile-admin-group-card__duplicate-form"
            method="post"
            action="<?php echo htmlspecialchars((string) ($adminTilesUrl ?? admin_url('tiles')), ENT_QUOTES, 'UTF-8'); ?>"
          >
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="tile_list_action" value="duplicate">
            <input type="hidden" name="group_id" value="<?php echo $groupId; ?>">
            <button type="submit" class="button-link button-link-muted tile-admin-group-card__duplicate-button"><?php echo htmlspecialchars($translate('TXT_ADMIN_TILES_DUPLICATE', 'Dupliquer'), ENT_QUOTES, 'UTF-8'); ?></button>
          </form>
        </div>
      </div>

      <div class="tile-admin-group-card__stats">
        <span class="tag"><?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_TILES_TILE_COUNT', '%d tuile(s)'), max(0, (int) ($group['tileCount'] ?? 0))), ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="tag"><?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_TILES_PAGE_COUNT', '%d page(s)'), max(0, (int) ($group['placementCount'] ?? 0))), ENT_QUOTES, 'UTF-8'); ?></span>
      </div>

      <?php if ($previewItems === []): ?>
      <p class="notice-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_TILES_NO_TILES', 'Ce groupe n a pas encore de tuiles configurées.'), ENT_QUOTES, 'UTF-8'); ?></p>
      <?php else: ?>
      <div class="admin-tile-mosaic admin-tile-mosaic--catalog" aria-hidden="true">
        <?php foreach ($previewItems as $previewItem): ?>
        <?php $renderAdminTilePreview(is_array($previewItem) ? $previewItem : [], 'catalog'); ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
