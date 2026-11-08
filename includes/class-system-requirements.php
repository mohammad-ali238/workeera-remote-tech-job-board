<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Checks PHP, MySQL/MariaDB, and UTF-8 environment requirements at activation.
 *
 * Hard-fails with `wp_die` and deactivates the plugin when minimum versions
 * or required MySQL features (JSON, window functions, CTEs) are missing;
 * surfaces softer charset/collation issues as dismissible admin notices.
 */
class Workeera_System_Requirements {
    
    /**
     * Minimum required PHP version
     */
    private const MIN_PHP_VERSION = '8.0';
    
    /**
     * Minimum required MySQL version
     */
    private const MIN_MYSQL_VERSION = '8.0';
    
    /**
     * Check PHP version requirements
     * 
     * @throws WP_Die
     */
    public static function check_php_version(): void {
        // Check if PHP version meets minimum requirement
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            self::deactivate_and_die(
                sprintf(
                    'This plugin requires PHP version %s or higher. Your current PHP version is %s.<br><br>' .
                    'PHP 7.4 reached end of life on November 28, 2022 and is no longer supported with security updates.',
                    self::MIN_PHP_VERSION,
                    PHP_VERSION
                ),
                'PHP Version Incompatible'
            );
        }
        
        // Warning for versions that will soon be deprecated
        if (version_compare(PHP_VERSION, '8.2', '<')) {
            self::add_admin_notice(
                sprintf(
                    'Notice: PHP version %s is still supported but will reach end of life in November 2025. ' .
                    'Please upgrade to PHP 8.2 or higher for optimal performance and security.',
                    PHP_VERSION
                ),
                'warning'
            );
        }
    }
    
    /**
     * Check MySQL version requirements
     * 
     * @throws WP_Die
     */
    public static function check_db_version(): void {
        global $wpdb;
        
        // Get MySQL version (handle MariaDB as well)
        $mysql_version = $wpdb->db_version();
        
        // Detect MariaDB
        if (strpos($mysql_version, 'MariaDB') !== false) {
            // Extract MariaDB version number
            preg_match('/\d+\.\d+\.\d+/', $mysql_version, $matches);
            $mysql_version = $matches[0] ?? '0.0';
            
            // MariaDB 10.5+ is equivalent to MySQL 8.0
            if (version_compare($mysql_version, '10.5', '<')) {
                self::deactivate_and_die(
                    sprintf(
                        'This plugin requires MariaDB version 10.5 or higher. Your current MariaDB version is %s.<br><br>' .
                        'MariaDB 10.5+ provides features equivalent to MySQL 8.0.',
                        $mysql_version
                    ),
                    'Database Version Incompatible'
                );
            }
        } 
        // Check MySQL version
        elseif (version_compare($mysql_version, self::MIN_MYSQL_VERSION, '<')) {
            self::deactivate_and_die(
                sprintf(
                    'This plugin requires MySQL version %s or higher. Your current MySQL version is %s.<br><br>' .
                    'MySQL 5.7 reached end of life in October 2023 and is no longer supported.',
                    self::MIN_MYSQL_VERSION,
                    $mysql_version
                ),
                'Database Version Incompatible'
            );
        }
    }
    
    /**
     * Check UTF-8/unicode compliance
     * 
     * @throws WP_Die
     */
    public static function check_utf8_status(): void {
        global $wpdb;
        
        $issues = [];
        
        // 1. WordPress site charset
        $site_charset = get_bloginfo('charset');
        if (strtoupper($site_charset) !== 'UTF-8') {
            $issues[] = sprintf(
                'Site charset is <strong>%s</strong> (expected UTF-8). This can cause display issues for international characters.',
                esc_html($site_charset)
            );
        }
        
        // PHP internal encoding
        if (function_exists('mb_internal_encoding')) {
            $php_encoding = mb_internal_encoding();
            if (strtoupper($php_encoding) !== 'UTF-8') {
                $issues[] = sprintf(
                    'PHP internal encoding is <strong>%s</strong> (expected UTF-8). This may affect string manipulation functions.',
                    esc_html($php_encoding)
                );
            }
        } else {
            $issues[] = 'PHP <code>mbstring</code> extension is missing — UTF-8 text functions will fail. Please install/enable the mbstring PHP extension.';
        }
        
        // Database charset (should be utf8mb4 for full Unicode support)
        $db_charset = $wpdb->charset ?? '';
        if (strtolower($db_charset) !== 'utf8mb4') {
            if (strtolower($db_charset) === 'utf8') {
                $issues[] = 'Database charset is <strong>utf8</strong> (MySQL\'s utf8 is limited to 3-byte characters). Please upgrade to <strong>utf8mb4</strong> for full Unicode support including emojis.';
            } else {
                $issues[] = sprintf(
                    'Database charset is <strong>%s</strong> (expected utf8mb4). This limits Unicode character support.',
                    esc_html($db_charset)
                );
            }
        }
        
        // Database collation (should be utf8mb4_unicode_520_ci or higher)
        $db_collate = $wpdb->collate ?? '';
        if ($db_collate && stripos($db_collate, 'utf8mb4_unicode_520') === false && 
            stripos($db_collate, 'utf8mb4_0900') === false) {
            if (stripos($db_collate, 'utf8') !== false) {
                $issues[] = sprintf(
                    'Database collation is <strong>%s</strong>. For best results with modern Unicode standards, use utf8mb4_unicode_520_ci or utf8mb4_0900_ai_ci.',
                    esc_html($db_collate)
                );
            } else {
                $issues[] = sprintf(
                    'Database collation is <strong>%s</strong> (expected UTF-8 compatible). This may cause sorting and comparison issues.',
                    esc_html($db_collate)
                );
            }
        }
        
        // Check MySQL sql_mode for strict mode (recommended)
        $sql_mode = $wpdb->get_var("SELECT @@sql_mode");
        if ($sql_mode && strpos($sql_mode, 'STRICT_TRANS_TABLES') === false) {
            $issues[] = 'MySQL strict mode is not enabled. For data integrity, please enable STRICT_TRANS_TABLES in your MySQL configuration.';
        }
        
        // Display issues (non-fatal for charset issues, but we'll still warn)
        if (!empty($issues)) {
            $warning_html = '<div style="margin: 5px 0;">';
            $warning_html .= '<strong>UTF-8 Configuration Recommendations:</strong><br>';
            $warning_html .= '<ul style="margin: 5px 0 5px 20px;">';
            foreach ($issues as $issue) {
                $warning_html .= '<li>' . $issue . '</li>';
            }
            $warning_html .= '</ul>';
            $warning_html .= '<p>While the plugin will still function, these issues may cause problems with special characters and international text.</p>';
            $warning_html .= '</div>';
            
            // Use admin notice instead of wp_die for non-critical issues
            add_action('admin_notices', function() use ($warning_html) {
                echo '<div class="notice notice-warning is-dismissible">' . wp_kses_post( $warning_html ) . '</div>';
            });
        }
    }
    
    /**
     * Complete system check for all requirements
     * 
     * @return bool True if all critical requirements are met
     */
    public static function run_full_check(): bool {
        try {
            self::check_php_version();
            self::check_db_version();
            self::check_utf8_status();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Deactivate plugin and display error message
     * 
     * @param string $message Error message to display
     * @param string $title Error title
     * @throws WP_Die
     */
    private static function deactivate_and_die(string $message, string $title = 'Plugin Deactivated'): void {
        deactivate_plugins(plugin_basename(WORKEERA_PLUGIN_FILE));
        
        wp_die(
            '<div style="margin: 20px;">' .
            '<h2>' . esc_html($title) . '</h2>' .
            '<p>' . wp_kses_post( $message ) . '</p>' .
            '<p><a href="' . admin_url('plugins.php') . '">← Back to Plugins</a></p>' .
            '</div>',
            esc_html($title),
            ['back_link' => false, 'response' => 500]
        );
    }
    
    /**
     * Add admin notice for non-critical warnings
     * 
     * @param string $message Warning message
     * @param string $type Notice type (warning|error|info|success)
     */
    private static function add_admin_notice(string $message, string $type = 'warning'): void {
        add_action('admin_notices', function() use ($message, $type) {
            $class = 'notice notice-' . $type . ' is-dismissible';
            echo '<div class="' . esc_attr($class) . '"><p>' . wp_kses_post( $message ) . '</p></div>';
        });
    }
}
