<?php
session_start();

define('ROOT_PATH', realpath(__DIR__ . '/../'));
define('LOCK_FILE', ROOT_PATH . '/install.lock');
define('CONFIG_DIR', ROOT_PATH . '/config');
define('CONFIG_FILE_DEFAULT', CONFIG_DIR . '/db.php');
define('CONFIG_GENERAL_FILE_DEFAULT', CONFIG_DIR . '/config.php');
define('SQL_FILE', ROOT_PATH . '/sql/install.sql');
define('LOG_FILE', __DIR__ . '/install.log');

// Gestion du bouton "copier tmp_config → config"
if (isset($_POST['move_tmp_to_config'])) {
    $tmpDir = ROOT_PATH . '/public/tmp_config';
    $targetDir = CONFIG_DIR;
    $messages = [];

    if (!is_dir($tmpDir)) {
        $_SESSION['error'] = "❌ Dossier temporaire non trouvé : $tmpDir";
    } elseif (!is_writable($targetDir)) {
        @chmod($targetDir, 0777);
        clearstatcache();
        if (!is_writable($targetDir)) {
            $_SESSION['error'] = "❌ Le dossier <code>/config</code> n’est pas inscriptible.";
        }
    }

    if (empty($_SESSION['error'])) {
        $success = true;

        foreach (['db.php', 'config.php'] as $file) {
            $src = "$tmpDir/$file";
            $dst = "$targetDir/$file";

            if (!file_exists($src)) {
                $messages[] = "❌ Fichier manquant dans tmp_config : $file";
                $success = false;
                continue;
            }

            if (!copy($src, $dst)) {
                $messages[] = "❌ Impossible de copier $file → $targetDir";
                $success = false;
            } else {
                $messages[] = "✅ $file copié avec succès.";
            }
        }

        if ($success) {
            $_SESSION['success'] = "✅ Tous les fichiers ont été déplacés dans <code>/config/</code>.";
            log_install("✅ tmp_config déplacé vers config/");
        } else {
            $_SESSION['error'] = implode("<br>", $messages);
        }
    }

    header("Location: install.php");
    exit;
}

$config = null;

function log_install($message) {
    $line = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

function tableExists($pdo, $dbName) {
    $stmt = $pdo->query("SHOW TABLES FROM `$dbName`");
    return $stmt && $stmt->rowCount() > 0;
}

function configFileContent($host, $name, $user, $pass, $lang) {
    return "<?php
define('DB_HOST', '$host');
define('DB_NAME', '$name');
define('DB_USER', '$user');
define('DB_PASS', '$pass');
define('DB_CHARSET', 'utf8mb4');
define('SITE_LANG', '$lang');
";
}

function generalConfigFileContent($lang) {
    return "<?php
define('SITE_LANG', '$lang');
define('DEBUG', true);
define('BASE_URL', 'http://localhost');
define('TIMEZONE', 'Europe/Paris');
";
}

// 🔒 Blocage manuel
if (file_exists(LOCK_FILE)) {
    exit('<h2>⚠️ Installation déjà verrouillée.</h2><p>Supprimez <code>install.lock</code> pour relancer.</p>');
}

// 🧠 Traitement principal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['move_tmp_to_config'])) {
    $host = $_POST['db_host'];
    $name = $_POST['db_name'];
    $user = $_POST['db_user'];
    $pass = $_POST['db_pass'];
    $admin = $_POST['admin_user'];
    $admin_pass = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT);
    $lang = $_POST['lang'];
    $action = $_POST['config_action'] ?? 'replace';

    try {
        log_install("Début installation avec $user@$host, base $name");

        $pdo = new PDO("mysql:host=$host", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");
        log_install("Base $name créée et sélectionnée");

        if (!tableExists($pdo, $name)) {
            if (!file_exists(SQL_FILE)) {
                throw new Exception("Fichier SQL manquant : " . SQL_FILE);
            }
            $sql = file_get_contents(SQL_FILE);
            $pdo->exec($sql);
            log_install("Fichier SQL importé avec succès");
        } else {
            log_install("Tables déjà présentes, pas d'import SQL");
        }

        $stmt = $pdo->prepare("REPLACE INTO users (email, password, role) VALUES (?, ?, 'admin')");
        $stmt->execute([$admin, $admin_pass]);
        log_install("Compte admin inséré : $admin");

        if (!is_dir(CONFIG_DIR)) {
            @mkdir(CONFIG_DIR, 0775, true);
            log_install("Création de " . CONFIG_DIR);
        }

        $useFallback = false;
        $configDirUsed = CONFIG_DIR;

        if (!is_writable(CONFIG_DIR)) {
            @chmod(CONFIG_DIR, 0777);
            clearstatcache();
        }

        if (!is_writable(CONFIG_DIR)) {
            $fallbackDir = ROOT_PATH . '/public/tmp_config';
            $configDirUsed = $fallbackDir;
            $useFallback = true;

            if (!is_dir($fallbackDir)) {
                if (!mkdir($fallbackDir, 0777, true)) {
                    throw new Exception("Impossible de créer config/ et fallback échoué : $fallbackDir");
                }
            }

            $_SESSION['warning'] = "⚠️ Le dossier <code>config/</code> est non inscriptible.<br>Les fichiers ont été placés dans <code>public/tmp_config/</code>.<br>Vous pouvez les déplacer manuellement ou utiliser le bouton ci-dessous.";
            log_install("⚠️ Fallback activé → $fallbackDir");
        }

        $configFile = $configDirUsed . '/db.php';
        $generalFile = $configDirUsed . '/config.php';

        if (file_exists($configFile) && $action === 'keep') {
            $_SESSION['success'] = "✅ Installation terminée (config existante conservée)";
            log_install("Config existante conservée");
        } else {
            $config = configFileContent($host, $name, $user, $pass, $lang);
            if (@file_put_contents($configFile, $config) === false) {
                $err = error_get_last();
                throw new Exception("Impossible d’écrire db.php : " . ($err['message'] ?? 'inconnue'));
            }

            $generalConfig = generalConfigFileContent($lang);
            if (@file_put_contents($generalFile, $generalConfig) === false) {
                $err = error_get_last();
                throw new Exception("Impossible d’écrire config.php : " . ($err['message'] ?? 'inconnue'));
            }

            log_install("✅ Fichiers de configuration écrits dans $configDirUsed");
            $_SESSION['success'] = "✅ Installation réussie. Configuration enregistrée.";
        }

    } catch (Exception $e) {
        $_SESSION['error'] = "❌ " . $e->getMessage();
        log_install("ERREUR : " . $e->getMessage());
    }

    header("Location: install.php");
    exit;
}

