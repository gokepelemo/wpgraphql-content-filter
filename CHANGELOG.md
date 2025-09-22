# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.10] - 2025-09-21

### Fixed

- **Plugin Activation**: Resolved fatal errors during plugin activation in WordPress
- **WordPress Function Guards**: Added proper guards for WordPress function calls to prevent errors when loading outside WordPress context
- **Constant Definition Timing**: Fixed timing issues with `plugin_dir_path()` and `plugin_dir_url()` function calls by deferring constant definition
- **Class Loading**: Enhanced content filter and main plugin classes to handle missing constants gracefully with fallback logic
- **Hook Registration**: Properly guarded `register_activation_hook`, `register_deactivation_hook`, and `register_uninstall_hook` calls

### Technical

- **Initialization Order**: Ensured constants are defined before plugin classes are instantiated
- **Fallback Logic**: Added safe constant access with dynamic path calculation fallbacks
- **Error Prevention**: Plugin now loads safely in any context without fatal errors

## [2.1.8] - 2025-09-21

### Enhanced

- **Professional HTML Processing**: Integrated `league/html-to-markdown` v5.0 for robust HTML-to-Markdown conversion
- **HTMLPurifier Integration**: Added `ezyang/htmlpurifier` v4.16 for comprehensive HTML sanitization and XSS protection
- **Complete Attribute Handling**: Now properly captures all HTML tag attributes including id, classes, data-* attributes, and complex nested structures
- **Security Improvements**: Enhanced XSS protection and malformed HTML handling through professional-grade libraries
- **Graceful Fallbacks**: Falls back to regex-based methods when libraries are unavailable
- **PHPUnit Testing**: Added comprehensive unit tests with simplified test architecture

### Fixed

- **HTML Tag Attribute Capturing**: Fixed issue where HTML tag attributes weren't being captured entirely during conversion
- **Malformed HTML Processing**: Better handling of malformed or incomplete HTML tags through HTMLPurifier
- **Unicode Content Handling**: Improved processing of Unicode characters and special content
- **Content Processing**: Enhanced content processing to handle complex HTML structures with multiple attributes

### Technical

- **Composer Dependencies**: Added professional-grade libraries for HTML processing
  - `league/html-to-markdown` ^5.0 for HTML-to-Markdown conversion
  - `ezyang/htmlpurifier` ^4.16 for HTML sanitization
- **Autoloader Management**: Implemented automatic Composer autoloader loading when available
- **Performance**: Optimized conversion process with library-based parsing vs regex fallback
- **Test Coverage**: Added PHPUnit test suite with bootstrap configuration and unit tests
- **Architecture**: Enhanced modular architecture with professional library integration

## [2.1.7] - 2025-09-21

### Enhanced

- **HTML to Markdown Conversion**: Integrated `league/html-to-markdown` package for robust HTML to Markdown conversion
- **Complete Tag Attribute Handling**: Now properly captures all HTML tag attributes including id, classes, data-* attributes, and other attributes
- **Graceful Fallback**: Falls back to regex-based conversion if the library is unavailable
- **Improved Error Handling**: Enhanced error logging and graceful degradation for conversion failures

### Fixed

- **HTML Tag Attribute Capturing**: Fixed issue where HTML tag attributes weren't being captured entirely during conversion
- **Content Processing**: Improved content processing to handle complex HTML structures with multiple attributes

### Technical

- **Composer Integration**: Added Composer dependency for `league/html-to-markdown` v5.0
- **Autoloader Management**: Implemented automatic Composer autoloader loading when available
- **Performance**: Optimized conversion process with library-based parsing vs regex fallback

## [2.1.1] - 2025-09-21

### Added

- **Post Type Selection**: Users can now select which post types have content filtering applied
- **Admin Interface Enhancements**: Added checkboxes for all public post types in settings
- **Granular Control**: Filter can be enabled/disabled per post type (posts, pages, custom post types)

### Fixed

