<?php
declare(strict_types=1);

namespace Caramagnols\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

class LoggerFactory
{
    public function __construct(private readonly string $logDir, private readonly string $env = 'development')
    {
    }

    public function create(string $channel = 'app'): Logger
    {
        $logger = new Logger($channel);

        $level = $this->env === 'production' ? Level::Info : Level::Debug;
        $path = rtrim($this->logDir, '/');
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $logger->pushHandler(new StreamHandler($path . '/' . $channel . '.log', $level));

        return $logger;
    }
}
