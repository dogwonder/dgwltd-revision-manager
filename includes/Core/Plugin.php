<?php
/**
 * Main Plugin Class
 *
 * @package DGW\RevisionManager\Core
 * @since 1.0.0
 */

declare(strict_types=1);

namespace DGW\RevisionManager\Core;

use DGW\RevisionManager\API\RevisionController;
use DGW\RevisionManager\Admin\EditorPanel;
use DGW\RevisionManager\Frontend\RevisionFilter;

/**
 * Main Plugin Class
 *
 * Singleton pattern implementation for the main plugin functionality.
 * Coordinates all plugin components and handles initialization.
 *
 * @since 1.0.0
 */
final class Plugin {

    /**
     * Plugin instance
     *
     * @since 1.0.0
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Plugin version
     *
     * @since 1.0.0
     * @var string
     */
    private string $version;

    /**
     * Plugin text domain
     *
     * @since 1.0.0
     * @var string
     */
    private string $plugin_name = 'dgwltd-revision-manager';

    /**
     * Assets manager
     *
     * @since 1.0.0
     * @var Assets|null
     */
    private ?Assets $assets = null;

    /**
     * API controller
     *
     * @since 1.0.0
     * @var RevisionController|null
     */
    private ?RevisionController $api_controller = null;

    /**
     * Editor panel manager
     *
     * @since 1.0.0
     * @var EditorPanel|null
     */
    private ?EditorPanel $editor_panel = null;

    /**
     * Frontend filter manager
     *
     * @since 1.0.0
     * @var RevisionFilter|null
     */
    private ?RevisionFilter $revision_filter = null;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->version = DGW_REVISION_MANAGER_VERSION;
    }

    /**
     * Get plugin instance
     *
     * @since 1.0.0
     * @return Plugin
     */
    public static function get_instance(): Plugin {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Run the plugin
     *
     * @since 1.0.0
     * @return void
     */
    public function run(): void {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_frontend_hooks();
        $this->define_api_hooks();
    }

    /**
     * Load plugin dependencies
     *
     * @since 1.0.0
     * @return void
     */
    private function load_dependencies(): void {
        // Initialize assets manager
        $this->assets = new Assets($this->get_plugin_name(), $this->get_version());

        // Initialize API controller
        $this->api_controller = new RevisionController();

        // Initialize editor panel
        $this->editor_panel = new EditorPanel();

        // Initialize frontend filter
        $this->revision_filter = new RevisionFilter();
    }

    /**
     * Define admin hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function define_admin_hooks(): void {
        // Asset loading
        add_action('enqueue_block_editor_assets', [$this->assets, 'enqueue_editor_assets']);

        // Editor panel hooks
        add_action('init', [$this->editor_panel, 'init']);

        // Add revision editor enhancements
        add_action('admin_enqueue_scripts', [$this, 'maybe_enqueue_revision_editor_assets']);
    }

    /**
     * Define frontend hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function define_frontend_hooks(): void {
        // Content filtering
        add_filter('the_content', [$this->revision_filter, 'filter_content'], 10, 1);
        add_filter('the_title', [$this->revision_filter, 'filter_title'], 10, 2);
        add_filter('get_the_excerpt', [$this->revision_filter, 'filter_excerpt'], 10, 2);
    }

    /**
     * Define API hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function define_api_hooks(): void {
        add_action('rest_api_init', [$this->api_controller, 'register_routes']);

        // Cache invalidation hooks
        add_action('wp_save_post_revision', [$this->api_controller, 'invalidate_revision_cache_on_save']);
        add_action('post_updated', [$this->api_controller, 'invalidate_revision_cache_on_update']);
        add_action('delete_post_revision', [$this->api_controller, 'invalidate_revision_cache_on_delete'], 10, 2);
    }

    /**
     * Maybe enqueue revision editor assets
     *
     * @since 1.0.0
     * @param string $hook The current admin page.
     * @return void
     */
    public function maybe_enqueue_revision_editor_assets(string $hook): void {
        // Debug: Log what hook we're getting
        if (WP_DEBUG) {
            error_log("DGW Revision Manager - Hook: {$hook}, GET params: " . print_r($_GET, true));
        }

        // Check if we're on the revision editor page
        // Hook for revision.php is typically just 'revision'
        if ('revision' === $hook || (isset($_GET['revision']) && is_numeric($_GET['revision']))) {
            if (WP_DEBUG) {
                error_log("DGW Revision Manager - Loading revision editor assets");
            }
            $this->assets->enqueue_revision_editor_assets();
        }
    }

    /**
     * Get plugin name
     *
     * @since 1.0.0
     * @return string
     */
    public function get_plugin_name(): string {
        return $this->plugin_name;
    }

    /**
     * Get plugin version
     *
     * @since 1.0.0
     * @return string
     */
    public function get_version(): string {
        return $this->version;
    }

    /**
     * Prevent cloning
     *
     * @since 1.0.0
     * @return void
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     *
     * @since 1.0.0
     * @return void
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }
}