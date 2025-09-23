<?php
/**
 * Assets Manager Class
 *
 * @package DGW\RevisionManager\Core
 * @since 1.0.0
 */

declare(strict_types=1);

namespace DGW\RevisionManager\Core;

/**
 * Assets Manager Class
 *
 * Handles loading of JavaScript and CSS assets for the plugin.
 *
 * @since 1.0.0
 */
class Assets {

    /**
     * Plugin name
     *
     * @since 1.0.0
     * @var string
     */
    private string $plugin_name;

    /**
     * Plugin version
     *
     * @since 1.0.0
     * @var string
     */
    private string $version;

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param string $plugin_name The plugin name.
     * @param string $version The plugin version.
     */
    public function __construct(string $plugin_name, string $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Enqueue editor assets
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_editor_assets(): void {
        $asset_file = DGW_REVISION_MANAGER_PLUGIN_DIR . 'build/editor.asset.php';

        if (!file_exists($asset_file)) {
            return;
        }

        $asset = include $asset_file;

        wp_enqueue_script(
            $this->plugin_name . '-editor',
            DGW_REVISION_MANAGER_PLUGIN_URL . 'build/editor.js',
            $asset['dependencies'],
            $asset['version'],
            false
        );

        wp_enqueue_style(
            $this->plugin_name . '-editor',
            DGW_REVISION_MANAGER_PLUGIN_URL . 'build/editor.css',
            [],
            $asset['version']
        );

        // Localize script with necessary data
        wp_localize_script(
            $this->plugin_name . '-editor',
            'dgwRevisionManager',
            [
                'apiUrl' => rest_url('dgw-revision-manager/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'postId' => get_the_ID(),
                'settings' => get_option('dgwltd_revision_manager_settings', []),
                'strings' => [
                    'revisionStatus' => __('Revision Status', 'dgwltd-revision-manager'),
                    'timeline' => __('Timeline', 'dgwltd-revision-manager'),
                    'current' => __('Current', 'dgwltd-revision-manager'),
                    'pending' => __('Pending', 'dgwltd-revision-manager'),
                    'past' => __('Past', 'dgwltd-revision-manager'),
                    'open' => __('Open', 'dgwltd-revision-manager'),
                    'locked' => __('Locked', 'dgwltd-revision-manager'),
                    'setAsCurrent' => __('Set as Current', 'dgwltd-revision-manager'),
                    'loading' => __('Loading...', 'dgwltd-revision-manager'),
                ]
            ]
        );
    }

    /**
     * Enqueue revision editor assets
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_revision_editor_assets(): void {
        $asset_file = DGW_REVISION_MANAGER_PLUGIN_DIR . 'build/revision-editor.asset.php';

        if (!file_exists($asset_file)) {
            return;
        }

        $asset = include $asset_file;

        wp_enqueue_script(
            $this->plugin_name . '-revision-editor',
            DGW_REVISION_MANAGER_PLUGIN_URL . 'build/revision-editor.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            $this->plugin_name . '-revision-editor',
            DGW_REVISION_MANAGER_PLUGIN_URL . 'build/revision-editor.css',
            [],
            $asset['version']
        );

        // Get current revision ID
        $revision_id = isset($_GET['revision']) ? (int) $_GET['revision'] : 0;
        $revision = wp_get_post_revision($revision_id);
        $post_id = $revision ? $revision->post_parent : 0;

        // Localize script with revision data
        wp_localize_script(
            $this->plugin_name . '-revision-editor',
            'dgwRevisionEditor',
            [
                'apiUrl' => rest_url('dgw-revision-manager/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'revisionId' => $revision_id,
                'postId' => $post_id,
                'canManageRevisions' => current_user_can('edit_posts'),
                'strings' => [
                    'setAsCurrent' => __('Set as Current', 'dgwltd-revision-manager'),
                    'revisionTimeline' => __('Revision Timeline', 'dgwltd-revision-manager'),
                    'confirmSetCurrent' => __('Are you sure you want to set this revision as the current version?', 'dgwltd-revision-manager'),
                    'success' => __('Revision set as current successfully!', 'dgwltd-revision-manager'),
                    'error' => __('Error setting revision as current.', 'dgwltd-revision-manager'),
                ]
            ]
        );
    }
}