- **Fatal Error Resolution**: Fixed multisite activation fatal error by adding missing Core class include
- **Missing Interface**: Added WPGraphQL_Content_Filter_Hook_Manager_Interface to resolve class dependencies
- **Admin Layout**: Fixed admin form layout for consistent field styling across all options
- **UI Improvements**: Markdown conversion options now use proper two-column table layout

### Changed

- **Settings Organization**: Moved "Filter Mode" to be the first setting on the admin page for better UX
- **Default Behavior**: Post type filtering defaults to posts and pages for backward compatibility
- **Performance**: Zero processing overhead for disabled post types
- **Release Format**: Updated release packages to use WordPress standard naming (wpgraphql-content-filter-{version}.zip)

### Technical

- **Modular Architecture**: Completed architecture refactor with proper interfaces and dependency injection
- **Enhanced Error Handling**: Improved error handling and graceful fallbacks throughout codebase
- **Memory Monitoring**: Enhanced memory usage monitoring in GraphQL hooks
- **Code Quality**: Comprehensive PHPDoc documentation and WordPress coding standards compliance

## [2.1.0] - 2025-09-21

### Major Refactor

- **Complete Modular Architecture**: Major refactor to modular, maintainable codebase
- **Performance Optimizations**: Enhanced memory management and processing efficiency
- **Interface Implementation**: Added proper hook manager interfaces for better code organization
- **Enhanced Documentation**: Comprehensive code documentation and inline comments

### Enhanced

- **Multisite Support**: Improved multisite compatibility and network settings management
- **Admin Interface**: Better form layouts and conditional field display
- **Error Handling**: Robust error handling with debug logging capabilities
- **Release Process**: Enhanced automated release process with better validation

### Technical

- **Code Organization**: Separated concerns with proper class structure and inheritance
- **Memory Management**: Implemented memory usage monitoring and protection mechanisms
- **Hook Management**: Improved GraphQL and REST API hook registration system
- **Build Process**: Enhanced production build validation and self-contained packages

## [2.0.8] - 2025-01-17

### Changed

- **Emergency Mode**: Temporarily disabled GraphQL and REST API integrations due to 512MB memory exhaustion issue during hook registration
- **Admin-Only Functionality**: Plugin now provides full admin interface and configuration management without API filtering
- **Systematic Debugging**: Implemented comprehensive step-by-step initialization logging to isolate memory leak sources

### Technical Notes

- Root cause identified: Both REST and GraphQL hook registration processes cause excessive memory consumption
- Memory leak isolated to API integration components, not core plugin functionality
- Admin interface, options management, and core features remain fully functional

## [2.0.7] - 2025-01-17

### Fixed

- **Memory Leak Resolution**: Fixed critical memory exhaustion issue in GraphQL hooks registration by replacing `serialize($callback)` with lightweight signature approach
- **Hook Registration Optimization**: Implemented memory-efficient callback signature generation that prevents 512MB memory usage during hook registration
- **Performance**: Eliminated expensive object serialization in duplicate hook prevention system

## [2.0.6] - 2025-01-17

### Fixed

- **Dependency Loading**: Fixed "Class WPGraphQL_Content_Filter_Cache not found" error by ensuring cache class is always loaded when needed
- **Memory Optimization**: Preserved memory optimizations while fixing critical dependency issue in GraphQL hooks initialization

## [2.0.5] - 2025-01-17

### Fixed

- **Memory Optimization**: Significant memory usage improvements through conditional module loading and on-demand class initialization
- **Performance**: Reduced memory footprint by loading only necessary components based on active features
- **Hook Registration**: Prevented duplicate hook registration to avoid infinite loops
- **Release Process**: Enhanced release script with better GitHub integration and error handling

## [1.0.9] - 2025-08-30

### Enhanced

- **Consistent UI Design**: REST API setting now prominently displayed at the top of BOTH site-level and network-level admin pages
- **Site-Level Admin Improvements**: Added dedicated "API Targets" section with enhanced visual styling for REST API setting
- **Better Organization**: Clear visual separation between API configuration and content filtering settings
- **Enhanced Descriptions**: More detailed explanations about the difference between REST API and WPGraphQL filtering
- **Visual Hierarchy**: Improved section headers, styling, and layout consistency across both admin interfaces

