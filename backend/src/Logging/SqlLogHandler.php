<?php

declare(strict_types=1);

namespace Caramagnols\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

final class SqlLogHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly SqlLogStore $store,
        int|string|Level $level = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $context = is_array($record->context) ? $record->context : [];

        $this->store->insert(
            $record->channel,
            strtolower($record->level->getName()),
            $record->message,
            $context,
            $record->datetime
        );
    }
}
