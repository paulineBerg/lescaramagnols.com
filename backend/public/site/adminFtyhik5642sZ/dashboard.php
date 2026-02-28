<?php
// backend/public/site/adminFtyhik5642sZ/dashboard.php

require_once __DIR__ . '/layout.php';

$adminPath = app_config('admin.login_path', 'adminFtyhik5642sZ');

admin_render_layout('Tableau de bord', function () use ($adminPath) {
    ?>
    <section class="cards-grid" aria-labelledby="admin-shortcuts">
      <article class="card" id="admin-shortcuts">
        <h2>Raccourcis</h2>
        <ul>
          <li>Gabarits : <code>backend/templates/pages</code></li>
          <li>Frontend : <code>frontend/src</code></li>
          <li>Docs menus : <code>backend/docs/menu.txt</code></li>
        </ul>
      </article>
      <article class="card" aria-labelledby="admin-roadmap">
        <h2 id="admin-roadmap">Prochaines étapes</h2>
        <ul>
          <li><span class="tag">Workflow</span> Finaliser les statuts articles et la piste de revue (B10, B11, B21).</li>
          <li><span class="tag">Modération</span> Préparer le module commentaires + anti-spam (B20, B22, B23).</li>
          <li><span class="tag">Analytics</span> Composer les indicateurs de performance (B40-B42).</li>
        </ul>
      </article>
      <article class="card" aria-labelledby="admin-security">
        <h2 id="admin-security">Sécurité</h2>
        <ul>
          <li>Chemin protégé : <code><?php echo htmlspecialchars((string) $adminPath, ENT_QUOTES, 'UTF-8'); ?></code></li>
          <li>Mettre à jour le mot de passe via <code>ADMIN_PASSWORD_HASH</code> dans <code>.env</code>.</li>
          <li>Activer 2FA SMTP après mise en ligne du module blog.</li>
        </ul>
      </article>
    </section>
    <p class="notice-muted">
      Besoin d'aide ? Consultez la feuille de route dans <code>README.md</code> et associez chaque ticket Bxx au jalon correspondant.
    </p>
    <?php
});
