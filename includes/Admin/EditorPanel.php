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
        // Add nonce field
        wp_nonce_field('dgw_revision_manager_meta', 'dgw_revision_manager_nonce');

        // Get current status
        $current_status = get_post_meta($post->ID, '_dgw_revision_status', true) ?: 'open';

        echo '<div id="dgw-revision-manager-meta-box">';
        echo '<p>' . esc_html__('This meta box will be replaced by the React component when the editor assets are loaded.', 'dgwltd-revision-manager') . '</p>';
        echo '<label for="dgw_revision_status">' . esc_html__('Status:', 'dgwltd-revision-manager') . '</label>';
        echo '<select name="dgw_revision_status" id="dgw_revision_status">';
        echo '<option value="open"' . selected($current_status, 'open', false) . '>' . esc_html__('Open', 'dgwltd-revision-manager') . '</option>';
        echo '<option value="pending"' . selected($current_status, 'pending', false) . '>' . esc_html__('Pending', 'dgwltd-revision-manager') . '</option>';
        echo '<option value="locked"' . selected($current_status, 'locked', false) . '>' . esc_html__('Locked', 'dgwltd-revision-manager') . '</option>';
        echo '</select>';
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
        // Check nonce
        if (!isset($_POST['dgw_revision_manager_nonce']) ||
            !wp_verify_nonce($_POST['dgw_revision_manager_nonce'], 'dgw_revision_manager_meta')) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save revision status
        if (isset($_POST['dgw_revision_status'])) {
            $status = sanitize_text_field($_POST['dgw_revision_status']);
            if (in_array($status, ['open', 'pending', 'locked'], true)) {
                update_post_meta($post_id, '_dgw_revision_status', $status);
            }
        }
    }
}