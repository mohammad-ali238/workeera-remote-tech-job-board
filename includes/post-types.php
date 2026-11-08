<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Registers the job and candidate post types and handles their lifecycle events.
 */
class Workeera_Post_types {

    /**
     * Hook post type registration and post status/deletion handlers.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_post_types' ], 5 );

        add_action( 'transition_post_status', [ $this, 'post_status_change_action' ], 5, 3 );
        add_action( 'before_delete_post', [ $this, 'post_before_delete_action' ], 15, 2 );
    }

    /**
     * Register the workeera_job and workeera_candidate custom post types.
     */
    public function register_post_types() {
        if ( ! is_blog_installed() || post_type_exists( 'workeera_job' ) ) {
            return;
        }

        // job post type
        $labels = array(
            'name'                  => esc_html_x( 'Jobs', 'Post type name', 'workeera-remote-tech-job-board' ),
            'singular_name'         => esc_html_x( 'Job', 'Post type singular name', 'workeera-remote-tech-job-board' ),
            'all_items'             => esc_html__( 'All Jobs', 'workeera-remote-tech-job-board' ),
            'menu_name'             => esc_html_x( 'Jobs', 'Admin menu name', 'workeera-remote-tech-job-board' ),
            'add_new'               => esc_html__( 'Add New', 'workeera-remote-tech-job-board' ),
            'add_new_item'          => esc_html__( 'Add new job', 'workeera-remote-tech-job-board' ),
            'edit'                  => esc_html_x( 'Edit', 'Edit job', 'workeera-remote-tech-job-board' ),
            'edit_item'             => esc_html__( 'Edit job', 'workeera-remote-tech-job-board' ),
            'new_item'              => esc_html__( 'New job', 'workeera-remote-tech-job-board' ),
            'view_item'             => esc_html__( 'View job', 'workeera-remote-tech-job-board' ),
            'view_items'            => esc_html__( 'View jobs', 'workeera-remote-tech-job-board' ),
            'search_items'          => esc_html__( 'Search jobs', 'workeera-remote-tech-job-board' ),
            'not_found'             => esc_html__( 'No jobs found', 'workeera-remote-tech-job-board' ),
            'featured_image'        => esc_html__( 'Company logo', 'workeera-remote-tech-job-board' ),
            'set_featured_image'    => esc_html__( 'Set company logo', 'workeera-remote-tech-job-board' ),
            'remove_featured_image' => esc_html__( 'Remove company logo', 'workeera-remote-tech-job-board' ),
            'use_featured_image'    => esc_html__( 'Use as company logo', 'workeera-remote-tech-job-board' ),
            'not_found_in_trash'    => esc_html__( 'No jobs found in trash', 'workeera-remote-tech-job-board' ),
            'filter_items_list'     => esc_html__( 'Filter jobs', 'workeera-remote-tech-job-board' ),
            'items_list_navigation' => esc_html__( 'Jobs navigation', 'workeera-remote-tech-job-board' ),
            'items_list'            => esc_html__( 'Jobs list', 'workeera-remote-tech-job-board' ),
        );
        $post_args = array(
            'labels'              => $labels,
            'description'         => esc_html__( 'This is where you can see all jobs.', 'workeera-remote-tech-job-board' ),
            'public'              => true,
            'show_ui'             => true,
            'publicly_queryable'  => true,
            'exclude_from_search' => true,
            'show_in_rest'        => true,
            'hierarchical'        => false,
            'rewrite'             => array( 'slug' => 'job', 'feeds' => true ),
            'query_var'           => true,
            'supports'            => array( 'title', 'editor', 'thumbnail' ),
            'show_in_nav_menus'   => true,
        );
        register_post_type( 'workeera_job', $post_args );

        // candidate post type
        $labels = array(
            'name'                  => esc_html_x( 'Candidates', 'Post type name', 'workeera-remote-tech-job-board' ),
            'singular_name'         => esc_html_x( 'Candidate', 'Post type singular name', 'workeera-remote-tech-job-board' ),
            'all_items'             => esc_html__( 'All Candidates', 'workeera-remote-tech-job-board' ),
            'menu_name'             => esc_html_x( 'Candidates', 'Admin menu name', 'workeera-remote-tech-job-board' ),
            'add_new'               => esc_html__( 'Add New', 'workeera-remote-tech-job-board' ),
            'add_new_item'          => esc_html__( 'Add new candidate', 'workeera-remote-tech-job-board' ),
            'edit'                  => esc_html_x( 'Edit', 'Edit candidate', 'workeera-remote-tech-job-board' ),
            'edit_item'             => esc_html__( 'Edit candidate', 'workeera-remote-tech-job-board' ),
            'new_item'              => esc_html__( 'New candidate', 'workeera-remote-tech-job-board' ),
            'search_items'          => esc_html__( 'Search candidates', 'workeera-remote-tech-job-board' ),
            'not_found'             => esc_html__( 'No candidates found', 'workeera-remote-tech-job-board' ),
            'not_found_in_trash'    => esc_html__( 'No candidates found in trash', 'workeera-remote-tech-job-board' ),
            'filter_items_list'     => esc_html__( 'Filter candidates', 'workeera-remote-tech-job-board' ),
            'items_list_navigation' => esc_html__( 'Candidates navigation', 'workeera-remote-tech-job-board' ),
            'items_list'            => esc_html__( 'Candidates list', 'workeera-remote-tech-job-board' ),
        );
        $post_args = array(
            'labels'              => $labels,
            'description'         => esc_html__( 'This is where you can see all candidates.', 'workeera-remote-tech-job-board' ),
            'public'              => false,
            'show_ui'             => true,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_in_rest'        => false,
            'hierarchical'        => false,
            'query_var'           => false,
            'supports'            => array( 'title', 'editor' ),
            'show_in_nav_menus'   => true,
        );
        register_post_type( 'workeera_candidate', $post_args );
    }

