<?php
/**
 * WPGraphQL Content Filter Core Orchestrator
 *
 * Coordinates all modules and manages the plugin lifecycle.
 *
 * @package WPGraphQL_Content_Filter
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WPGraphQL_Content_Filter_Core
 *
 * Main plugin orchestrator that coordinates all modules, handles initialization,
 * and manages the plugin lifecycle with optimal performance.
 *
 * @since 1.0.0
 */
class WPGraphQL_Content_Filter_Core {
    /**
     * Plugin version.
     *
     * @var string
     */
    private $version = '2.0.0';

    /**
     * Plugin instance (singleton).
     *
     * @var WPGraphQL_Content_Filter_Core|null
     */
    private static $instance = null;

    /**
     * Options manager instance.
     *
     * @var WPGraphQL_Content_Filter_Options|null
     */
    private $options_manager = null;

    /**
     * Cache manager instance.
     *
     * @var WPGraphQL_Content_Filter_Cache|null
     */
    private $cache_manager = null;

    /**
     * Content processor instance.
     *
     * @var WPGraphQL_Content_Filter_Content_Processor|null
     */
    private $content_processor = null;

    /**
     * GraphQL hooks manager instance.
     *
     * @var WPGraphQL_Content_Filter_GraphQL_Hooks|null
     */
    private $graphql_hooks = null;

    /**
     * REST API hooks manager instance.
     *
     * @var WPGraphQL_Content_Filter_REST_Hooks|null
     */
    private $rest_hooks = null;

    /**
     * Admin interface instance.
     *
     * @var WPGraphQL_Content_Filter_Admin|null
     */
    private $admin = null;

    /**
     * Plugin initialized flag.
     *
     * @var bool
     */
    private $initialized = false;

    /**
     * Performance monitoring data.
     *
     * @var array
     */
    private $performance_data = [
        'init_time' => 0,
        'memory_usage' => 0,
        'hooks_registered' => 0,
        'modules_loaded' => 0
    ];

    /**
     * Get singleton instance.
     *
     * @return WPGraphQL_Content_Filter_Core Plugin instance.
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        $this->performance_data['init_time'] = microtime(true);
        $this->performance_data['memory_usage'] = memory_get_usage(true);
    }

    /**
     * Initialize the plugin with memory protection.
     *
     * @return void
     */
    public function init() {
        try {
            // Load plugin dependencies with memory protection
            $this->load_dependencies();
            
            // Initialize core components with full functionality
            $this->initialize_hooks_safely();
            
        } catch (Exception $e) {
            error_log("WPGraphQL Content Filter: Error during initialization - " . $e->getMessage());
        }
    }    /**
     * Load plugin dependencies with memory protection.
     *
     * @return void
     */
    private function load_dependencies() {
        $includes_path = plugin_dir_path(__FILE__);

        error_log("WPGraphQL Content Filter: Starting selective dependency loading");

        // Load only the most essential classes
        require_once $includes_path . 'interfaces/interface-content-filter.php';
        error_log("WPGraphQL Content Filter: Loaded content filter interface");
        
        require_once $includes_path . 'class-wpgraphql-content-filter-options.php';
        error_log("WPGraphQL Content Filter: Loaded options class");
        
        require_once $includes_path . 'class-wpgraphql-content-filter-content-processor.php';
        error_log("WPGraphQL Content Filter: Loaded content processor class");
        
        // Load ALL classes including cache
        if ($this->is_object_cache_available()) {
            require_once $includes_path . 'interfaces/interface-cache-provider.php';
            require_once $includes_path . 'class-wpgraphql-content-filter-cache.php';
            error_log("WPGraphQL Content Filter: Cache classes loaded");
        }
        
        // Test loading BOTH GraphQL and REST hooks classes together
        if (class_exists('WPGraphQL')) {
            error_log("WPGraphQL Content Filter: Loading GraphQL hooks with interface...");
            require_once $includes_path . 'interfaces/interface-hook-manager.php';
            require_once $includes_path . 'class-wpgraphql-content-filter-graphql-hooks.php';
            error_log("WPGraphQL Content Filter: GraphQL hooks class loaded");
        }
        
        // Load REST hooks only if REST API filtering is enabled
        $options = get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, []);
        if (!empty($options['apply_to_rest_api'])) {
            error_log("WPGraphQL Content Filter: Loading REST hooks class...");
            require_once $includes_path . 'class-wpgraphql-content-filter-rest-hooks.php';
            error_log("WPGraphQL Content Filter: REST hooks class loaded");
        } else {
            error_log("WPGraphQL Content Filter: REST API filtering disabled, skipping REST hooks");
        }
        
