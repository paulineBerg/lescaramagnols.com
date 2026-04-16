<?php
$formData = is_array($formData ?? null) ? $formData : [];
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
    'draft' => $translate('TXT_ADMIN_ARTICLE_STATUS_DRAFT', 'Brouillon'),
    'scheduled' => $translate('TXT_ADMIN_ARTICLE_STATUS_SCHEDULED', 'Planifié'),
    'published' => $translate('TXT_ADMIN_ARTICLE_STATUS_PUBLISHED', 'Publié'),
];
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
        'context' => (string) ($contentMediaPolicy['context'] ?? 'article'),
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
$currentLanguage = (string) ($formData['lang'] ?? 'fr');
$featuredImageSrc = trim((string) ($formData['featured_image_src'] ?? ''));
$featuredImageAlt = trim((string) ($formData['featured_image_alt'] ?? ''));
?>

<section class="cards-grid">
  <article class="card">
    <h2><?php echo ($isNewArticle ?? false) ? 'Nouvel article' : 'Article du blog'; ?></h2>
    <p>
      Une catégorie principale, plusieurs tags, puis filtres rapides automatiquement réutilisables sur le front.
      Les commentaires et traductions techniques déjà présents sont conservés à la sauvegarde.
    </p>
    <p class="actions-inline">
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($articlesIndexUrl ?? $adminArticlesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Retour à la liste</a>
      <?php if (($isNewArticle ?? false) === false): ?>
      <span class="tag"><?php echo strtoupper(htmlspecialchars($currentLanguage, ENT_QUOTES, 'UTF-8')); ?></span>
      <?php endif; ?>
    </p>
  </article>

  <article class="card">
    <h2>Référentiel existant</h2>
    <p class="notice-muted">Saisis librement, avec suggestions issues des articles déjà enregistrés.</p>
    <div class="field">
      <label>Catégories existantes</label>
      <div class="taxonomy-chip-list">
        <?php foreach (($availableCategories ?? []) as $category): ?>
        <span class="taxonomy-chip"><?php echo htmlspecialchars((string) $category, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="field">
      <label>Tags existants</label>
      <div class="taxonomy-chip-list">
        <?php foreach (($availableTags ?? []) as $tag): ?>
        <span class="taxonomy-chip"><?php echo htmlspecialchars((string) $tag, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </article>
</section>

<?php if (($message ?? null) !== null): ?>
<div class="notice notice-success"><?php echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if (($error ?? null) !== null): ?>
<div class="notice notice-error"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<section class="card">
  <h2>Édition</h2>

  <form method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars((string) ($currentArticleUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />

    <div class="admin-form-grid admin-form-grid-3">
      <div class="field admin-form-span-2">
        <label for="article-title">Titre</label>
        <input id="article-title" name="article[title]" type="text" value="<?php echo htmlspecialchars((string) ($formData['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
      </div>

      <div class="field">
        <label for="article-status">Statut</label>
        <select id="article-status" name="article[status]">
          <?php foreach (($supportedStatuses ?? []) as $status): ?>
          <option value="<?php echo htmlspecialchars((string) $status, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($formData['status'] ?? 'draft') === $status ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($statusLabels[$status] ?? (string) $status, ENT_QUOTES, 'UTF-8'); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="article-slug">Slug</label>
        <input id="article-slug" name="article[slug]" type="text" value="<?php echo htmlspecialchars((string) ($formData['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
      </div>

      <div class="field">
        <label for="article-lang">Langue</label>
        <select id="article-lang" name="article[lang]">
          <?php foreach (($availableLanguages ?? []) as $language): ?>
          <option value="<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($formData['lang'] ?? 'fr') === $language ? ' selected' : ''; ?>>
            <?php echo strtoupper(htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8')); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="article-author">Auteur</label>
        <input id="article-author" name="article[author]" type="text" value="<?php echo htmlspecialchars((string) ($formData['author'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
      </div>

      <div class="field">
        <label for="article-date">Date</label>
        <input id="article-date" name="article[date]" type="datetime-local" value="<?php echo htmlspecialchars((string) ($formData['date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
        <p class="admin-form-help">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_DATE_HELP', 'Date éditoriale de référence (tri, affichage et chronologie).'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
      </div>

      <div class="field">
        <label for="article-scheduled-publish-at"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_SCHEDULED_AT_LABEL', 'Publication programmée'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input
          id="article-scheduled-publish-at"
          name="article[scheduled_publish_at]"
          type="datetime-local"
          value="<?php echo htmlspecialchars((string) ($formData['scheduled_publish_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        />
        <p class="admin-form-help">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_SCHEDULED_AT_HELP', 'Pour une publication automatique, choisissez le statut "Planifié" puis renseignez cette date.'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
      </div>

      <div class="field admin-form-span-2">
        <label for="article-page-slug">Page parent de publication</label>
        <select id="article-page-slug" name="article[page_slug]" required>
          <option value="" disabled<?php echo trim((string) ($formData['page_slug'] ?? '')) === '' ? ' selected' : ''; ?>>Choisir une page parent (obligatoire)</option>
          <?php foreach (($availablePageOptions ?? []) as $pageOption): ?>
          <?php
          $pageSlug = (string) ($pageOption['slug'] ?? '');
          $pageTitle = trim((string) ($pageOption['title'] ?? 'Page sans titre'));
          $pageRoute = trim((string) ($pageOption['route'] ?? ''));
          $pageStatus = trim((string) ($pageOption['status'] ?? 'draft'));
          ?>
          <option value="<?php echo htmlspecialchars($pageSlug, ENT_QUOTES, 'UTF-8'); ?>"<?php echo (($formData['page_slug'] ?? '') === $pageSlug) ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>
            <?php echo $pageRoute !== '' ? ' · ' . htmlspecialchars($pageRoute, ENT_QUOTES, 'UTF-8') : ''; ?>
            <?php echo $pageStatus !== '' ? ' · ' . htmlspecialchars($statusLabels[$pageStatus] ?? $pageStatus, ENT_QUOTES, 'UTF-8') : ''; ?>
            <?php echo $pageSlug !== '' ? ' · ' . htmlspecialchars($pageSlug, ENT_QUOTES, 'UTF-8') : ''; ?>
          </option>
          <?php endforeach; ?>
        </select>
        <p class="admin-form-help">
          Obligatoire. L’article est ouvert depuis la page blog sur cette page parent, puis déroule automatiquement l’article demandé.
        </p>
      </div>

      <div class="field admin-form-span-2">
        <label for="article-parent-slug">Article parent</label>
        <select id="article-parent-slug" name="article[parent_slug]">
          <option value="">Aucun parent (article racine)</option>
          <?php foreach (($availableParentArticles ?? []) as $parentArticle): ?>
          <?php
          $parentSlug = (string) ($parentArticle['slug'] ?? '');
          $parentTitle = (string) ($parentArticle['title'] ?? 'Article sans titre');
          $parentStatus = (string) ($parentArticle['status'] ?? 'draft');
          $parentDate = trim((string) ($parentArticle['date'] ?? ''));
          ?>
          <option value="<?php echo htmlspecialchars($parentSlug, ENT_QUOTES, 'UTF-8'); ?>"<?php echo (($formData['parent_slug'] ?? '') === $parentSlug) ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($parentTitle, ENT_QUOTES, 'UTF-8'); ?>
            [<?php echo strtoupper(htmlspecialchars((string) ($parentArticle['lang'] ?? $currentLanguage), ENT_QUOTES, 'UTF-8')); ?>]
            <?php echo $parentDate !== '' ? ' · ' . htmlspecialchars($parentDate, ENT_QUOTES, 'UTF-8') : ''; ?>
            · <?php echo htmlspecialchars($statusLabels[$parentStatus] ?? $parentStatus, ENT_QUOTES, 'UTF-8'); ?>
            · <?php echo htmlspecialchars($parentSlug, ENT_QUOTES, 'UTF-8'); ?>
          </option>
          <?php endforeach; ?>
        </select>
        <p class="admin-form-help">
          L’article sera affiche sous ce parent sur le front. Les suggestions suivent la langue actuellement ouverte dans l’edition.
        </p>
      </div>

      <div class="field">
        <label for="article-child-sort-order">Ordre manuel sous le parent</label>
        <input
          id="article-child-sort-order"
          name="article[child_sort_order]"
          type="number"
          min="1"
          step="1"
          value="<?php echo htmlspecialchars((string) ($formData['child_sort_order'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        />
        <p class="admin-form-help">Optionnel. Laisser vide pour utiliser l’ordre de creation.</p>
      </div>

      <div class="field">
        <label for="article-category">Catégorie</label>
        <input id="article-category" name="article[category]" type="text" list="article-categories-list" value="<?php echo htmlspecialchars((string) ($formData['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
      </div>

      <div class="field admin-form-span-2">
        <label for="article-tags">Tags</label>
        <input id="article-tags" name="article[tags_input]" type="text" value="<?php echo htmlspecialchars((string) ($formData['tags_input'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="austin, club, sortie" />
        <p class="admin-form-help">Saisie libre, séparée par des virgules. Les tags existants restent proposés ci-dessus comme référence.</p>
      </div>

      <div class="field admin-form-span-2">
        <label for="article-featured-image-src"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_FEATURED_IMAGE_LABEL', 'Image de couverture'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input
          id="article-featured-image-src"
          name="article[featured_image_src]"
          type="text"
          value="<?php echo htmlspecialchars($featuredImageSrc, ENT_QUOTES, 'UTF-8'); ?>"
          placeholder="/uploads/editorial/article/..."
        />
        <p class="admin-form-help">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_FEATURED_IMAGE_HELP', 'Utilisée dans les cartes blog et les balises SEO (Open Graph / Twitter).'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
      </div>

      <div class="field">
        <label for="article-image-file"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_FEATURED_IMAGE_UPLOAD_LABEL', 'Upload image'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input
          id="article-image-file"
          name="article_image_file"
          type="file"
          accept="image/jpeg,image/png,image/webp,image/gif,image/avif"
        />
        <p class="admin-form-help">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_FEATURED_IMAGE_UPLOAD_HELP', 'JPG, PNG, WebP, GIF ou AVIF. Le chemin est rempli automatiquement après upload.'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
      </div>

      <div class="field">
        <label for="article-featured-image-alt"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_FEATURED_IMAGE_ALT_LABEL', 'Texte alternatif'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input
          id="article-featured-image-alt"
          name="article[featured_image_alt]"
          type="text"
          value="<?php echo htmlspecialchars($featuredImageAlt, ENT_QUOTES, 'UTF-8'); ?>"
        />
      </div>

      <div class="field">
        <label for="article-featured-image-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_FEATURED_IMAGE_TITLE_LABEL', 'Titre image'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input
          id="article-featured-image-title"
          name="article[featured_image_title]"
          type="text"
          value="<?php echo htmlspecialchars((string) ($formData['featured_image_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        />
      </div>

      <div class="field">
        <label for="article-featured-image-width"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_FEATURED_IMAGE_WIDTH_LABEL', 'Largeur'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input
          id="article-featured-image-width"
          name="article[featured_image_width]"
          type="number"
          min="1"
          max="8192"
          step="1"
          value="<?php echo htmlspecialchars((string) ($formData['featured_image_width'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        />
      </div>

      <div class="field">
        <label for="article-featured-image-height"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_FEATURED_IMAGE_HEIGHT_LABEL', 'Hauteur'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input
          id="article-featured-image-height"
          name="article[featured_image_height]"
          type="number"
          min="1"
          max="8192"
          step="1"
          value="<?php echo htmlspecialchars((string) ($formData['featured_image_height'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        />
      </div>

      <div class="field admin-form-span-2">
        <label for="article-featured-image-caption"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_FEATURED_IMAGE_CAPTION_LABEL', 'Légende'), ENT_QUOTES, 'UTF-8'); ?></label>
        <textarea
          id="article-featured-image-caption"
          name="article[featured_image_caption]"
          rows="2"
        ><?php echo htmlspecialchars((string) ($formData['featured_image_caption'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>

      <?php if ($featuredImageSrc !== ''): ?>
      <div class="field admin-form-span-2">
        <label><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_FEATURED_IMAGE_PREVIEW_LABEL', 'Aperçu image'), ENT_QUOTES, 'UTF-8'); ?></label>
        <p>
          <img
            src="<?php echo htmlspecialchars($featuredImageSrc, ENT_QUOTES, 'UTF-8'); ?>"
            alt="<?php echo htmlspecialchars($featuredImageAlt !== '' ? $featuredImageAlt : $translate('TXT_ADMIN_ARTICLE_FEATURED_IMAGE_PREVIEW_ALT', 'Aperçu image article'), ENT_QUOTES, 'UTF-8'); ?>"
            width="<?php echo ((int) ($formData['featured_image_width'] ?? 0) > 0) ? (int) $formData['featured_image_width'] : 320; ?>"
            height="<?php echo ((int) ($formData['featured_image_height'] ?? 0) > 0) ? (int) $formData['featured_image_height'] : 180; ?>"
            loading="lazy"
            decoding="async"
            fetchpriority="low"
            style="max-width: 24rem; width: 100%; height: auto; border-radius: 0.75rem;"
          />
        </p>
      </div>
      <?php endif; ?>

      <div class="field admin-form-span-2">
        <label for="article-excerpt">Extrait</label>
        <textarea id="article-excerpt" name="article[excerpt]" rows="4"><?php echo htmlspecialchars((string) ($formData['excerpt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>

      <div class="field admin-form-span-2">
        <label for="article-content">Contenu HTML</label>
        <div class="actions-inline">
          <button
            type="button"
            class="button-link button-link-muted"
            data-content-media-open="article-media-insert-dialog"
            data-content-media-target="article-content"
          >
            Inserer un media (image / video)
          </button>
        </div>
        <textarea id="article-content" class="textarea-large" name="article[content]" rows="16" required><?php echo htmlspecialchars((string) ($formData['content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>
    </div>

    <div class="actions-inline actions-inline-end">
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($articlesIndexUrl ?? $adminArticlesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Annuler</a>
      <button type="submit">Sauvegarder</button>
    </div>
  </form>

  <datalist id="article-categories-list">
    <?php foreach (($availableCategories ?? []) as $category): ?>
    <option value="<?php echo htmlspecialchars((string) $category, ENT_QUOTES, 'UTF-8'); ?>"></option>
    <?php endforeach; ?>
  </datalist>

  <dialog
    class="region-modal content-media-dialog"
    id="article-media-insert-dialog"
    data-content-media-dialog
    data-content-media-policy="<?php echo htmlspecialchars($contentMediaPolicyJson, ENT_QUOTES, 'UTF-8'); ?>"
  >
    <div class="region-modal__surface">
      <div class="region-modal__header">
        <div>
          <p class="region-modal__eyebrow">Bibliotheque medias</p>
          <h3>Inserer un media dans le contenu</h3>
          <p>Selectionne une image ou une video, puis insertion au curseur dans le champ actif.</p>
        </div>
        <button type="button" class="button-link button-link-muted" data-content-media-close>Fermer</button>
      </div>

      <div class="region-modal__body">
        <div class="admin-form-grid admin-form-grid-2 content-media-toolbar">
          <div class="field content-media-dialog__search">
            <label for="article-media-insert-search">Recherche</label>
            <input id="article-media-insert-search" type="text" placeholder="nom, chemin, format..." data-content-media-search />
          </div>
          <div class="field">
            <label for="article-media-insert-folder">Dossier</label>
            <select id="article-media-insert-folder" data-content-media-folder>
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
              <label for="article-media-insert-preset">Preset</label>
              <select id="article-media-insert-preset" data-content-media-preset>
                <option value="figure-default">Figure standard</option>
                <option value="figure-wide">Figure pleine largeur</option>
                <option value="figure-left">Figure flottante gauche</option>
                <option value="figure-right">Figure flottante droite</option>
                <option value="raw">Balise simple (sans figure)</option>
              </select>
            </div>
            <div class="field">
              <label for="article-media-insert-classes">Classes CSS supplementaires</label>
              <input id="article-media-insert-classes" type="text" placeholder="ex: rounded shadow-lg" data-content-media-extra-classes />
            </div>
            <div class="field">
              <label for="article-media-insert-alt">Alt image par defaut</label>
              <input id="article-media-insert-alt" type="text" placeholder="description courte" data-content-media-alt />
            </div>
          </div>

          <div class="admin-form-grid admin-form-grid-3">
            <div class="field">
              <label class="checkbox-field" for="article-media-insert-lazy">
                <input id="article-media-insert-lazy" type="checkbox" data-content-media-lazy checked />
                Charger en lazy (images)
              </label>
            </div>
            <div class="field">
              <label class="checkbox-field" for="article-media-insert-dimensions">
                <input id="article-media-insert-dimensions" type="checkbox" data-content-media-include-dimensions checked />
                Injecter width/height (si disponibles)
              </label>
            </div>
            <div class="field">
              <label class="checkbox-field" for="article-media-insert-governance-strict">
                <input id="article-media-insert-governance-strict" type="checkbox" data-content-media-governance-strict checked />
                Bloquer les assets hors charte
              </label>
            </div>
          </div>

          <div class="admin-form-grid admin-form-grid-3">
            <div class="field">
              <label class="checkbox-field" for="article-media-insert-video-controls">
                <input id="article-media-insert-video-controls" type="checkbox" data-content-media-video-controls checked />
                Video: controls
              </label>
            </div>
            <div class="field">
              <label class="checkbox-field" for="article-media-insert-video-muted">
                <input id="article-media-insert-video-muted" type="checkbox" data-content-media-video-muted />
                Video: muted
              </label>
            </div>
            <div class="field">
              <label class="checkbox-field" for="article-media-insert-video-autoplay">
                <input id="article-media-insert-video-autoplay" type="checkbox" data-content-media-video-autoplay />
                Video: autoplay
              </label>
            </div>
            <div class="field">
              <label class="checkbox-field" for="article-media-insert-video-loop">
                <input id="article-media-insert-video-loop" type="checkbox" data-content-media-video-loop />
                Video: loop
              </label>
            </div>
            <div class="field admin-form-span-2">
              <label for="article-media-insert-video-poster">Video poster (optionnel)</label>
              <input id="article-media-insert-video-poster" type="text" placeholder="/uploads/editorial/library/.../poster.webp" data-content-media-video-poster />
            </div>
            <div class="field">
              <label class="checkbox-field" for="article-media-insert-filter-governance">
                <input id="article-media-insert-filter-governance" type="checkbox" data-content-media-filter-governance />
                Afficher seulement les assets conformes
              </label>
            </div>
          </div>

          <p class="notice-muted">
            Gouvernance ARTICLE:
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

  <?php if (($isNewArticle ?? false) === false): ?>
  <hr />
  <h3>Suppression</h3>
  <p class="notice-muted">
    Supprime l’article courant et efface aussi toutes les discussions rattachées à ce slug/langue.
    Les éventuels articles enfants sont conservés, mais détachés de ce parent.
  </p>
  <form method="post" action="<?php echo htmlspecialchars((string) ($currentArticleUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>" onsubmit="return confirm('Supprimer définitivement cet article et ses discussions rattachées ?');">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="article_action" value="delete" />
    <p class="checkbox-field">
      <label for="confirm-article-delete">
        <input id="confirm-article-delete" type="checkbox" name="confirm_delete" value="1" required />
        Je confirme la suppression définitive.
      </label>
    </p>
    <div class="actions-inline actions-inline-end">
      <button class="button-danger" type="submit">Supprimer l’article</button>
    </div>
  </form>
  <?php endif; ?>
</section>

<?php if (($childArticles ?? []) !== []): ?>
<section class="card">
  <h2>Articles enfants deja rattaches</h2>
  <p class="notice-muted">Leur affichage front suit d’abord l’ordre manuel s’il est renseigne, sinon la date de creation.</p>
  <div class="table-shell">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Titre</th>
          <th>Statut</th>
          <th>Date</th>
          <th>Creation</th>
          <th>Ordre manuel</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($childArticles ?? []) as $childArticle): ?>
        <tr>
          <td>
            <strong><?php echo htmlspecialchars((string) ($childArticle['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong><br />
            <code><?php echo htmlspecialchars((string) ($childArticle['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
          </td>
          <td><?php echo htmlspecialchars((string) ($childArticle['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) ($childArticle['date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) ($childArticle['createdAt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) (($childArticle['childSortOrder'] ?? null) ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
          <td>
            <a class="button-link" href="<?php echo htmlspecialchars((string) ($childArticle['editPath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Ouvrir</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?php $cspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
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

    const dialog = document.getElementById('article-media-insert-dialog');
    if (!(dialog instanceof HTMLDialogElement)) {
      return;
    }

    const openButtons = document.querySelectorAll('[data-content-media-open="article-media-insert-dialog"]');
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
        context: String(parsed.context || 'article'),
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

    openButtons.forEach((button) => {
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
        setInlineStatus('Media insere dans le contenu.', false);
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
