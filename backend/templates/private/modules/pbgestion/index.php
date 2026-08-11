<?php
$pbgestion = is_array($viewModel['pbgestion'] ?? null) ? $viewModel['pbgestion'] : [];
$view = is_string($pbgestion['view'] ?? null) ? (string) $pbgestion['view'] : 'overview';
$csrfToken = is_string($pbgestion['csrfToken'] ?? null) ? (string) $pbgestion['csrfToken'] : '';
$urls = is_array($pbgestion['urls'] ?? null) ? $pbgestion['urls'] : [];
$dashboard = is_array($pbgestion['dashboard'] ?? null) ? $pbgestion['dashboard'] : [];
$oneTimeEnrollment = is_array($pbgestion['oneTimeEnrollment'] ?? null) ? $pbgestion['oneTimeEnrollment'] : null;
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
  <nav class="private-module-nav" aria-label="Navigation PB Gestion">
    <div class="private-module-nav-row">
      <a class="<?php echo $isActive('overview'); ?>" href="<?php echo $h($url('overview')); ?>">Vue d’ensemble</a>
      <a class="<?php echo $isActive('coverage'); ?>" href="<?php echo $h($url('coverage')); ?>">Couverture</a>
      <a class="<?php echo $isActive('networks'); ?>" href="<?php echo $h($url('networks')); ?>">Réseaux</a>
      <a class="<?php echo $isActive('devices'); ?>" href="<?php echo $h($url('devices')); ?>">Appareils</a>
      <a class="<?php echo $isActive('computers'); ?>" href="<?php echo $h($url('computers')); ?>">Ordinateurs</a>
      <a class="<?php echo $isActive('alerts'); ?>" href="<?php echo $h($url('alerts')); ?>">Alertes</a>
      <a class="<?php echo $isActive('scans'); ?>" href="<?php echo $h($url('scans')); ?>">Scans</a>
      <a class="<?php echo $isActive('backups'); ?>" href="<?php echo $h($url('backups')); ?>">Sauvegardes</a>
      <a class="<?php echo $isActive('agents'); ?>" href="<?php echo $h($url('agents')); ?>">Agents et installation</a>
      <a class="<?php echo $isActive('settings'); ?>" href="<?php echo $h($url('settings')); ?>">Paramètres</a>
      <a class="<?php echo $isActive('help'); ?>" href="<?php echo $h($url('help')); ?>">Aide</a>
    </div>
  </nav>

  <?php if ($view === 'overview'): ?>
    <section class="private-module-dashboard">
      <div class="private-list-header">
        <div>
          <span class="tag">PB Gestion</span>
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
  <?php elseif ($view === 'coverage'): ?>
    <section class="card private-card-wide">
      <h2>Couverture</h2>
      <p class="muted">La couverture dépend de l’âge des contacts agents. Au-delà de deux intervalles attendus, elle devient partielle puis interrompue.</p>
      <div class="private-dashboard-summary">
        <section class="private-dashboard-panel"><h3>Etat</h3><p><strong><?php echo $h($statusLabel((string) ($dashboard['coverage_state'] ?? 'interrupted'))); ?></strong></p></section>
        <section class="private-dashboard-panel"><h3>Dernier contact</h3><p><strong><?php echo $h($dateLabel($dashboard['latest_seen_at'] ?? null)); ?></strong></p></section>
        <section class="private-dashboard-panel"><h3>Commandes en cours</h3><p><strong><?php echo (int) ($dashboard['commands_pending'] ?? 0); ?></strong></p></section>
      </div>
    </section>
  <?php elseif ($view === 'networks'): ?>
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
  <?php elseif ($view === 'devices' || $view === 'computers'): ?>
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
  <?php elseif ($view === 'alerts'): ?>
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
  <?php elseif ($view === 'scans'): ?>
    <section class="card private-card-wide">
      <h2>Scans et historique</h2>
      <p class="muted">Les synthèses de scan sont datées. Les listes détaillées de ports ne sont pas stockées durablement sur OVH.</p>
      <p class="muted">Scans connus: <?php echo (int) ($dashboard['scans_total'] ?? 0); ?>.</p>
    </section>
  <?php elseif ($view === 'backups'): ?>
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
  <?php elseif ($view === 'agents'): ?>
    <section class="card private-card-wide">
      <h2>Agents et installation</h2>
      <p class="muted">Créez un code d’appairage à usage unique. Le code n’est jamais conservé en clair.</p>
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
  <?php elseif ($view === 'settings'): ?>
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
      <h2>Aide PB Gestion</h2>
      <p class="muted">PB Gestion affiche l’état local utile transmis par les agents. Les données brutes réseau restent locales par défaut.</p>
      <h3>Avant de commencer</h3>
      <p>Installez un agent, créez un code d’appairage, puis vérifiez que le dernier contact apparaît dans la vue d’ensemble.</p>
      <h3>Commandes</h3>
      <p>Les actions sont asynchrones: elles sont placées en file d’attente et récupérées par l’agent lors du prochain contact signé.</p>
      <h3>8. Informations techniques</h3>
      <p>Endpoints agent: `/api/pbgestion/v1/*`. Signature Ed25519, horodatage UTC, séquence croissante et UUID de requête mémorisé 24 h.</p>
    </section>
  <?php endif; ?>
</section>
