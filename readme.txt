=== WPGraphQL Content Filter ===
Contributors: gokepelemo
Tags: wpgraphql, graphql, rest-api, content-filter, sanitize, security, api
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.1.18
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Filter and sanitize content in WPGraphQL and REST API responses with configurable HTML stripping, Markdown conversion, and custom tag allowlists.

== Description ==

WPGraphQL Content Filter is a powerful WordPress plugin that automatically filters and sanitizes content in both WPGraphQL and REST API responses. Perfect for headless WordPress implementations where you need consistent content formatting across different front-end applications.

**Key Features:**

* **Multiple Filter Modes**: None, Strip All HTML, Convert to Markdown, Custom Allowed Tags
* **Universal API Support**: Works with both WPGraphQL and WordPress REST API
* **Custom Post Type Support**: Automatically detects and filters all public post types
* **Configurable Options**: Apply filtering to content and/or excerpt fields
* **Markdown Conversion**: Convert HTML to clean Markdown with configurable options
* **Security Focused**: Built with WordPress security best practices
* **Performance Optimized**: Cached options and efficient processing
* **Multisite Support**: Network-level configuration with site-level override controls

**Filter Modes:**

1. **None**: No filtering applied (default)
2. **Strip All HTML**: Remove all HTML tags, optionally preserve line breaks
3. **Convert to Markdown**: Transform HTML into clean Markdown syntax
4. **Custom Allowed Tags**: Only allow specified HTML tags

**API Compatibility:**

* **GraphQL**: Requires WPGraphQL plugin, works with all registered post types
* **REST API**: Works with all public post types out of the box
* **Custom Post Types**: Automatically supports WooCommerce products, events, portfolios, etc.

**Perfect For:**

* Headless WordPress implementations
* Mobile app backends
* Content syndication
* Security-conscious applications
* Multi-platform content delivery

== Installation ==

**Recommended Installation (Git Clone):**

1. Navigate to your WordPress plugins directory:
   ```
   cd /path/to/your/wordpress/wp-content/plugins/
   ```

2. Clone the repository:
   ```
   git clone https://github.com/gokepelemo/wpgraphql-content-filter.git
   ```

3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Settings > GraphQL Content Filter to configure your filtering options
5. Choose your filter mode and save settings

**Alternative Manual Installation:**

1. Download the plugin files from the GitHub repository
2. Upload the plugin files to `/wp-content/plugins/wpgraphql-content-filter/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure as above

**For GraphQL Support:**
Install and activate the WPGraphQL plugin for GraphQL functionality. REST API filtering works without additional plugins.

**For Multisite Networks:**
1. Network activate the plugin from Network Admin > Plugins
2. Configure network-wide settings in Network Admin > Settings > GraphQL Content Filter
3. Choose whether individual sites can override network settings
4. Site administrators can configure local settings (if allowed)

== Frequently Asked Questions ==

= Do I need WPGraphQL installed? =

WPGraphQL is only required for GraphQL filtering. The plugin will still filter REST API responses without WPGraphQL installed.

= Does this work with custom post types? =

Yes! The plugin automatically detects and filters:
- All public post types (REST API)
- All WPGraphQL-registered post types (GraphQL)
- WooCommerce products, events, portfolios, and more

= Will this affect my site's performance? =

The plugin is designed for performance with cached options and efficient processing. Filtering only occurs on API responses, not front-end page loads.

= Can I customize which fields are filtered? =

Yes, you can choose to filter content fields, excerpt fields, or both through the admin settings.

= Is the original content preserved? =

The filtering only affects API responses. Your original content in the database remains unchanged.

= Can I use custom HTML tags? =

Yes, the "Custom Allowed Tags" mode lets you specify exactly which HTML tags to preserve (e.g., p,strong,em,a).

= Does this work with WordPress Multisite? =

Yes! The plugin supports multisite networks with:
- Network-level configuration for all sites
- Option to enforce network settings across all sites
- Site-level override permissions (configurable)
- Centralized management for network administrators

= How do I configure multisite settings? =

Network activate the plugin and visit Network Admin > Settings > GraphQL Content Filter to configure network-wide settings. You can choose whether individual sites can override these settings.

== Screenshots ==

1. Plugin settings page with all configuration options
2. Filter mode selection with detailed descriptions
3. Markdown conversion options for fine-tuned control
4. REST API response showing filtered content
5. GraphQL query results with sanitized output

== Changelog ==

= 1.0.0 =
* Initial release
* Multiple filter modes (none, strip all, markdown, custom)
* Admin settings page with comprehensive options
* GraphQL field integration (requires WPGraphQL plugin)
* REST API integration (works with all public post types)
* Custom post type support
* Performance optimizations and security enhancements
* Multisite network support with granular site override controls
* Uninstall cleanup

== Upgrade Notice ==

= 1.0.0 =
Initial release of WPGraphQL Content Filter. Configure your content filtering preferences in Settings > GraphQL Content Filter.

== Developer Information ==

**Hooks and Filters:**

* `wpgraphql_content_filter_options` - Modify plugin options
* `wpgraphql_content_filter_before_apply` - Modify content before filtering
* `wpgraphql_content_filter_after_apply` - Modify content after filtering

**Actions:**

* `wpgraphql_content_filter_loaded` - Fired when plugin is loaded
* `wpgraphql_content_filter_settings_saved` - Fired when settings are saved

**Extending the Plugin:**

The plugin uses a modular architecture making it easy to extend with custom filtering modes or additional API support.

== Support ==

For support, feature requests, and bug reports, please visit the [plugin support forum](https://wordpress.org/support/plugin/wpgraphql-content-filter/).

== Contributing ==

This plugin is open source and welcomes contributions. Visit the [GitHub repository](https://github.com/gokepelemo/wpgraphql-content-filter) to contribute.
