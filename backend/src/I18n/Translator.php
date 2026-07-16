<?php

declare(strict_types=1);

namespace Caramagnols\I18n;

class Translator
{
    private array $cache = [];

    public function __construct(private readonly string $langDir, private readonly string $default = 'fr')
    {
    }

    public function resolveFile(string $lang): string
    {
        $file = $this->langDir . '/' . $lang . '.php';

        if (!file_exists($file)) {
            $file = $this->langDir . '/' . $this->default . '.php';
        }

        return $file;
    }

    public function load(string $lang): array
    {
        $file = $this->resolveFile($lang);

        $mtime = @filemtime($file) ?: null;
        $key = $file;

        if (isset($this->cache[$key]) && $this->cache[$key]['mtime'] === $mtime) {
            return $this->cache[$key]['data'];
        }

        $data = require $file;
        $data = is_array($data) ? $data : [];

        if (function_exists('sanitize_translation_array')) {
            $data = sanitize_translation_array($data);
        }

        $this->cache[$key] = [
            'mtime' => $mtime,
            'data' => $data,
        ];

        return $data;
    }

    public function clearCache(?string $lang = null): void
    {
        if ($lang === null) {
            $this->cache = [];
            return;
        }

        $file = $this->resolveFile($lang);
        unset($this->cache[$file]);
    }
}
