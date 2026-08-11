<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$dashboard = is_array($pbGestionDashboard ?? null) ? $pbGestionDashboard : [];
$agents = is_array($dashboard['agents'] ?? null) ? $dashboard['agents'] : [];
$revokedAgents = is_array($dashboard['revoked_agents'] ?? null) ? $dashboard['revoked_agents'] : [];
$versions = is_array($dashboard['versions'] ?? null) ? $dashboard['versions'] : [];
$policies = is_array($dashboard['policies'] ?? null) ? $dashboard['policies'] : [];
$latestScans = is_array($dashboard['latest_scans'] ?? null) ? $dashboard['latest_scans'] : [];
$postures = is_array($dashboard['postures'] ?? null) ? $dashboard['postures'] : [];
$retentions = is_array($dashboard['retentions'] ?? null) ? $dashboard['retentions'] : [];
$adminPbGestionUrl = is_string($adminPbGestionUrl ?? null) ? $adminPbGestionUrl : admin_url('pbgestion');
?>
<h1>PB Gestion</h1>

<?php if (($message ?? null) !== null): ?>
<div class="notice notice-success" role="status"><?php echo $escape($message); ?></div>
<?php endif; ?>
<?php if (($error ?? null) !== null): ?>
<div class="notice notice-error" role="alert"><?php echo $escape($error); ?></div>
<?php endif; ?>

<section class="cards-grid dashboard-kpis" aria-labelledby="pbgestion-overview">
  <article class="card dashboard-kpi-card" id="pbgestion-overview">
    <span class="tag">Parc</span>
    <strong class="dashboard-kpi-value"><?php echo (int) ($dashboard['agents_active'] ?? 0); ?></strong>
    <p class="dashboard-kpi-label">agent(s) actif(s) sur <?php echo (int) ($dashboard['agents_total'] ?? 0); ?></p>
  </article>
  <article class="card dashboard-kpi-card">
    <span class="tag">Couverture</span>
    <strong class="dashboard-kpi-value"><?php echo (int) ($dashboard['networks_total'] ?? 0); ?></strong>
    <p class="dashboard-kpi-label">réseau(x), <?php echo (int) ($dashboard['devices_total'] ?? 0); ?> appareil(s)</p>
  </article>
  <article class="card dashboard-kpi-card">
    <span class="tag">Sécurité</span>
    <strong class="dashboard-kpi-value"><?php echo (int) ($dashboard['alerts_open'] ?? 0); ?></strong>
    <p class="dashboard-kpi-label">alerte(s) ouverte(s), <?php echo (int) ($dashboard['commands_pending'] ?? 0); ?> commande(s)</p>
  </article>
  <article class="card dashboard-kpi-card">
    <span class="tag">Rétention</span>
    <strong class="dashboard-kpi-value"><?php echo (int) ($dashboard['details_to_purge'] ?? 0); ?></strong>
    <p class="dashboard-kpi-label">détail(s) temporaire(s) expiré(s)</p>
  </article>
</section>

<section class="admin-section">
  <h2>Maintenance des détails temporaires</h2>
  <form method="post" action="<?php echo $escape($adminPbGestionUrl); ?>" class="actions-inline">
    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken ?? ''); ?>" />
    <button type="submit" name="pbgestion_action" value="dry_run_expired_details">Estimer la purge</button>
    <button class="button-danger" type="submit" name="pbgestion_action" value="purge_expired_details">Purger les expirés</button>
  </form>
</section>

