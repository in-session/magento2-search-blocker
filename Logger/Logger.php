<?php
declare(strict_types=1);

namespace InSession\SearchBlocker\Logger;

use Monolog\Logger as MonologLogger;

/**
 * Custom logger for the InSession_SearchBlocker module.
 *
 * Extends Magento’s default Monolog logger to write blocked search events
 * to `var/log/search_blocker.log`, depending on the admin configuration.
 *
 * @see \InSession\SearchBlocker\Logger\Handler
 */
class Logger extends MonologLogger
{
    /**
     * @inheritDoc
     */
    public function __construct(
        string $name = 'search_blocker',
        array $handlers = [],
        array $processors = []
    ) {
        parent::__construct($name, $handlers, $processors);
    }
}
