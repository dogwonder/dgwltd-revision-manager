<?php
/**
 * Plugin Name:         DGW Revision Manager
 * Plugin URI:          https://dgw.ltd/
 * Description:         Modern WordPress revision management with timeline visualization and enhanced editor integration. Built with @wordpress/scripts and React components.
 * Version:             1.0.0
 * Requires at least:   6.0
 * Requires PHP:        8.0
 * Author:              DGW.ltd
 * Author URI:          https://dgw.ltd/
 * Text Domain:         dgwltd-revision-manager
 * Domain Path:         /languages
 * Network:             false
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package DGW\RevisionManager
 * @since 1.0.0
 */

declare(strict_types=1);

namespace DGW\RevisionManager;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('DGW_REVISION_MANAGER_VERSION', '1.0.0');
define('DGW_REVISION_MANAGER_PLUGIN_FILE', __FILE__);
define('DGW_REVISION_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DGW_REVISION_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DGW_REVISION_MANAGER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Minimum requirements check
if (version_compare(PHP_VERSION, '8.0', '<')) {
    add_action('admin_notices', function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'DGW Revision Manager requires PHP 8.0 or higher. Please update your PHP version.',
                'dgwltd-revision-manager'
            )
        );
    });
    return;
}

if (version_compare($GLOBALS['wp_version'], '6.0', '<')) {
    add_action('admin_notices', function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'DGW Revision Manager requires WordPress 6.0 or higher. Please update your WordPress installation.',
                'dgwltd-revision-manager'
            )
        );
    });
    return;
}

/**
 * Load required files manually
 *
 * @since 1.0.0
 * @return void
 */
function load_required_files(): void {
    $required_files = [
        'includes/Core/Plugin.php',
        'includes/Core/Assets.php',
        'includes/API/RevisionController.php',
        'includes/Admin/EditorPanel.php',
        'includes/Frontend/RevisionFilter.php',
        'includes/Utils/RevisionHelper.php',
    ];

    foreach ($required_files as $file) {
        $file_path = DGW_REVISION_MANAGER_PLUGIN_DIR . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}

/**
 * Initialize the plugin
 *
 * @since 1.0.0
 * @return void
 */
function init_plugin(): void {
    try {
        // Load required files
        load_required_files();

        // Initialize the plugin
        if (class_exists(__NAMESPACE__ . '\Core\Plugin')) {
            $plugin = Core\Plugin::get_instance();
            $plugin->run();
        } else {
            throw new \Exception('Plugin core class not found');
        }
    } catch (\Exception $e) {
        add_action('admin_notices', function () use ($e): void {
            printf(
                '<div class="notice notice-error"><p><strong>DGW Revision Manager Error:</strong> %s</p></div>',
                esc_html($e->getMessage())
            );
        });
        error_log('DGW Revision Manager initialization error: ' . $e->getMessage());
    }
}

// Initialize plugin on plugins_loaded hook
add_action('plugins_loaded', __NAMESPACE__ . '\init_plugin');

/**
 * Plugin activation hook
 *
 * @since 1.0.0
 * @return void
 */
function activate_plugin(): void {
    try {
        // Flush rewrite rules for potential REST API routes
        flush_rewrite_rules();

        // Set default options
        add_option('dgwltd_revision_manager_settings', [
            'timeline_limit' => 6,
            'default_mode' => 'open',
            'enable_timeline' => true
        ]);
    } catch (\Exception $e) {
        // Deactivate the plugin if activation fails
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html($e->getMessage()),
            esc_html__('Plugin Activation Error', 'dgwltd-revision-manager'),
            ['back_link' => true]
        );
    }
}
register_activation_hook(__FILE__, __NAMESPACE__ . '\activate_plugin');

/**
 * Plugin deactivation hook
 *
 * @since 1.0.0
 * @return void
 */
function deactivate_plugin(): void {
    try {
        // Clean up rewrite rules
        flush_rewrite_rules();
    } catch (\Exception $e) {
        error_log('DGW Revision Manager deactivation error: ' . $e->getMessage());
    }
}
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\deactivate_plugin');