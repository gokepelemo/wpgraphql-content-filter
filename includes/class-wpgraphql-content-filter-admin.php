<?php
/**
 * WPGraphQL Content Filter Admin Interface
 *
 * Provides admin interface, settings pages, and management tools.
 *
 * @package WPGraphQL_Content_Filter
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WPGraphQL_Content_Filter_Admin
 *
 * Handles all admin interface functionality including settings pages,
 * diagnostics, cache management, and performance monitoring.
 *
 * @since 1.0.0
 */
class WPGraphQL_Content_Filter_Admin {
    /**
     * Cache manager instance.
     *
     * @var WPGraphQL_Content_Filter_Cache
     */
    private $cache_manager;

    /**
     * Content processor instance.
     *
     * @var WPGraphQL_Content_Filter_Content_Processor
     */
    private $content_processor;

    /**
     * Constructor.
     *
     * @param WPGraphQL_Content_Filter_Cache            $cache_manager    Cache manager instance.
     * @param WPGraphQL_Content_Filter_Content_Processor $content_processor Content processor instance.
     */
    public function __construct($cache_manager, $content_processor) {
        $this->cache_manager = $cache_manager;
        $this->content_processor = $content_processor;
    }

    /**
     * Initialize admin interface.
     *
     * @return void
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_wpgraphql_content_filter_cache_action', [$this, 'handle_cache_ajax']);
        add_action('wp_ajax_wpgraphql_content_filter_diagnostics', [$this, 'handle_diagnostics_ajax']);
    }

    /**
     * Add admin menu pages.
     *
     * @return void
     */
    public function add_admin_menu() {
        add_options_page(
            __('WPGraphQL Content Filter', 'wpgraphql-content-filter'),
            __('Content Filter', 'wpgraphql-content-filter'),
            'manage_options',
            'wpgraphql-content-filter',
            [$this, 'render_settings_page']
        );

        add_management_page(
            __('Content Filter Diagnostics', 'wpgraphql-content-filter'),
            __('Content Filter', 'wpgraphql-content-filter'),
            'manage_options',
            'wpgraphql-content-filter-diagnostics',
            [$this, 'render_diagnostics_page']
        );
    }

