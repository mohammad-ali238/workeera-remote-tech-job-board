<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Base class for Workeera custom taxonomy admin handling.
 */
class Workeera_Taxonomies {

    protected static $workeera_table_prefix = 'workeera_';

    /**
     * Return the fully-prefixed Workeera table prefix.
     *
     * @return string Prefixed table name root.
     */
    protected static function table_prefix() {
        global $wpdb;

        return $wpdb->prefix . self::$workeera_table_prefix;
    }

    /**
     * Check whether the current user may edit taxonomy terms.
     *
     * @return bool|null True if allowed, null otherwise.
     */
    protected function can_edit_terms() {
        if (current_user_can('manage_options')) {
            return true;
        }
    }

    /**
     * Check whether the current user may delete taxonomy terms.
     *
     * @return bool|null True if allowed, null otherwise.
     */
    protected function can_delete_terms() {
        if (current_user_can('manage_options')) {
            return true;
        }
    }

    /**
     * Resolve the primary-key column name for a given taxonomy.
     *
     * @param  string $tax_name Taxonomy plural slug.
     * @return string           Primary-key column name.
     */
    public static function taxonomy_id_col_name($tax_name = 'cities') {
        if ($tax_name == 'countries') {
            $id_column_name = 'country_id';
        } else {
            $id_column_name = 'city_id';
        }
        $custom_taxonomies = Workeera_Custom_Taxonomies::list();
        if (isset($custom_taxonomies[$tax_name]['primary_key'])) {
            $id_column_name = $custom_taxonomies[$tax_name]['primary_key'];
        }

        return $id_column_name;
    }

