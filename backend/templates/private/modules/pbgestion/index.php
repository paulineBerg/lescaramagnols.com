<?php
$pbgestion = is_array($viewModel['pbgestion'] ?? null) ? $viewModel['pbgestion'] : [];
$view = is_string($pbgestion['view'] ?? null) ? (string) $pbgestion['view'] : 'overview';
$csrfToken = is_string($pbgestion['csrfToken'] ?? null) ? (string) $pbgestion['csrfToken'] : '';
$urls = is_array($pbgestion['urls'] ?? null) ? $pbgestion['urls'] : [];
$app = is_array($pbgestion['app'] ?? null) ? $pbgestion['app'] : [];
$appKind = is_string($app['kind'] ?? null) ? (string) $app['kind'] : 'security';
$appTitle = is_string($app['title'] ?? null) ? (string) $app['title'] : 'Sécurité réseau';
$appNavLabel = is_string($app['navLabel'] ?? null) ? (string) $app['navLabel'] : 'Navigation ' . $appTitle;
$isSecurityApp = $appKind === 'security';
$isPhotoApp = $appKind === 'photo';
$dashboard = is_array($pbgestion['dashboard'] ?? null) ? $pbgestion['dashboard'] : [];
$photoGeo = is_array($pbgestion['photoGeo'] ?? null) ? $pbgestion['photoGeo'] : [];
$photoGeoCounters = is_array($photoGeo['counters'] ?? null) ? $photoGeo['counters'] : [];
$photoGeoBatches = is_array($photoGeo['batches'] ?? null) ? $photoGeo['batches'] : [];
$oneTimeEnrollment = is_array($pbgestion['oneTimeEnrollment'] ?? null) ? $pbgestion['oneTimeEnrollment'] : null;
$restrictedPhotoPreview = is_array($pbgestion['restrictedPhotoPreview'] ?? null) ? $pbgestion['restrictedPhotoPreview'] : null;
$moduleNotice = is_string($notice ?? null) ? (string) $notice : '';
$moduleErrorMessage = is_string($errorMessage ?? null) ? (string) $errorMessage : '';
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$url = static function (string $key) use ($urls): string {
    return is_string($urls[$key] ?? null) ? (string) $urls[$key] : '#';
};
$isActive = static fn (string $key): string => $view === $key ? 'active' : '';
$agents = is_array($dashboard['agents'] ?? null) ? $dashboard['agents'] : [];
$networks = is_array($dashboard['networks'] ?? null) ? $dashboard['networks'] : [];
$devices = is_array($dashboard['devices'] ?? null) ? $dashboard['devices'] : [];
$alerts = is_array($dashboard['alerts'] ?? null) ? $dashboard['alerts'] : [];
$commands = is_array($dashboard['commands'] ?? null) ? $dashboard['commands'] : [];
$backups = is_array($dashboard['backups'] ?? null) ? $dashboard['backups'] : [];
$dateLabel = static function (mixed $value): string {
    if (!is_string($value) || trim($value) === '') {
        return 'jamais';
    }
    $timestamp = strtotime($value);

    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : 'jamais';
};
$statusLabel = static function (string $status): string {
    return match (strtolower($status)) {
        'complete' => 'complète',
        'partial' => 'partielle',
        'interrupted' => 'interrompue',
        'active' => 'actif',
        'revoked' => 'révoqué',
        'pending' => 'en attente',
        'trusted' => 'approuvé',
        'limited' => 'limité',
        'public' => 'public',
        'ignored' => 'ignoré',
        default => $status !== '' ? $status : 'inconnu',
    };
};
?>
<section class="private-dashboard pbgestion-module" data-pbgestion-root>
  <nav class="private-module-nav" aria-label="<?php echo $h($appNavLabel); ?>">
    <div class="private-module-nav-row">
      <?php if ($isSecurityApp): ?>
      <a class="<?php echo $isActive('overview'); ?>" href="<?php echo $h($url('overview')); ?>">Vue d’ensemble</a>
      <a class="<?php echo $isActive('coverage'); ?>" href="<?php echo $h($url('coverage')); ?>">Couverture</a>
      <a class="<?php echo $isActive('networks'); ?>" href="<?php echo $h($url('networks')); ?>">Réseaux</a>
      <a class="<?php echo $isActive('devices'); ?>" href="<?php echo $h($url('devices')); ?>">Appareils</a>
      <a class="<?php echo $isActive('computers'); ?>" href="<?php echo $h($url('computers')); ?>">Ordinateurs</a>
      <a class="<?php echo $isActive('alerts'); ?>" href="<?php echo $h($url('alerts')); ?>">Alertes</a>
      <a class="<?php echo $isActive('scans'); ?>" href="<?php echo $h($url('scans')); ?>">Scans</a>
      <a class="<?php echo $isActive('backups'); ?>" href="<?php echo $h($url('backups')); ?>">Sauvegardes</a>
      <?php endif; ?>
      <?php if ($isPhotoApp): ?>
      <a class="<?php echo $isActive('photos'); ?>" href="<?php echo $h($url('photos')); ?>">Renommage</a>
      <a class="<?php echo $isActive('history'); ?>" href="<?php echo $h($url('history')); ?>">Historique</a>
      <a class="<?php echo $isActive('counters'); ?>" href="<?php echo $h($url('counters')); ?>">Compteurs</a>
      <?php endif; ?>
      <a class="<?php echo $isActive('agents'); ?>" href="<?php echo $h($url('agents')); ?>"><?php echo $isPhotoApp ? 'Agents' : 'Agents et installation'; ?></a>
      <?php if ($isSecurityApp): ?>
      <a class="<?php echo $isActive('settings'); ?>" href="<?php echo $h($url('settings')); ?>">Paramètres</a>
      <?php endif; ?>
      <a class="<?php echo $isActive('help'); ?>" href="<?php echo $h($url('help')); ?>">Aide</a>
    </div>
  </nav>
  <?php if ($moduleNotice !== ''): ?>
    <div class="notice notice-success" role="status"><?php echo $h($moduleNotice); ?></div>
  <?php endif; ?>
  <?php if ($moduleErrorMessage !== ''): ?>
    <div class="notice notice-error" role="alert"><?php echo $h($moduleErrorMessage); ?></div>
  <?php endif; ?>

  <?php if ($isSecurityApp && $view === 'overview'): ?>
    <section class="private-module-dashboard">
      <div class="private-list-header">
        <div>
          <span class="tag">Sécurité réseau</span>
          <h2>Vue d’ensemble</h2>
          <p class="muted">Etat utile des agents locaux, sans conserver les détails réseau bruts sur OVH.</p>
        </div>
        <a class="private-create-button" href="<?php echo $h($url('agents')); ?>">Installer un agent</a>
      </div>
      <div class="private-dashboard-summary">
        <section class="private-dashboard-panel">
          <h3>Couverture</h3>
          <p><strong><?php echo $h($statusLabel((string) ($dashboard['coverage_state'] ?? 'interrupted'))); ?></strong></p>
          <p class="muted">Dernier contact: <?php echo $h($dashboard['latest_seen_age'] ?? 'jamais'); ?></p>
        </section>
        <section class="private-dashboard-panel">
          <h3>Agents actifs</h3>
          <p><strong><?php echo (int) ($dashboard['agents_active'] ?? 0); ?></strong></p>
          <p class="muted"><?php echo (int) ($dashboard['agents_total'] ?? 0); ?> agent(s) connus</p>
        </section>
        <section class="private-dashboard-panel">
          <h3>Alertes ouvertes</h3>
          <p><strong><?php echo (int) ($dashboard['alerts_open'] ?? 0); ?></strong></p>
          <p class="muted">Une absence d’alerte ne prouve pas une absence de risque.</p>
        </section>
      </div>
    </section>
  <?php elseif ($isSecurityApp && $view === 'coverage'): ?>
    <section class="card private-card-wide">
      <h2>Couverture</h2>
      <p class="muted">La couverture dépend de l’âge des contacts agents. Au-delà de deux intervalles attendus, elle devient partielle puis interrompue.</p>
      <div class="private-dashboard-summary">
        <section class="private-dashboard-panel"><h3>Etat</h3><p><strong><?php echo $h($statusLabel((string) ($dashboard['coverage_state'] ?? 'interrupted'))); ?></strong></p></section>
        <section class="private-dashboard-panel"><h3>Dernier contact</h3><p><strong><?php echo $h($dateLabel($dashboard['latest_seen_at'] ?? null)); ?></strong></p></section>
        <section class="private-dashboard-panel"><h3>Commandes en cours</h3><p><strong><?php echo (int) ($dashboard['commands_pending'] ?? 0); ?></strong></p></section>
      </div>
    </section>
  <?php elseif ($isSecurityApp && $view === 'networks'): ?>
    <section class="card private-card-wide">
      <h2>Réseaux</h2>
      <p class="muted">Les réseaux non approuvés n’autorisent pas de scan actif. Les identifiants restent pseudonymes.</p>
      <?php if ($networks === []): ?>
        <p class="muted">Aucun réseau reçu pour le moment.</p>
      <?php else: ?>
        <table><thead><tr><th>Réseau</th><th>Etat</th><th>Dernière vue</th></tr></thead><tbody>
          <?php foreach ($networks as $network): if (!is_array($network)) { continue; } ?>
            <tr>
              <td><?php echo $h($network['display_label'] ?? $network['network_token'] ?? 'Réseau'); ?></td>
              <td><?php echo $h($statusLabel((string) ($network['trust_state'] ?? 'pending'))); ?></td>
              <td><?php echo $h($dateLabel($network['last_seen_at'] ?? null)); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    </section>
  <?php elseif ($isSecurityApp && ($view === 'devices' || $view === 'computers')): ?>
    <section class="card private-card-wide">
      <h2><?php echo $view === 'computers' ? 'Ordinateurs' : 'Appareils'; ?></h2>
      <p class="muted">Liste synthétique. Les détails complets sont demandés explicitement et expirent rapidement.</p>
      <?php if ($devices === []): ?>
        <p class="muted">Aucun appareil reçu pour le moment.</p>
      <?php else: ?>
        <table><thead><tr><th>Jeton</th><th>Type</th><th>Risque</th><th>Dernière vue</th></tr></thead><tbody>
          <?php foreach ($devices as $device): if (!is_array($device)) { continue; } ?>
            <?php if ($view === 'computers' && ($device['device_kind'] ?? '') !== 'computer') { continue; } ?>
            <tr>
              <td><?php echo $h($device['device_token'] ?? ''); ?></td>
              <td><?php echo $h($device['device_kind'] ?? 'unknown'); ?></td>
              <td><?php echo $h($device['risk_level'] ?? 'unknown'); ?></td>
              <td><?php echo $h($dateLabel($device['last_seen_at'] ?? null)); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    </section>
  <?php elseif ($isSecurityApp && $view === 'alerts'): ?>
    <section class="card private-card-wide">
      <h2>Alertes</h2>
      <p class="muted">Une seule alerte ouverte est conservée par clé logique. Les alertes résolues restent soumises à la rétention.</p>
      <?php if ($alerts === []): ?>
        <p class="muted">Aucune alerte ouverte ou récente.</p>
      <?php else: ?>
        <table><thead><tr><th>Sévérité</th><th>Titre</th><th>Résumé</th><th>Etat</th><th>Dernière vue</th></tr></thead><tbody>
          <?php foreach ($alerts as $alert): if (!is_array($alert)) { continue; } ?>
            <tr>
              <td><?php echo $h($alert['severity'] ?? 'info'); ?></td>
              <td><?php echo $h($alert['title'] ?? 'Alerte'); ?></td>
              <td><?php echo $h($alert['summary'] ?? ''); ?></td>
              <td><?php echo $h($statusLabel((string) ($alert['status'] ?? 'open'))); ?></td>
              <td><?php echo $h($dateLabel($alert['last_seen_at'] ?? null)); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    </section>
  <?php elseif ($isSecurityApp && $view === 'scans'): ?>
    <section class="card private-card-wide">
      <h2>Scans et historique</h2>
      <p class="muted">Les synthèses de scan sont datées. Les listes détaillées de ports ne sont pas stockées durablement sur OVH.</p>
      <p class="muted">Scans connus: <?php echo (int) ($dashboard['scans_total'] ?? 0); ?>.</p>
    </section>
  <?php elseif ($isSecurityApp && $view === 'backups'): ?>
    <section class="card private-card-wide">
      <h2>Sauvegardes</h2>
      <p class="muted">Les snapshots locaux et les sauvegardes externes sont distingués. Aucun chemin arbitraire n’est envoyé depuis le BO.</p>
      <?php if ($backups === []): ?>
        <p class="muted">Aucun état de sauvegarde reçu.</p>
      <?php else: ?>
        <table><thead><tr><th>Agent</th><th>Snapshot</th><th>Sauvegarde externe</th><th>Vérification</th></tr></thead><tbody>
          <?php foreach ($backups as $backup): if (!is_array($backup)) { continue; } ?>
            <tr>
              <td>#<?php echo (int) ($backup['agent_id'] ?? 0); ?></td>
              <td><?php echo $h($backup['snapshot_state'] ?? 'unknown'); ?></td>
              <td><?php echo $h($backup['external_backup_state'] ?? 'unknown'); ?></td>
              <td><?php echo $h($dateLabel($backup['last_verify_at'] ?? null)); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    </section>
  <?php elseif ($isPhotoApp && $view === 'photos'): ?>
    <section class="card private-card-wide">
      <h2>Photo rename</h2>
      <p class="muted">Choisissez le navigateur pour produire des copies renommées, ou l’agent local pour renommer directement des dossiers autorisés.</p>
      <?php if ($agents === []): ?>
        <section class="notice notice-info" role="status">
          <strong>Aucun agent local détecté.</strong>
          Le renommage reste disponible sans installation : sélectionnez des photos ci-dessous, la webapp prépare des copies renommées dans une archive ZIP, puis vous les enregistrez où vous voulez. Les originaux ne sont pas modifiés.
        </section>
      <?php endif; ?>
      <section class="private-dashboard-panel">
        <h3>Mode navigateur Android, iOS et desktop</h3>
        <p class="muted">Les fichiers sélectionnés restent dans le navigateur. La commune est déduite automatiquement des coordonnées GPS EXIF quand elles existent ; le champ ci-dessous sert seulement de secours.</p>
        <div class="private-list-tools photo-browser-renamer" data-photo-browser-renamer data-photo-browser-geocode-url="<?php echo $h($url('photos')); ?>" data-photo-browser-csrf="<?php echo $h($csrfToken); ?>">
          <label>Photos
            <input type="file" accept="image/jpeg,image/png,image/webp,image/heic,.jpg,.jpeg,.png,.webp,.heic" multiple data-photo-browser-files />
          </label>
          <label>Commune de secours
            <input type="text" maxlength="160" placeholder="si GPS absent" data-photo-browser-commune />
          </label>
          <label>Premier numéro
            <input type="number" min="1" max="999999" value="1" data-photo-browser-start />
          </label>
          <label>Tri
            <select data-photo-browser-sort>
              <option value="taken" selected>date de prise de vue</option>
              <option value="date">date fichier navigateur</option>
              <option value="name">nom actuel</option>
            </select>
          </label>
          <div class="private-actions">
            <button type="button" class="private-button-secondary" data-photo-browser-preview>Prévisualiser</button>
            <button type="button" class="private-create-button" data-photo-browser-download disabled>Télécharger les copies</button>
          </div>
          <p class="muted" data-photo-browser-status>Aucun fichier sélectionné.</p>
          <div class="private-table-wrap">
            <table data-photo-browser-table hidden>
              <thead><tr><th>Aperçu</th><th>Nom actuel</th><th>Taille</th><th>Commune</th><th>Copie renommée</th><th>État et détails</th></tr></thead>
              <tbody data-photo-browser-rows></tbody>
            </table>
          </div>
        </div>
      </section>
      <?php if ($agents === []): ?>
        <p class="muted">Aucun agent appairé: le navigateur produit des copies renommées téléchargeables, sans accès direct aux dossiers locaux.</p>
      <?php else: ?>
        <section class="private-dashboard-panel">
          <h3>Mode agent desktop</h3>
          <p class="muted">L’agent est le parcours rapide pour les dossiers locaux autorisés sur Windows, Linux ou macOS. Il reste optionnel et ne s’installe qu’après consentement explicite.</p>
        </section>
        <div class="private-dashboard-summary">
          <section class="private-dashboard-panel">
            <h3>Source</h3>
            <form method="post" action="<?php echo $h($url('photos')); ?>" class="private-list-tools">
              <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
              <input type="hidden" name="action" value="queue_command" />
              <label>Agent
                <select name="agent_id">
                  <?php foreach ($agents as $agent): if (!is_array($agent) || ($agent['status'] ?? '') !== 'active') { continue; } ?>
                    <option value="<?php echo (int) ($agent['id'] ?? 0); ?>"><?php echo $h($agent['display_name'] ?? 'Agent'); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Action
                <select name="command_type">
                  <option value="photo.roots.list">Lister les racines autorisées</option>
                  <option value="photo.folder.scan">Scanner un dossier</option>
                </select>
              </label>
              <label>Racine autorisée
                <input type="text" name="root_uid" maxlength="64" placeholder="photos-principales" />
              </label>
              <label>Dossier relatif
                <input type="text" name="relative_dir" maxlength="240" placeholder="2026/vacances" />
              </label>
              <label>
                <input type="checkbox" name="include_subdirectories" value="1" />
                Inclure les sous-dossiers
              </label>
              <button type="submit" class="private-create-button">Envoyer à l’agent</button>
            </form>
          </section>
          <section class="private-dashboard-panel">
            <h3>Aperçu de renommage</h3>
            <form method="post" action="<?php echo $h($url('photos')); ?>" class="private-list-tools">
              <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
              <input type="hidden" name="action" value="queue_command" />
              <input type="hidden" name="command_type" value="photo.rename.preview" />
              <label>Agent
                <select name="agent_id">
                  <?php foreach ($agents as $agent): if (!is_array($agent) || ($agent['status'] ?? '') !== 'active') { continue; } ?>
                    <option value="<?php echo (int) ($agent['id'] ?? 0); ?>"><?php echo $h($agent['display_name'] ?? 'Agent'); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Racine autorisée
                <input type="text" name="root_uid" maxlength="64" required />
              </label>
              <label>Dossier relatif
                <input type="text" name="relative_dir" maxlength="240" />
              </label>
              <label>Photos sélectionnées
                <textarea name="items" rows="7" placeholder="IMG_0001.jpg&#10;IMG_0002.jpg" required></textarea>
              </label>
              <input type="hidden" name="separator" value="-" />
              <input type="hidden" name="counter_digits" value="2" />
              <label>Tri
                <select name="sort_order">
                  <option value="chronological">chronologique</option>
                  <option value="name">nom actuel</option>
                  <option value="city">ville</option>
                  <option value="manual">ordre saisi</option>
                </select>
              </label>
              <button type="submit" class="private-create-button">Demander l’aperçu</button>
            </form>
          </section>
        </div>
        <div class="private-dashboard-summary">
          <section class="private-dashboard-panel">
            <h3>Exécution validée</h3>
            <form method="post" action="<?php echo $h($url('photos')); ?>" class="private-list-tools">
              <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
              <input type="hidden" name="action" value="queue_command" />
              <input type="hidden" name="command_type" value="photo.rename.execute" />
              <label>Agent
                <select name="agent_id">
                  <?php foreach ($agents as $agent): if (!is_array($agent) || ($agent['status'] ?? '') !== 'active') { continue; } ?>
                    <option value="<?php echo (int) ($agent['id'] ?? 0); ?>"><?php echo $h($agent['display_name'] ?? 'Agent'); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Lot
                <input type="text" name="batch_uid" maxlength="32" required />
              </label>
              <label>Aperçu validé
                <input type="text" name="preview_uid" maxlength="32" required />
              </label>
              <button type="submit" class="private-button-danger">Renommer le lot validé</button>
            </form>
          </section>
          <section class="private-dashboard-panel">
            <h3>Annulation</h3>
            <form method="post" action="<?php echo $h($url('photos')); ?>" class="private-list-tools">
              <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
              <input type="hidden" name="action" value="queue_command" />
              <label>Agent
                <select name="agent_id">
                  <?php foreach ($agents as $agent): if (!is_array($agent) || ($agent['status'] ?? '') !== 'active') { continue; } ?>
                    <option value="<?php echo (int) ($agent['id'] ?? 0); ?>"><?php echo $h($agent['display_name'] ?? 'Agent'); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Action
                <select name="command_type">
                  <option value="photo.rename.rollback_preview">Prévisualiser l’annulation</option>
                  <option value="photo.rename.rollback_execute">Exécuter l’annulation validée</option>
                </select>
              </label>
              <label>Lot
                <input type="text" name="batch_uid" maxlength="32" required />
              </label>
              <label>Aperçu inverse validé
                <input type="text" name="preview_uid" maxlength="32" />
              </label>
              <button type="submit" class="private-button-secondary">Envoyer</button>
            </form>
          </section>
        </div>
      <?php endif; ?>
    </section>
  <?php elseif ($isPhotoApp && $view === 'history'): ?>
    <section class="card private-card-wide">
      <h2>Historique</h2>
      <p class="muted">Chaque lot conserve les noms d’origine, les noms attribués, les communes, les numéros et les erreurs utiles. Le rollback ne diminue jamais les compteurs.</p>
      <table><thead><tr><th>Date</th><th>Agent</th><th>Fichiers</th><th>Statut</th></tr></thead><tbody>
        <?php if (($photoGeo['schema_available'] ?? false) !== true): ?>
          <tr><td colspan="4" class="muted">Migration Photo rename en attente.</td></tr>
        <?php elseif ($photoGeoBatches === []): ?>
          <tr><td colspan="4" class="muted">Aucun lot enregistré.</td></tr>
        <?php else: ?>
          <?php foreach ($photoGeoBatches as $batch): if (!is_array($batch)) { continue; } ?>
            <tr>
              <td><?php echo $h($dateLabel($batch['created_at'] ?? null)); ?></td>
              <td><?php echo $h($batch['agent_uid'] ?? ('#' . (int) ($batch['agent_id'] ?? 0))); ?></td>
              <td><?php echo (int) ($batch['total_files'] ?? 0); ?></td>
              <td><?php echo $h($statusLabel((string) ($batch['status'] ?? 'draft'))); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody></table>
    </section>
  <?php elseif ($isPhotoApp && $view === 'counters'): ?>
    <section class="card private-card-wide">
      <h2>Compteurs</h2>
      <p class="muted">Les compteurs sont globaux par commune et stockés dans la BDD OVH. Une initialisation manuelle renseigne un dernier numéro déjà utilisé sans scanner le dossier de destination.</p>
      <form method="post" action="<?php echo $h($url('counters')); ?>" class="private-list-tools">
        <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
        <input type="hidden" name="action" value="photo_counter_initialize" />
        <label>Commune
          <input type="text" name="commune_name" maxlength="160" required />
        </label>
        <label>Dernier numéro déjà utilisé
          <input type="number" name="last_number" min="0" max="999999" value="0" required />
        </label>
        <button type="submit" class="private-button-secondary">Initialiser</button>
      </form>
      <table><thead><tr><th>Commune</th><th>Dernier numéro</th><th>Dernière réservation</th></tr></thead><tbody>
        <?php if (($photoGeo['schema_available'] ?? false) !== true): ?>
          <tr><td colspan="3" class="muted">Migration Photo rename en attente.</td></tr>
        <?php elseif ($photoGeoCounters === []): ?>
          <tr><td colspan="3" class="muted">Aucun compteur initialisé.</td></tr>
        <?php else: ?>
          <?php foreach ($photoGeoCounters as $counter): if (!is_array($counter)) { continue; } ?>
            <tr>
              <td><?php echo $h($counter['commune_name'] ?? ''); ?></td>
              <td><?php echo (int) ($counter['last_number'] ?? 0); ?></td>
              <td><?php echo $h($dateLabel($counter['updated_at'] ?? null)); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody></table>
    </section>
  <?php elseif ($view === 'agents'): ?>
    <section class="card private-card-wide">
      <h2>Agents et installation</h2>
      <p class="muted">
        <?php if ($isPhotoApp): ?>
          L’agent local est optionnel. Photo rename fonctionne sans agent avec des copies ZIP, et les ordinateurs acceptés sont retrouvés ici après appairage.
        <?php else: ?>
          Installez un agent local uniquement après consentement explicite. Chaque ordinateur appairé conserve sa configuration locale et réapparaît ici lors de ses contacts.
        <?php endif; ?>
      </p>
      <section class="private-dashboard-panel">
        <h3>Installer l’agent local PbGestion</h3>
        <p class="muted">Le téléchargement crée un appairage valable 30 minutes et l’intègre dans le script. La confirmation se fait par popup avant le téléchargement; aucun texte n’est à recopier.</p>
        <form method="post" action="<?php echo $h($url('agents')); ?>" class="private-list-tools" data-private-sensitive-action="installation agent local" data-private-confirm-message="Installer un agent local sur cet ordinateur ?">
          <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
          <input type="hidden" name="action" value="download_agent_installer" />
          <input type="hidden" name="installer_consent" value="1" />
          <label>Ordinateur ou usage
            <input type="text" name="location_label" maxlength="160" placeholder="Maison, bureau, PC principal" />
          </label>
          <label>Plateforme
            <select name="installer_platform">
              <option value="windows">Windows</option>
              <option value="linux">Linux</option>
              <option value="macos">macOS</option>
            </select>
          </label>
          <button type="submit" class="private-create-button">Télécharger l’installeur local</button>
        </form>
      </section>
      <section class="private-dashboard-panel">
        <h3>Supprimer l’agent local</h3>
        <p class="muted">La révocation bloque l’agent côté webapp. Pour nettoyer aussi l’ordinateur, téléchargez le script de suppression adapté puis exécutez-le sur le poste concerné.</p>
        <form method="post" action="<?php echo $h($url('agents')); ?>" class="private-list-tools" data-private-sensitive-action="suppression agent local" data-private-confirm-message="Télécharger le script de suppression locale ?">
          <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
          <input type="hidden" name="action" value="download_agent_uninstaller" />
          <label>Plateforme
            <select name="installer_platform">
              <option value="windows">Windows</option>
              <option value="linux">Linux</option>
              <option value="macos">macOS</option>
            </select>
          </label>
          <button type="submit" class="private-button-danger">Télécharger la suppression locale</button>
        </form>
      </section>
      <p class="muted">Appairage manuel de secours: créez un code à usage unique si vous copiez ou lancez l’agent vous-même. Le code reste valable 30 minutes et n’est jamais conservé en clair.</p>
      <?php if ($oneTimeEnrollment !== null): ?>
        <div class="notice notice-success" role="status">
          Code à saisir dans l’agent: <strong><?php echo $h($oneTimeEnrollment['code_grouped'] ?? ''); ?></strong>.
          Expiration: <?php echo $h($dateLabel($oneTimeEnrollment['expires_at'] ?? null)); ?>.
        </div>
      <?php endif; ?>
      <form method="post" action="<?php echo $h($url('agents')); ?>" class="private-list-tools">
        <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
        <input type="hidden" name="action" value="create_enrollment" />
        <label>Lieu ou usage
          <input type="text" name="location_label" maxlength="160" placeholder="Maison, bureau, PC principal" />
        </label>
        <button type="submit" class="private-create-button">Créer un code 30 minutes</button>
      </form>
      <?php if ($agents === []): ?>
        <p class="muted">Aucun agent appairé.</p>
      <?php else: ?>
        <table><thead><tr><th>Ordinateur</th><th>OS</th><th>Etat</th><th>Version</th><th>Dernier contact</th><th>Action</th></tr></thead><tbody>
          <?php foreach ($agents as $agent): if (!is_array($agent)) { continue; } ?>
            <tr>
              <td>
                <?php echo $h($agent['display_name'] ?? 'Agent'); ?>
                <?php if (is_string($agent['location_label'] ?? null) && trim((string) $agent['location_label']) !== ''): ?>
                  <br><span class="muted"><?php echo $h($agent['location_label']); ?></span>
                <?php endif; ?>
              </td>
              <td><?php echo $h($agent['os_family'] ?? ''); ?></td>
              <td><?php echo $h($statusLabel((string) ($agent['status'] ?? 'unknown'))); ?></td>
              <td><?php echo $h($agent['agent_version'] ?? ''); ?></td>
              <td><?php echo $h($dateLabel($agent['last_seen_at'] ?? null)); ?></td>
              <td>
                <form method="post" action="<?php echo $h($url('agents')); ?>">
                  <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
                  <input type="hidden" name="action" value="revoke_agent" />
                  <input type="hidden" name="agent_id" value="<?php echo (int) ($agent['id'] ?? 0); ?>" />
                  <button type="submit" class="private-button-danger">Révoquer</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    </section>
  <?php elseif ($isSecurityApp && $view === 'settings'): ?>
    <section class="card private-card-wide">
      <h2>Paramètres</h2>
      <p class="muted">Les politiques et commandes restent fermées. Les suppressions temporaires sont exécutées par petits lots.</p>
      <form method="post" action="<?php echo $h($url('settings')); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
        <input type="hidden" name="action" value="purge_details" />
        <button type="submit" class="private-button-secondary">Purger les détails expirés</button>
      </form>
    </section>
  <?php else: ?>
    <section class="card private-card-wide">
      <?php if ($isPhotoApp): ?>
      <h2>Aide Photo rename</h2>
      <p class="muted">Photo rename propose un mode navigateur universel et un mode agent local optionnel. Les photos ne sont pas envoyées à OVH par le mode navigateur.</p>
      <h3>Avant de commencer</h3>
      <p>Sur Android et iOS, sélectionnez les photos dans le navigateur puis téléchargez les copies renommées. Sur ordinateur, installez l’agent PbGestion seulement après consentement explicite pour scanner ou renommer directement des dossiers locaux.</p>
      <h3>Commandes</h3>
      <p>Les commandes photo sont asynchrones et restent bornées à une racine locale autorisée, un dossier relatif et une sélection explicite. Le dossier destination n’est jamais analysé pour calculer le prochain numéro.</p>
      <h3>8. Informations techniques</h3>
      <p>Les commandes photo sont récupérées par l’agent via `/api/pbgestion/v1/*`. L’agent doit utiliser ExifTool localement, renommer sans écrasement en deux passes et renvoyer des erreurs structurées comme <strong>target_exists</strong>.</p>
      <?php else: ?>
      <h2>Aide Sécurité réseau</h2>
      <p class="muted">Sécurité réseau affiche l’état local utile transmis par les agents. Les données brutes réseau restent locales par défaut.</p>
      <h3>Avant de commencer</h3>
      <p>Installez un agent, créez un code d’appairage, puis vérifiez que le dernier contact apparaît dans la vue d’ensemble.</p>
      <h3>Commandes</h3>
      <p>Les actions sont asynchrones: elles sont placées en file d’attente et récupérées par l’agent lors du prochain contact signé.</p>
      <h3>8. Informations techniques</h3>
      <p>Endpoints agent: `/api/pbgestion/v1/*`. Signature Ed25519, horodatage UTC, séquence croissante et UUID de requête mémorisé 24 h.</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</section>
