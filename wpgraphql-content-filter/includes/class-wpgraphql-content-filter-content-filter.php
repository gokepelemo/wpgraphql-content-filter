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
     * Cached HTMLPurifier instances for different configurations.
     *
     * @var array
     */
    private $htmlpurifier_cache = [];

    /**
     * Cached HtmlConverter instances for different configurations.
     *
     * @var array
     */
    private $htmlconverter_cache = [];

    /**
     * Content processing cache to avoid reprocessing identical content.
     *
     * @var array
     */
    private $content_cache = [];
    
    /**
     * Get singleton instance.
     *
     * @return WPGraphQL_Content_Filter_Content_Filter
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->options_manager = WPGraphQL_Content_Filter_Options_Manager::get_instance();
        }
        return self::$instance;
    }
    
    /**
     * Get cached HTMLPurifier instance for stripping all tags.
     *
     * @param bool $preserve_line_breaks Whether to preserve line breaks.
     * @return HTMLPurifier|null
     */
    private function get_strip_all_purifier($preserve_line_breaks = true) {
        $cache_key = 'strip_all_' . ($preserve_line_breaks ? 'with_breaks' : 'no_breaks');

        if (!isset($this->htmlpurifier_cache[$cache_key])) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.AllowedElements', []);
            $config->set('HTML.AllowedAttributes', []);
            $config->set('AutoFormat.RemoveEmpty', true);
            $config->set('HTML.Allowed', '');
            $config->set('CSS.AllowedProperties', []);
            $config->set('Attr.AllowedClasses', []);
            $config->set('Cache.SerializerPath', null); // Disable caching to avoid file system issues

            $this->htmlpurifier_cache[$cache_key] = new HTMLPurifier($config);
        }

        return $this->htmlpurifier_cache[$cache_key];
    }

    /**
     * Get cached HTMLPurifier instance for custom tag filtering.
     *
     * @param string $allowed_tags Comma-separated list of allowed tags.
     * @return HTMLPurifier|null
     */
    private function get_custom_tags_purifier($allowed_tags) {
        $cache_key = 'custom_' . md5($allowed_tags);

        if (!isset($this->htmlpurifier_cache[$cache_key])) {
            $config = HTMLPurifier_Config::createDefault();

            if (empty($allowed_tags)) {
                $config->set('HTML.AllowedElements', []);
                $config->set('HTML.AllowedAttributes', []);
            } else {
                $tags = array_map('trim', explode(',', $allowed_tags));
                $allowed_elements = [];

                foreach ($tags as $tag) {
                    $tag = trim($tag, '<>');
                    if (!empty($tag)) {
                        $allowed_elements[] = $tag;
                    }
                }

                $config->set('HTML.AllowedElements', $allowed_elements);
                $config->set('HTML.AllowedAttributes', [
                    '*' => ['class', 'id'],
                    'a' => ['href', 'title'],
                    'img' => ['src', 'alt', 'width', 'height'],
                ]);
            }

            $config->set('AutoFormat.RemoveEmpty', true);
            $config->set('Cache.SerializerPath', null);

            $this->htmlpurifier_cache[$cache_key] = new HTMLPurifier($config);
        }

        return $this->htmlpurifier_cache[$cache_key];
    }

    /**
     * Get cached HtmlConverter instance.
     *
     * @param array $options Conversion options.
     * @return \League\HTMLToMarkdown\HtmlConverter|null
     */
    private function get_html_converter($options) {
        // Create a cache key based on relevant options
        $cache_key = 'converter_' .
            ($options['convert_headings'] ? '1' : '0') . '_' .
            ($options['convert_links'] ? '1' : '0');

        if (!isset($this->htmlconverter_cache[$cache_key])) {
            $converter = new \League\HTMLToMarkdown\HtmlConverter([
                'header_style' => 'atx',
                'preserve_comments' => false,
                'strip_tags' => true,
                'remove_nodes' => 'script style',
            ]);

            // Configure converters based on options
            if (!$options['convert_headings']) {
                $converter->getConfig()->setOption('strip_placeholder_links', true);
            }

            if (!$options['convert_links']) {
                $converter->getConfig()->setOption('strip_placeholder_links', true);
            }

            $this->htmlconverter_cache[$cache_key] = $converter;
        }

        return $this->htmlconverter_cache[$cache_key];
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

        // Create cache key for content caching
        $cache_key = md5($content . $field_type . serialize($options));
        if (isset($this->content_cache[$cache_key])) {
            return $this->content_cache[$cache_key];
        }

        try {
            $result = $this->apply_filter($content, $options);
            // Cache the result (limit cache size to prevent memory issues)
            if (count($this->content_cache) < 100) {
                $this->content_cache[$cache_key] = $result;
            }
            return $result;
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
     * Strip all HTML tags using HTMLPurifier.
     *
     * @param string $content The content to process.
     * @param bool $preserve_line_breaks Whether to preserve line breaks.
     * @return string
     */
    private function strip_all_tags($content, $preserve_line_breaks = true) {
        // Try HTMLPurifier first for robust HTML cleaning
        if (class_exists('HTMLPurifier')) {
            try {
                if ($preserve_line_breaks) {
                    // Convert block elements to line breaks before purifying
                    $content = preg_replace('/<\/?(p|div|br|h[1-6]|li|ul|ol)[^>]*>/i', "\n", $content);
                }

                $purifier = $this->get_strip_all_purifier($preserve_line_breaks);
                $content = $purifier->purify($content);

                // Clean up extra whitespace
                if ($preserve_line_breaks) {
                    $content = preg_replace('/\n+/', "\n", $content);
                }
                $content = trim($content);
                $content = preg_replace('/[ \t]+/', ' ', $content);

                // HTMLPurifier should handle all HTML properly, no need for orphaned attribute cleanup
                return $content;
            } catch (Exception $e) {
                // Log error and fall back to wp_strip_all_tags
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WPGraphQL Content Filter: HTMLPurifier failed, falling back to wp_strip_all_tags: ' . $e->getMessage());
                }
                // Fall through to fallback method
            }
        }

        // Fallback to original method (includes orphaned attribute cleanup)
        return $this->strip_all_tags_fallback($content, $preserve_line_breaks);
    }

    /**
     * Strip all HTML tags using WordPress functions (fallback method).
     *
     * @param string $content The content to process.
     * @param bool $preserve_line_breaks Whether to preserve line breaks.
     * @return string
     */
    private function strip_all_tags_fallback($content, $preserve_line_breaks = true) {
        // Convert HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($preserve_line_breaks) {
            // Convert block elements to line breaks
            $content = preg_replace('/<\/?(p|div|br|h[1-6]|li|ul|ol)[^>]*>/i', "\n", $content);
            $content = preg_replace('/\n+/', "\n", $content);
        }

        // Strip all HTML tags
        $content = wp_strip_all_tags($content);

        // Clean up any orphaned attributes that might be left behind
        $content = $this->clean_orphaned_attributes($content);

        // Clean up whitespace
        $content = trim($content);
        $content = preg_replace('/[ \t]+/', ' ', $content);

        return $content;
    }
    
    /**
     * Convert HTML to Markdown using league/html-to-markdown.
     *
     * @param string $content The content to convert.
     * @param array $options The conversion options.
     * @return string
     */
    private function convert_to_markdown($content, $options) {
        // Check if league/html-to-markdown is available
        if (class_exists('\League\HTMLToMarkdown\HtmlConverter')) {
            try {
                $converter = $this->get_html_converter($options);
                $markdown = $converter->convert($content);

                // Clean up extra whitespace
                $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);
                $markdown = trim($markdown);

                // league/html-to-markdown handles all HTML properly, no need for additional cleanup
                return $markdown;
            } catch (Exception $e) {
                // Log error and fall back to regex method
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WPGraphQL Content Filter: HTMLToMarkdown conversion failed, falling back to regex: ' . $e->getMessage());
                }
                // Fall through to regex method
            }
        }

        // Fallback to regex-based conversion (includes orphaned attribute cleanup)
        return $this->convert_to_markdown_regex($content, $options);
    }

    /**
     * Convert HTML to Markdown using regex patterns (fallback method).
     *
     * @param string $content The content to convert.
     * @param array $options The conversion options.
     * @return string
     */
    private function convert_to_markdown_regex($content, $options) {
        // Use a single pass with multiple patterns where possible for better performance
        $patterns = [];
        $replacements = [];

        if ($options['convert_headings']) {
            // Combine heading patterns
            $patterns[] = '/<h([1-6])[^>]*>(.*?)<\/h\1>/i';
            $replacements[] = function($matches) {
                return str_repeat('#', $matches[1]) . ' ' . $matches[2];
            };
        }

        if ($options['convert_emphasis']) {
            // Combine strong/bold tags
            $patterns[] = '/<(strong|b)[^>]*>(.*?)<\/\1>/i';
            $replacements[] = '**$2**';

            // Combine em/italic tags
            $patterns[] = '/<(em|i)[^>]*>(.*?)<\/\1>/i';
            $replacements[] = '_$2_';
        }

        if ($options['convert_links']) {
            $patterns[] = '/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/i';
            $replacements[] = '[$2]($1)';
        }

        if ($options['convert_lists']) {
            // Remove list containers
            $patterns[] = '/<\/?(ul|ol)[^>]*>/i';
            $replacements[] = '';

            // Convert list items
            $patterns[] = '/<li[^>]*>(.*?)<\/li>/i';
            $replacements[] = '- $1';
        }

        // Basic paragraph and line break handling
        $patterns[] = '/<\/?p[^>]*>/i';
        $replacements[] = '';

        $patterns[] = '/<br[^>]*>/i';
        $replacements[] = "\n";

        $patterns[] = '/<code[^>]*>(.*?)<\/code>/i';
        $replacements[] = '`$1`';

        $patterns[] = '/<pre[^>]*>(.*?)<\/pre>/i';
        $replacements[] = "```\n\$1\n```";

        // Apply all patterns in a single pass for better performance
        foreach ($patterns as $i => $pattern) {
            if (is_callable($replacements[$i])) {
                $content = preg_replace_callback($pattern, $replacements[$i], $content);
            } else {
                $content = preg_replace($pattern, $replacements[$i], $content);
            }
        }

        // Strip remaining HTML tags
        $content = wp_strip_all_tags($content);

        // Clean up any orphaned attributes that might be left behind
        $content = $this->clean_orphaned_attributes($content);

        // Clean up extra whitespace
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);

        return $content;
    }
    
    /**
     * Strip tags except allowed ones using HTMLPurifier.
     *
     * @param string $content The content to process.
     * @param string $allowed_tags Comma-separated list of allowed tags.
     * @return string
     */
    private function strip_custom_tags($content, $allowed_tags) {
        // Try HTMLPurifier first for robust HTML cleaning
        if (class_exists('HTMLPurifier')) {
            try {
                $purifier = $this->get_custom_tags_purifier($allowed_tags);
                // HTMLPurifier handles all HTML properly, no need for additional cleanup
                return $purifier->purify($content);
            } catch (Exception $e) {
                // Log error and fall back to strip_tags
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WPGraphQL Content Filter: HTMLPurifier custom tags failed, falling back to strip_tags: ' . $e->getMessage());
                }
                // Fall through to fallback method
            }
        }

        // Fallback to original method (includes orphaned attribute cleanup)
        return $this->strip_custom_tags_fallback($content, $allowed_tags);
    }

    /**
     * Strip tags except allowed ones using improved fallback method.
     *
     * @param string $content The content to process.
     * @param string $allowed_tags Comma-separated list of allowed tags.
     * @return string
     */
    private function strip_custom_tags_fallback($content, $allowed_tags) {
        if (empty($allowed_tags)) {
            return $this->strip_all_tags_fallback($content, true);
        }

        // Parse allowed tags
        $tags = array_map('trim', explode(',', $allowed_tags));
        $allowed = '<' . implode('><', $tags) . '>';

        // Use strip_tags but then clean up any orphaned attributes
        $content = strip_tags($content, $allowed);

        // Clean up orphaned attributes that strip_tags might leave behind
        $content = $this->clean_orphaned_attributes($content);

        return $content;
    }

    /**
     * Clean up orphaned HTML attributes that may be left behind by strip_tags.
     *
     * @param string $content The content to clean.
     * @return string
     */
    private function clean_orphaned_attributes($content) {
        // More efficient cleanup of orphaned attributes using combined patterns
        // Remove orphaned attribute patterns like: href="..." class="..." id="..." etc.
        $content = preg_replace('/\s+\w+(?:-\w+)*\s*=\s*["\'][^"\']*["\']/', '', $content);

        // Remove orphaned attribute patterns without quotes like: href=value class=value
        $content = preg_replace('/\s+\w+(?:-\w+)*\s*=\s*[^\s<>"\']+/', '', $content);

        // Remove common orphaned boolean attributes in one pass
        $content = preg_replace('/\s+(?:class|id|href|src|alt|title|target|rel|style|width|height|role|type|name|value|placeholder|disabled|checked|selected|readonly|required|autofocus|multiple|hidden|defer|async|autoplay|controls|loop|muted|data-\w+|aria-\w+)\b/', '', $content);

        // Clean up extra whitespace that might be left behind
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        return $content;
    }
}