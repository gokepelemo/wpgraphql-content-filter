# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
