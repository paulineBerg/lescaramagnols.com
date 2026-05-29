<?php
$blocNote = is_array($viewModel['blocNote'] ?? null) ? $viewModel['blocNote'] : [];
$blocNoteView = is_string($blocNote['view'] ?? null) ? (string) $blocNote['view'] : 'dashboard';
$blocNoteBaseUrl = is_string($blocNote['baseUrl'] ?? null) ? (string) $blocNote['baseUrl'] : private_portal_url('blocnote');
$blocNoteCsrfToken = is_string($blocNote['csrfToken'] ?? null) ? (string) $blocNote['csrfToken'] : '';
$blocNoteNotes = is_array($blocNote['notes'] ?? null) ? $blocNote['notes'] : [];
$blocNoteCategories = is_array($blocNote['categories'] ?? null) ? $blocNote['categories'] : [];
$blocNoteDashboard = is_array($blocNote['dashboard'] ?? null) ? $blocNote['dashboard'] : [];
$blocNoteFormValues = is_array($blocNote['formValues'] ?? null) ? $blocNote['formValues'] : [];
$blocNoteColors = is_array($blocNote['categoryColors'] ?? null) ? $blocNote['categoryColors'] : [];
if ($blocNoteColors === []) {
    $blocNoteColors = ['#ffffff', '#fff1d6', '#ffe0e0', '#e1f7d5', '#d6ecff', '#eadbff', '#ffdff3'];
}
$blocNoteDefaultColor = is_string($blocNote['categoryDefaultColor'] ?? null) ? (string) $blocNote['categoryDefaultColor'] : '#ffffff';
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$blocNoteColorClass = static function (mixed $value, string $default = '#ffffff'): string {
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
$blocNoteUrl = static function (string $view, array $extra = []) use ($blocNoteBaseUrl): string {
    $params = array_merge(['view' => $view], $extra);

    return $blocNoteBaseUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};
$blocNoteDate = static function (mixed $value): string {
    $timestamp = is_string($value) ? strtotime($value) : false;

    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '';
};
$blocNoteSelectedCategoryId = is_numeric($blocNoteFormValues['category_id'] ?? null) ? (int) $blocNoteFormValues['category_id'] : 0;
$blocNoteModalNotes = [];
foreach ($blocNoteNotes as $note) {
    if (!is_array($note) || !is_numeric($note['id'] ?? null)) {
        continue;
    }

    $blocNoteModalNotes[(string) (int) $note['id']] = [
        'title' => (string) ($note['displayTitle'] ?? $note['title'] ?? 'Sans titre'),
        'category' => (string) ($note['categoryName'] ?? 'Sans catégorie'),
        'updated' => $blocNoteDate($note['updatedAt'] ?? ''),
        'content' => (string) ($note['contentText'] ?? ''),
    ];
}
$blocNoteNotesJson = json_encode($blocNoteModalNotes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
if (!is_string($blocNoteNotesJson)) {
    $blocNoteNotesJson = '{}';
}
$blocNoteStatus = is_string($blocNoteDashboard['status'] ?? null) ? (string) $blocNoteDashboard['status'] : 'empty';
$blocNoteStatusLabel = match ($blocNoteStatus) {
    'healthy' => 'Bloc-note prêt',
    'partial' => 'Organisation à compléter',
    'warning' => 'Vérification conseillée',
    'idle' => 'Peu d’activité récente',
    default => 'Aucune note enregistrée',
};
?>
<section class="blocnote-module" data-blocnote-root>

  <div class="blocnote-hero">
    <span class="tag">Notes privées</span>
    <h2>Bloc-note</h2>
    <p class="muted">Un espace personnel pour noter, classer et retrouver rapidement les informations utiles.</p>
  </div>

  <nav class="blocnote-tabs" aria-label="Navigation Bloc-note">
    <a class="<?php echo $blocNoteView === 'dashboard' ? 'active' : ''; ?>" href="<?php echo $h($blocNoteUrl('dashboard')); ?>">Tableau de bord</a>
    <a class="<?php echo $blocNoteView === 'notes' ? 'active' : ''; ?>" href="<?php echo $h($blocNoteUrl('notes')); ?>">Mes notes</a>
    <a class="<?php echo $blocNoteView === 'form' ? 'active' : ''; ?>" href="<?php echo $h($blocNoteUrl('form')); ?>">Nouvelle note</a>
    <a class="<?php echo $blocNoteView === 'categories' ? 'active' : ''; ?>" href="<?php echo $h($blocNoteUrl('categories')); ?>">Catégories</a>
    <a class="<?php echo $blocNoteView === 'help' ? 'active' : ''; ?>" href="<?php echo $h($blocNoteUrl('help')); ?>">Aide</a>
  </nav>

  <?php if ($blocNoteView === 'dashboard'): ?>
    <section class="blocnote-card">
      <span class="blocnote-status"><?php echo $h($blocNoteStatusLabel); ?></span>
      <h3>Tableau de bord Bloc-note</h3>
      <p class="muted">Suivi rapide des notes, de l’activité récente et de la qualité du classement.</p>
      <div class="blocnote-actions">
        <a class="blocnote-link-button" href="<?php echo $h($blocNoteUrl('form')); ?>">Créer une note</a>
        <a class="blocnote-link-button" href="<?php echo $h($blocNoteUrl('notes')); ?>">Ouvrir mes notes</a>
        <a class="blocnote-link-button" href="<?php echo $h($blocNoteUrl('categories')); ?>">Gérer les catégories</a>
      </div>
    </section>

    <div class="blocnote-grid private-stack-top">
      <section class="blocnote-card blocnote-kpi"><span>Notes totales</span><strong><?php echo (int) ($blocNoteDashboard['totalNotes'] ?? 0); ?></strong></section>
      <section class="blocnote-card blocnote-kpi"><span>Modifiées sur 7 jours</span><strong><?php echo (int) ($blocNoteDashboard['recentNotesCount'] ?? 0); ?></strong></section>
      <section class="blocnote-card blocnote-kpi"><span>Catégories personnalisées</span><strong><?php echo (int) ($blocNoteDashboard['customCategoriesTotal'] ?? 0); ?></strong></section>
      <section class="blocnote-card blocnote-kpi"><span>Notes sans titre</span><strong><?php echo (int) ($blocNoteDashboard['untitledNotesCount'] ?? 0); ?></strong></section>
    </div>

    <div class="blocnote-grid blocnote-grid-wide private-stack-top">
      <section class="blocnote-card">
        <h3>Activité récente</h3>
        <?php $recentNotes = is_array($blocNoteDashboard['recentNotes'] ?? null) ? $blocNoteDashboard['recentNotes'] : []; ?>
        <?php if ($recentNotes === []): ?>
          <p class="muted">Aucune note enregistrée pour le moment.</p>
        <?php else: ?>
          <div class="blocnote-note-list">
            <?php foreach ($recentNotes as $note): ?>
              <?php if (!is_array($note)): continue; endif; ?>
              <article class="blocnote-note-card <?php echo $h($blocNoteColorClass($note['categoryColor'] ?? $note['color'] ?? '#ffffff', $blocNoteDefaultColor)); ?>">
                <h3><?php echo $h($note['displayTitle'] ?? $note['title'] ?? 'Sans titre'); ?></h3>
                <p class="blocnote-meta"><?php echo $h($note['categoryName'] ?? 'Sans catégorie'); ?> · <?php echo $h($blocNoteDate($note['updatedAt'] ?? '')); ?></p>
                <p class="blocnote-note-excerpt"><?php echo $h($note['excerpt'] ?? ''); ?></p>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="blocnote-card">
        <h3>Répartition par catégorie</h3>
        <?php $categoryUsage = is_array($blocNoteDashboard['categoryUsage'] ?? null) ? $blocNoteDashboard['categoryUsage'] : []; ?>
        <?php if ($categoryUsage === []): ?>
          <p class="muted">Aucune catégorie n’a encore de note associée.</p>
        <?php else: ?>
          <div class="blocnote-category-list">
            <?php foreach ($categoryUsage as $row): ?>
              <?php if (!is_array($row)): continue; endif; ?>
              <div class="blocnote-category-row <?php echo $h($blocNoteColorClass($row['color'] ?? '#ffffff', $blocNoteDefaultColor)); ?>">
                <h3><span class="blocnote-color-dot <?php echo $h($blocNoteColorClass($row['color'] ?? '#ffffff', $blocNoteDefaultColor)); ?>"></span><?php echo $h($row['name'] ?? 'Catégorie'); ?></h3>
                <p class="blocnote-meta"><?php echo (int) ($row['count'] ?? 0); ?> note(s)</p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  <?php elseif ($blocNoteView === 'notes'): ?>
    <section class="blocnote-card">
      <h3>Mes notes</h3>
      <?php if ($blocNoteNotes === []): ?>
        <p class="muted">Aucune note enregistrée pour le moment.</p>
        <p><a class="blocnote-link-button" href="<?php echo $h($blocNoteUrl('form')); ?>">Créer une note</a></p>
      <?php else: ?>
        <div class="blocnote-filter-row" data-blocnote-filters>
          <div class="blocnote-filter-field">
            <label for="blocnote-filter-text">Titre</label>
            <input type="text" id="blocnote-filter-text" placeholder="Filtrer par titre" />
          </div>
          <div class="blocnote-filter-field">
            <label for="blocnote-filter-category">Catégorie</label>
            <select id="blocnote-filter-category">
              <option value="all">Toutes</option>
              <?php foreach ($blocNoteCategories as $category): ?>
                <?php if (!is_array($category)): continue; endif; ?>
                <option value="<?php echo (int) ($category['id'] ?? 0); ?>"><?php echo $h($category['name'] ?? 'Catégorie'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="blocnote-filter-field">
            <label for="blocnote-filter-sort">Tri</label>
            <select id="blocnote-filter-sort">
              <option value="default">Dernière modification</option>
              <option value="title">Titre A-Z</option>
              <option value="created">Création récente</option>
            </select>
          </div>
          <label class="private-checkbox-inline">
            <input type="checkbox" id="blocnote-filter-content" />
            <span>Chercher aussi dans le contenu</span>
          </label>
          <button type="button" class="blocnote-button-secondary" data-blocnote-filter-reset>Réinitialiser</button>
        </div>

        <div class="blocnote-note-list" data-blocnote-note-list>
          <?php foreach ($blocNoteNotes as $note): ?>
            <?php if (!is_array($note)): continue; endif; ?>
            <?php $noteId = is_numeric($note['id'] ?? null) ? (int) $note['id'] : 0; ?>
            <article class="blocnote-note-card <?php echo $h($blocNoteColorClass($note['categoryColor'] ?? $note['color'] ?? '#ffffff', $blocNoteDefaultColor)); ?>"
                     data-note-id="<?php echo $noteId; ?>"
                     data-note-title="<?php echo $h(strtolower((string) ($note['displayTitle'] ?? $note['title'] ?? ''))); ?>"
                     data-note-content="<?php echo $h(strtolower((string) ($note['contentText'] ?? ''))); ?>"
                     data-note-category="<?php echo (int) ($note['categoryId'] ?? 0); ?>"
                     data-note-created="<?php echo $h($note['createdAt'] ?? ''); ?>"
                     data-note-updated="<?php echo $h($note['updatedAt'] ?? ''); ?>">
              <h3><?php echo $h($note['displayTitle'] ?? $note['title'] ?? 'Sans titre'); ?></h3>
              <p class="blocnote-meta">
                <span class="blocnote-color-dot <?php echo $h($blocNoteColorClass($note['categoryColor'] ?? '#ffffff', $blocNoteDefaultColor)); ?>"></span>
                <?php echo $h($note['categoryName'] ?? 'Sans catégorie'); ?> · <?php echo $h($blocNoteDate($note['updatedAt'] ?? '')); ?>
              </p>
              <p class="blocnote-note-excerpt"><?php echo $h($note['excerpt'] ?? ''); ?></p>
              <div class="blocnote-note-actions">
                <button type="button" data-blocnote-open-note="<?php echo $noteId; ?>">Lire</button>
                <a class="blocnote-link-button" href="<?php echo $h($blocNoteUrl('form', ['note_id' => (string) $noteId])); ?>">Modifier</a>
                <form method="post" action="<?php echo $h($blocNoteBaseUrl); ?>">
                  <input type="hidden" name="csrf_token" value="<?php echo $h($blocNoteCsrfToken); ?>" />
                  <input type="hidden" name="action" value="delete_note" />
                  <input type="hidden" name="note_id" value="<?php echo $noteId; ?>" />
                  <button type="submit" class="blocnote-button-danger">Supprimer</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php elseif ($blocNoteView === 'form'): ?>
    <section class="blocnote-card">
      <h3><?php echo ((int) ($blocNoteFormValues['note_id'] ?? 0) > 0) ? 'Modifier la note' : 'Nouvelle note'; ?></h3>
      <form class="blocnote-form" method="post" action="<?php echo $h($blocNoteBaseUrl); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $h($blocNoteCsrfToken); ?>" />
        <input type="hidden" name="action" value="save_note" />
        <input type="hidden" name="note_id" value="<?php echo (int) ($blocNoteFormValues['note_id'] ?? 0); ?>" />
        <div>
          <label for="blocnote-title">Titre</label>
          <input type="text" id="blocnote-title" name="title" maxlength="191" value="<?php echo $h($blocNoteFormValues['title'] ?? ''); ?>" />
        </div>
        <div>
          <label for="blocnote-category">Catégorie</label>
          <select id="blocnote-category" name="category_id">
            <?php foreach ($blocNoteCategories as $category): ?>
              <?php if (!is_array($category)): continue; endif; ?>
              <?php $categoryId = is_numeric($category['id'] ?? null) ? (int) $category['id'] : 0; ?>
              <option value="<?php echo $categoryId; ?>" <?php echo $categoryId === $blocNoteSelectedCategoryId ? 'selected' : ''; ?>><?php echo $h($category['name'] ?? 'Catégorie'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="blocnote-content">Contenu</label>
          <textarea id="blocnote-content" name="content"><?php echo $h($blocNoteFormValues['content'] ?? ''); ?></textarea>
        </div>
        <div class="blocnote-actions">
          <button type="submit">Enregistrer</button>
          <a class="blocnote-link-button" href="<?php echo $h($blocNoteUrl('notes')); ?>">Retour aux notes</a>
        </div>
      </form>
    </section>
  <?php elseif ($blocNoteView === 'categories'): ?>
    <div class="blocnote-grid blocnote-grid-wide">
      <section class="blocnote-card">
        <h3>Catégorie</h3>
        <form class="blocnote-form" method="post" action="<?php echo $h($blocNoteBaseUrl); ?>" id="blocnote-category-form">
          <input type="hidden" name="csrf_token" value="<?php echo $h($blocNoteCsrfToken); ?>" />
          <input type="hidden" name="action" value="save_category" />
          <input type="hidden" name="category_id" value="0" data-blocnote-category-id />
          <div>
            <label for="blocnote-category-name">Nom</label>
            <input type="text" id="blocnote-category-name" name="category_name" maxlength="80" required data-blocnote-category-name />
          </div>
          <div>
            <label>Couleur</label>
            <div class="blocnote-color-choices">
              <?php foreach ($blocNoteColors as $color): ?>
                <?php $color = is_string($color) ? $color : $blocNoteDefaultColor; ?>
                <label class="blocnote-color-choice">
                  <input type="radio" name="category_color" value="<?php echo $h($color); ?>" <?php echo $color === $blocNoteDefaultColor ? 'checked' : ''; ?> />
                  <span class="blocnote-color-swatch <?php echo $h($blocNoteColorClass($color, $blocNoteDefaultColor)); ?>"></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="blocnote-actions">
            <button type="submit">Enregistrer la catégorie</button>
            <button type="button" class="blocnote-button-secondary" data-blocnote-category-reset>Nouvelle catégorie</button>
          </div>
        </form>
      </section>

      <section class="blocnote-card">
        <h3>Catégories existantes</h3>
        <div class="blocnote-category-list">
          <?php foreach ($blocNoteCategories as $category): ?>
            <?php if (!is_array($category)): continue; endif; ?>
            <?php $categoryId = is_numeric($category['id'] ?? null) ? (int) $category['id'] : 0; ?>
            <?php $categoryIsDefault = !empty($category['isDefault']); ?>
            <article class="blocnote-category-row <?php echo $h($blocNoteColorClass($category['color'] ?? '#ffffff', $blocNoteDefaultColor)); ?>">
              <h3><span class="blocnote-color-dot <?php echo $h($blocNoteColorClass($category['color'] ?? '#ffffff', $blocNoteDefaultColor)); ?>"></span><?php echo $h($category['name'] ?? 'Catégorie'); ?></h3>
              <p class="blocnote-meta"><?php echo (int) ($category['notesCount'] ?? 0); ?> note(s)<?php echo $categoryIsDefault ? ' · catégorie par défaut' : ''; ?></p>
              <div class="blocnote-category-actions">
                <button type="button"
                        class="blocnote-button-secondary"
                        data-blocnote-category-edit
                        data-category-id="<?php echo $categoryId; ?>"
                        data-category-name="<?php echo $h($category['name'] ?? ''); ?>"
                        data-category-color="<?php echo $h($category['color'] ?? $blocNoteDefaultColor); ?>">Modifier</button>
                <?php if (!$categoryIsDefault): ?>
                  <form method="post" action="<?php echo $h($blocNoteBaseUrl); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $h($blocNoteCsrfToken); ?>" />
                    <input type="hidden" name="action" value="set_default_category" />
                    <input type="hidden" name="category_id" value="<?php echo $categoryId; ?>" />
                    <button type="submit" class="blocnote-button-secondary">Définir par défaut</button>
                  </form>
                  <form method="post" action="<?php echo $h($blocNoteBaseUrl); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $h($blocNoteCsrfToken); ?>" />
                    <input type="hidden" name="action" value="delete_category" />
                    <input type="hidden" name="category_id" value="<?php echo $categoryId; ?>" />
                    <button type="submit" class="blocnote-button-danger">Supprimer</button>
                  </form>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    </div>
  <?php else: ?>
    <div class="blocnote-grid blocnote-grid-wide">
      <section class="blocnote-card">
        <h3>Tableau de bord</h3>
        <p class="muted">Affiche l’état général, les dernières notes modifiées et les catégories les plus utilisées.</p>
      </section>
      <section class="blocnote-card">
        <h3>Mes notes</h3>
        <p class="muted">Liste toutes les notes avec filtres par titre, catégorie, contenu et tri.</p>
      </section>
      <section class="blocnote-card">
        <h3>Nouvelle note</h3>
        <p class="muted">Crée ou modifie une note. Un titre ou un contenu suffit pour enregistrer.</p>
      </section>
      <section class="blocnote-card">
        <h3>Catégories</h3>
        <p class="muted">Crée des catégories couleur, choisit la catégorie par défaut et réattribue automatiquement les notes si une catégorie est supprimée.</p>
      </section>
    </div>
  <?php endif; ?>

  <div class="blocnote-modal" hidden data-blocnote-modal aria-hidden="true">
    <div class="blocnote-modal-panel" role="dialog" aria-modal="true" aria-labelledby="blocnote-modal-title">
      <header class="blocnote-modal-header">
        <div>
          <h3 id="blocnote-modal-title">Note</h3>
          <p class="blocnote-meta" data-blocnote-modal-meta></p>
        </div>
        <button type="button" class="blocnote-button-secondary" data-blocnote-modal-close>Fermer</button>
      </header>
      <div class="blocnote-modal-content" data-blocnote-modal-content></div>
    </div>
  </div>

  <?php $blocNoteCspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
  <script<?php echo $blocNoteCspNonce !== '' ? ' nonce="' . $h($blocNoteCspNonce) . '"' : ''; ?>>
    (() => {
      const root = document.querySelector('[data-blocnote-root]');
      if (!root) {
        return;
      }

      const notesById = <?php echo $blocNoteNotesJson; ?>;
      const modal = root.querySelector('[data-blocnote-modal]');
      const modalTitle = root.querySelector('#blocnote-modal-title');
      const modalMeta = root.querySelector('[data-blocnote-modal-meta]');
      const modalContent = root.querySelector('[data-blocnote-modal-content]');
      const closeModal = () => {
        if (!modal) {
          return;
        }
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
      };

      root.querySelectorAll('[data-blocnote-open-note]').forEach((button) => {
        button.addEventListener('click', () => {
          const note = notesById[button.getAttribute('data-blocnote-open-note') || ''];
          if (!note || !modal || !modalTitle || !modalMeta || !modalContent) {
            return;
          }

          modalTitle.textContent = note.title || 'Sans titre';
          modalMeta.textContent = `${note.category || 'Sans catégorie'} · ${note.updated || ''}`;
          modalContent.textContent = note.content || 'Aucun contenu.';
          modal.hidden = false;
          modal.setAttribute('aria-hidden', 'false');
        });
      });

      root.querySelectorAll('[data-blocnote-modal-close]').forEach((button) => {
        button.addEventListener('click', closeModal);
      });
      modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
          closeModal();
        }
      });

      const list = root.querySelector('[data-blocnote-note-list]');
      const textFilter = root.querySelector('#blocnote-filter-text');
      const categoryFilter = root.querySelector('#blocnote-filter-category');
      const sortFilter = root.querySelector('#blocnote-filter-sort');
      const contentFilter = root.querySelector('#blocnote-filter-content');
      const resetFilter = root.querySelector('[data-blocnote-filter-reset]');
      const storageKey = 'private_blocnote_filters';

      const readFilters = () => ({
        text: textFilter instanceof HTMLInputElement ? textFilter.value.trim().toLowerCase() : '',
        category: categoryFilter instanceof HTMLSelectElement ? categoryFilter.value : 'all',
        sort: sortFilter instanceof HTMLSelectElement ? sortFilter.value : 'default',
        content: contentFilter instanceof HTMLInputElement ? contentFilter.checked : false,
      });

      const saveFilters = () => {
        try {
          localStorage.setItem(storageKey, JSON.stringify(readFilters()));
        } catch (error) {
          localStorage.removeItem(storageKey);
        }
      };

      const applyFilters = () => {
        if (!(list instanceof HTMLElement)) {
          return;
        }

        const filters = readFilters();
        const cards = Array.from(list.querySelectorAll('[data-note-id]'));
        cards.forEach((card) => {
          const title = card.getAttribute('data-note-title') || '';
          const content = card.getAttribute('data-note-content') || '';
          const category = card.getAttribute('data-note-category') || '0';
          const matchesText = filters.text === '' || title.includes(filters.text) || (filters.content && content.includes(filters.text));
          const matchesCategory = filters.category === 'all' || filters.category === category;
          card.hidden = !(matchesText && matchesCategory);
        });

        const sorted = cards.sort((left, right) => {
          if (filters.sort === 'title') {
            return (left.getAttribute('data-note-title') || '').localeCompare(right.getAttribute('data-note-title') || '', 'fr');
          }
          if (filters.sort === 'created') {
            return (right.getAttribute('data-note-created') || '').localeCompare(left.getAttribute('data-note-created') || '');
          }

          return (right.getAttribute('data-note-updated') || '').localeCompare(left.getAttribute('data-note-updated') || '');
        });
        sorted.forEach((card) => list.appendChild(card));
      };

      if (textFilter || categoryFilter || sortFilter || contentFilter) {
        try {
          const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
          if (textFilter instanceof HTMLInputElement && typeof saved.text === 'string') {
            textFilter.value = saved.text;
          }
          if (categoryFilter instanceof HTMLSelectElement && typeof saved.category === 'string') {
            categoryFilter.value = saved.category;
          }
          if (sortFilter instanceof HTMLSelectElement && typeof saved.sort === 'string') {
            sortFilter.value = saved.sort;
          }
          if (contentFilter instanceof HTMLInputElement) {
            contentFilter.checked = saved.content === true;
          }
        } catch (error) {
          return;
        }

        [textFilter, categoryFilter, sortFilter, contentFilter].forEach((field) => {
          field?.addEventListener('input', () => {
            saveFilters();
            applyFilters();
          });
          field?.addEventListener('change', () => {
            saveFilters();
            applyFilters();
          });
        });
        resetFilter?.addEventListener('click', () => {
          if (textFilter instanceof HTMLInputElement) {
            textFilter.value = '';
          }
          if (categoryFilter instanceof HTMLSelectElement) {
            categoryFilter.value = 'all';
          }
          if (sortFilter instanceof HTMLSelectElement) {
            sortFilter.value = 'default';
          }
          if (contentFilter instanceof HTMLInputElement) {
            contentFilter.checked = false;
          }
          saveFilters();
          applyFilters();
        });
        applyFilters();
      }

      const categoryId = root.querySelector('[data-blocnote-category-id]');
      const categoryName = root.querySelector('[data-blocnote-category-name]');
      const categoryReset = root.querySelector('[data-blocnote-category-reset]');
      const selectCategoryColor = (color) => {
        root.querySelectorAll('input[name="category_color"]').forEach((input) => {
          if (input instanceof HTMLInputElement) {
            input.checked = input.value === color;
          }
        });
      };

      root.querySelectorAll('[data-blocnote-category-edit]').forEach((button) => {
        button.addEventListener('click', () => {
          if (categoryId instanceof HTMLInputElement) {
            categoryId.value = button.getAttribute('data-category-id') || '0';
          }
          if (categoryName instanceof HTMLInputElement) {
            categoryName.value = button.getAttribute('data-category-name') || '';
            categoryName.focus();
          }
          selectCategoryColor(button.getAttribute('data-category-color') || '#ffffff');
          document.getElementById('blocnote-category-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });

      categoryReset?.addEventListener('click', () => {
        if (categoryId instanceof HTMLInputElement) {
          categoryId.value = '0';
        }
        if (categoryName instanceof HTMLInputElement) {
          categoryName.value = '';
          categoryName.focus();
        }
        selectCategoryColor('#ffffff');
      });
    })();
  </script>
</section>
