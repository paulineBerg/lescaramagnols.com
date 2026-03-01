<?php
// backend/core/menu_loader.php
// Charge les menus depuis backend/data/menus.json si présent,
// sinon fallback sur config/menu_data.php (legacy).

declare(strict_types=1);

function load_menus(): array
{
    $jsonPath = ROOT_PATH . '/data/menus.json';
    if (file_exists($jsonPath) && is_readable($jsonPath)) {
        $raw = file_get_contents($jsonPath);
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
    }

    // Fallback legacy
    $configPath = ROOT_PATH . '/config/menu_data.php';
    if (file_exists($configPath)) {
        $data = require $configPath;
        return is_array($data) ? $data : [];
    }

    return [
        'menu1' => [],
        'banniere' => [],
        'menu2' => [],
        'menu3' => [],
        'menuDroit' => [],
        'menuGauche' => [],
    ];
}

function save_menus(array $menus): bool
{
    $jsonPath = ROOT_PATH . '/data/menus.json';
    $dir = dirname($jsonPath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
    }

    $json = json_encode($menus, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    $backupPath = $jsonPath . '.bak';
    if (file_exists($jsonPath)) {
        @copy($jsonPath, $backupPath);
    }

    return (bool) file_put_contents($jsonPath, $json);
}
