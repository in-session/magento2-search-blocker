<?php
declare(strict_types=1);

namespace InSession\SearchBlocker\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Provides selectable logging channels for the SearchBlocker admin configuration.
 *
 * Appears as a multi-select field in system.xml and allows choosing
 * which areas (Frontend, REST, GraphQL) should log blocked searches.
 */
class LogChannels implements OptionSourceInterface
{
    /**
     * Returns the list of available log channels.
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'controller', 'label' => __('Frontend Controller')],
            ['value' => 'rest', 'label' => __('REST API')],
            ['value' => 'graphql', 'label' => __('GraphQL')],
        ];
    }
}