<section class="admin-section">
  <h2>Agents</h2>
  <?php if ($agents === []): ?>
    <p class="muted">Aucun agent PB Gestion appairé.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Agent</th>
          <th>Utilisateur</th>
          <th>Statut</th>
          <th>OS</th>
          <th>Version</th>
          <th>Dernier signal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($agents as $agent): ?>
        <tr>
          <td><?php echo $escape($agent['display_name'] ?? 'Agent PB Gestion'); ?></td>
          <td>#<?php echo (int) ($agent['owner_id'] ?? 0); ?></td>
          <td><?php echo $escape($agent['status'] ?? ''); ?></td>
          <td><?php echo $escape(trim((string) ($agent['os_family'] ?? '') . ' ' . (string) ($agent['os_version'] ?? ''))); ?></td>
          <td><?php echo $escape($agent['agent_version'] ?? '-'); ?></td>
          <td><?php echo $escape($agent['last_seen_at'] ?? '-'); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="admin-section">
  <h2>Synthèses de scan</h2>
  <?php if ($latestScans === []): ?>
    <p class="muted">Aucune synthèse de scan reçue.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Type</th>
          <th>Statut</th>
          <th>Appareils</th>
          <th>Changements</th>
          <th>Alertes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($latestScans as $scan): ?>
        <tr>
          <td><?php echo $escape($scan['scanned_at'] ?? '-'); ?></td>
          <td><?php echo $escape($scan['scan_type'] ?? ''); ?></td>
          <td><?php echo $escape($scan['status'] ?? ''); ?></td>
          <td><?php echo (int) ($scan['devices_seen'] ?? 0); ?></td>
          <td><?php echo (int) ($scan['changes_seen'] ?? 0); ?></td>
          <td><?php echo (int) ($scan['alerts_opened'] ?? 0); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="admin-section">
  <h2>Posture</h2>
  <?php if ($postures === []): ?>
    <p class="muted">Aucune posture consolidée.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Agent</th>
          <th>État</th>
          <th>Risque</th>
          <th>Signalé le</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($postures as $posture): ?>
        <tr>
          <td>#<?php echo (int) ($posture['agent_id'] ?? 0); ?></td>
          <td><?php echo $escape($posture['posture_state'] ?? ''); ?></td>
          <td><?php echo $escape($posture['risk_level'] ?? ''); ?></td>
          <td><?php echo $escape($posture['reported_at'] ?? '-'); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="admin-section">
  <h2>Versions disponibles</h2>
  <?php if ($versions === []): ?>
    <p class="muted">Aucune version d’agent publiée.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Version</th>
          <th>Canal</th>
          <th>OS</th>
          <th>Checksum</th>
          <th>Publication</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($versions as $version): ?>
        <tr>
          <td><?php echo $escape($version['version'] ?? ''); ?></td>
          <td><?php echo $escape($version['channel'] ?? ''); ?></td>
          <td><?php echo $escape($version['os_family'] ?? ''); ?></td>
          <td><?php echo $escape($version['sha256'] ?? '-'); ?></td>
          <td><?php echo $escape($version['published_at'] ?? '-'); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="admin-section">
  <h2>Politiques par défaut</h2>
  <?php if ($policies === []): ?>
    <p class="muted">Aucune politique serveur enregistrée.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Nom</th>
          <th>Version</th>
          <th>Statut</th>
          <th>Création</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($policies as $policy): ?>
        <tr>
          <td><?php echo $escape($policy['policy_name'] ?? ''); ?></td>
          <td><?php echo (int) ($policy['policy_version'] ?? 0); ?></td>
          <td><?php echo $escape($policy['status'] ?? ''); ?></td>
          <td><?php echo $escape($policy['created_at'] ?? '-'); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="admin-section">
  <h2>Agents révoqués</h2>
  <?php if ($revokedAgents === []): ?>
    <p class="muted">Aucun agent révoqué.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Agent</th>
          <th>Utilisateur</th>
          <th>Motif</th>
          <th>Révocation</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($revokedAgents as $agent): ?>
        <tr>
          <td><?php echo $escape($agent['display_name'] ?? 'Agent PB Gestion'); ?></td>
          <td>#<?php echo (int) ($agent['owner_id'] ?? 0); ?></td>
          <td><?php echo $escape($agent['revoked_reason'] ?? '-'); ?></td>
          <td><?php echo $escape($agent['revoked_at'] ?? '-'); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="admin-section">
  <h2>Rétentions</h2>
  <ul>
    <?php foreach ($retentions as $label => $retention): ?>
      <li><?php echo $escape($label); ?> : <?php echo $escape($retention); ?></li>
    <?php endforeach; ?>
  </ul>
</section>
