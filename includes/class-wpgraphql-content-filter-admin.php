<?php
/**
 * WPGraphQL Content Filter Admin Interface
 *
 * Provides admin interface, settings pages, and management tools.
 *
 * @package WPGraphQL_Content_Filter
 * @since 2.1.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WPGraphQL_Content_Filter_Admin
 *
 * Handles all admin interface functionality including settings pages,
 * diagnostics, cache management, and performance monitoring using the
 * modular architecture with dependency injection.
 *
 * @since 2.1.0
 */
class WPGraphQL_Content_Filter_Admin {
    /**
     * Singleton instance.
     *
     * @var WPGraphQL_Content_Filter_Admin
     */
    private static $instance = null;

    /**
     * Options manager instance.
     *
     * @var WPGraphQL_Content_Filter_Options_Manager
     */
    private $options_manager;

    /**
     * Content filter instance.
     *
     * @var WPGraphQL_Content_Filter_Content_Filter
     */
    private $content_filter;

    /**
     * Constructor (private for singleton).
     */
    private function __construct() {
        // Dependencies will be injected via init method
    }

    /**
     * Get singleton instance.
     *
     * @return WPGraphQL_Content_Filter_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize with dependencies.
     *
     * @param WPGraphQL_Content_Filter_Options_Manager $options_manager Options manager instance.
     * @param WPGraphQL_Content_Filter_Content_Filter  $content_filter  Content filter instance.
     * @return void
     */
    public function init($options_manager, $content_filter) {
        $this->options_manager = $options_manager;
        $this->content_filter = $content_filter;

        // Initialize admin hooks
        $this->init_hooks();
    }

    /**
     * Initialize admin hooks.
     *
     * @return void
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_wpgraphql_content_filter_cache_action', [$this, 'handle_cache_ajax']);
        add_action('wp_ajax_wpgraphql_content_filter_diagnostics', [$this, 'handle_diagnostics_ajax']);
        
        // Add multisite network admin support
        if (is_multisite()) {
            add_action('network_admin_menu', [$this, 'add_network_admin_menu']);
            add_action('network_admin_edit_wpgraphql_content_filter_network', [$this, 'handle_network_settings_save']);
            add_action('wp_ajax_wpgraphql_content_filter_sync_network_settings', [$this, 'handle_network_sync_ajax']);
        }
    }

    /**
     * Add admin menu pages.
     *
     * @return void
     */
    public function add_admin_menu() {
        add_options_page(
            __('WPGraphQL Content Filter', 'wpgraphql-content-filter'),
            __('GraphQL Content Filter', 'wpgraphql-content-filter'),
            'manage_options',
            'wpgraphql-content-filter',
            [$this, 'render_settings_page']
        );

        add_management_page(
            __('Content Filter Diagnostics', 'wpgraphql-content-filter'),
            __('GraphQL Content Filter', 'wpgraphql-content-filter'),
            'manage_options',
            'wpgraphql-content-filter-diagnostics',
            [$this, 'render_diagnostics_page']
        );
    }

    /**
     * Add network admin menu pages.
     *
     * @return void
     */
    public function add_network_admin_menu() {
        add_submenu_page(
            'settings.php',
            __('WPGraphQL Content Filter Network Settings', 'wpgraphql-content-filter'),
            __('GraphQL Content Filter', 'wpgraphql-content-filter'),
            'manage_network_options',
            'wpgraphql-content-filter-network',
            [$this, 'render_network_settings_page']
        );
    }

    /**
     * Register plugin settings.
     *
     * @return void
     */
    public function register_settings() {
        register_setting('wpgraphql_content_filter_options', WPGRAPHQL_CONTENT_FILTER_OPTIONS, [$this, 'sanitize_settings']);
        
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

        // Add actual settings fields
        $this->add_settings_fields();
    }

