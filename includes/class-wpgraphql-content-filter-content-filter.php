<?php
/**
 * Content Filter for WPGraphQL Content Filter
 *
 * Handles the actual content filtering logic for different modes.
 *
 * @package WPGraphQL_Content_Filter
 * @since 2.1.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WPGraphQL_Content_Filter_Content_Filter
 *
 * Applies filtering to content based on configured options.
 *
 * @since 2.1.0
 */
class WPGraphQL_Content_Filter_Content_Filter {
    
    /**
     * Options manager instance.
     *
     * @var WPGraphQL_Content_Filter_Options_Manager
     */
    private $options_manager;
    
    /**
     * Singleton instance.
     *
     * @var WPGraphQL_Content_Filter_Content_Filter|null
     */
    private static $instance = null;
    
    /**
     * Get singleton instance.
     *
     * @return WPGraphQL_Content_Filter_Content_Filter
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor.
     */
    private function __construct() {
        $this->options_manager = WPGraphQL_Content_Filter_Options_Manager::get_instance();
    }
    
    /**
     * Universal content filter - handles both content and excerpt.
     *
     * @param string $content The content to filter.
     * @param string $field_type The field type ('content' or 'excerpt').
     * @param array|null $options_override Optional options to override defaults.
     * @return string
     */
    public function filter_field_content($content, $field_type = 'content', $options_override = null) {
        // Early return for empty content
        if (empty($content) || !is_string($content)) {
            return $content;
        }

        // Get cached options for better performance, or use override
        $options = $options_override !== null ? $options_override : $this->options_manager->get_options();

        // Early return if filtering is disabled
        if ($options['filter_mode'] === 'none') {
            return $content;
        }

        // Check if filtering is enabled for this field type
        $field_setting = ($field_type === 'content') ? 'apply_to_content' : 'apply_to_excerpt';
        if (empty($options[$field_setting])) {
            return $content;
        }

        try {
            return $this->apply_filter($content, $options);
        } catch (Exception $e) {
            // Log error in debug mode and return original content
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'WPGraphQL Content Filter Error [%s]: %s',
                    $field_type,
                    $e->getMessage()
                ));
            }
            return $content;
        }
    }
    
    /**
     * Apply the actual filtering based on mode.
     *
     * @param string $content The content to filter.
     * @param array $options The plugin options.
     * @return string
     */
    private function apply_filter($content, $options) {
        // Early return for empty content
        if (empty($content) || !is_string($content)) {
            return $content;
        }
        
        // Cache frequently used options to avoid array lookups
        $filter_mode = $options['filter_mode'] ?? 'none';
        
        switch ($filter_mode) {
            case 'strip_all':
                return $this->strip_all_tags($content, $options['preserve_line_breaks'] ?? true);
                
            case 'markdown':
                return $this->convert_to_markdown($content, $options);
                
            case 'custom':
                $allowed_tags = $options['custom_allowed_tags'] ?? '';
                return $this->strip_custom_tags($content, $allowed_tags);
                
            case 'none':
            default:
                return $content;
        }
    }
    
    /**
     * Strip all HTML tags.
     *
     * @param string $content The content to process.
     * @param bool $preserve_line_breaks Whether to preserve line breaks.
     * @return string
     */
    private function strip_all_tags($content, $preserve_line_breaks = true) {
        // Convert HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        if ($preserve_line_breaks) {
            // Convert block elements to line breaks
            $content = preg_replace('/<\/?(p|div|br|h[1-6]|li|ul|ol)[^>]*>/i', "\n", $content);
            $content = preg_replace('/\n+/', "\n", $content);
        }
        
        // Strip all HTML tags
        $content = wp_strip_all_tags($content);
        
        // Clean up whitespace
        $content = trim($content);
        $content = preg_replace('/[ \t]+/', ' ', $content);
        
        return $content;
    }
    
    /**
     * Convert HTML to Markdown.
     *
     * @param string $content The content to convert.
     * @param array $options The conversion options.
     * @return string
     */
    private function convert_to_markdown($content, $options) {
        $replacements = [];
        
        if ($options['convert_headings']) {
            $replacements = array_merge($replacements, [
                '/<h1[^>]*>(.*?)<\/h1>/i' => '# $1',
                '/<h2[^>]*>(.*?)<\/h2>/i' => '## $1',
                '/<h3[^>]*>(.*?)<\/h3>/i' => '### $1',
                '/<h4[^>]*>(.*?)<\/h4>/i' => '#### $1',
                '/<h5[^>]*>(.*?)<\/h5>/i' => '##### $1',
                '/<h6[^>]*>(.*?)<\/h6>/i' => '###### $1',
            ]);
        }
        
        if ($options['convert_emphasis']) {
            $replacements = array_merge($replacements, [
                '/<strong[^>]*>(.*?)<\/strong>/i' => '**$1**',
                '/<b[^>]*>(.*?)<\/b>/i' => '**$1**',
                '/<em[^>]*>(.*?)<\/em>/i' => '_$1_',
                '/<i[^>]*>(.*?)<\/i>/i' => '_$1_',
            ]);
        }
        
        if ($options['convert_links']) {
            $replacements = array_merge($replacements, [
                '/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/i' => '[$2]($1)',
            ]);
        }
        
        if ($options['convert_lists']) {
            $replacements = array_merge($replacements, [
                '/<ul[^>]*>/i' => '',
                '/<\/ul>/i' => '',
                '/<ol[^>]*>/i' => '',
                '/<\/ol>/i' => '',
                '/<li[^>]*>(.*?)<\/li>/i' => '- $1',
            ]);
        }
        
        // Basic paragraph and line break handling
        $replacements = array_merge($replacements, [
            '/<p[^>]*>/i' => '',
            '/<\/p>/i' => "\n\n",
            '/<br[^>]*>/i' => "\n",
            '/<code[^>]*>(.*?)<\/code>/i' => '`$1`',
            '/<pre[^>]*>(.*?)<\/pre>/i' => "```\n$1\n```",
        ]);
        
        foreach ($replacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        // Strip remaining HTML tags
        $content = wp_strip_all_tags($content);
        
        // Clean up extra whitespace
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Strip tags except allowed ones.
     *
     * @param string $content The content to process.
     * @param string $allowed_tags Comma-separated list of allowed tags.
     * @return string
     */
    private function strip_custom_tags($content, $allowed_tags) {
        if (empty($allowed_tags)) {
            return wp_strip_all_tags($content);
        }
        
        // Parse allowed tags
        $tags = array_map('trim', explode(',', $allowed_tags));
        $allowed = '<' . implode('><', $tags) . '>';
        
        return strip_tags($content, $allowed);
    }
}