        // Always load admin interface (it will self-check admin context)
        require_once $includes_path . 'class-wpgraphql-content-filter-admin.php';
        error_log("WPGraphQL Content Filter: Admin class loaded");
        
        error_log("WPGraphQL Content Filter: Selective dependency loading complete");
    }
    
    /**
     * Safely initialize hooks with full functionality.
     */
    private function initialize_hooks_safely() {
        error_log("WPGraphQL Content Filter: Starting full hooks initialization...");
        
        try {
            // Initialize content processor (required)
            $this->content_processor = new WPGraphQL_Content_Filter_Content_Processor();
            error_log("WPGraphQL Content Filter: Content processor initialized");

            // Initialize cache manager if available
            $cache_manager = null;
            if (class_exists('WPGraphQL_Content_Filter_Cache')) {
                $cache_manager = new WPGraphQL_Content_Filter_Cache();
                $this->cache_manager = $cache_manager;
                error_log("WPGraphQL Content Filter: Cache manager initialized");
            }

            // Initialize GraphQL hooks if available
            if (class_exists('WPGraphQL') && class_exists('WPGraphQL_Content_Filter_GraphQL_Hooks')) {
                error_log("WPGraphQL Content Filter: Initializing GraphQL hooks with cache...");
                $this->graphql_hooks = new WPGraphQL_Content_Filter_GraphQL_Hooks(
                    $this->content_processor,
                    $cache_manager
                );
                error_log("WPGraphQL Content Filter: GraphQL hooks initialized successfully");
            }

            // Initialize REST hooks if available and enabled
            $options = get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, []);
            if (!empty($options['apply_to_rest_api']) && class_exists('WPGraphQL_Content_Filter_REST_Hooks')) {
                error_log("WPGraphQL Content Filter: Initializing REST hooks with cache...");
                $this->rest_hooks = new WPGraphQL_Content_Filter_REST_Hooks(
                    $this->content_processor,
                    $cache_manager
                );
                error_log("WPGraphQL Content Filter: REST hooks initialized successfully");
            }

            // Initialize full admin interface (matches original screenshot)
            if (class_exists('WPGraphQL_Content_Filter_Admin')) {
                error_log("WPGraphQL Content Filter: Initializing full admin interface...");
                $this->admin = new WPGraphQL_Content_Filter_Admin(
                    $cache_manager,
                    $this->content_processor
                );
                // Initialize admin hooks and menu
                $this->admin->init();
                error_log("WPGraphQL Content Filter: Full admin interface initialized");
            }
            
            error_log("WPGraphQL Content Filter: All components initialized successfully");
        } catch (Exception $e) {
            error_log("WPGraphQL Content Filter: Error in full initialization - " . $e->getMessage());
        }
    }

    /**
     * Initialize core modules with minimal memory footprint.
     *
     * @return void
     */
    private function init_modules() {
        // Initialize content processor (required)
        $this->content_processor = new WPGraphQL_Content_Filter_Content_Processor();
        $this->performance_data['modules_loaded']++;

        // Initialize cache manager only if object cache is available and cache class is loaded
        $cache_manager = null;
        if (class_exists('WPGraphQL_Content_Filter_Cache')) {
            $cache_manager = new WPGraphQL_Content_Filter_Cache();
            $this->cache_manager = $cache_manager;
        }

        // Initialize GraphQL hooks only if WPGraphQL is available
        if (class_exists('WPGraphQL') && class_exists('WPGraphQL_Content_Filter_GraphQL_Hooks')) {
            $this->graphql_hooks = new WPGraphQL_Content_Filter_GraphQL_Hooks(
                $this->content_processor,
                $cache_manager
            );
            $this->performance_data['modules_loaded']++;
        }

        // Initialize REST hooks only if needed
        $options = get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, []);
        if (!empty($options['apply_to_rest_api']) && class_exists('WPGraphQL_Content_Filter_REST_Hooks')) {
            $this->rest_hooks = new WPGraphQL_Content_Filter_REST_Hooks(
                $this->content_processor,
                $cache_manager
            );
            $this->performance_data['modules_loaded']++;
        }

        // Initialize admin interface if in admin context
        if (is_admin()) {
            $this->admin = new WPGraphQL_Content_Filter_Admin(
                $this->cache_manager,
                $this->content_processor
            );
            $this->performance_data['modules_loaded']++;
        }
    }

    /**
     * Register core hooks.
     *
     * @return void
     */
    private function register_hooks() {
        // Plugin lifecycle hooks
        add_action('plugins_loaded', [$this, 'on_plugins_loaded'], 10);
        add_action('init', [$this, 'on_init'], 15);
        
        // Cache invalidation hooks
        add_action('save_post', [$this, 'on_post_updated'], 10, 1);
        add_action('delete_post', [$this, 'on_post_deleted'], 10, 1);
        add_action('update_option_' . WPGRAPHQL_CONTENT_FILTER_OPTIONS, [$this, 'on_options_updated'], 10, 2);
        
        if (is_multisite()) {
            add_action('update_site_option_' . WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS, [$this, 'on_network_options_updated'], 10, 2);
        }

        $this->performance_data['hooks_registered'] = 6;
    }

    /**
     * Set up lazy loading for context-specific hooks.
     *
     * @return void
     */
    private function setup_lazy_loading() {
        // GraphQL hooks - load only when needed
        add_action('graphql_init', function() {
            if ($this->graphql_hooks) {
                $this->graphql_hooks->maybe_register_hooks();
            }
        }, 5);

        // REST API hooks - load only when needed
        add_action('rest_api_init', function() {
            if ($this->rest_hooks) {
                $this->rest_hooks->maybe_register_hooks();
            }
        }, 5);

        // Admin hooks - already conditionally loaded
        if ($this->admin) {
            add_action('admin_init', [$this->admin, 'init'], 10);
        }
    }

    /**
     * Handle plugins loaded event.
     *
     * @return void
     */
    public function on_plugins_loaded() {
        // Check for required dependencies
        $this->check_dependencies();

        // Warm up essential caches if enabled
        $options = WPGraphQL_Content_Filter_Options::get_effective_options();
        if (!empty($options['auto_warm_cache'])) {
            add_action('wp_loaded', [$this, 'warm_essential_cache'], 20);
        }
    }

    /**
     * Handle init event.
     *
     * @return void
     */
    public function on_init() {
        // Load text domain
        load_plugin_textdomain(
            'wpgraphql-content-filter',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );

        // Register any additional hooks that need to run after init
        $this->register_late_hooks();
    }

    /**
     * Handle post updated event.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function on_post_updated($post_id) {
        if ($this->cache_manager) {
            $this->cache_manager->clear_post_cache($post_id);
        }
    }

    /**
     * Handle post deleted event.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function on_post_deleted($post_id) {
        if ($this->cache_manager) {
            $this->cache_manager->clear_post_cache($post_id);
        }
    }

    /**
     * Handle options updated event.
     *
     * @param mixed $old_value Old option value.
     * @param mixed $new_value New option value.
     * @return void
     */
    public function on_options_updated($old_value, $new_value) {
        // Invalidate options cache
        WPGraphQL_Content_Filter_Options::invalidate_cache('current');
        
        // Clear all content cache since filtering behavior may have changed
        if ($this->cache_manager) {
            $this->cache_manager->flush();
        }
    }

    /**
     * Handle network options updated event.
     *
     * @param mixed $old_value Old option value.
     * @param mixed $new_value New option value.
     * @return void
     */
    public function on_network_options_updated($old_value, $new_value) {
        // Invalidate network options cache
        WPGraphQL_Content_Filter_Options::invalidate_cache('network');
        
        // Clear all content cache since filtering behavior may have changed
        if ($this->cache_manager) {
            $this->cache_manager->flush();
        }
    }

    /**
     * Check for required dependencies.
     *
     * @return void
     */
    private function check_dependencies() {
        $missing_dependencies = [];

        // Check for WPGraphQL if GraphQL features are enabled
        $options = WPGraphQL_Content_Filter_Options::get_effective_options();
        if (!empty($options['enable_graphql']) && !class_exists('WPGraphQL')) {
            $missing_dependencies[] = 'WPGraphQL';
        }

        // Display admin notices for missing dependencies
        if (!empty($missing_dependencies) && is_admin()) {
            add_action('admin_notices', function() use ($missing_dependencies) {
                $message = sprintf(
                    __('WPGraphQL Content Filter requires the following plugins: %s', 'wpgraphql-content-filter'),
                    implode(', ', $missing_dependencies)
                );
                printf('<div class="notice notice-warning"><p>%s</p></div>', esc_html($message));
            });
        }
    }

    /**
     * Warm essential cache entries.
     *
     * @return void
     */
    public function warm_essential_cache() {
        if (!$this->cache_manager) {
            return;
        }

        // Warm cache for recent posts (limited to prevent performance issues)
        $recent_posts = get_posts([
            'numberposts' => 10,
            'post_status' => 'publish',
            'fields' => 'ids'
        ]);

        if (!empty($recent_posts)) {
            $this->cache_manager->warm_cache($recent_posts);
        }
    }

    /**
     * Register hooks that need to run after init.
     *
     * @return void
     */
    private function register_late_hooks() {
        // Add any late hooks here
    }

    /**
     * Get plugin version.
     *
     * @return string Plugin version.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get performance data.
     *
     * @return array Performance statistics.
     */
    public function get_performance_data() {
        return $this->performance_data;
    }

    /**
     * Get module instance.
     *
     * @param string $module Module name.
     * @return object|null Module instance or null if not found.
     */
    public function get_module($module) {
        switch ($module) {
            case 'options':
                return $this->options_manager;
            case 'cache':
                return $this->cache_manager;
            case 'processor':
                return $this->content_processor;
            case 'graphql':
                return $this->graphql_hooks;
            case 'rest':
                return $this->rest_hooks;
            case 'admin':
                return $this->admin;
            default:
                return null;
        }
    }

    /**
     * Check if plugin is fully initialized.
     *
     * @return bool True if initialized, false otherwise.
     */
    public function is_initialized() {
        return $this->initialized;
    }

    /**
     * Deactivate the plugin and clean up.
     *
     * @return void
     */
    public function deactivate() {
        // Unregister all hooks
        if ($this->graphql_hooks) {
            $this->graphql_hooks->unregister_hooks();
        }

        if ($this->rest_hooks) {
            $this->rest_hooks->unregister_hooks();
        }

        // Clear all caches
        if ($this->cache_manager) {
            $this->cache_manager->flush();
        }

        // Reset options cache
        WPGraphQL_Content_Filter_Options::reset_caches();
    }

    /**
     * Get comprehensive plugin statistics for debugging/monitoring.
     *
     * @return array Complete plugin statistics.
     */
    public function get_stats() {
        $stats = [
            'plugin' => [
                'version' => $this->version,
                'initialized' => $this->initialized,
                'performance' => $this->performance_data
            ]
        ];

        if ($this->cache_manager) {
            $stats['cache'] = $this->cache_manager->get_stats();
        }

        if ($this->content_processor) {
            $stats['processor'] = $this->content_processor->get_stats();
        }

        $stats['options'] = WPGraphQL_Content_Filter_Options::get_cache_stats();
        $stats['system'] = [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION
        ];

        return $stats;
    }

    /**
     * Prevent cloning of the instance.
     *
     * @return void
     */
    private function __clone() {}

    /**
     * Check if object cache is available and functional.
     *
     * @return bool True if object cache is available, false otherwise.
     */
    private function is_object_cache_available() {
        // For now, disable cache loading entirely to prevent memory issues
        // Cache can be re-enabled once object cache plugins are confirmed working
        return false;
    }

    /**
     * Prevent unserialization of the instance.
     *
     * @return void
     */
    public function __wakeup() {}
}