<?php
declare(strict_types=1);

namespace InSession\SearchBlocker\Logger;

use Magento\Framework\Logger\Handler\Base as BaseHandler;
use Monolog\Logger;

/**
 * Log handler for the InSession_SearchBlocker module.
 *
 * Defines where and how the module writes log entries.
 * The handler determines the target file and log level used
 * by the custom SearchBlocker logger.
 *
 * Log file: `var/log/search_blocker.log`
 *
 * @see \InSession\SearchBlocker\Logger\Logger
 */
class Handler extends BaseHandler
{
    /**
     * Log file path relative to the Magento root directory.
     *
     * @var string
     */
    protected $fileName = '/var/log/search_blocker.log';

    /**
     * Minimum logging level for this handler.
     *
     * @var int
     */
    protected $loggerType = Logger::INFO;
}
