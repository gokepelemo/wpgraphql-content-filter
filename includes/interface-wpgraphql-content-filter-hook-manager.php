<?php
/**
 * Hook Manager Interface for WPGraphQL Content Filter
 *
 * Defines the contract for hook managers in the plugin.
 *
 * @package WPGraphQL_Content_Filter
 * @since 2.1.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface WPGraphQL_Content_Filter_Hook_Manager_Interface
 *
 * Contract for hook manager classes that handle GraphQL and REST API hooks.
 *
 * @since 2.1.0
 */
interface WPGraphQL_Content_Filter_Hook_Manager_Interface {

    /**
     * Register hooks for the specific API type.
     *
     * @return void
     */
    public function register_hooks();

    /**
     * Unregister hooks for the specific API type.
     *
     * @return void
     */
    public function unregister_hooks();

    /**
     * Check if hooks should be loaded based on context.
     *
     * @return bool
     */
    public function should_load();
}