// 🔒 Verrouillage manuel
if (isset($_GET['lock'])) {
    file_put_contents(LOCK_FILE, "locked");
    log_install("🔒 Script verrouillé manuellement");
    header("Location: install.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><title>Installation Les Caramagnols</title>
<style>
body{font-family:sans-serif;padding:2em;max-width:600px;margin:auto;}
input,select{width:100%;padding:.5em;margin:.2em 0;}
pre{background:#f4f4f4;padding:1em;overflow:auto;}
</style></head><body>
<h1>🔧 Installation du site Les Caramagnols</h1>

<?php
foreach (['error', 'warning', 'success'] as $type) {
    if (!empty($_SESSION[$type])) {
        $color = $type === 'error' ? 'red' : ($type === 'warning' ? 'orange' : 'green');
        echo "<p style='color:$color'>" . $_SESSION[$type] . "</p>";
        unset($_SESSION[$type]);
    }
}
?>

<form method="post">
    <h3>Paramètres MySQL</h3>
    <label>Hôte MySQL : <input name="db_host" value="localhost" required></label>
    <label>Nom de la base : <input name="db_name" required></label>
    <label>Utilisateur : <input name="db_user" required></label>
    <label>Mot de passe : <input name="db_pass" type="password"></label>

    <h3>Compte administrateur</h3>
    <label>Email : <input name="admin_user" type="email" required></label>
    <label>Mot de passe : <input name="admin_pass" type="password" required></label>

    <h3>Langue du site</h3>
    <select name="lang">
        <option value="fr">Français</option>
        <option value="en">English</option>
    </select>

    <?php if (file_exists(CONFIG_FILE_DEFAULT)): ?>
        <h3>📄 Configuration existante</h3>
        <pre><?php echo htmlspecialchars(file_get_contents(CONFIG_FILE_DEFAULT)); ?></pre>
        <p>
            <label><input type="radio" name="config_action" value="keep" checked> Conserver</label><br>
            <label><input type="radio" name="config_action" value="replace"> Remplacer</label>
        </p>
    <?php endif; ?>

    <br><button type="submit">🚀 Lancer l’installation</button>
</form>

<?php if (!file_exists(LOCK_FILE)): ?>
    <form method="get"><button name="lock" value="1" style="margin-top:2em">🔒 Verrouiller l’installateur</button></form>
<?php else: ?>
    <p>✅ Script verrouillé.</p>
<?php endif; ?>

<?php
$tmpPath = ROOT_PATH . '/public/tmp_config';
if (is_dir($tmpPath) && file_exists("$tmpPath/db.php") && file_exists("$tmpPath/config.php")):
?>
    <form method="post">
        <button type="submit" name="move_tmp_to_config" style="margin-top:2em">
            📤 Copier automatiquement tmp_config → config
        </button>
    </form>
<?php endif; ?>

</body></html>
