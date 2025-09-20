<?php
/**
 * Cache Provider Interface
 *
 * Defines the contract for cache providers.
 *
 * @package WPGraphQL_Content_Filter
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for cache providers.
 *
 * This interface defines the contract that all cache providers must implement.
 * It allows for different caching backends to be used interchangeably.
 *
 * @since 1.0.0
 */
interface WPGraphQL_Content_Filter_Cache_Provider_Interface {
    /**
     * Get a value from the cache.
     *
     * @param string $key The cache key.
     * @return mixed The cached value or false if not found.
     */
    public function get($key);

    /**
     * Set a value in the cache.
     *
     * @param string $key        The cache key.
     * @param mixed  $value      The value to cache.
     * @param int    $expiration The expiration time in seconds.
     * @return bool True on success, false on failure.
     */
    public function set($key, $value, $expiration = 0);

    /**
     * Delete a value from the cache.
     *
     * @param string $key The cache key.
     * @return bool True on success, false on failure.
     */
    public function delete($key);

    /**
     * Flush all cached values.
     *
     * @return bool True on success, false on failure.
     */
    public function flush();

    /**
     * Check if the cache provider is available.
     *
     * @return bool True if available, false otherwise.
     */
    public function is_available();

    /**
     * Get the name of the cache provider.
     *
     * @return string The provider name.
     */
    public function get_name();

    /**
     * Get multiple values from the cache.
     *
     * @param array $keys Array of cache keys.
     * @return array Array of key => value pairs.
     */
    public function get_multiple($keys);

    /**
     * Set multiple values in the cache.
     *
     * @param array $data        Array of key => value pairs.
     * @param int   $expiration  The expiration time in seconds.
     * @return bool True on success, false on failure.
     */
    public function set_multiple($data, $expiration = 0);
}