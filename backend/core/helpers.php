<?php

/**
 * Charge le manifest Vite une seule fois par requete.
 */
function load_vite_manifest(): ?array {
    static $cache = null;
    static $cachedMtime = null;

    $manifestPath = ROOT_PATH . '/public/.vite/manifest.json';
    if (!file_exists($manifestPath)) {
        return null;
    }

    $mtime = @filemtime($manifestPath) ?: null;
    if ($cache !== null && $cachedMtime === $mtime) {
        return $cache;
    }

    $json = file_get_contents($manifestPath);
    $decoded = json_decode($json, true);
    $cache = is_array($decoded) ? $decoded : [];
    $cachedMtime = $mtime;

    return $cache;
}

function vite_asset($entry) {
    $manifest = load_vite_manifest();
    if (!$manifest || !isset($manifest[$entry]['file'])) {
        return '/assets/' . $entry; // fallback
    }

    return '/assets/' . $manifest[$entry]['file'];
}

function vite_css($entry) {
    $manifest = load_vite_manifest();
    if (!$manifest || !isset($manifest[$entry]['css'])) {
        return [];
    }

    return $manifest[$entry]['css'];
}
