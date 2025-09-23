<?php
/**
 * Revision REST API Controller
 *
 * @package DGW\RevisionManager\API
 * @since 1.0.0
 */

declare(strict_types=1);

namespace DGW\RevisionManager\API;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Revision REST API Controller
 *
 * Handles REST API endpoints for revision management.
 *
 * @since 1.0.0
 */
class RevisionController extends WP_REST_Controller {

    /**
     * The namespace of this controller's route.
     *
     * @since 1.0.0
     * @var string
     */
    protected $namespace = 'dgw-revision-manager/v1';

    /**
     * The base of this controller's route.
     *
     * @since 1.0.0
     * @var string
     */
    protected $rest_base = 'revisions';

    /**
     * Register REST API routes
     *
     * @since 1.0.0
     * @return void
     */
    public function register_routes(): void {
        // Get post revisions with timeline data
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<post_id>\d+)',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_post_revisions'],
                    'permission_callback' => [$this, 'get_revisions_permissions_check'],
                    'args' => [
                        'post_id' => [
                            'description' => __('The post ID to get revisions for.', 'dgwltd-revision-manager'),
                            'type' => 'integer',
                            'required' => true,
                        ]
                    ]
                ]
            ]
        );

        // Set revision as current
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<revision_id>\d+)/set-current',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'set_revision_as_current'],
                    'permission_callback' => [$this, 'set_current_permissions_check'],
                    'args' => [
                        'revision_id' => [
                            'description' => __('The revision ID to set as current.', 'dgwltd-revision-manager'),
                            'type' => 'integer',
                            'required' => true,
                        ]
                    ]
                ]
            ]
        );

        // Update post revision status
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<post_id>\d+)/status',
            [
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [$this, 'update_post_status'],
                    'permission_callback' => [$this, 'update_status_permissions_check'],
                    'args' => [
                        'post_id' => [
                            'description' => __('The post ID to update status for.', 'dgwltd-revision-manager'),
                            'type' => 'integer',
                            'required' => true,
                        ],
                        'status' => [
                            'description' => __('The revision status to set.', 'dgwltd-revision-manager'),
                            'type' => 'string',
                            'enum' => ['open', 'pending', 'locked'],
                            'required' => true,
                        ]
                    ]
                ]
            ]
        );
    }

    /**
     * Get post revisions with timeline data
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_post_revisions(WP_REST_Request $request) {
        $post_id = (int) $request['post_id'];
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error(
                'rest_post_invalid_id',
                __('Invalid post ID.', 'dgwltd-revision-manager'),
                ['status' => 404]
            );
        }

        // Get all revisions for the post
        $revisions = wp_get_post_revisions($post_id, [
            'posts_per_page' => get_option('dgwltd_revision_manager_settings')['timeline_limit'] ?? 6,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        // Get current revision ID (this would be implemented based on your revision tracking logic)
        $current_revision_id = $this->get_current_revision_id($post_id);

        // If no current revision is set, default to the most recent revision
        if (!$current_revision_id && !empty($revisions)) {
            $most_recent = reset($revisions);
            $current_revision_id = $most_recent->ID;
            // Save this as the current revision for future reference
            update_post_meta($post_id, '_dgw_current_revision_id', $current_revision_id);
        }

        $timeline_data = [];
        $position = 0;

        foreach ($revisions as $revision) {
            $is_current = ($revision->ID === $current_revision_id);
            $status = $this->get_revision_status($revision->ID, $is_current, $current_revision_id);

            $timeline_data[] = [
                'id' => $revision->ID,
                'date' => $revision->post_date,
                'date_gmt' => $revision->post_date_gmt,
                'title' => $revision->post_title,
                'author' => get_userdata($revision->post_author)->display_name ?? '',
                'status' => $status,
                'is_current' => $is_current,
                'excerpt' => wp_trim_words($revision->post_content, 15),
                'position' => $position
            ];

            $position++;
        }

        // Get post revision status
        $post_status = get_post_meta($post_id, '_dgw_revision_status', true) ?: 'open';

        return new WP_REST_Response([
            'post_id' => $post_id,
            'post_status' => $post_status,
            'current_revision_id' => $current_revision_id,
            'timeline' => $timeline_data,
            'total_revisions' => count($timeline_data)
        ]);
    }

    /**
     * Set revision as current
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function set_revision_as_current(WP_REST_Request $request) {
        $revision_id = (int) $request['revision_id'];
        $revision = wp_get_post_revision($revision_id);

        if (!$revision) {
            return new WP_Error(
                'rest_revision_invalid_id',
                __('Invalid revision ID.', 'dgwltd-revision-manager'),
                ['status' => 404]
            );
        }

        $post_id = $revision->post_parent;
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error(
                'rest_post_invalid_id',
                __('Invalid post ID.', 'dgwltd-revision-manager'),
                ['status' => 404]
            );
        }

        // Temporarily disable revisions to prevent duplicates when switching
        remove_action('post_updated', 'wp_save_post_revision', 10);

        // Update the post with revision data (non-destructive)
        $updated_post = [
            'ID' => $post_id,
            'post_title' => $revision->post_title,
            'post_content' => $revision->post_content,
            'post_excerpt' => $revision->post_excerpt,
        ];

        $result = wp_update_post($updated_post, true);

        // Re-enable revisions
        add_action('post_updated', 'wp_save_post_revision', 10, 1);

        if (is_wp_error($result)) {
            return new WP_Error(
                'rest_cannot_update_post',
                __('Cannot update post with revision data.', 'dgwltd-revision-manager'),
                ['status' => 500]
            );
        }

        // Store the current revision ID
        update_post_meta($post_id, '_dgw_current_revision_id', $revision_id);

        // Update revision meta to mark as current
        $this->update_revision_statuses($post_id, $revision_id);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Revision set as current successfully.', 'dgwltd-revision-manager'),
            'post_id' => $post_id,
            'revision_id' => $revision_id
        ]);
    }

    /**
     * Update post revision status
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function update_post_status(WP_REST_Request $request) {
        $post_id = (int) $request['post_id'];
        $status = sanitize_text_field($request['status']);

        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error(
                'rest_post_invalid_id',
                __('Invalid post ID.', 'dgwltd-revision-manager'),
                ['status' => 404]
            );
        }

        // Update post revision status
        update_post_meta($post_id, '_dgw_revision_status', $status);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Post status updated successfully.', 'dgwltd-revision-manager'),
            'post_id' => $post_id,
            'status' => $status
        ]);
    }

    /**
     * Check permissions for getting revisions
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full data about the request.
     * @return bool True if the request has read access, false otherwise.
     */
    public function get_revisions_permissions_check(WP_REST_Request $request): bool {
        $post_id = (int) $request['post_id'];
        return current_user_can('edit_post', $post_id);
    }

    /**
     * Check permissions for setting current revision
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full data about the request.
     * @return bool True if the request has edit access, false otherwise.
     */
    public function set_current_permissions_check(WP_REST_Request $request): bool {
        $revision_id = (int) $request['revision_id'];
        $revision = wp_get_post_revision($revision_id);

        if (!$revision) {
            return false;
        }

        return current_user_can('edit_post', $revision->post_parent);
    }

    /**
     * Check permissions for updating status
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full data about the request.
     * @return bool True if the request has edit access, false otherwise.
     */
    public function update_status_permissions_check(WP_REST_Request $request): bool {
        $post_id = (int) $request['post_id'];
        return current_user_can('edit_post', $post_id);
    }

    /**
     * Get current revision ID for a post
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @return int|null The current revision ID or null if not set.
     */
    private function get_current_revision_id(int $post_id): ?int {
        $revision_id = get_post_meta($post_id, '_dgw_current_revision_id', true);
        return $revision_id ? (int) $revision_id : null;
    }

    /**
     * Get revision status based on date comparison with current revision
     *
     * @since 1.0.0
     * @param int $revision_id The revision ID.
     * @param bool $is_current Whether this is the current revision.
     * @param int $current_revision_id The current revision ID for comparison.
     * @return string The revision status.
     */
    private function get_revision_status(int $revision_id, bool $is_current, int $current_revision_id): string {
        if ($is_current) {
            return 'current';
        }

        // Get the current revision to compare dates
        $current_revision = wp_get_post_revision($current_revision_id);
        $this_revision = wp_get_post_revision($revision_id);

        if (!$current_revision || !$this_revision) {
            return 'past'; // Fallback
        }

        // Compare dates: if this revision is newer than current, it's pending
        if (strtotime($this_revision->post_date) > strtotime($current_revision->post_date)) {
            return 'pending';
        }

        return 'past';
    }

    /**
     * Update revision statuses when a new current is set
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @param int $current_revision_id The new current revision ID.
     * @return void
     */
    private function update_revision_statuses(int $post_id, int $current_revision_id): void {
        $revisions = wp_get_post_revisions($post_id);

        foreach ($revisions as $revision) {
            if ($revision->ID === $current_revision_id) {
                update_metadata('post', $revision->ID, '_dgw_revision_status', 'current');
            } else {
                // Mark others as past or pending based on date
                $status = strtotime($revision->post_date) > strtotime(get_post($current_revision_id)->post_date) ? 'pending' : 'past';
                update_metadata('post', $revision->ID, '_dgw_revision_status', $status);
            }
        }
    }
}