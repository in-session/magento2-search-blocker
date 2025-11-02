<?php
declare(strict_types=1);

namespace InSession\SearchBlocker\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\CatalogSearch\Controller\Result\Index as SearchController;
use InSession\SearchBlocker\Model\Config;
use InSession\SearchBlocker\Logger\Logger;

/**
 * Plugin to prevent forbidden or suspicious search terms
 * in the Magento catalog search controller.
 *
 * Intercepts the default catalog search and validates the "q" parameter
 * before allowing execution. If the term is blacklisted, empty, or contains
 * SQL-like patterns, the user is redirected safely.
 */
class PreventSearchOnController
{
    /**
     * HTTP request object for accessing query parameters.
     *
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * Central configuration model for SearchBlocker.
     *
     * @var Config
     */
    private Config $config;

    /**
     * Message manager to show user-friendly messages in the frontend.
     *
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * Factory to create redirect results.
     *
     * @var RedirectFactory
     */
    private RedirectFactory $redirectFactory;

    /**
     * Custom SearchBlocker logger.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param RequestInterface  $request         Current HTTP request.
     * @param Config            $config          Module configuration model.
     * @param ManagerInterface  $messageManager  Magento message manager.
     * @param RedirectFactory   $redirectFactory Redirect response factory.
     * @param Logger            $logger          Custom SearchBlocker logger.
     */
    public function __construct(
        RequestInterface $request,
        Config $config,
        ManagerInterface $messageManager,
        RedirectFactory $redirectFactory,
        Logger $logger
    ) {
        $this->request = $request;
        $this->config = $config;
        $this->messageManager = $messageManager;
        $this->redirectFactory = $redirectFactory;
        $this->logger = $logger;
    }

    /**
     * Around plugin for \Magento\CatalogSearch\Controller\Result\Index::execute().
     *
     * Validates the search query before running the original logic.
     * Redirects the user if a forbidden or suspicious term is detected.
     *
     * @param SearchController $subject The original controller instance.
     * @param \Closure         $proceed Callback to the original method.
     *
     * @return Redirect|\Magento\Framework\Controller\ResultInterface
     * @throws LocalizedException
     */
    public function aroundExecute(SearchController $subject, \Closure $proceed)
    {
        if (
            !$this->config->isGlobalEnabled() ||
            !$this->config->isEnabledForChannel('controller')
        ) {
            return $proceed();
        }

        /** @var string $query */
        $query = (string)$this->request->getParam('q', '');
        $normalized = trim(mb_strtolower($query));

        try {
            // Empty term
            if ($normalized === '') {
                return $proceed();
            }

            // SQL injection detection
            if (
                $this->config->isRegexFilterEnabled() &&
                preg_match(Config::SQL_INJECTION_PATTERN_FRONTEND, $normalized)
            ) {
                throw new LocalizedException(__('Suspicious search term detected.'));
            }

            // Blacklist validation
            foreach ($this->config->getBlacklistedTerms() as $blockedTerm) {
                if ($blockedTerm !== '' && str_contains($normalized, $blockedTerm)) {
                    throw new LocalizedException(__('This search term is not allowed.'));
                }
            }

            // (Optional) log allowed searches at info level
            if ($this->config->isLoggingEnabled('controller')) {
                $this->logger->info(sprintf('Allowed search term: "%s"', $normalized));
            }

            return $proceed();

        } catch (LocalizedException $e) {
            // Log blocked term if logging enabled for this channel
            if ($this->config->isLoggingEnabled('controller')) {
                $this->logger->warning(sprintf(
                    'Blocked frontend search term "%s" (%s)',
                    $normalized,
                    $e->getMessage()
                ));
            }

            return $this->redirectWithMessage($e->getMessage());
        }
    }

    /**
     * Creates a redirect response and displays an error message.
     *
     * @param string|\Magento\Framework\Phrase $message Error message to display.
     * @return Redirect Redirect response to a safe page.
     */
    private function redirectWithMessage(string|\Magento\Framework\Phrase $message): Redirect
    {
        $this->messageManager->addErrorMessage($message);

        $redirectPath = $this->config->getRedirectPath();
        $resultRedirect = $this->redirectFactory->create();

        if ($redirectPath) {
            $resultRedirect->setUrl($redirectPath);
        } else {
            $resultRedirect->setPath('noroute');
        }

        return $resultRedirect;
    }
}
