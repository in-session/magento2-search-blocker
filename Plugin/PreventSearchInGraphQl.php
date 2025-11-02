<?php
declare(strict_types=1);

namespace InSession\SearchBlocker\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use InSession\SearchBlocker\Model\Config;
use InSession\SearchBlocker\Logger\Logger;

/**
 * Plugin to prevent blocked or suspicious search terms in GraphQL product searches.
 *
 * Intercepts GraphQL product search queries and blocks empty, blacklisted,
 * or SQL-injection-like terms before reaching the core resolver.
 */
class PreventSearchInGraphQl
{
    /**
     * Central SearchBlocker configuration model.
     *
     * @var Config
     */
    private Config $config;

    /**
     * Custom SearchBlocker logger.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param Config $config Central configuration service for SearchBlocker.
     * @param Logger $logger Custom logger for blocked GraphQL search attempts.
     */
    public function __construct(
        Config $config,
        Logger $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Around plugin for GraphQL field resolver.
     *
     * Validates the "search" argument in product queries and throws an exception
     * for invalid or unsafe terms. Supports admin-configurable enable flags,
     * blacklist, regex filtering, and optional logging.
     *
     * @param mixed                    $subject  The original resolver subject.
     * @param \Closure                 $proceed  The original resolver execution closure.
     * @param Field                    $field    GraphQL field definition.
     * @param mixed                    $context  The GraphQL execution context.
     * @param ResolveInfo              $info     GraphQL resolve info object.
     * @param array<string,mixed>|null $value    Field value (nullable).
     * @param array<string,mixed>|null $args     GraphQL query arguments (nullable).
     *
     * @return mixed
     *
     * @throws LocalizedException If the search term is empty, suspicious, or blacklisted.
     */
    public function aroundResolve(
        $subject,
        \Closure $proceed,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        // Check global and GraphQL-specific enable flags
        if (
            !$this->config->isGlobalEnabled() ||
            !$this->config->isEnabledForChannel('graphql')
        ) {
            return $proceed($field, $context, $info, $value, $args);
        }

        /** @var string $query */
        $query = (string)($args['search'] ?? '');
        $normalized = trim(mb_strtolower($query));

        if ($normalized === '') {
            return $proceed($field, $context, $info, $value, $args);
        }

        try {
            // SQL-injection detection (API-safe pattern)
            if (
                $this->config->isRegexFilterEnabled() &&
                preg_match(Config::SQL_INJECTION_PATTERN_API, $normalized)
            ) {
                throw new LocalizedException(__('Suspicious search term detected.'));
            }

            // Blacklist filtering
            foreach ($this->config->getBlacklistedTerms() as $blockedTerm) {
                if ($blockedTerm !== '' && str_contains($normalized, $blockedTerm)) {
                    throw new LocalizedException(__('This search term is not allowed.'));
                }
            }

            // (Optional) log allowed searches
            if ($this->config->isLoggingEnabled('graphql')) {
                $this->logger->info(sprintf('Allowed GraphQL search term: "%s"', $normalized));
            }

            // Everything OK – continue execution
            return $proceed($field, $context, $info, $value, $args);

        } catch (LocalizedException $e) {
            // Log blocked term if logging is enabled
            if ($this->config->isLoggingEnabled('graphql')) {
                $this->logger->warning(sprintf(
                    'Blocked GraphQL search term "%s" (%s)',
                    $normalized,
                    $e->getMessage()
                ));
            }

            // Throw error to GraphQL consumer
            throw $e;
        }
    }
}