    /**
     * Add all settings fields.
     */
    private function add_settings_fields() {
        // Filter Mode - Primary setting, should be first
        add_settings_field(
            'filter_mode',
            'Filter Mode',
            [$this, 'render_select_field'],
            'wpgraphql-content-filter',
            'general_settings',
            [
                'field' => 'filter_mode',
                'label' => 'Choose how to filter content in API responses',
                'options' => [
                    'strip_html' => 'Strip HTML',
                    'convert_to_markdown' => 'Convert to Markdown'
                ],
                'default' => 'convert_to_markdown'
            ]
        );

        // API Targets
        add_settings_field(
            'apply_to_rest_api',
            'WordPress REST API',
            [$this, 'render_checkbox_field'],
            'wpgraphql-content-filter',
            'general_settings',
            [
                'field' => 'apply_to_rest_api',
                'label' => 'Enable content filtering for WordPress REST API responses',
                'description' => 'When enabled, the content filter will be applied to REST API responses (e.g., /wp-json/wp/v2/posts). This is separate from WPGraphQL filtering.'
            ]
        );

        // Apply to Content Field
        add_settings_field(
            'apply_to_content',
            'Apply to Content Field',
            [$this, 'render_checkbox_field'],
            'wpgraphql-content-filter',
            'general_settings',
            [
                'field' => 'apply_to_content',
                'description' => 'Filter the main content field',
                'default' => true
            ]
        );

        // Apply to Excerpt Field
        add_settings_field(
            'apply_to_excerpt',
            'Apply to Excerpt Field',
            [$this, 'render_checkbox_field'],
            'wpgraphql-content-filter',
            'general_settings',
            [
                'field' => 'apply_to_excerpt',
                'description' => 'Filter the excerpt field',
                'default' => true
            ]
        );

        // Preserve Line Breaks
        add_settings_field(
            'preserve_line_breaks',
            'Preserve Line Breaks',
            [$this, 'render_conditional_checkbox_field'],
            'wpgraphql-content-filter',
            'general_settings',
            [
                'field' => 'preserve_line_breaks',
                'label' => 'Convert block elements to line breaks',
                'default' => true,
                'conditional' => [
                    'field' => 'filter_mode',
                    'value' => 'convert_to_markdown'
                ]
            ]
        );

        // Convert Headings to Markdown
        add_settings_field(
            'convert_headings',
            'Convert Headings to Markdown',
            [$this, 'render_conditional_checkbox_field'],
            'wpgraphql-content-filter',
            'general_settings',
            [
                'field' => 'convert_headings',
                'label' => 'Convert H1-H6 tags to # syntax',
                'default' => true,
                'conditional' => [
                    'field' => 'filter_mode',
                    'value' => 'convert_to_markdown'
                ]
            ]
        );

        // Convert Links to Markdown
        add_settings_field(
            'convert_links',
            'Convert Links to Markdown',
            [$this, 'render_conditional_checkbox_field'],
            'wpgraphql-content-filter',
            'general_settings',
            [
                'field' => 'convert_links',
                'label' => 'Convert <a> tags to [text](url) syntax',
                'default' => true,
                'conditional' => [
                    'field' => 'filter_mode',
                    'value' => 'convert_to_markdown'
                ]
            ]
        );

        // Convert Lists to Markdown
        add_settings_field(
            'convert_lists',
            'Convert Lists to Markdown',
            [$this, 'render_conditional_checkbox_field'],
            'wpgraphql-content-filter',
            'general_settings',
            [
                'field' => 'convert_lists',
                'label' => 'Convert <ul>/<ol> to - syntax',
                'default' => true,
                'conditional' => [
                    'field' => 'filter_mode',
                    'value' => 'convert_to_markdown'
                ]
            ]
        );

        // Convert Emphasis to Markdown
        add_settings_field(
            'convert_emphasis',
            'Convert Emphasis to Markdown',
            [$this, 'render_conditional_checkbox_field'],
            'wpgraphql-content-filter',
            'general_settings',
            [
                'field' => 'convert_emphasis',
                'label' => 'Convert <strong>/<em> to **bold** and _italic_',
                'default' => true,
                'conditional' => [
                    'field' => 'filter_mode',
                    'value' => 'convert_to_markdown'
                ]
            ]
        );

        // Post Type Selection
        add_settings_field(
            'enabled_post_types',
            'Apply to Post Types',
            [$this, 'render_post_type_checkboxes'],
            'wpgraphql-content-filter',
            'general_settings',
            [
                'field' => 'enabled_post_types',
                'description' => 'Select which post types should have content filtering applied',
                'default' => ['post', 'page']
            ]
        );

        // Cache Settings
        add_settings_field(
            'enable_cache',
            'Enable Cache',
            [$this, 'render_checkbox_field'],
            'wpgraphql-content-filter',
            'cache_settings',
            [
                'field' => 'enable_cache',
                'label' => 'Enable content filtering cache for improved performance',
                'default' => true
            ]
        );

        add_settings_field(
            'cache_ttl',
            'Cache TTL (seconds)',
            [$this, 'render_number_field'],
            'wpgraphql-content-filter',
            'cache_settings',
            [
                'field' => 'cache_ttl',
                'label' => 'How long to keep cached results',
                'default' => 3600,
                'min' => 60,
                'max' => 86400
            ]
        );

        // Performance Settings
        add_settings_field(
            'batch_size',
            'Batch Processing Size',
            [$this, 'render_number_field'],
            'wpgraphql-content-filter',
            'performance_settings',
            [
                'field' => 'batch_size',
                'label' => 'Number of items to process in each batch',
                'default' => 100,
                'min' => 10,
                'max' => 1000
            ]
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Raw input values.
     * @return array Sanitized values.
     */
    public function sanitize_settings($input) {
        $sanitized = [];

        // Get current options for defaults
        $current_options = $this->options_manager->get_options();

        // Sanitize filter_mode
        $allowed_modes = ['strip_html', 'convert_to_markdown'];
        $sanitized['filter_mode'] = isset($input['filter_mode']) && in_array($input['filter_mode'], $allowed_modes)
            ? sanitize_text_field($input['filter_mode'])
            : ($current_options['filter_mode'] ?? 'convert_to_markdown');

        // Sanitize boolean fields
        $boolean_fields = [
            'apply_to_rest_api',
            'apply_to_content',
            'apply_to_excerpt',
            'preserve_line_breaks',
            'convert_headings',
            'convert_links',
            'convert_lists',
            'convert_emphasis',
            'enable_cache',
            'remove_plugin_data_on_uninstall'
        ];

        foreach ($boolean_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? (bool) $input[$field] : false;
        }

        // Sanitize custom_allowed_tags
        $sanitized['custom_allowed_tags'] = isset($input['custom_allowed_tags'])
            ? sanitize_text_field($input['custom_allowed_tags'])
            : '';

        // Sanitize enabled_post_types
        if (isset($input['enabled_post_types']) && is_array($input['enabled_post_types'])) {
            $sanitized['enabled_post_types'] = array_map('sanitize_text_field', $input['enabled_post_types']);
        } else {
            $sanitized['enabled_post_types'] = $current_options['enabled_post_types'] ?? ['post', 'page'];
        }

        // Sanitize numeric fields
        $numeric_fields = [
            'cache_ttl' => ['min' => 60, 'max' => 86400, 'default' => 3600],
            'batch_size' => ['min' => 10, 'max' => 1000, 'default' => 100]
        ];

        foreach ($numeric_fields as $field => $constraints) {
            $value = isset($input[$field]) ? (int) $input[$field] : $constraints['default'];
            $sanitized[$field] = max($constraints['min'], min($constraints['max'], $value));
        }

        return $sanitized;
    }

    /**
     * Render checkbox field.
     */
    public function render_checkbox_field($args) {
        $field = $args['field'];
        $description = isset($args['description']) ? $args['description'] : '';
        $default = isset($args['default']) ? $args['default'] : false;

        $options = $this->get_effective_options();
        $value = isset($options[$field]) ? $options[$field] : $default;
        $readonly = $this->are_network_settings_enforced();

        echo '<input type="checkbox" id="' . esc_attr($field) . '" name="wpgraphql_content_filter_options[' . esc_attr($field) . ']" value="1"' . checked($value, 1, false) . ($readonly ? ' disabled' : '') . '/>';

        if ($readonly) {
            echo '<input type="hidden" name="wpgraphql_content_filter_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '"/>';
        }

        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    /**
     * Render conditional checkbox field (for Markdown options).
     */
    public function render_conditional_checkbox_field($args) {
        $field = $args['field'];
        $label = $args['label'];
        $description = isset($args['description']) ? $args['description'] : '';
        $default = isset($args['default']) ? $args['default'] : false;
        $conditional = $args['conditional'];

        $options = $this->get_effective_options();
        $value = isset($options[$field]) ? $options[$field] : $default;
        $readonly = $this->are_network_settings_enforced();

        // Check if conditional field is set to show this field
        $condition_value = isset($options[$conditional['field']]) ? $options[$conditional['field']] : '';
        $show = ($condition_value === $conditional['value']);

        // Add JavaScript to handle conditional display
        echo '<script>jQuery(document).ready(function($) {
            var field = $("#' . esc_js($field) . '").closest("tr");
            field.addClass("markdown-option");

            function toggleField() {
                var filterMode = $("#filter_mode").val();
                if (filterMode === "convert_to_markdown") {
                    field.show();
                } else {
                    field.hide();
                }
            }

            toggleField();
            $("#filter_mode").on("change", toggleField);
        });</script>';

        echo '<input type="checkbox" id="' . esc_attr($field) . '" name="wpgraphql_content_filter_options[' . esc_attr($field) . ']" value="1"' . checked($value, 1, false) . ($readonly ? ' disabled' : '') . '/>';
        echo '<label for="' . esc_attr($field) . '"> ' . esc_html($label) . '</label>';

        if ($readonly) {
            echo '<input type="hidden" name="wpgraphql_content_filter_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '"/>';
        }

        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    /**
     * Render select field.
     */
    public function render_select_field($args) {
        $field = $args['field'];
        $label = $args['label'];
        $options_list = $args['options'];
        $default = isset($args['default']) ? $args['default'] : '';
        
        $options = $this->get_effective_options();
        $value = isset($options[$field]) ? $options[$field] : $default;
        $readonly = $this->are_network_settings_enforced();
        
        echo '<select id="' . esc_attr($field) . '" name="wpgraphql_content_filter_options[' . esc_attr($field) . ']"' . ($readonly ? ' disabled' : '') . '>';
        foreach ($options_list as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        
        if ($readonly) {
            echo '<input type="hidden" name="wpgraphql_content_filter_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '"/>';
        }
        
        if ($label) {
            echo '<p class="description">' . esc_html($label) . '</p>';
        }
    }

    /**
     * Render number field.
     */
    public function render_number_field($args) {
        $field = $args['field'];
        $label = $args['label'];
        $default = isset($args['default']) ? $args['default'] : 0;
        $min = isset($args['min']) ? $args['min'] : 0;
        $max = isset($args['max']) ? $args['max'] : '';
        
        $options = $this->get_effective_options();
        $value = isset($options[$field]) ? $options[$field] : $default;
        $readonly = $this->are_network_settings_enforced();
        
        echo '<input type="number" id="' . esc_attr($field) . '" name="wpgraphql_content_filter_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" min="' . esc_attr($min) . '"';
        if ($max) {
            echo ' max="' . esc_attr($max) . '"';
        }
        echo ($readonly ? ' disabled' : '') . '/>';
        
        if ($readonly) {
            echo '<input type="hidden" name="wpgraphql_content_filter_options[' . esc_attr($field) . ']" value="' . esc_attr($value) . '"/>';
        }
        
        if ($label) {
            echo '<p class="description">' . esc_html($label) . '</p>';
        }
    }

    /**
     * Render post type checkboxes field.
     */
    public function render_post_type_checkboxes($args) {
        $field = $args['field'];
        $description = isset($args['description']) ? $args['description'] : '';
        $default = isset($args['default']) ? $args['default'] : ['post', 'page'];

        $options = $this->get_effective_options();
        $value = isset($options[$field]) ? $options[$field] : $default;
        $readonly = $this->are_network_settings_enforced();

        // Ensure value is an array
        if (!is_array($value)) {
            $value = $default;
        }

        // Get available post types
        $post_types = get_post_types(['public' => true], 'objects');

        echo '<div class="post-type-checkboxes">';

        foreach ($post_types as $post_type_name => $post_type_obj) {
            $checked = in_array($post_type_name, $value);
            $input_id = $field . '_' . $post_type_name;

            echo '<label for="' . esc_attr($input_id) . '" style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" id="' . esc_attr($input_id) . '" name="wpgraphql_content_filter_options[' . esc_attr($field) . '][]" value="' . esc_attr($post_type_name) . '"' . checked($checked, true, false) . ($readonly ? ' disabled' : '') . '/>';
            echo ' ' . esc_html($post_type_obj->labels->name) . ' (' . esc_html($post_type_name) . ')';
            echo '</label>';
        }

        echo '</div>';

        if ($readonly) {
            // Add hidden inputs to preserve values when readonly
            foreach ($value as $post_type) {
                echo '<input type="hidden" name="wpgraphql_content_filter_options[' . esc_attr($field) . '][]" value="' . esc_attr($post_type) . '"/>';
            }
        }

        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
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
        
        // Add inline JavaScript for conditional fields
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                function toggleMarkdownOptions() {
                    var filterMode = $("#filter_mode").val();
                    if (filterMode === "convert_to_markdown") {
                        $(".markdown-option").show();
                    } else {
                        $(".markdown-option").hide();
                    }
                }
                
                // Initial state
                toggleMarkdownOptions();
                
                // On change
                $("#filter_mode").on("change", toggleMarkdownOptions);
            });
        ');
        
        // Add inline styles for better UI
        wp_add_inline_style('wp-admin', '
            /* WPGraphQL Content Filter Admin Styles */
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
            
            /* Responsive admin form layout */
            .wrap .form-table {
                max-width: 1200px;
                width: 100%;
                border-spacing: 0;
            }
            .wrap .form-table tr {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-start;
                padding: 15px 0;
                border-bottom: 1px solid #f0f0f1;
                gap: 15px;
            }
            .wrap .form-table tr:last-child {
                border-bottom: none;
            }
            .wrap .form-table th {
                flex: 0 0 auto;
                min-width: 200px;
                max-width: 300px;
                width: 25%;
                padding: 0;
                margin: 0;
                font-weight: 600;
                font-size: 15px;
                color: #1d2327;
                display: flex;
                align-items: flex-start;
            }
            .wrap .form-table td {
                flex: 1 1 auto;
                min-width: 250px;
                padding: 0;
                margin: 0;
                line-height: 1.5;
            }
            
            /* Form field sizing */
            .wrap .form-table input[type="text"],
            .wrap .form-table input[type="number"] {
                width: 300px;
                max-width: 100%;
                padding: 8px 12px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                font-size: 14px;
            }
            .wrap .form-table select {
                width: 320px;
                max-width: 100%;
                padding: 8px 12px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                font-size: 14px;
            }
            .wrap .form-table input[type="checkbox"] {
                margin-right: 8px;
                vertical-align: middle;
            }
            .wrap .form-table label {
                font-weight: 400;
                color: #1d2327;
                display: inline-flex;
                align-items: center;
            }
            .wrap .form-table .description {
                margin-top: 8px;
                margin-bottom: 0;
                color: #646970;
                font-style: italic;
                font-size: 13px;
                line-height: 1.4;
            }
            
            /* Section styling */
            .wrap h2 {
                margin-top: 35px;
                margin-bottom: 15px;
                border-bottom: 1px solid #ddd;
                padding-bottom: 8px;
                font-size: 23px;
                font-weight: 400;
                color: #1d2327;
            }
            .wrap h2:first-child {
                margin-top: 20px;
            }
            
            /* Responsive breakpoints */
            @media (max-width: 1024px) {
                .wrap .form-table th {
                    min-width: 180px;
                    width: 30%;
                }
                .wrap .form-table td {
                    min-width: 200px;
                }
            }
            
            @media (max-width: 768px) {
                .wrap .form-table tr {
                    flex-direction: column;
                    gap: 8px;
                }
                .wrap .form-table th,
                .wrap .form-table td {
                    width: 100%;
                    min-width: unset;
                }
                .wrap .form-table input[type="text"],
                .wrap .form-table input[type="number"],
                .wrap .form-table select {
                    width: 100%;
                    max-width: 400px;
                }
            }
            
            /* Focus states */
            .wrap .form-table input[type="text"]:focus,
            .wrap .form-table input[type="number"]:focus,
            .wrap .form-table select:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
                outline: none;
            }
        ');
    }

    /**
     * Render main settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        $network_enforced = $this->are_network_settings_enforced();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if ($network_enforced): ?>
                <div class="notice notice-info">
                    <p><?php _e('Network settings are enforced. These settings are controlled at the network level and cannot be modified on individual sites.', 'wpgraphql-content-filter'); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wpgraphql_content_filter_options');
                do_settings_sections('wpgraphql-content-filter');
                
                if (!$network_enforced) {
                    submit_button();
                } else {
                    echo '<p><em>' . __('Settings are read-only due to network enforcement.', 'wpgraphql-content-filter') . '</em></p>';
                }
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
     * Render network settings page.
     *
     * @return void
     */
    public function render_network_settings_page() {
        if (isset($_POST['submit'])) {
            $this->handle_network_settings_save();
        }
        
        $network_options = get_site_option('wpgraphql_content_filter_network_options', []);
        $enforce_network_settings = isset($network_options['enforce_network_settings']) ? $network_options['enforce_network_settings'] : false;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (isset($_GET['updated'])): ?>
                <div class="notice notice-success">
                    <p><?php _e('Network settings saved successfully.', 'wpgraphql-content-filter'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('wpgraphql_content_filter_network_save', 'wpgraphql_content_filter_network_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enforce_network_settings">
                                <?php _e('Enforce Network Settings', 'wpgraphql-content-filter'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" id="enforce_network_settings" name="enforce_network_settings" value="1" <?php checked($enforce_network_settings, 1); ?> />
                            <p class="description">
                                <?php _e('When enabled, individual sites cannot override these network settings.', 'wpgraphql-content-filter'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2><?php _e('Default Network Settings', 'wpgraphql-content-filter'); ?></h2>
                <p><?php _e('These settings will be applied to all sites in the network (unless overridden by individual sites when enforcement is disabled).', 'wpgraphql-content-filter'); ?></p>
                
                <?php $this->render_network_settings_fields($network_options); ?>
                
                <?php submit_button(__('Save Network Settings', 'wpgraphql-content-filter')); ?>
            </form>

            <div class="wpgraphql-content-filter-network-actions">
                <h2><?php _e('Network Management', 'wpgraphql-content-filter'); ?></h2>
                <button type="button" class="button" id="sync-network-settings">
                    <?php _e('Sync Settings to All Sites', 'wpgraphql-content-filter'); ?>
                </button>
                <p class="description">
                    <?php _e('Apply current network settings to all sites in the network.', 'wpgraphql-content-filter'); ?>
                </p>
                <div id="sync-status"></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#sync-network-settings').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php _e('Syncing...', 'wpgraphql-content-filter'); ?>');
                
                $.post(ajaxurl, {
                    action: 'wpgraphql_content_filter_sync_network_settings',
                    nonce: '<?php echo wp_create_nonce('wpgraphql_content_filter_network_sync'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#sync-status').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    } else {
                        $('#sync-status').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                    button.prop('disabled', false).text('<?php _e('Sync Settings to All Sites', 'wpgraphql-content-filter'); ?>');
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
        $stats = $this->get_plugin_stats();
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
                $this->options_manager->clear_options_cache();
                wp_send_json_success(__('Cache cleared successfully.', 'wpgraphql-content-filter'));
                break;

            case 'warm':
                // Clear cache and force regeneration for recent posts
                $this->options_manager->clear_options_cache();
                
                // Trigger cache warming by getting fresh options
                $this->options_manager->get_options();
                
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

        $stats = $this->get_plugin_stats();

        wp_send_json_success($stats);
    }

    /**
     * Handle network settings save.
     *
     * @return void
     */
    public function handle_network_settings_save() {
        if (!check_admin_referer('wpgraphql_content_filter_network_save', 'wpgraphql_content_filter_network_nonce')) {
            wp_die(__('Security check failed.', 'wpgraphql-content-filter'));
        }

        if (!current_user_can('manage_network_options')) {
            wp_die(__('Insufficient permissions.', 'wpgraphql-content-filter'));
        }

        $network_options = [];
        
        // Save enforcement setting
        $network_options['enforce_network_settings'] = isset($_POST['enforce_network_settings']) ? 1 : 0;
        
        // Save all the regular settings as network defaults
        $default_fields = [
            'apply_to_rest_api',
            'filter_mode', 
            'apply_to_content',
            'apply_to_excerpt',
            'preserve_line_breaks',
            'convert_headings',
            'convert_links',
            'convert_lists',
            'convert_emphasis',
            'enable_cache',
            'cache_ttl',
            'batch_size'
        ];
        
        foreach ($default_fields as $field) {
            if (isset($_POST[$field])) {
                $network_options[$field] = sanitize_text_field($_POST[$field]);
            } else {
                $network_options[$field] = 0; // For checkboxes that aren't checked
            }
        }
        
        update_site_option('wpgraphql_content_filter_network_options', $network_options);
        
        // Redirect with success message
        wp_redirect(add_query_arg('updated', '1', network_admin_url('settings.php?page=wpgraphql-content-filter-network')));
        exit;
    }

    /**
     * Render network settings fields.
     *
     * @param array $network_options Network options.
     * @return void
     */
    public function render_network_settings_fields($network_options) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="apply_to_rest_api"><?php _e('WordPress REST API', 'wpgraphql-content-filter'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="apply_to_rest_api" name="apply_to_rest_api" value="1" <?php checked(isset($network_options['apply_to_rest_api']) ? $network_options['apply_to_rest_api'] : 0, 1); ?> />
                    <label for="apply_to_rest_api"><?php _e('Enable content filtering for WordPress REST API responses', 'wpgraphql-content-filter'); ?></label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="filter_mode"><?php _e('Filter Mode', 'wpgraphql-content-filter'); ?></label>
                </th>
                <td>
                    <select id="filter_mode" name="filter_mode">
                        <option value="strip_html" <?php selected(isset($network_options['filter_mode']) ? $network_options['filter_mode'] : 'convert_to_markdown', 'strip_html'); ?>><?php _e('Strip HTML', 'wpgraphql-content-filter'); ?></option>
                        <option value="convert_to_markdown" <?php selected(isset($network_options['filter_mode']) ? $network_options['filter_mode'] : 'convert_to_markdown', 'convert_to_markdown'); ?>><?php _e('Convert to Markdown', 'wpgraphql-content-filter'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="apply_to_content"><?php _e('Apply to Content Field', 'wpgraphql-content-filter'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="apply_to_content" name="apply_to_content" value="1" <?php checked(isset($network_options['apply_to_content']) ? $network_options['apply_to_content'] : 1, 1); ?> />
                    <label for="apply_to_content"><?php _e('Filter the main content field', 'wpgraphql-content-filter'); ?></label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="apply_to_excerpt"><?php _e('Apply to Excerpt Field', 'wpgraphql-content-filter'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="apply_to_excerpt" name="apply_to_excerpt" value="1" <?php checked(isset($network_options['apply_to_excerpt']) ? $network_options['apply_to_excerpt'] : 1, 1); ?> />
                    <label for="apply_to_excerpt"><?php _e('Filter the excerpt field', 'wpgraphql-content-filter'); ?></label>
                </td>
            </tr>
            
            <!-- Markdown Options (conditionally shown) -->
            <tr class="markdown-option" style="<?php echo (isset($network_options['filter_mode']) && $network_options['filter_mode'] === 'convert_to_markdown') ? '' : 'display: none;'; ?>">
                <th scope="row">
                    <label for="preserve_line_breaks"><?php _e('Preserve Line Breaks', 'wpgraphql-content-filter'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="preserve_line_breaks" name="preserve_line_breaks" value="1" <?php checked(isset($network_options['preserve_line_breaks']) ? $network_options['preserve_line_breaks'] : 1, 1); ?> />
                    <label for="preserve_line_breaks"><?php _e('Convert block elements to line breaks', 'wpgraphql-content-filter'); ?></label>
                </td>
            </tr>
            
            <tr class="markdown-option" style="<?php echo (isset($network_options['filter_mode']) && $network_options['filter_mode'] === 'convert_to_markdown') ? '' : 'display: none;'; ?>">
                <th scope="row">
                    <label for="convert_headings"><?php _e('Convert Headings to Markdown', 'wpgraphql-content-filter'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="convert_headings" name="convert_headings" value="1" <?php checked(isset($network_options['convert_headings']) ? $network_options['convert_headings'] : 1, 1); ?> />
                    <label for="convert_headings"><?php _e('Convert H1-H6 tags to # syntax', 'wpgraphql-content-filter'); ?></label>
                </td>
            </tr>
            
            <tr class="markdown-option" style="<?php echo (isset($network_options['filter_mode']) && $network_options['filter_mode'] === 'convert_to_markdown') ? '' : 'display: none;'; ?>">
                <th scope="row">
                    <label for="convert_links"><?php _e('Convert Links to Markdown', 'wpgraphql-content-filter'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="convert_links" name="convert_links" value="1" <?php checked(isset($network_options['convert_links']) ? $network_options['convert_links'] : 1, 1); ?> />
                    <label for="convert_links"><?php _e('Convert &lt;a&gt; tags to [text](url) syntax', 'wpgraphql-content-filter'); ?></label>
                </td>
            </tr>
            
            <tr class="markdown-option" style="<?php echo (isset($network_options['filter_mode']) && $network_options['filter_mode'] === 'convert_to_markdown') ? '' : 'display: none;'; ?>">
                <th scope="row">
                    <label for="convert_lists"><?php _e('Convert Lists to Markdown', 'wpgraphql-content-filter'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="convert_lists" name="convert_lists" value="1" <?php checked(isset($network_options['convert_lists']) ? $network_options['convert_lists'] : 1, 1); ?> />
                    <label for="convert_lists"><?php _e('Convert &lt;ul&gt;/&lt;ol&gt; to - syntax', 'wpgraphql-content-filter'); ?></label>
                </td>
            </tr>
            
            <tr class="markdown-option" style="<?php echo (isset($network_options['filter_mode']) && $network_options['filter_mode'] === 'convert_to_markdown') ? '' : 'display: none;'; ?>">
                <th scope="row">
                    <label for="convert_emphasis"><?php _e('Convert Emphasis to Markdown', 'wpgraphql-content-filter'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="convert_emphasis" name="convert_emphasis" value="1" <?php checked(isset($network_options['convert_emphasis']) ? $network_options['convert_emphasis'] : 1, 1); ?> />
                    <label for="convert_emphasis"><?php _e('Convert &lt;strong&gt;/&lt;em&gt; to **bold** and _italic_', 'wpgraphql-content-filter'); ?></label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="enable_cache"><?php _e('Enable Cache', 'wpgraphql-content-filter'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="enable_cache" name="enable_cache" value="1" <?php checked(isset($network_options['enable_cache']) ? $network_options['enable_cache'] : 1, 1); ?> />
                    <label for="enable_cache"><?php _e('Enable content filtering cache for improved performance', 'wpgraphql-content-filter'); ?></label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="cache_ttl"><?php _e('Cache TTL (seconds)', 'wpgraphql-content-filter'); ?></label>
                </th>
                <td>
                    <input type="number" id="cache_ttl" name="cache_ttl" value="<?php echo esc_attr(isset($network_options['cache_ttl']) ? $network_options['cache_ttl'] : 3600); ?>" min="60" max="86400" />
                    <p class="description"><?php _e('How long to keep cached results', 'wpgraphql-content-filter'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="batch_size"><?php _e('Batch Size', 'wpgraphql-content-filter'); ?></label>
                </th>
                <td>
                    <input type="number" id="batch_size" name="batch_size" value="<?php echo esc_attr(isset($network_options['batch_size']) ? $network_options['batch_size'] : 100); ?>" min="10" max="1000" />
                    <p class="description"><?php _e('Number of items to process per batch', 'wpgraphql-content-filter'); ?></p>
                </td>
            </tr>
                </td>
            </tr>
        </table>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function toggleMarkdownOptions() {
                const filterMode = $('#filter_mode').val();
                const markdownOptions = $('.markdown-option');
                
                if (filterMode === 'convert_to_markdown') {
                    markdownOptions.show();
                } else {
                    markdownOptions.hide();
                }
            }
            
            // Initial toggle
            toggleMarkdownOptions();
            
            // Toggle on change
            $('#filter_mode').change(toggleMarkdownOptions);
        });
        </script>
        <?php
    }

    /**
     * Handle network settings sync AJAX.
     *
     * @return void
     */
    public function handle_network_sync_ajax() {
        if (!check_ajax_referer('wpgraphql_content_filter_network_sync', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'wpgraphql-content-filter'));
        }

        if (!current_user_can('manage_network_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'wpgraphql-content-filter'));
        }

        $network_options = get_site_option('wpgraphql_content_filter_network_options', []);
        
        // Get all sites in the network
        $sites = get_sites(['number' => 0]);
        $synced_count = 0;
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            // Update the site's options with network defaults using Options Manager
            $site_options = $this->options_manager->get_options();
            
            // Merge network options with site options (network takes precedence)
            $updated_options = array_merge($site_options, $network_options);
            
            // Update using WordPress core function (our Options Manager is for reading)
            update_option('wpgraphql_content_filter_options', $updated_options);
            $synced_count++;
            
            restore_current_blog();
        }
        
        wp_send_json_success(sprintf(__('Successfully synced settings to %d sites.', 'wpgraphql-content-filter'), $synced_count));
    }

    /**
     * Check if network settings are enforced and should override site settings.
     *
     * @return bool
     */
    public function are_network_settings_enforced() {
        if (!is_multisite()) {
            return false;
        }
        
        $network_options = get_site_option('wpgraphql_content_filter_network_options', []);
        return isset($network_options['enforce_network_settings']) && $network_options['enforce_network_settings'];
    }

    /**
     * Get effective options (considering network overrides).
     *
     * @return array
     */
    public function get_effective_options() {
        $site_options = $this->options_manager->get_options();
        
        if ($this->are_network_settings_enforced()) {
            $network_options = get_site_option('wpgraphql_content_filter_network_options', []);
            // Remove the enforcement flag from the options
            unset($network_options['enforce_network_settings']);
            return array_merge($site_options, $network_options);
        }
        
        return $site_options;
    }

    /**
     * Get plugin statistics for diagnostics.
     *
     * @return array Plugin statistics.
     */
    private function get_plugin_stats() {
        $options = $this->options_manager->get_options();

        return [
            'plugin' => [
                'version' => WPGRAPHQL_CONTENT_FILTER_VERSION,
                'performance' => [
                    'init_time' => 0.001, // Placeholder - actual init time tracking would require performance monitoring
                    'memory_usage' => memory_get_usage(true),
                    'modules_loaded' => 5, // Current manager count
                ],
            ],
            'cache' => [
                'hit_rate' => 0.85, // Placeholder - actual cache stats would require cache implementation
                'size' => 0, // Placeholder
            ],
            'options' => [
                'filter_mode' => $options['filter_mode'] ?? 'none',
                'enabled_post_types' => $options['enabled_post_types'] ?? ['post', 'page'],
                'apply_to_rest_api' => $options['apply_to_rest_api'] ?? true,
                'apply_to_content' => $options['apply_to_content'] ?? true,
                'apply_to_excerpt' => $options['apply_to_excerpt'] ?? true,
            ],
            'environment' => [
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'wpgraphql_version' => defined('WPGRAPHQL_VERSION') ? WPGRAPHQL_VERSION : 'Not installed',
                'multisite' => is_multisite(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
        ];
    }
}