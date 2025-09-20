<?php
/**
 * Content Filter Strategy Interface
 *
 * Defines the contract for content filtering strategies.
 *
 * @package WPGraphQL_Content_Filter
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for content filtering strategies.
 *
 * This interface defines the contract that all content filtering strategies must implement.
 * It allows for different filtering approaches to be used interchangeably.
 *
 * @since 1.0.0
 */
interface WPGraphQL_Content_Filter_Interface {
    /**
     * Apply the content filter strategy.
     *
     * @param string $content The content to filter.
     * @param array  $options The filtering options.
     * @return string The filtered content.
     */
    public function apply($content, $options);

    /**
     * Get the name of the filter strategy.
     *
     * @return string The strategy name.
     */
    public function get_name();

    /**
     * Check if the strategy is available/supported.
     *
     * @return bool True if available, false otherwise.
     */
    public function is_available();

    /**
     * Get the priority for this filter strategy.
     *
     * @return int The priority (lower numbers = higher priority).
     */
    public function get_priority();
}