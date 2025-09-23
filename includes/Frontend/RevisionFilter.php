<?php
/**
 * Frontend Revision Filter
 *
 * @package DGW\RevisionManager\Frontend
 * @since 1.0.0
 */

declare(strict_types=1);

namespace DGW\RevisionManager\Frontend;

/**
 * Frontend Revision Filter
 *
 * Handles filtering of frontend content to show current revisions.
 *
 * @since 1.0.0
 */
class RevisionFilter {

    /**
     * Filter post content
     *
     * @since 1.0.0
     * @param string $content The post content.
     * @return string The filtered content.
     */
    public function filter_content(string $content): string {
        if (is_admin() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $post = get_post();
        if (!$post) {
            return $content;
        }

        $current_revision_id = $this->get_current_revision_id($post->ID);
        if (!$current_revision_id) {
            return $content;
        }

        $revision = wp_get_post_revision($current_revision_id);
        if (!$revision) {
            return $content;
        }

        return $revision->post_content;
    }

    /**
     * Filter post title
     *
     * @since 1.0.0
     * @param string $title The post title.
     * @param int $post_id The post ID.
     * @return string The filtered title.
     */
    public function filter_title(string $title, int $post_id = 0): string {
        if (is_admin() || !$post_id) {
            return $title;
        }

        $current_revision_id = $this->get_current_revision_id($post_id);
        if (!$current_revision_id) {
            return $title;
        }

        $revision = wp_get_post_revision($current_revision_id);
        if (!$revision) {
            return $title;
        }

        return $revision->post_title;
    }

    /**
     * Filter post excerpt
     *
     * @since 1.0.0
     * @param string $excerpt The post excerpt.
     * @param \WP_Post $post The post object.
     * @return string The filtered excerpt.
     */
    public function filter_excerpt(string $excerpt, \WP_Post $post): string {
        if (is_admin()) {
            return $excerpt;
        }

        $current_revision_id = $this->get_current_revision_id($post->ID);
        if (!$current_revision_id) {
            return $excerpt;
        }

        $revision = wp_get_post_revision($current_revision_id);
        if (!$revision) {
            return $excerpt;
        }

        return $revision->post_excerpt ?: wp_trim_words($revision->post_content, 55);
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
}