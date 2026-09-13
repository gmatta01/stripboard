#!/bin/bash
# Install WordPress test suite
# Based on https://github.com/WordPress/wordpress-develop/blob/trunk/tools/install-wp-tests.sh

if [ $# -lt 4 ]; then
  echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
  exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}
SKIP_DB_CREATE=${6:-false}

WP_TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress}

set -e

download() {
  local url
  if [ "latest" == "$1" ]; then
    url="https://wordpress.org/latest.tar.gz"
  else
    url="https://wordpress.org/wordpress-$1.tar.gz"
  fi
  
  echo "Downloading WordPress from: $url"
  curl -sL "$url" -o /tmp/wordpress.tar.gz
  
  # Clean up any existing directory
  rm -rf "$WP_CORE_DIR"
  mkdir -p "$WP_CORE_DIR"
  
  # Extract to a temp dir first
  local tmp_extract="/tmp/wp-extract-$$"
  rm -rf "$tmp_extract"
  mkdir -p "$tmp_extract"
  tar -xzf /tmp/wordpress.tar.gz -C "$tmp_extract"
  
  # Move contents (should be wordpress/ directory)
  if [ -d "$tmp_extract/wordpress" ]; then
    mv "$tmp_extract/wordpress"/* "$WP_CORE_DIR/"
    mv "$tmp_extract/wordpress"/.* "$WP_CORE_DIR/" 2>/dev/null || true
  fi
  
  rm -rf "$tmp_extract"
  rm -f /tmp/wordpress.tar.gz
  
  echo "WordPress extracted to: $WP_CORE_DIR"
}

install_db() {
  local mysql_admin_command
  mysql_admin_command="mysqladmin -u $DB_USER -p$DB_PASS create $DB_NAME"
  eval $mysql_admin_command
}

install_wp() {
  if [ ! -f "$WP_CORE_DIR/wp-includes/version.php" ]; then
    download "$WP_VERSION"
  fi

  if [ ! -f "$WP_CORE_DIR/wp-includes/version.php" ]; then
    echo "WordPress download failed"
    exit 1
  fi
}

install_polyfills() {
  echo "Installing PHPUnit Polyfills..."
  cd "$WP_TESTS_DIR"
  
  # Check if polyfills are already installed
  if [ -f "vendor/yoast/phpunit-polyfills/phpunitpolyfills.php" ]; then
    echo "PHPUnit Polyfills already installed"
    return 0
  fi
  
  # Download composer if not present
  if [ ! -f "composer.phar" ]; then
    echo "Downloading Composer..."
    curl -sS https://getcomposer.org/installer | php -- --quiet
  fi
  
  # Install polyfills
  echo "Running composer install..."
  php composer.phar require --dev yoast/phpunit-polyfills:^2.0 --no-interaction --no-scripts 2>&1 || true
  
  # Verify installation
  if [ -f "vendor/yoast/phpunit-polyfills/phpunitpolyfills.php" ]; then
    echo "PHPUnit Polyfills installed successfully"
  else
    echo "WARNING: PHPUnit Polyfills installation may have failed"
    # Try alternative: install directly without composer.json
    mkdir -p vendor/yoast/phpunit-polyfills
    curl -sL "https://github.com/Yoast/PHPUnit-Polyfills/archive/refs/tags/2.0.1.tar.gz" -o /tmp/polyfills.tar.gz
    tar -xzf /tmp/polyfills.tar.gz -C /tmp
    cp -r /tmp/PHPUnit-Polyfills-2.0.1/* vendor/yoast/phpunit-polyfills/
    rm -rf /tmp/PHPUnit-Polyfills-* /tmp/polyfills.tar.gz
    echo "Polyfills installed via direct download"
  fi
}

install_test_suite() {
  # Always ensure polyfills are installed
  if [ -d "$WP_TESTS_DIR" ]; then
    install_polyfills
    return 0
  fi
  
  mkdir -p "$WP_TESTS_DIR"
  
  # Download WordPress test suite from wordpress-develop
  echo "Downloading WordPress test suite..."
  local test_suite_url="https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.tar.gz"
  curl -sL "$test_suite_url" -o /tmp/wp-develop.tar.gz
  
  local tmp_extract="/tmp/wp-develop-extract-$$"
  rm -rf "$tmp_extract"
  mkdir -p "$tmp_extract"
  tar -xzf /tmp/wp-develop.tar.gz -C "$tmp_extract"
  
  # Find the tests/phpunit directory
  local phpunit_src=$(find "$tmp_extract" -type d -name "phpunit" -path "*/tests/phpunit" | head -1)
  
  if [ -d "$phpunit_src" ]; then
    echo "Copying test suite files from $phpunit_src..."
    cp -r "$phpunit_src"/* "$WP_TESTS_DIR/"
  else
    echo "Failed to find test suite source"
    exit 1
  fi
  
  rm -rf "$tmp_extract"
  rm -f /tmp/wp-develop.tar.gz
  
  # Install polyfills
  install_polyfills

  # Set up test config
  cat > "$WP_TESTS_DIR/wp-tests-config.php" <<EOF
<?php
define( 'ABSPATH', '$WP_CORE_DIR/' );
define( 'DB_NAME', '$DB_NAME' );
define( 'DB_USER', '$DB_USER' );
define( 'DB_PASSWORD', '$DB_PASS' );
define( 'DB_HOST', '$DB_HOST' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
\$table_prefix = 'wptests_';
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
EOF
}

if [ "$SKIP_DB_CREATE" != "true" ]; then
  install_db
fi

install_wp
install_test_suite