    /**
     * Update term counts and job listing status when a post's status changes.
     *
     * @param  string  $new_status New post status.
     * @param  string  $old_status Previous post status.
     * @param  WP_Post $post       Post object being transitioned.
     */
    public function post_status_change_action( $new_status, $old_status, $post ) {

        if ( $post->post_type === 'workeera_job' ) {
            // update terms counts
            if ( $new_status === 'publish' && $old_status !== 'publish' ) {
                // Job published — increment counts for all taxonomy terms + location.
                Workeera_Object_Terms::update_post_terms_count( $post->ID, 'job', 'plus' );
                //
                if ( 'auto-draft' !== $old_status ) {
                    $job_field_data = [
                        'listing_status' => 'active'
                    ];
                    Workeera_Query_Handler::save_item_data( 'job_fields', 'job_id', $post->ID, $job_field_data );
                }
            } elseif ( $new_status !== 'publish' && $old_status === 'publish' ) {
                // Job unpublished — decrement counts for all taxonomy terms + location.
                Workeera_Object_Terms::update_post_terms_count( $post->ID, 'job', 'minus' );
                //
                if ( 'pending' === $new_status ) {
                    $job_field_data = [
                        'listing_status' => $new_status
                    ];
                    Workeera_Query_Handler::save_item_data( 'job_fields', 'job_id', $post->ID, $job_field_data );
                }
            }
        }

        if ( $post->post_type === 'workeera_candidate' ) {
            // update terms counts
            if ( $new_status === 'publish' && $old_status !== 'publish' ) {
                Workeera_Object_Terms::update_post_terms_count( $post->ID, 'candidate', 'plus' );
            } elseif ( $new_status !== 'publish' && $old_status === 'publish' ) {
                Workeera_Object_Terms::update_post_terms_count( $post->ID, 'candidate', 'minus' );
            }
        }
    }

    /**
     * Clean up plugin tables, term relations and meta before a job/candidate is deleted.
     *
     * @param  int     $post_id ID of the post being deleted.
     * @param  WP_Post $post    Post object being deleted.
     */
    public function post_before_delete_action( $post_id, $post ) {

        if ( $post->post_type === 'workeera_job' ) {
            // delete job field
            Workeera_Query_Handler::delete_saved_record( 'job_fields', [ 'job_id' => $post_id ] );

            // delete job applications
            Workeera_Query_Handler::delete_saved_record( 'applications', [ 'job_id' => $post_id ] );

            if ( $post->post_status === 'publish' ) {
                // Decrement term counts before deleting relation records.
                Workeera_Object_Terms::update_post_terms_count( $post_id, 'job', 'minus' );
            }

            // Delete all term relations for this job.
            self::delete_post_term_relations( $post_id, 'job' );

            // Delete location row (counts already handled inside update_post_terms_count).
            Workeera_Object_Terms_Relation::delete_post_location( $post_id, 'job', false );
        }

        if ( $post->post_type === 'workeera_candidate' ) {

            if ( $post->post_status === 'publish' ) {
                Workeera_Object_Terms::update_post_terms_count( $post_id, 'candidate', 'minus' );
            }

            // Delete all term relations for this candidate.
            self::delete_post_term_relations( $post_id, 'candidate' );

            // Delete location row (counts already handled inside update_post_terms_count).
            Workeera_Object_Terms_Relation::delete_post_location( $post_id, 'candidate', false );
        }

        if ( in_array( $post->post_type, [ 'workeera_job', 'workeera_candidate' ], true ) ) {
            // delete meta
            Workeera_Query_Handler::delete_saved_record( 'metadata', [ 'object_id' => $post_id, 'object_type' => 'post' ], [ '%d', '%s' ] );
        }
    }

    /**
     * Delete all taxonomy relation records for a post.
     *
     * @param int    $post_id
     * @param string $object_type 'job' | 'candidate'
     */
    private static function delete_post_term_relations( $post_id, $object_type ) {
        global $wpdb;

        $taxonomy_map = Workeera_Object_Terms_Relation::post_type_taxonomy_map( $object_type );

        foreach ( $taxonomy_map as $cfg ) {
            $rel_table = $wpdb->prefix . $cfg['rel_table'];
            $wpdb->delete(
                $rel_table,
                [ 'object_id' => $post_id, 'object_type' => 'post' ],
                [ '%d', '%s' ]
            );
        }
    }
}

new Workeera_Post_types;
