<?php
declare(strict_types=1);

namespace Caramagnols\I18n;

use Caramagnols\Security\Cookies;

class Translator
{
    private array $cache = [];

    public function __construct(private readonly string $langDir, private readonly string $default = 'fr')
    {
    }

    public function load(string $lang): array
    {
        $file = $this->langDir . '/' . $lang . '.php';
        if (!file_exists($file)) {
            $file = $this->langDir . '/' . $this->default . '.php';
        }

        $mtime = @filemtime($file) ?: null;
        $key = $file;

        if (isset($this->cache[$key]) && $this->cache[$key]['mtime'] === $mtime) {
            return $this->cache[$key]['data'];
        }

        $data = require $file;
        $data = is_array($data) ? $data : [];

        $this->cache[$key] = [
            'mtime' => $mtime,
            'data' => $data,
        ];

        // Cookie de langue en Lax pour partage front/back sans forcer HttpOnly
        setcookie('lang', $lang, array_merge(Cookies::secureOptions(), [
            'expires' => time() + 365 * 24 * 3600,
            'httponly' => false, // le front peut lire le cookie pour aligner la langue
        ]));

        return $data;
    }
}
