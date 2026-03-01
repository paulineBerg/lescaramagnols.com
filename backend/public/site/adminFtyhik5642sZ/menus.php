<?php
// backend/public/site/adminFtyhik5642sZ/menus.php

require_once __DIR__ . '/layout.php';
require_once dirname(__DIR__, 3) . '/core/menu_loader.php';

// Charge menus existants (JSON ou fallback)
$menus = load_menus();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!admin_validate_csrf_token($token)) {
        $error = 'Session expirée, merci de réessayer.';
    } else {
        $raw = $_POST['menus_json'] ?? '';
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $error = 'JSON invalide. Vérifiez la syntaxe.';
        } else {
            // Validation minimale : présence des clés principales
            $expectedKeys = ['menu1', 'banniere', 'menu2', 'menu3'];
            foreach ($expectedKeys as $key) {
                if (!array_key_exists($key, $decoded)) {
                    $error = "Clé manquante : {$key}";
                    break;
                }
            }
            if ($error === null && save_menus($decoded)) {
                $menus = $decoded;
                $message = 'Menus sauvegardés dans data/menus.json (backup .bak conservé).';
            } else {
                $error = $error ?? 'Impossible d’écrire le fichier menus.json (permissions ?).';
            }
        }
    }
}

$menusJsonPretty = json_encode($menus, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$csrfToken = admin_csrf_token();

admin_render_layout('Menus du site', function () use ($menusJsonPretty, $csrfToken, $message, $error) {
    ?>
    <section class="card">
      <h2>Édition des menus</h2>
      <p>Source principale : <code>backend/data/menus.json</code> (fallback <code>config/menu_data.php</code>).</p>
      <?php if ($message): ?><div class="notice" style="color:#0d6c30;"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
      <?php if ($error): ?><div class="notice" style="color:#a11a2a;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <label for="menus_json"><strong>Menus (JSON)</strong></label>
        <textarea id="menus_json" name="menus_json" rows="26" style="width:100%; font-family: 'Fira Code', monospace; font-size: 0.95rem; border-radius: 10px; border:1px solid #ccc; padding:1rem;"><?php echo htmlspecialchars($menusJsonPretty, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <p style="font-size:0.9rem;color:#555;">Clés attendues : <code>menu1</code>, <code>banniere</code>, <code>menu2</code>, <code>menu3</code> (optionnel : <code>menuDroit</code>, <code>menuGauche</code>). Sauvegarde automatique en <code>menus.json</code> + backup <code>menus.json.bak</code>.</p>
        <button type="submit" style="margin-top:1rem; padding:0.8rem 1.2rem; border-radius:10px; background:#1d6f8d; color:#fff; border:none; cursor:pointer;">Sauvegarder</button>
      </form>
    </section>
    <?php
});
