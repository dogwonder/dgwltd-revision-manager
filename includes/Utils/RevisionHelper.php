<?php
/**
 * Revision Helper Utilities
 *
 * @package DGW\RevisionManager\Utils
 * @since 1.0.0
 */

declare(strict_types=1);

namespace DGW\RevisionManager\Utils;

/**
 * Revision Helper Utilities
 *
 * Provides utility functions for revision management.
 *
 * @since 1.0.0
 */
class RevisionHelper {

    /**
     * Get revision timeline data for a post
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @param int $limit The maximum number of revisions to return.
     * @return array The timeline data.
     */
    public static function get_revision_timeline(int $post_id, int $limit = 6): array {
        $revisions = wp_get_post_revisions($post_id, [
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        $current_revision_id = self::get_current_revision_id($post_id);

        // If no current revision is set, default to the most recent revision
        if (!$current_revision_id && !empty($revisions)) {
            $most_recent = reset($revisions);
            $current_revision_id = $most_recent->ID;
            // Save this as the current revision for future reference
            update_post_meta($post_id, '_dgw_current_revision_id', $current_revision_id);
        }

        $timeline = [];
        $position = 0;

        foreach ($revisions as $revision) {
            $is_current = ($revision->ID === $current_revision_id);
            $status = self::determine_revision_status($revision->ID, $is_current, $current_revision_id);

            $timeline[] = [
                'id' => $revision->ID,
                'date' => $revision->post_date,
                'title' => $revision->post_title,
                'author' => get_userdata($revision->post_author)->display_name ?? '',
                'status' => $status,
                'is_current' => $is_current,
                'excerpt' => wp_trim_words($revision->post_content, 15),
                'position' => $position
            ];

            $position++;
        }

        return $timeline;
    }

    /**
     * Get current revision ID for a post
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @return int|null The current revision ID or null if not set.
     */
    public static function get_current_revision_id(int $post_id): ?int {
        $revision_id = get_post_meta($post_id, '_dgw_current_revision_id', true);
        return $revision_id ? (int) $revision_id : null;
    }

    /**
     * Set a revision as the current revision
     *
     * @since 1.0.0
     * @param int $revision_id The revision ID to set as current.
     * @return bool True on success, false on failure.
     */
    public static function set_current_revision(int $revision_id): bool {
        $revision = wp_get_post_revision($revision_id);
        if (!$revision) {
            return false;
        }

        $post_id = $revision->post_parent;

        // Temporarily disable revisions to prevent duplicates when switching
        remove_action('post_updated', 'wp_save_post_revision', 10);

        // Update the main post with revision data
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
            return false;
        }

        // Store the current revision ID
        update_post_meta($post_id, '_dgw_current_revision_id', $revision_id);

        // Update revision statuses
        self::update_revision_statuses($post_id, $revision_id);

        return true;
    }

    /**
     * Determine revision status based on date comparison with current revision
     *
     * @since 1.0.0
     * @param int $revision_id The revision ID.
     * @param bool $is_current Whether this is the current revision.
     * @param int $current_revision_id The current revision ID for comparison.
     * @return string The revision status.
     */
    private static function determine_revision_status(int $revision_id, bool $is_current, int $current_revision_id): string {
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
    private static function update_revision_statuses(int $post_id, int $current_revision_id): void {
        $revisions = wp_get_post_revisions($post_id);

        foreach ($revisions as $revision) {
            if ($revision->ID === $current_revision_id) {
                update_metadata('post', $revision->ID, '_dgw_revision_status', 'current');
            } else {
                // Mark others as past or pending based on date
                $current_revision = wp_get_post_revision($current_revision_id);
                if ($current_revision) {
                    $status = strtotime($revision->post_date) > strtotime($current_revision->post_date) ? 'pending' : 'past';
                    update_metadata('post', $revision->ID, '_dgw_revision_status', $status);
                }
            }
        }
    }

    /**
     * Get post revision status
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @return string The post revision status.
     */
    public static function get_post_revision_status(int $post_id): string {
        return get_post_meta($post_id, '_dgw_revision_status', true) ?: 'open';
    }

    /**
     * Set post revision status
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @param string $status The status to set.
     * @return bool True on success, false on failure.
     */
    public static function set_post_revision_status(int $post_id, string $status): bool {
        if (!in_array($status, ['open', 'pending', 'locked'], true)) {
            return false;
        }

        return (bool) update_post_meta($post_id, '_dgw_revision_status', $status);
    }
}