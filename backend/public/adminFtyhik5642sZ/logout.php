<?php
$target = '../site/adminFtyhik5642sZ/logout.php';

if (!headers_sent()) {
    header('Location: ' . $target, true, 302);
}

echo '<!DOCTYPE html><meta charset="utf-8" /><title>Redirection</title>';
echo '<p>Redirection vers <a href="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '</a>.</p>';