### Added

- **Custom REST API Callback**: New dedicated callback method for prominent REST API setting display on site-level admin
- **Section Headers**: Clear visual organization with "API Targets" and "Content Filtering Settings" sections
- **Enhanced Styling**: Background highlighting, larger checkboxes, and improved typography for important settings

## [1.0.8] - 2025-08-30

### Enhanced

- **Network Admin Settings**: All specific settings (Apply to Content Field, Apply to Excerpt Field, Apply to REST API, Preserve Line Breaks, etc.) are now fully configurable at the network level for network-wide defaults
- **Improved UI Layout**: Restructured settings page with "Apply to REST API" prominently positioned at the top as a separate API target section
- **Better Visual Hierarchy**: REST API setting now clearly distinguished from content field settings with enhanced styling and clearer labeling
- **Complete Settings Parity**: Network admin now has complete feature parity with site-level settings

### Added

- **API Targets Section**: New dedicated section for API-related settings with clear visual separation
- **Enhanced Descriptions**: More detailed explanations for REST API functionality

## [1.0.7] - 2025-08-30

### Fixed

- **Network Admin Access**: Fixed network-level settings not being accessible in network admin
- **Network Options Initialization**: Added automatic initialization of network options when not present
- **Admin Menu Registration**: Improved admin menu registration for both multisite and single-site installations
- **Constants Usage**: Updated remaining hardcoded option names to use constants

### Enhanced

- **Cross-Platform Compatibility**: Network admin settings now available in both multisite and single-site WordPress
- **Mu-Plugin Support**: Better support for installations where this is used as a must-use plugin
- **Option Management**: More robust option handling and initialization

## [1.0.6] - 2025-08-30

### Added

- **WPGraphQL Dependency Check**: Plugin now requires WPGraphQL to be installed and activated
- **Version Compatibility Check**: Ensures WPGraphQL version 1.0.0 or higher is installed
- **Enhanced Error Handling**: Automatic plugin deactivation if dependencies are not met
- **User-Friendly Notices**: Clear admin notices explaining dependency requirements

### Changed

- **Plugin Description**: Updated to clearly indicate WPGraphQL requirement
- **Plugin Headers**: Added "Requires Plugins: wp-graphql" header for WordPress 6.5+ compatibility
- **Initialization Logic**: Plugin only initializes when dependencies are satisfied

### Security

- **Safe Deactivation**: Plugin safely deactivates itself if WPGraphQL is not available

## [1.0.5] - 2025-08-30

### Added

- **Plugin Action Links**: Added "Settings" links to the Installed Plugins page for easier access to plugin settings
- **REST API Control**: Added option to enable/disable content filtering for WordPress REST API
- **Enhanced UI**: Plugin settings are now directly accessible from both regular and network admin plugin pages

### Changed

- REST API filtering is now optional and can be controlled via plugin settings
- Plugin action links provide direct access to settings from the Plugins page

### Optimized

- **Code Constants**: Added constants for option names to reduce repetition and improve maintainability
- **Helper Methods**: Created `get_site_data()` helper method to reduce duplicate database calls
- **Version Consistency**: Fixed version number consistency between plugin header and internal constants
- **Performance**: Optimized option retrieval patterns throughout the codebase

## [1.0.4] - 2025-08-30

### Fixed

- **Admin Menu Visibility**: Fixed issue where admin menus weren't showing properly in multisite environments
- **Settings Registration**: Simplified logic for when site-level settings should be available
- **Network Settings Access**: Ensured network admin settings are always accessible when appropriate
- **Site Override Logic**: Fixed overly restrictive conditions that prevented site menus from appearing

### Changed

- Simplified admin menu and settings registration logic to be more permissive by default
- Site-level settings now show unless network settings are explicitly enforced
- Removed complex logic that was preventing proper menu display

## [1.0.3] - 2025-08-30

### Added

- **Network Settings Synchronization**:
  - Network settings are now automatically synced to all sites when changed
  - Manual sync button in network admin for immediate synchronization
  - Override tracking system to distinguish between inherited and overridden settings
  - AJAX-powered sync functionality with progress feedback

