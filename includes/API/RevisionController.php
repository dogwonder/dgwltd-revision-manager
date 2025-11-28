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
                        ],
                        'limit' => [
                            'description' => __('Number of revisions to return. Default is timeline_limit setting.', 'dgwltd-revision-manager'),
                            'type' => 'integer',
                            'required' => false,
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

        // Update post revision mode
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<post_id>\d+)/mode',
            [
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [$this, 'update_revision_mode'],
                    'permission_callback' => [$this, 'update_mode_permissions_check'],
                    'args' => [
                        'post_id' => [
                            'description' => __('The post ID to update revision mode for.', 'dgwltd-revision-manager'),
                            'type' => 'integer',
                            'required' => true,
                        ],
                        'mode' => [
                            'description' => __('The revision mode to set.', 'dgwltd-revision-manager'),
                            'type' => 'string',
                            'enum' => ['open', 'pending'],
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

        // Get post revision mode (per-post setting)
        $revision_mode = get_post_meta($post_id, '_dgw_revision_mode', true) ?: 'open';

        // In "open" mode, don't interfere with WordPress - return minimal data
        if ($revision_mode === 'open') {
            return new WP_REST_Response([
                'post_id' => $post_id,
                'revision_mode' => $revision_mode,
                'current_revision_id' => null,
                'timeline' => [],
                'total_revisions' => 0
            ]);
        }

        // Only for "pending" mode - check cache first
        $cached_data = $this->get_cached_revision_data($post_id);
        if ($cached_data !== null) {
            return new WP_REST_Response($cached_data);
        }

        // Cache miss - build revision data and cache it
        // Get all revisions for the post
        $limit = $request->get_param('limit') ?: (get_option('dgwltd_revision_manager_settings')['timeline_limit'] ?? 6);

        $revisions = wp_get_post_revisions($post_id, [
            'posts_per_page' => $limit,
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

        // Prepare response data
        $response_data = [
            'post_id' => $post_id,
            'revision_mode' => $revision_mode,
            'current_revision_id' => $current_revision_id,
            'timeline' => $timeline_data,
            'total_revisions' => count($timeline_data)
        ];

        // Cache the data for future requests
        $this->set_revision_data_cache($post_id, $response_data);

        return new WP_REST_Response($response_data);
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

        // First get the revision to find the post ID
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

        // Double-validate using our validation helper for consistency
        $validated_revision = $this->validate_revision_for_post($revision_id, $post_id);

        if (!$validated_revision) {
            return new WP_Error(
                'rest_revision_validation_failed',
                __('Revision validation failed. The revision may have been deleted or corrupted.', 'dgwltd-revision-manager'),
                ['status' => 400]
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

        // Invalidate cache since revision data has changed
        $this->invalidate_revision_cache($post_id);

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
     * Update post revision mode
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function update_revision_mode(WP_REST_Request $request) {
        $post_id = (int) $request['post_id'];
        $mode = sanitize_text_field($request->get_param('mode'));

        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error(
                'rest_post_invalid_id',
                __('Invalid post ID.', 'dgwltd-revision-manager'),
                ['status' => 404]
            );
        }

        // Get previous mode for audit logging
        $previous_mode = get_post_meta($post_id, '_dgw_revision_mode', true) ?: 'open';

        // Update post revision mode
        update_post_meta($post_id, '_dgw_revision_mode', $mode);

        // If switching to "open" mode, clean up any revision tracking meta
        if ($mode === 'open') {
            delete_post_meta($post_id, '_dgw_current_revision_id');
            delete_post_meta($post_id, '_dgw_revision_status');
        }

        // Audit log for revision mode changes
        $this->log_revision_mode_change($post_id, $previous_mode, $mode, $post->post_title);

        // Invalidate cache since revision mode has changed
        $this->invalidate_revision_cache($post_id);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Revision mode updated successfully.', 'dgwltd-revision-manager'),
            'post_id' => $post_id,
            'mode' => $mode
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

        // Users need to be able to edit the post to view its revision timeline
        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }

        // Additional capability check for viewing revisions
        return current_user_can('read') && current_user_can('edit_posts');
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

        $post_id = $revision->post_parent;

        // Users need to be able to edit the post
        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }

        // Additional checks for publishing revisions (similar to publish_posts capability)
        $post = get_post($post_id);
        if ($post && $post->post_status === 'publish') {
            // For published posts, require publish capability for the post type
            $post_type_obj = get_post_type_object($post->post_type);
            if ($post_type_obj && !current_user_can($post_type_obj->cap->publish_posts)) {
                return false;
            }
        }

        return true;
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
     * Check permissions for updating revision mode
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Full data about the request.
     * @return bool True if the request has edit access, false otherwise.
     */
    public function update_mode_permissions_check(WP_REST_Request $request): bool {
        $post_id = (int) $request['post_id'];

        // Users need to be able to edit the post
        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }

        // Changing revision mode is a significant operation - require additional capabilities
        $post = get_post($post_id);
        if ($post) {
            $post_type_obj = get_post_type_object($post->post_type);

            // Require publish capability for the post type (editors and above)
            if ($post_type_obj && !current_user_can($post_type_obj->cap->publish_posts)) {
                return false;
            }

            // Additional check: Only allow admins and editors to change revision modes
            // Authors cannot change revision workflows
            if (!current_user_can('edit_others_posts')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate that a revision ID exists and belongs to the specified post
     *
     * @since 1.0.0
     * @param int $revision_id The revision ID to validate.
     * @param int $post_id The expected parent post ID.
     * @return \WP_Post|false The revision object if valid, false otherwise.
     */
    private function validate_revision_for_post(int $revision_id, int $post_id) {
        $revision = wp_get_post_revision($revision_id);

        if (!$revision) {
            return false;
        }

        if ($revision->post_parent !== $post_id) {
            return false;
        }

        return $revision;
    }

    /**
     * Get current revision ID for a post with validation
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @return int|null The current revision ID or null if not set/invalid.
     */
    private function get_current_revision_id(int $post_id): ?int {
        $revision_id = get_post_meta($post_id, '_dgw_current_revision_id', true);

        if (!$revision_id) {
            return null;
        }

        $revision_id = (int) $revision_id;

        // Validate: Ensure revision exists and belongs to this post
        $revision = $this->validate_revision_for_post($revision_id, $post_id);

        if (!$revision) {
            // Invalid revision - clean up invalid reference
            delete_post_meta($post_id, '_dgw_current_revision_id');

            if (WP_DEBUG) {
                error_log("DGW.ltd Revision Manager: Cleaned up invalid current revision ID {$revision_id} for post {$post_id}");
            }

            return null;
        }

        return $revision_id;
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

    /**
     * Get cache key for revision data
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @param string $type The cache type (main, mode, status).
     * @return string The cache key.
     */
    private function get_cache_key(int $post_id, string $type = 'main'): string {
        return "dgw_revisions_{$type}_{$post_id}";
    }

    /**
     * Get cached revision data
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @return array|null Cached data or null if not found/invalid.
     */
    private function get_cached_revision_data(int $post_id): ?array {
        // Skip cache in debug mode with no-cache parameter
        if (defined('WP_DEBUG') && WP_DEBUG && isset($_GET['no-cache'])) {
            return null;
        }

        $cache_key = $this->get_cache_key($post_id);
        $cached = get_transient($cache_key);

        if ($cached !== false && is_array($cached)) {
            // Validate cache freshness
            if ($this->is_cache_valid($cached, $post_id)) {
                return $cached;
            }

            // Cache is stale, delete it
            delete_transient($cache_key);
        }

        return null;
    }

    /**
     * Set revision data cache
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @param array $data The data to cache.
     * @return void
     */
    private function set_revision_data_cache(int $post_id, array $data): void {
        $cache_key = $this->get_cache_key($post_id);
        $data['cached_at'] = current_time('timestamp');

        set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Check if cached data is still valid
     *
     * @since 1.0.0
     * @param array $cached_data The cached data.
     * @param int $post_id The post ID.
     * @return bool True if cache is valid, false otherwise.
     */
    private function is_cache_valid(array $cached_data, int $post_id): bool {
        if (!isset($cached_data['cached_at'])) {
            return false;
        }

        // Check if post has been modified since cache was created
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        $post_modified = strtotime($post->post_modified_gmt);
        $cache_created = $cached_data['cached_at'];

        return $post_modified <= $cache_created;
    }

    /**
     * Invalidate revision cache for a post
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @return void
     */
    public function invalidate_revision_cache(int $post_id): void {
        $cache_key = $this->get_cache_key($post_id);
        delete_transient($cache_key);

        // Also clear related cache keys
        delete_transient($this->get_cache_key($post_id, 'mode'));
        delete_transient($this->get_cache_key($post_id, 'status'));
    }

    /**
     * Invalidate cache when post is updated
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @return void
     */
    public function invalidate_revision_cache_on_update(int $post_id): void {
        $this->invalidate_revision_cache($post_id);
    }

    /**
     * Invalidate cache when revision is saved
     *
     * @since 1.0.0
     * @param int $revision_id The revision ID.
     * @return void
     */
    public function invalidate_revision_cache_on_save(int $revision_id): void {
        $revision = wp_get_post_revision($revision_id);
        if ($revision) {
            $this->invalidate_revision_cache($revision->post_parent);
        }
    }

    /**
     * Invalidate cache when revision is deleted
     *
     * @since 1.0.0
     * @param int $revision_id The revision ID.
     * @param \WP_Post $revision The revision post object.
     * @return void
     */
    public function invalidate_revision_cache_on_delete(int $revision_id, \WP_Post $revision): void {
        $this->invalidate_revision_cache($revision->post_parent);
    }

    /**
     * Log revision mode changes for audit trail
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @param string $previous_mode The previous revision mode.
     * @param string $new_mode The new revision mode.
     * @param string $post_title The post title for context.
     * @return void
     */
    private function log_revision_mode_change(int $post_id, string $previous_mode, string $new_mode, string $post_title): void {
        // Only log if mode actually changed
        if ($previous_mode === $new_mode) {
            return;
        }

        $current_user = wp_get_current_user();
        $user_display = $current_user->display_name ?: $current_user->user_login;

        // Only log to error log in debug mode or if explicitly enabled
        if (WP_DEBUG || defined('DGW_REVISION_AUDIT_ERROR_LOG') && DGW_REVISION_AUDIT_ERROR_LOG) {
            $log_entry = sprintf(
                '[DGW.ltd Revision Manager] User "%s" (ID: %d) changed revision mode for post "%s" (ID: %d) from "%s" to "%s"',
                $user_display,
                $current_user->ID,
                $post_title,
                $post_id,
                $previous_mode,
                $new_mode
            );

            error_log($log_entry);
        }

        // Also store in post meta for admin interface (last 10 changes)
        $audit_log = get_post_meta($post_id, '_dgw_revision_mode_audit', true) ?: [];

        $audit_log[] = [
            'timestamp' => current_time('timestamp'),
            'user_id' => $current_user->ID,
            'user_name' => $user_display,
            'previous_mode' => $previous_mode,
            'new_mode' => $new_mode,
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        // Keep only the last 10 entries
        $audit_log = array_slice($audit_log, -10);

        update_post_meta($post_id, '_dgw_revision_mode_audit', $audit_log);
    }
}