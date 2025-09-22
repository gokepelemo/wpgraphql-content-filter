#!/bin/bash

# WPGraphQL Content Filter - Production Build Validator
# Validates that the plugin build is self-contained and production-ready

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to validate production build
validate_production_build() {
    local build_dir=${1:-"."}
    
    print_status "Validating production build in: $build_dir"
    
    # Check for development files that should not be in production
    local dev_files=(
        "composer.json"
        "composer.lock"
        "phpunit.xml"
        "phpunit.xml.dist"
        "phpcs.xml"
        "phpstan.neon"
        ".phpunit.result.cache"
        "tests/"
        "build/"
        "dist/"
        "releases/"
        "coverage/"
        "node_modules/"
        ".git/"
        ".gitignore"
        "release.sh"
    )
    
    local found_dev_files=()
    
    for file in "${dev_files[@]}"; do
        if [ -e "$build_dir/$file" ]; then
            found_dev_files+=("$file")
        fi
    done
    
    if [ ${#found_dev_files[@]} -ne 0 ]; then
        print_error "Development files found in production build:"
        for file in "${found_dev_files[@]}"; do
            echo "  - $file"
        done
        return 1
    fi
    
    print_success "No development files found in production build"
    
    # Check for required production files
    local required_files=(
        "wpgraphql-content-filter.php"
        "readme.txt"
        "includes/"
        "languages/"
    )
    
    local missing_files=()
    
    for file in "${required_files[@]}"; do
        if [ ! -e "$build_dir/$file" ]; then
            missing_files+=("$file")
        fi
    done
    
    if [ ${#missing_files[@]} -ne 0 ]; then
        print_error "Required production files missing:"
        for file in "${missing_files[@]}"; do
            echo "  - $file"
        done
        return 1
    fi
    
    print_success "All required production files present"

    # Check for production dependencies
    if [ -d "$build_dir/vendor" ]; then
        print_status "Validating production dependencies..."

        # Check for required runtime libraries
        local required_libs=(
            "vendor/league/html-to-markdown"
            "vendor/ezyang/htmlpurifier"
            "vendor/autoload.php"
        )

        local missing_libs=()

        for lib in "${required_libs[@]}"; do
            if [ ! -e "$build_dir/$lib" ]; then
                missing_libs+=("$lib")
            fi
        done

        if [ ${#missing_libs[@]} -ne 0 ]; then
            print_warning "Some expected runtime libraries are missing:"
            for lib in "${missing_libs[@]}"; do
                echo "  - $lib"
            done
        else
            print_success "Required runtime libraries found"
        fi

        # Check that we don't have development dependencies
        local dev_libs=(
            "vendor/phpunit"
            "vendor/brain/monkey"
            "vendor/squizlabs/php_codesniffer"
            "vendor/phpstan"
        )

        local found_dev_libs=()

        for lib in "${dev_libs[@]}"; do
            if [ -d "$build_dir/$lib" ]; then
                found_dev_libs+=("$lib")
            fi
        done

        if [ ${#found_dev_libs[@]} -ne 0 ]; then
            print_error "Development dependencies found in production build:"
            for lib in "${found_dev_libs[@]}"; do
                echo "  - $lib"
            done
            return 1
        fi

        print_success "No development dependencies found in vendor directory"
    else
        print_warning "No vendor directory found - plugin may require manual dependency installation"
    fi
    
    # Check PHP syntax in production files
    print_status "Validating PHP syntax in production files..."
    
    if command -v php > /dev/null; then
        local php_files=$(find "$build_dir" -name "*.php" -type f)
        local syntax_errors=0
        
        while IFS= read -r php_file; do
            if ! php -l "$php_file" > /dev/null 2>&1; then
                print_error "PHP syntax error in: $php_file"
                syntax_errors=$((syntax_errors + 1))
            fi
        done <<< "$php_files"
        
        if [ $syntax_errors -gt 0 ]; then
            print_error "$syntax_errors PHP syntax errors found"
            return 1
        fi
        
        print_success "All PHP files have valid syntax"
    else
        print_warning "PHP not available - skipping syntax validation"
    fi
    
    # Check for WordPress plugin headers
    print_status "Validating WordPress plugin headers..."
    
    local main_file="$build_dir/wpgraphql-content-filter.php"
    if [ -f "$main_file" ]; then
        local required_headers=(
            "Plugin Name"
            "Description"
            "Version"
            "Author"
        )
        
        local missing_headers=()
        
        for header in "${required_headers[@]}"; do
            if ! grep -q "^[[:space:]]*\*[[:space:]]*$header:" "$main_file"; then
                missing_headers+=("$header")
            fi
        done
        
        if [ ${#missing_headers[@]} -ne 0 ]; then
            print_error "Missing WordPress plugin headers:"
            for header in "${missing_headers[@]}"; do
                echo "  - $header"
            done
            return 1
        fi
        
        print_success "WordPress plugin headers validated"
    else
        print_error "Main plugin file not found: $main_file"
        return 1
    fi
    
    # Check for external dependencies
    print_status "Checking for external dependencies..."
    
    local dependency_patterns=(
        "require.*vendor"
        "include.*vendor"
        "composer"
        "autoload"
    )
    
    local dependencies_found=()
    
    while IFS= read -r php_file; do
        for pattern in "${dependency_patterns[@]}"; do
            if grep -q "$pattern" "$php_file" 2>/dev/null; then
                dependencies_found+=("$php_file: $pattern")
            fi
        done
    done <<< "$(find "$build_dir" -name "*.php" -type f)"
    
    if [ ${#dependencies_found[@]} -ne 0 ]; then
        print_status "External dependencies found:"
        for dep in "${dependencies_found[@]}"; do
            echo "  - $dep"
        done

        # This is expected if we have vendor directory with production dependencies
        if [ -d "$build_dir/vendor" ]; then
            print_status "Dependencies are expected due to bundled production libraries"
        else
            print_warning "Dependencies found but no vendor directory - verify these are not missing runtime requirements"
        fi
    else
        print_success "No external dependencies detected"
    fi
    
    # Calculate build size
    if command -v du > /dev/null; then
        local build_size=$(du -sh "$build_dir" | cut -f1)
        print_status "Production build size: $build_size"
    fi
    
    # Summary
    print_success "Production build validation completed successfully"
    print_status "Build is self-contained and ready for distribution"
    
    return 0
}

# Main execution
if [ $# -eq 0 ]; then
    # Validate current directory
    validate_production_build "."
else
    # Validate specified directory
    validate_production_build "$1"
fi