<?php
declare(strict_types=1);

namespace InSession\SearchBlocker\Plugin;

use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Search\Api\SearchInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use InSession\SearchBlocker\Model\Config;
use InSession\SearchBlocker\Logger\Logger;

/**
 * Plugin to prevent blacklisted or suspicious search terms in REST API search requests.
 *
 * Intercepts Magento's `SearchInterface::search()` used by endpoints like:
 * `/rest/V1/products?searchCriteria[filter_groups][0][filters][0][field]=search_term`
 *
 * Validates REST input for empty, blacklisted, or SQL-injection-like terms.
 * Logging and enable state are controlled via Admin configuration.
 */
class PreventSearchOnRestApi
{
    /**
     * Central configuration model for SearchBlocker.
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
     * @param Config $config Central configuration handler for SearchBlocker.
     * @param Logger $logger Custom logger for REST API channel.
     */
    public function __construct(
        Config $config,
        Logger $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Around plugin for SearchInterface::search().
     *
     * Validates the `search_term` filter value in REST API search requests
     * before executing the original search logic. Throws HTTP 400 (Bad Request)
     * when the term is invalid, suspicious, or blacklisted.
     *
     * @param SearchInterface         $subject        The original Search API service.
     * @param \Closure                $proceed        Original method callback.
     * @param SearchCriteriaInterface $searchCriteria The search criteria from the REST request.
     *
     * @return mixed
     *
     * @throws WebapiException If the search term is empty, suspicious, or blacklisted.
     */
    public function aroundSearch(
        SearchInterface $subject,
        \Closure $proceed,
        SearchCriteriaInterface $searchCriteria
    ) {
        // Check global and REST-specific enable flags
        if (
            !$this->config->isGlobalEnabled() ||
            !$this->config->isEnabledForChannel('rest')
        ) {
            return $proceed($searchCriteria);
        }

        /** @var string $normalized */
        $normalized = $this->extractSearchTerm($searchCriteria);

        if ($normalized === '') {
            return $proceed($searchCriteria);
        }

        try {

            // SQL injection detection (API-safe regex)
            if (
                $this->config->isRegexFilterEnabled() &&
                preg_match(Config::SQL_INJECTION_PATTERN_API, $normalized)
            ) {
                throw new WebapiException(
                    __('Suspicious search term detected.'),
                    0,
                    WebapiException::HTTP_BAD_REQUEST
                );
            }

            // Blacklist filtering
            foreach ($this->config->getBlacklistedTerms() as $blockedTerm) {
                if ($blockedTerm !== '' && str_contains($normalized, $blockedTerm)) {
                    throw new WebapiException(
                        __('This search term is not allowed.'),
                        0,
                        WebapiException::HTTP_BAD_REQUEST
                    );
                }
            }

            // (Optional) log allowed searches
            if ($this->config->isLoggingEnabled('rest')) {
                $this->logger->info(sprintf('Allowed REST search term: "%s"', $normalized));
            }

            // All good → continue
            return $proceed($searchCriteria);

        } catch (WebapiException $e) {
            // Log blocked term if logging is enabled for REST channel
            if ($this->config->isLoggingEnabled('rest')) {
                $this->logger->warning(sprintf(
                    'Blocked REST search term "%s" (%s)',
                    $normalized,
                    $e->getMessage()
                ));
            }

            throw $e;
        }
    }

    /**
     * Extracts the `search_term` value from the SearchCriteria filter groups.
     *
     * @param SearchCriteriaInterface $searchCriteria The search criteria containing filters.
     * @return string The normalized (lowercase, trimmed) search term, or empty string if not found.
     */
    private function extractSearchTerm(SearchCriteriaInterface $searchCriteria): string
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                if ($filter->getField() === 'search_term') {
                    return trim(mb_strtolower((string)$filter->getValue()));
                }
            }
        }
        return '';
    }
}
