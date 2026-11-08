<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Admin-side AJAX endpoints for Select2-style lookups (pages, users, terms).
 */
class Workeera_Admin_Ajax {

    /**
     * Register admin AJAX action callbacks.
     */
    public function __construct() {
        add_action('workeera_ajax_get_page_results', [$this, 'get_pages_list']);
        add_action('workeera_ajax_get_users_results', [$this, 'get_users_list']);
    }

    /**
     * AJAX: return matching published pages for a Select2 lookup.
     */
    public function get_pages_list() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_admin_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        $pages = [];

        // Restrict to users who can actually edit content — these lookups feed
        // the post-edit metabox dropdowns. A plain logged-in check would let
        // any subscriber enumerate every page title on the site.
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json(array('results' => $pages));
        }

        global $wpdb;

        if (isset($_POST['q'])) {
            $query_results = $wpdb->get_results( $wpdb->prepare("SELECT ID,post_title FROM {$wpdb->prefix}posts WHERE post_status IN ('publish') AND post_type=%s AND post_title LIKE %s limit 50", 'page', '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) . '%') );
        } else {
            $query_results = $wpdb->get_results( $wpdb->prepare("SELECT ID,post_title FROM {$wpdb->prefix}posts WHERE post_status IN ('publish') AND post_type=%s limit 50", 'page') );
        }
        if (!empty($query_results)) {
            foreach ($query_results as $row) {
                $id = $row->ID;
                /* translators: %d: page ID */
                $pages[] = array('id' => absint($id), 'text' => esc_html( $row->post_title ) . ' (' . sprintf( esc_html__( 'id: %d', 'workeera-remote-tech-job-board' ), $id ) . ')');
            }
        }

        wp_send_json(array('results' => $pages));
    }

    /**
     * AJAX: return matching users (optionally filtered by workeera_user_type).
     */
    public function get_users_list() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_admin_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        $users = [];

        // Listing users (including admin display names) is sensitive — require
        // a content-editor capability rather than a bare login check.
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json(array('results' => $users));
        }

        global $wpdb;

        $user_type = isset( $_POST['user_type'] ) ? sanitize_text_field( wp_unslash( $_POST['user_type'] ) ) : '';

        if (!empty($user_type)) {
            // Join with metadata table and filter by user_type
            if (isset($_POST['q'])) {
                $query_results = $wpdb->get_results( $wpdb->prepare(
                    "SELECT u.ID, u.display_name
                    FROM {$wpdb->prefix}users u
                    LEFT JOIN {$wpdb->prefix}workeera_metadata m ON u.ID = m.object_id AND m.object_type = 'user' AND m.meta_key = 'workeera_user_type'
                    WHERE u.display_name LIKE %s
                    AND (m.meta_value IS NULL OR m.meta_value = %s)
                    LIMIT 50",
                    '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) . '%',
                    $user_type
                ) );
            } else {
                $query_results = $wpdb->get_results( $wpdb->prepare(
                    "SELECT u.ID, u.display_name
                    FROM {$wpdb->prefix}users u
                    LEFT JOIN {$wpdb->prefix}workeera_metadata m ON u.ID = m.object_id AND m.object_type = 'user' AND m.meta_key = 'workeera_user_type'
                    WHERE (m.meta_value IS NULL OR m.meta_value = %s)
                    LIMIT 50",
                    $user_type
                ) );
            }
        } else {
            // Original query without user_type filtering
            if (isset($_POST['q'])) {
                $query_results = $wpdb->get_results( $wpdb->prepare(
                    "SELECT ID, display_name
                    FROM {$wpdb->prefix}users
                    WHERE display_name LIKE %s
                    LIMIT 50",
                    '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) . '%'
                ) );
            } else {
                $query_results = $wpdb->get_results( $wpdb->prepare(
                    "SELECT ID, display_name
                    FROM {$wpdb->prefix}users
                    WHERE 1=%d
                    LIMIT 50",
                    1
                ) );
            }
        }
        if (!empty($query_results)) {
            foreach ($query_results as $row) {
                $id = $row->ID;
                /* translators: %d: user ID */
                $users[] = array('id' => absint($id), 'text' => esc_html( $row->display_name ) . ' (' . sprintf( esc_html__( 'id: %d', 'workeera-remote-tech-job-board' ), $id ) . ')');
            }
        }

        wp_send_json(array('results' => $users));
    }
}

new Workeera_Admin_Ajax;