- **Enhanced Admin Interface**:
  - Visual indicators showing which settings are inherited vs overridden
  - Clear distinction between network and site-level configurations
  - Improved user experience with override status display
  - Enhanced error handling and user feedback

- **Improved Data Structure**:
  - Site options now store override tracking metadata
  - Legacy option format migration for existing installations
  - Better separation of network and site-specific data
  - Enhanced validation and sanitization

### Changed

- **Multisite Architecture Overhaul**:
  - Refactored option calculation logic for better inheritance handling
  - Improved activation process for new and existing sites
  - Enhanced cache management for multisite environments
  - Better handling of network setting changes

- **Site Option Storage**:
  - New format: `{options: {...}, overrides: {...}, last_sync: timestamp}`
  - Automatic migration from legacy format
  - Improved data integrity and consistency

### Fixed

- Network settings now properly propagate to all sites immediately
- Override detection works correctly for all setting types
- Cache invalidation properly handles multisite scenarios
- Activation process correctly initializes all sites with network settings

## [1.0.2] - 2025-08-30

### Added

- **Database Cleanup Feature**:
  - Optional plugin data removal during uninstall
  - User-controlled cleanup with clear warnings
  - Safe deletion of plugin-specific data only
  - Multisite-aware cleanup functionality

## [1.0.1] - 2025-08-30

### Added

- **Performance Optimizations**:
  - Options caching system for improved performance
  - Cache invalidation hooks for option updates
  - Early return optimizations in content filtering
  - Reduced database queries through smart caching

- **Enhanced Multisite Support**:
  - Automatic new site activation handling
  - Improved option inheritance logic
  - Network-wide cache invalidation
  - Better error handling and logging

- **Plugin Architecture Improvements**:
  - Version upgrade detection and handling
  - Enhanced error logging with context
  - Optimized activation process for large networks
  - Improved memory usage and performance

### Changed

- **Optimized Content Filtering**:
  - More efficient filter application logic
  - Better error handling with detailed logging
  - Improved performance for repeated filtering operations

- **Enhanced Admin Interface**:
  - Better cache management for admin operations
  - Improved option saving with cache invalidation
  - More responsive settings updates

### Fixed

- Performance issues with repeated option fetching
- Cache consistency across multisite networks
- Memory optimization for large content filtering operations
- Improved error handling in edge cases

## [1.0.0] - 2025-08-30

### Added

- Initial release of WPGraphQL Content Filter
- Multiple content filtering modes:
  - None (no filtering)
  - Strip All HTML tags
  - Convert HTML to Markdown
  - Custom allowed HTML tags
- Dual API support:
  - WPGraphQL integration (requires WPGraphQL plugin)
  - WordPress REST API integration (works out of the box)
- Custom post type support:
  - Automatic detection of WPGraphQL-registered post types
  - Support for all public post types in REST API
- Admin settings page with comprehensive configuration options:
  - Filter mode selection
  - Field targeting (content/excerpt)
  - Markdown conversion options
  - Custom HTML tag allowlists
- Performance optimizations:
  - Cached plugin options
  - Efficient content processing
  - Error handling with fallbacks
- Security enhancements:
  - Input sanitization and validation
  - Capability checks for admin access
  - Proper nonce handling
- WordPress.org repository preparation:
  - Comprehensive readme.txt
  - Proper activation/deactivation hooks
  - Uninstall cleanup script
- Multisite network support:
  - Network-level configuration interface
  - Site-level override permissions
  - Centralized settings management
  - Multisite-aware activation/deactivation
- Developer-friendly features:
  - Modular architecture
  - Documented hooks and filters
  - Extensible design patterns
- Internationalization support:
  - Text domain: wpgraphql-content-filter
  - Translation-ready strings
- GPL v2+ licensing with proper headers

### Technical Details

- Minimum PHP version: 7.4
- Minimum WordPress version: 5.0
- Tested up to WordPress 6.6
- Optional dependency: WPGraphQL plugin (for GraphQL functionality)
- No database tables created
- Options stored as single database option
- Clean uninstall with complete data removal
