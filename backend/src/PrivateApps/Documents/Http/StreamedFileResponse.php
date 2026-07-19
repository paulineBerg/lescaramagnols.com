<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Http;

use Caramagnols\Http\Response;

/**
 * Réponse HTTP qui diffuse un fichier par blocs au lieu de le charger en
 * mémoire. Compatible avec le cycle de vie standard ($response->send()).
 */
final class StreamedFileResponse extends Response
{
    public function __construct(
        private readonly string $absolutePath,
        int $status = 200,
        array $headers = []
    ) {
        parent::__construct($status, $headers, '');
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        $handle = @fopen($this->absolutePath, 'rb');
        if ($handle === false) {
            return;
        }

        while (!feof($handle)) {
            $chunk = fread($handle, 262144);
            if ($chunk === false) {
                break;
            }

            echo $chunk;
        }

        fclose($handle);
    }
}
