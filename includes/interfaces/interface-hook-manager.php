<?php
/**
 * Hook Manager Interface
 *
 * Defines the contract for hook managers.
 *
 * @package WPGraphQL_Content_Filter
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for hook managers.
 *
 * This interface defines the contract that all hook managers must implement.
 * It allows for different hook registration strategies to be used interchangeably.
 *
 * @since 1.0.0
 */
interface WPGraphQL_Content_Filter_Hook_Manager_Interface {
    /**
     * Register hooks with WordPress.
     *
     * @return void
     */
    public function register_hooks();

    /**
     * Unregister hooks from WordPress.
     *
     * @return void
     */
    public function unregister_hooks();

    /**
     * Check if hooks are registered.
     *
     * @return bool True if registered, false otherwise.
     */
    public function are_hooks_registered();

    /**
     * Get the hook manager name.
     *
     * @return string The manager name.
     */
    public function get_name();

    /**
     * Check if the hook manager should be loaded.
     *
     * @return bool True if should be loaded, false otherwise.
     */
    public function should_load();

    /**
     * Get the priority for hook registration.
     *
     * @return int The priority (lower numbers = higher priority).
     */
    public function get_priority();

    /**
     * Conditionally register hooks based on current context.
     *
     * @return void
     */
    public function maybe_register_hooks();
}