    /**
     * Register plugin settings.
     *
     * @return void
     */
    public function register_settings() {
        register_setting('wpgraphql_content_filter_options', WPGRAPHQL_CONTENT_FILTER_OPTIONS);
        
        // General Settings Section
        add_settings_section(
            'general_settings',
            __('General Settings', 'wpgraphql-content-filter'),
            [$this, 'render_general_settings_section'],
            'wpgraphql-content-filter'
        );

        // Cache Settings Section
        add_settings_section(
            'cache_settings',
            __('Cache Settings', 'wpgraphql-content-filter'),
            [$this, 'render_cache_settings_section'],
            'wpgraphql-content-filter'
        );

        // Performance Settings Section
        add_settings_section(
            'performance_settings',
            __('Performance Settings', 'wpgraphql-content-filter'),
            [$this, 'render_performance_settings_section'],
            'wpgraphql-content-filter'
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     * @return void
     */
    public function enqueue_admin_assets($hook_suffix) {
        if (strpos($hook_suffix, 'wpgraphql-content-filter') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        
        // Add inline styles for better UI
        wp_add_inline_style('wp-admin', '
            .wpgraphql-content-filter-stats { 
                background: #f9f9f9; 
                padding: 15px; 
                border-left: 4px solid #0073aa; 
                margin: 20px 0; 
            }
            .wpgraphql-content-filter-cache-actions {
                margin: 20px 0;
            }
            .wpgraphql-content-filter-cache-actions .button {
                margin-right: 10px;
            }
        ');
    }

    /**
     * Render main settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wpgraphql_content_filter_options');
                do_settings_sections('wpgraphql-content-filter');
                submit_button();
                ?>
            </form>

            <div class="wpgraphql-content-filter-cache-actions">
                <h2><?php _e('Cache Management', 'wpgraphql-content-filter'); ?></h2>
                <button type="button" class="button" id="clear-cache">
                    <?php _e('Clear All Cache', 'wpgraphql-content-filter'); ?>
                </button>
                <button type="button" class="button" id="warm-cache">
                    <?php _e('Warm Cache', 'wpgraphql-content-filter'); ?>
                </button>
                <div id="cache-status"></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#clear-cache').on('click', function() {
                $.post(ajaxurl, {
                    action: 'wpgraphql_content_filter_cache_action',
                    cache_action: 'clear',
                    nonce: '<?php echo wp_create_nonce('wpgraphql_content_filter_cache'); ?>'
                }, function(response) {
                    $('#cache-status').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                });
            });

            $('#warm-cache').on('click', function() {
                $.post(ajaxurl, {
                    action: 'wpgraphql_content_filter_cache_action',
                    cache_action: 'warm',
                    nonce: '<?php echo wp_create_nonce('wpgraphql_content_filter_cache'); ?>'
                }, function(response) {
                    $('#cache-status').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render diagnostics page.
     *
     * @return void
     */
    public function render_diagnostics_page() {
        $core = WPGraphQL_Content_Filter_Core::get_instance();
        $stats = $core->get_stats();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="wpgraphql-content-filter-stats">
                <h2><?php _e('Plugin Statistics', 'wpgraphql-content-filter'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Metric', 'wpgraphql-content-filter'); ?></th>
                            <th><?php _e('Value', 'wpgraphql-content-filter'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php _e('Plugin Version', 'wpgraphql-content-filter'); ?></td>
                            <td><?php echo esc_html($stats['plugin']['version']); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Initialization Time', 'wpgraphql-content-filter'); ?></td>
                            <td><?php echo esc_html(number_format($stats['plugin']['performance']['init_time'] * 1000, 2)); ?> ms</td>
                        </tr>
                        <tr>
                            <td><?php _e('Memory Usage', 'wpgraphql-content-filter'); ?></td>
                            <td><?php echo esc_html(size_format($stats['plugin']['performance']['memory_usage'])); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Modules Loaded', 'wpgraphql-content-filter'); ?></td>
                            <td><?php echo esc_html($stats['plugin']['performance']['modules_loaded']); ?></td>
                        </tr>
                        <?php if (isset($stats['cache'])): ?>
                        <tr>
                            <td><?php _e('Cache Hit Rate', 'wpgraphql-content-filter'); ?></td>
                            <td><?php echo esc_html(number_format($stats['cache']['hit_rate'] * 100, 1)); ?>%</td>
                        </tr>
                        <tr>
                            <td><?php _e('Cache Size', 'wpgraphql-content-filter'); ?></td>
                            <td><?php echo esc_html($stats['cache']['size']); ?> items</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <button type="button" class="button" id="refresh-diagnostics">
                <?php _e('Refresh Diagnostics', 'wpgraphql-content-filter'); ?>
            </button>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#refresh-diagnostics').on('click', function() {
                location.reload();
            });
        });
        </script>
        <?php
    }

    /**
     * Render general settings section.
     *
     * @return void
     */
    public function render_general_settings_section() {
        echo '<p>' . __('Configure general content filtering options.', 'wpgraphql-content-filter') . '</p>';
    }

    /**
     * Render cache settings section.
     *
     * @return void
     */
    public function render_cache_settings_section() {
        echo '<p>' . __('Configure cache behavior and performance settings.', 'wpgraphql-content-filter') . '</p>';
    }

    /**
     * Render performance settings section.
     *
     * @return void
     */
    public function render_performance_settings_section() {
        echo '<p>' . __('Configure performance optimization settings.', 'wpgraphql-content-filter') . '</p>';
    }

    /**
     * Handle cache AJAX actions.
     *
     * @return void
     */
    public function handle_cache_ajax() {
        if (!check_ajax_referer('wpgraphql_content_filter_cache', 'nonce', false)) {
            wp_die(__('Security check failed.', 'wpgraphql-content-filter'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'wpgraphql-content-filter'));
        }

        $action = sanitize_text_field($_POST['cache_action']);

        switch ($action) {
            case 'clear':
                $this->cache_manager->flush();
                wp_send_json_success(__('Cache cleared successfully.', 'wpgraphql-content-filter'));
                break;

            case 'warm':
                // Warm cache for recent posts
                $recent_posts = get_posts([
                    'numberposts' => 20,
                    'post_status' => 'publish',
                    'fields' => 'ids'
                ]);
                
                if (!empty($recent_posts)) {
                    $this->cache_manager->warm_cache($recent_posts);
                }
                
                wp_send_json_success(__('Cache warmed successfully.', 'wpgraphql-content-filter'));
                break;

            default:
                wp_send_json_error(__('Invalid cache action.', 'wpgraphql-content-filter'));
        }
    }

    /**
     * Handle diagnostics AJAX actions.
     *
     * @return void
     */
    public function handle_diagnostics_ajax() {
        if (!check_ajax_referer('wpgraphql_content_filter_diagnostics', 'nonce', false)) {
            wp_die(__('Security check failed.', 'wpgraphql-content-filter'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'wpgraphql-content-filter'));
        }

        $core = WPGraphQL_Content_Filter_Core::get_instance();
        $stats = $core->get_stats();

        wp_send_json_success($stats);
    }
}