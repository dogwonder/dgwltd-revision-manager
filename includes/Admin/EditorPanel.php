<?php
/**
 * Editor Panel Manager
 *
 * @package DGW\RevisionManager\Admin
 * @since 1.0.0
 */

declare(strict_types=1);

namespace DGW\RevisionManager\Admin;

/**
 * Editor Panel Manager
 *
 * Handles registration of editor panel and meta boxes.
 *
 * @since 1.0.0
 */
class EditorPanel {

    /**
     * Initialize the editor panel
     *
     * @since 1.0.0
     * @return void
     */
    public function init(): void {
        add_action('add_meta_boxes', [$this, 'add_revision_meta_boxes']);
        add_action('save_post', [$this, 'save_revision_meta']);
    }

    /**
     * Add revision meta boxes
     *
     * @since 1.0.0
     * @return void
     */
    public function add_revision_meta_boxes(): void {
        $post_types = get_post_types(['public' => true], 'names');

        foreach ($post_types as $post_type) {
            add_meta_box(
                'dgw-revision-manager',
                __('Revision Manager', 'dgwltd-revision-manager'),
                [$this, 'render_revision_meta_box'],
                $post_type,
                'side',
                'high'
            );
        }
    }

    /**
     * Render revision meta box
     *
     * @since 1.0.0
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function render_revision_meta_box(\WP_Post $post): void {
        // Check revision mode
        $revision_mode = get_post_meta($post->ID, '_dgw_revision_mode', true) ?: 'open';

        echo '<div id="dgw-revision-manager-meta-box">';

        if ($revision_mode === 'open') {
            echo '<p>' . esc_html__('This post uses standard WordPress revisions.', 'dgwltd-revision-manager') . '</p>';
            echo '<p class="description">' . esc_html__('Revision management is available in the editor sidebar if needed.', 'dgwltd-revision-manager') . '</p>';
        } else {
            // Add nonce field for any future form handling
            wp_nonce_field('dgw_revision_manager_meta', 'dgw_revision_manager_nonce');

            // Get current status for display
            $current_status = get_post_meta($post->ID, '_dgw_revision_status', true) ?: 'open';

            echo '<p>' . esc_html__('Revision management is handled by the sidebar panel. Use the "Revision Manager" option in the editor sidebar.', 'dgwltd-revision-manager') . '</p>';
            echo '<p class="description">' . sprintf(
                esc_html__('Current status: %s', 'dgwltd-revision-manager'),
                '<strong>' . esc_html(ucfirst($current_status)) . '</strong>'
            ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Save revision meta data
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @return void
     */
    public function save_revision_meta(int $post_id): void {
        // No longer needed since revision status is managed via REST API
        // This method is kept for potential future meta box functionality
        return;
    }
}