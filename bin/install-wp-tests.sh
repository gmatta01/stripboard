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
  if [ "latest" == "$1" ]; then
    local url="https://wordpress.org/latest.tar.gz"
  else
    local url="https://wordpress.org/wordpress-$1.tar.gz"
  fi
  curl -sL "$url" -o /tmp/wordpress.tar.gz
  tar -xzf /tmp/wordpress.tar.gz -C /tmp --strip-components=1
}

install_db() {
  local mysql_admin_command
  mysql_admin_command="mysqladmin -u $DB_USER -p$DB_PASS create $DB_NAME"
  eval $mysql_admin_command
}

install_wp() {
  if [ ! -f "$WP_CORE_DIR/wp-includes/version.php" ]; then
    rm -rf "$WP_CORE_DIR"
    mkdir -p "$WP_CORE_DIR"
    download "$WP_VERSION"
    mv /tmp/wordpress/* "$WP_CORE_DIR/"
    mv /tmp/wordpress/.* "$WP_CORE_DIR/" 2>/dev/null || true
  fi

  if [ ! -f "$WP_CORE_DIR/wp-includes/version.php" ]; then
    echo "WordPress download failed"
    exit 1
  fi
}

install_test_suite() {
  if [ ! -d "$WP_TESTS_DIR" ]; then
    mkdir -p "$WP_TESTS_DIR"

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
  fi
}

if [ "$SKIP_DB_CREATE" != "true" ]; then
  install_db
fi

install_wp
install_test_suite
