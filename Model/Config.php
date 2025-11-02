<?php
declare(strict_types=1);

namespace InSession\SearchBlocker\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Central configuration access class for SearchBlocker.
 *
 * Contains XML path constants, SQL injection regex patterns,
 * and convenience methods for checking configuration state
 * per channel (frontend, REST, GraphQL).
 */
class Config
{
    /** Global enable/disable for the entire module */
    public const XML_PATH_ENABLED_GLOBAL = 'insession_searchblocker/general/enabled';

    /** Enable/disable blocking in frontend catalog search controller */
    public const XML_PATH_ENABLED_CONTROLLER = 'insession_searchblocker/general/enable_controller';

    /** Enable/disable blocking in REST API */
    public const XML_PATH_ENABLED_REST = 'insession_searchblocker/general/enable_rest';

    /** Enable/disable blocking in GraphQL */
    public const XML_PATH_ENABLED_GRAPHQL = 'insession_searchblocker/general/enable_graphql';

    /** Comma-separated list of blacklisted search terms */
    public const XML_PATH_TERMS = 'insession_searchblocker/general/terms';

    /** Redirect path for blocked frontend searches */
    public const XML_PATH_REDIRECT = 'insession_searchblocker/general/redirect_path';

    /** Enable or disable SQL regex filter */
    public const XML_PATH_REGEX_FILTER = 'insession_searchblocker/general/enable_regex_filter';

    /** Enable or disable logging */
    public const XML_PATH_ENABLE_LOGGING = 'insession_searchblocker/logging/enable_logging';

    /** Select log channels (controller, rest, graphql) */
    public const XML_PATH_LOG_CHANNELS = 'insession_searchblocker/logging/log_channels';

    /**
     * Strict frontend regex – checks for encoded or literal SQL keywords.
     * Used mainly in frontend controllers where user input is more unpredictable.
     */
    public const SQL_INJECTION_PATTERN_FRONTEND =
        '/(union|select|insert|delete|update|drop|sleep|benchmark|waitfor|concat|information_schema|%27|%22|\'|--|%23|%3B|%3D|\sor\s|\sand\s|%C0%A7|%C0%A2)/i';

    /**
     * Safer API regex – detects SQL keywords as full words to avoid false positives.
     * Used for REST and GraphQL APIs, where input is more structured.
     */
    public const SQL_INJECTION_PATTERN_API =
        '/\b(union|select|insert|delete|update|drop|sleep|benchmark|waitfor|information_schema)\b|(--|#|\/\*|\*\/|;)/i';

    public function __construct(private ScopeConfigInterface $scopeConfig) {}

    /**
     * Checks if the module is enabled globally.
     */
    public function isGlobalEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED_GLOBAL, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Checks if blocking is enabled for a specific channel.
     *
     * @param string $channel One of: "controller", "rest", "graphql"
     */
    public function isEnabledForChannel(string $channel): bool
    {
        $path = match ($channel) {
            'controller' => self::XML_PATH_ENABLED_CONTROLLER,
            'rest'       => self::XML_PATH_ENABLED_REST,
            'graphql'    => self::XML_PATH_ENABLED_GRAPHQL,
            default      => null,
        };

        return $path
            ? $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE)
            : false;
    }

    /**
     * Checks if SQL regex filtering is enabled.
     */
    public function isRegexFilterEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_REGEX_FILTER, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Returns the blacklist as a lowercase array.
     */
    public function getBlacklistedTerms(): array
    {
        $terms = (string)$this->scopeConfig->getValue(self::XML_PATH_TERMS, ScopeInterface::SCOPE_STORE);
        return array_filter(array_map('trim', explode(',', mb_strtolower($terms))));
    }

    /**
     * Returns the configured redirect path or null.
     */
    public function getRedirectPath(): ?string
    {
        $path = (string)$this->scopeConfig->getValue(self::XML_PATH_REDIRECT, ScopeInterface::SCOPE_STORE);
        return $path !== '' ? $path : null;
    }

    /**
     * Checks whether logging is enabled globally or for a specific channel.
     */
    public function isLoggingEnabled(?string $channel = null): bool
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLE_LOGGING, ScopeInterface::SCOPE_STORE)) {
            return false;
        }

        if ($channel === null) {
            return true;
        }

        $channels = (string)$this->scopeConfig->getValue(self::XML_PATH_LOG_CHANNELS, ScopeInterface::SCOPE_STORE);
        $list = array_map('trim', explode(',', strtolower($channels)));

        return in_array(strtolower($channel), $list, true);
    }
}