    /**
     * Generate a unique slug for a taxonomy term, appending a suffix on collision.
     *
     * @param  string $slug     Proposed slug.
     * @param  string $tax_name Taxonomy plural slug.
     * @param  int    $term_id  Existing term id when editing (excluded from uniqueness check).
     * @param  int    $parent   Optional parent term id for hierarchical taxonomies.
     * @return string           Unique sanitized slug.
     */
    protected static function verify_slug($slug, $tax_name = 'cities', $term_id = 0, $parent = 0) {
        global $wpdb;
        
        $slug = sanitize_title($slug);
        
        if (empty($slug)) {
            $slug = 'slug';
        }
        
        // Validate the table-name suffix and primary-key column (both derived
        // from $tax_name) before interpolating them — identifiers can't be
        // bound through $wpdb->prepare(). Fall back to a safe no-collision slug
        // if either is somehow invalid.
        $id_column_name = workeera_safe_sql_identifier( self::taxonomy_id_col_name($tax_name) );
        $table_suffix   = workeera_safe_sql_identifier( $tax_name );
        if ($table_suffix === '' || $id_column_name === '') {
            return $slug;
        }
        $table_name = self::table_prefix() . $table_suffix;

        // Get the original slug for comparison
        $original_slug = $slug;
        $suffix = 2;

        $data_query = $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table_name}` WHERE slug = %s",
            $slug
        );

        // Add parent condition if needed (for hierarchical)
        if (!empty($parent)) {
            $data_query .= $wpdb->prepare(" AND parent = %d", $parent);
        }

        // Exclude current term if editing
        if ($term_id > 0) {
            $data_query .= $wpdb->prepare(" AND %i != %d", $id_column_name, $term_id);
        }

        // Check for uniqueness with hierarchical context
        while ($wpdb->get_var($data_query) > 0) {

            $slug = $original_slug . '-' . $suffix;
            $suffix++;

            // Rebuild query with new slug
            $data_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table_name}` WHERE slug = %s",
                $slug
            );

            if (!empty($parent)) {
                $data_query .= $wpdb->prepare(" AND parent = %d", $parent);
            }

            if ($term_id > 0) {
                $data_query .= $wpdb->prepare(" AND %i != %d", $id_column_name, $term_id);
            }

            if ($suffix > 100) {
                $slug = $original_slug . '-' . uniqid();
                break;
            }
        }
        
        return $slug;
    }

    /**
     * Increment or decrement the cached object count for a term.
     *
     * @param int    $term_id     Term id whose count should change.
     * @param string $update_type Either 'plus' or 'minus'.
     * @param string $term_type   Taxonomy plural slug.
     * @param string $object_type Object type (e.g. 'job') the count is for.
     */
    protected static function update_terms_count($term_id, $update_type = 'plus', $term_type = 'cities', $object_type = 'job') {
        global $wpdb;

        $terms_count_table = self::table_prefix() . 'term_counts';

        $term_count_row = $wpdb->get_row($wpdb->prepare("SELECT term_count_id,count FROM `{$terms_count_table}` WHERE term_id=%d AND term_type=%s AND object_type=%s", [$term_id, $term_type, $object_type]));
        if ($update_type == 'minus') {
            if (isset($term_count_row->term_count_id) && $term_count_row->term_count_id > 0) {
                $term_count_id = $term_count_row->term_count_id;
                $wpdb->update($terms_count_table, ['count' => absint($term_count_row->count - 1)], ['term_count_id' => $term_count_id], ['%d'], ['%d']);
            }
        } else {
            if (isset($term_count_row->term_count_id) && $term_count_row->term_count_id > 0) {
                $term_count_id = $term_count_row->term_count_id;
                $wpdb->update($terms_count_table, ['count' => ($term_count_row->count + 1)], ['term_count_id' => $term_count_id], ['%d'], ['%d']);
            } else {
                $wpdb->insert($terms_count_table, ['term_id' => $term_id, 'term_type' => $term_type, 'object_type' => $object_type, 'count' => 1], ['%d', '%s', '%s', '%d']);
            }
        }
    }

    /**
     * Redirect out-of-range page_num query vars to a valid page.
     *
     * @param int $terms_total_count Total number of terms.
     * @param int $per_page_results  Terms shown per page.
     */
    protected function paginate_redirect($terms_total_count, $per_page_results) {
        $page_num = workeera_get_query_var('page_num');
        if (!empty($page_num)) {
            $page_num = absint($page_num);
            if ($page_num < 1) {
                wp_safe_redirect(add_query_arg(array('page_num' => 1)));
                exit;
            }
            $total_pages = 1;
            if ($terms_total_count > $per_page_results) {
                $total_pages = ceil($terms_total_count/$per_page_results);
            }
            if ($page_num > $total_pages) {
                wp_safe_redirect(add_query_arg(array('page_num' => $total_pages)));
                exit;
            }
        }
    }

    /**
     * Render the WordPress-style pagination markup for the terms table.
     *
     * @param int    $terms_total_count Total number of terms.
     * @param int    $per_page_results  Terms shown per page.
     * @param string $place             Either 'header' or 'footer'.
     */
    protected function terms_pagination($terms_total_count, $per_page_results, $place = 'header') {
        $show_pagination = false;
        $pages_num_class = ' one-page';
        $terms_total_count = absint($terms_total_count);
        if ($terms_total_count > $per_page_results) {
            $show_pagination = true;
            $pages_num_class = '';
            //
            $total_pages = ceil($terms_total_count/$per_page_results);

            $page_num = absint(workeera_get_query_var('page_num'));
            if ($page_num < 1) {
                $page_num = 1;
            }
            $page_num = $page_num > $total_pages ? $total_pages : $page_num;
        }
        ?>
        <div class="tablenav-pages<?php echo esc_attr($pages_num_class) ?>">
            <?php /* translators: %s: total item count */ ?>
            <span class="displaying-num"><?php printf(esc_html_x('%s Items', 'Admin terms total', 'workeera-remote-tech-job-board'), number_format($terms_total_count)) ?></span>
            <?php
            if ($show_pagination) {
                ?>
                <span class="pagination-links">
                    <?php
                    if ($page_num > 1) {
                        $last_page_url = remove_query_arg(array('page_num'));
                        if ($page_num > 2) {
                            $prev_page_url = add_query_arg(array('page_num' => ($page_num-1)));
                        } else {
                            $prev_page_url = $last_page_url;
                        }
                        ?>
                        <a class="first-page button" href="<?php echo esc_url($last_page_url) ?>">
                            <span class="screen-reader-text"><?php echo esc_html_x('First page', 'Admin terms', 'workeera-remote-tech-job-board') ?></span><span aria-hidden="true">&laquo;</span>
                        </a>
                        <a class="prev-page button" href="<?php echo esc_url($prev_page_url) ?>">
                            <span class="screen-reader-text"><?php echo esc_html_x('Previous page', 'Admin terms', 'workeera-remote-tech-job-board') ?></span><span aria-hidden="true">&lsaquo;</span>
                        </a>
                        <?php
                    } else {
                        ?>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                        <?php
                    }
                    if ($place == 'footer') {
                        ?>
                        <span class="screen-reader-text"><?php echo esc_html_x('Current page', 'Admin terms', 'workeera-remote-tech-job-board') ?></span>
                        <span id="table-paging" class="paging-input"><span class="tablenav-paging-text"><?php echo absint($page_num) ?> <?php esc_html_e('of', 'workeera-remote-tech-job-board') ?> <span class="total-pages"><?php echo absint($total_pages) ?></span></span></span>
                        <?php
                    } else {
                        ?>
                        <span class="paging-input">
                            <label for="current-page-selector" class="screen-reader-text"><?php echo esc_html_x('Current page', 'Admin terms', 'workeera-remote-tech-job-board') ?></label>
                            <input class="current-page" id="current-page-selector" type="text" value="<?php echo absint($page_num) ?>" size="1" aria-describedby="table-paging">
                            <span class="tablenav-paging-text"> <?php esc_html_e('of', 'workeera-remote-tech-job-board') ?> <span class="total-pages"><?php echo absint($total_pages) ?></span></span>
                        </span>
                        <?php
                    }
                    if ($page_num < $total_pages) {
                        ?>
                        <a class="next-page button" href="<?php echo esc_url(add_query_arg(array('page_num' => ($page_num+1)))) ?>">
                            <span class="screen-reader-text"><?php echo esc_html_x('Next page', 'Admin terms', 'workeera-remote-tech-job-board') ?></span><span aria-hidden="true">&rsaquo;</span>
                        </a>
                        <a class="last-page button" href="<?php echo esc_url(add_query_arg(array('page_num' => $total_pages))) ?>">
                            <span class="screen-reader-text"><?php echo esc_html_x('Last page', 'Admin terms', 'workeera-remote-tech-job-board') ?></span><span aria-hidden="true">&raquo;</span>
                        </a>
                        <?php
                    } else {
                        ?>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
                        <?php
                    }
                    ?>
                </span>
                <?php
            }
            ?>
        </div>
        <?php
    }

    /**
     * Handle the bulk-delete POST action on the term listing screen.
     *
     * @param object $taxonomy_obj     Taxonomy object exposing delete_term().
     * @param string $tax_type         Taxonomy plural slug.
     * @param int    $per_page_results Maximum number of terms to delete per request.
     */
    protected function delete_bulk_terms($taxonomy_obj, $tax_type, $per_page_results) {
        // Verify the nonce first (fail early), then the capability — kept as two
        // separate guards so the nonce check can never be short-circuited away.
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_admin_nonce')) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if (
            isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['workeera_taxonomy']) && $_POST['workeera_taxonomy'] == $tax_type &&
            isset($_POST['delete_tags']) && !empty($_POST['delete_tags']) && is_array($_POST['delete_tags'])
        ) {

            $custom_taxonomies = Workeera_Custom_Taxonomies::list();

            $term_items = array_map('absint', $_POST['delete_tags']);

            $terms_count = 1;
            foreach ($term_items as $term_id) {

                $taxonomy_obj->delete_term($term_id);
                if ($terms_count >= $per_page_results) {
                    break;
                }
                $terms_count++;
            }

            $taxonomy_page_name = 'workeera-' . $tax_type;
            $url = admin_url('/admin.php');
            if (isset($custom_taxonomies[$tax_type]['page_name'])) {
                $url = admin_url('/' . $custom_taxonomies[$tax_type]['page_name']);
            }

            $query_args = [];
            if (isset($custom_taxonomies[$tax_type]['query_args']) && !empty($custom_taxonomies[$tax_type]['query_args']) && is_array($custom_taxonomies[$tax_type]['query_args'])) {
                $tax_query_args = $custom_taxonomies[$tax_type]['query_args'];
                foreach ($tax_query_args as $arg_key => $arg_val) {
                    $query_args[$arg_key] = sanitize_text_field(wp_unslash($arg_val));
                }
            }
            $query_args['page'] = $taxonomy_page_name;

            wp_safe_redirect(add_query_arg($query_args, esc_url($url)));
            exit;
        }
    }

}
