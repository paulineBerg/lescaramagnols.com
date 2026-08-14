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
      <?php endif; ?>
      <a class="<?php echo $isActive('agents'); ?>" href="<?php echo $h($url('agents')); ?>">Agents et installation</a>
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
      <p class="muted">Les originaux restent sur l’ordinateur de l’agent. Le BO envoie des demandes bornées : racine autorisée, dossier relatif et sélection explicite.</p>
      <section class="private-dashboard-panel">
        <h3>Mode restreint sans agent</h3>
        <p class="muted">Si l’installation locale est refusée, le BO peut seulement calculer un aperçu à partir d’une liste copiée-collée. Il ne lit pas les EXIF, ne géocode pas et ne renomme aucun fichier.</p>
        <form method="post" action="<?php echo $h($url('photos')); ?>" class="private-list-tools">
          <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
          <input type="hidden" name="action" value="photo_restricted_preview" />
          <label>Photos à prévisualiser
            <textarea name="restricted_items" rows="6" placeholder="IMG_0001.jpg;Cogolin;2026-08-13 12:00:00&#10;IMG_0002.jpg;Cogolin;2026-08-13 12:05:00" required></textarea>
          </label>
          <label>Texte avant
            <input type="text" name="text_before" maxlength="80" placeholder="Vacances" />
          </label>
          <label>Texte après
            <input type="text" name="text_after" maxlength="80" />
          </label>
          <label>Séparateur
            <select name="separator">
              <option value="-">-</option>
              <option value="_">_</option>
              <option value=" ">espace</option>
            </select>
          </label>
          <label>Chiffres compteur
            <input type="number" name="counter_digits" min="1" max="6" value="3" />
          </label>
          <label>Tri
            <select name="sort_order">
              <option value="manual">ordre saisi</option>
              <option value="chronological">chronologique</option>
              <option value="name">nom actuel</option>
              <option value="city">ville</option>
            </select>
          </label>
          <button type="submit" class="private-button-secondary">Prévisualiser sans agent</button>
        </form>
        <?php if ($restrictedPhotoPreview !== null): ?>
          <?php
          $restrictedPreview = is_array($restrictedPhotoPreview['preview'] ?? null) ? $restrictedPhotoPreview['preview'] : [];
          $restrictedSummary = is_array($restrictedPreview['summary'] ?? null) ? $restrictedPreview['summary'] : [];
          $restrictedOperations = is_array($restrictedPreview['operations'] ?? null) ? $restrictedPreview['operations'] : [];
          ?>
          <div class="notice notice-success" role="status">
            Lot <?php echo $h($restrictedPhotoPreview['batch_uid'] ?? ''); ?> :
            <?php echo (int) ($restrictedSummary['ready'] ?? 0); ?> prêt(s),
            <?php echo (int) ($restrictedSummary['conflicts'] ?? 0); ?> conflit(s).
          </div>
          <?php if ($restrictedOperations !== []): ?>
            <table><thead><tr><th>Nom actuel</th><th>Nom proposé</th><th>Etat</th></tr></thead><tbody>
              <?php foreach ($restrictedOperations as $operation): if (!is_array($operation)) { continue; } ?>
                <tr>
                  <td><?php echo $h($operation['old_name'] ?? ''); ?></td>
                  <td><?php echo $h($operation['new_name'] ?? ''); ?></td>
                  <td><?php echo $h($statusLabel((string) ($operation['status'] ?? ''))); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody></table>
          <?php endif; ?>
        <?php endif; ?>
      </section>
      <?php if ($agents === []): ?>
        <p class="muted">Aucun agent appairé: seules les fonctions restreintes ci-dessus sont disponibles.</p>
      <?php else: ?>
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
              <label>Texte avant
                <input type="text" name="text_before" maxlength="80" placeholder="Vacances" />
              </label>
              <label>Texte après
                <input type="text" name="text_after" maxlength="80" />
              </label>
              <label>Séparateur
                <select name="separator">
                  <option value="-">-</option>
                  <option value="_">_</option>
                  <option value=" ">espace</option>
                </select>
              </label>
              <label>Chiffres compteur
                <input type="number" name="counter_digits" min="1" max="6" value="3" />
              </label>
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
  <?php elseif ($view === 'agents'): ?>
    <section class="card private-card-wide">
      <h2>Agents et installation</h2>
      <p class="muted">
        <?php if ($isPhotoApp): ?>
          Installez l’agent local uniquement après consentement explicite. En cas de refus, Photo rename reste disponible en mode restreint sans accès fichiers.
        <?php else: ?>
          Installez un agent local uniquement après consentement explicite. En cas de refus, Photo rename reste disponible en mode restreint sans accès fichiers.
        <?php endif; ?>
      </p>
      <section class="private-dashboard-panel">
        <h3>Installer l’agent local PbGestion</h3>
        <p class="muted">Le téléchargement crée un code d’appairage valable 10 minutes. Le script demandé installe les fichiers sous un dossier maître <strong>pbgestion</strong> du profil Windows courant, crée une tâche planifiée locale et redemande de taper OUI avant toute installation.</p>
        <form method="post" action="<?php echo $h($url('agents')); ?>" class="private-list-tools">
          <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
          <input type="hidden" name="action" value="download_agent_installer" />
          <label>Lieu ou usage
            <input type="text" name="location_label" maxlength="160" placeholder="Maison, bureau, PC principal" />
          </label>
          <label>
            <input type="checkbox" name="installer_consent" value="1" />
            Je comprends que l’agent local s’installe sur cet ordinateur et exécutera seulement les commandes PbGestion validées.
          </label>
          <label>Confirmation
            <input type="text" name="installer_confirmation" maxlength="16" placeholder="INSTALLER" required />
          </label>
          <button type="submit" class="private-create-button">Télécharger l’installeur local</button>
        </form>
      </section>
      <p class="muted">Appairage manuel: créez un code à usage unique si vous installez ou lancez l’agent vous-même. Le code n’est jamais conservé en clair.</p>
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
        <button type="submit" class="private-create-button">Créer un code 10 minutes</button>
      </form>
      <?php if ($agents === []): ?>
        <p class="muted">Aucun agent appairé.</p>
      <?php else: ?>
        <table><thead><tr><th>Agent</th><th>Etat</th><th>Version</th><th>Dernier contact</th><th>Action</th></tr></thead><tbody>
          <?php foreach ($agents as $agent): if (!is_array($agent)) { continue; } ?>
            <tr>
              <td><?php echo $h($agent['display_name'] ?? 'Agent'); ?></td>
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
      <p class="muted">Photo rename prépare des noms de fichiers photo sans lire les fichiers locaux depuis OVH. Le mode restreint calcule seulement un aperçu à partir de la liste saisie.</p>
      <h3>Avant de commencer</h3>
      <p>Utilisez le mode restreint si aucun agent local n’est accepté. Installez l’agent PbGestion seulement après consentement explicite pour scanner ou renommer des fichiers sur le PC.</p>
      <h3>Commandes</h3>
      <p>Les commandes photo sont asynchrones et restent bornées à une racine locale autorisée, un dossier relatif et une sélection explicite.</p>
      <h3>8. Informations techniques</h3>
      <p>Les commandes photo sont récupérées par l’agent via `/api/pbgestion/v1/*`. L’agent conserve les journaux et aperçus locaux sous le dossier maître <strong>pbgestion</strong>.</p>
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
