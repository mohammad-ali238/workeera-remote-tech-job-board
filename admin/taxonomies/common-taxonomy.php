<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Generic admin handler for any Workeera custom taxonomy table.
 */
class Workeera_Common_Taxonomy extends Workeera_Taxonomies {

    private $per_page_results = 15;

    private $single_slug = '';

    private $plural_slug = '';

    private $single_name = '';

    private $plural_name = '';

    private $table_name = '';

    private $primary_key = '';

    private $page_name = '';

    private $query_args = [];

    private $parents = [];

    private $object_counts = [];

    private $terms_total_count;

    /**
     * Wire up admin hooks for a taxonomy described by the given args.
     *
     * @param array $taxonomy_args Taxonomy configuration (slugs, table, parents, etc.).
     */
    public function __construct($taxonomy_args = []) {

        $this->single_slug = isset($taxonomy_args['single_slug']) ? $taxonomy_args['single_slug'] : '';
        $this->plural_slug = isset($taxonomy_args['plural_slug']) ? $taxonomy_args['plural_slug'] : '';
        $this->single_name = isset($taxonomy_args['single_name']) ? $taxonomy_args['single_name'] : '';
        $this->plural_name = isset($taxonomy_args['plural_name']) ? $taxonomy_args['plural_name'] : '';
        $this->table_name = isset($taxonomy_args['table_name']) ? $taxonomy_args['table_name'] : '';
        $this->primary_key = isset($taxonomy_args['primary_key']) ? $taxonomy_args['primary_key'] : '';
        $this->page_name = isset($taxonomy_args['page_name']) ? $taxonomy_args['page_name'] : '';
        $this->query_args = isset($taxonomy_args['query_args']) ? $taxonomy_args['query_args'] : [];
        $this->parents = isset($taxonomy_args['parents']) ? $taxonomy_args['parents'] : [];
        $this->object_counts = isset($taxonomy_args['counts']) ? $taxonomy_args['counts'] : [];

        if (isset($taxonomy_args['per_page_terms']) && is_numeric($taxonomy_args['per_page_terms']) && $taxonomy_args['per_page_terms'] > 0) {
            $this->per_page_results = absint($taxonomy_args['per_page_terms']);
        }

        if (empty($this->single_slug) || empty($this->plural_slug) || empty($this->table_name) || empty($this->primary_key)) {
            return;
        }

        add_action('admin_init', [$this, 'terms_init']);

        add_action('admin_menu', [$this, 'admin_menu_pages']);

        add_action("wp_ajax_workeera_add_{$this->single_slug}_term", [$this, 'add_new_term']);

        add_action("wp_ajax_workeera_update_{$this->single_slug}_term", [$this, 'update_term_item']);

        add_action("wp_ajax_workeera_delete_{$this->single_slug}_term", [$this, 'delete_term_item']);

        add_action("workeera_ajax_update_{$this->single_slug}_form", [$this, 'update_term_form']);

        $tax_parents = $this->parents;
        if (!empty($tax_parents) && is_array($tax_parents)) {
            foreach ($tax_parents as $parent_tax_slug => $parent_tax) {
                $parent_tax_plural_slug = isset($parent_tax['plural_slug']) ? $parent_tax['plural_slug'] : '';

                add_action("workeera_ajax_parent_tax_{$parent_tax_plural_slug}_drpdwn", [$this, 'get_parent_terms_dropdown']);
            }
        }
    }

    /**
     * Set up per-request state (counts, pagination, bulk-delete) on the listing screen.
     */
    public function terms_init() {
        global $pagenow, $wpdb;

        $page_name = 'admin.php';
        if (!empty($this->page_name)) {
            $page_name = $this->page_name;
        }

        $per_page_results = $this->per_page_results;

        $page = workeera_get_query_var('page');

        if ($pagenow == $page_name && $page == 'workeera-' . $this->plural_slug) {
            $table_name = self::table_prefix() . $this->table_name;
            if (!$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) )) {
                return;
            }
            $this->terms_total_count = $this->terms_count();
            $terms_total_count = $this->terms_total_count;

            $this->paginate_redirect($terms_total_count, $per_page_results);

            // for bulk delete terms
            $this->delete_bulk_terms($this, $this->plural_slug, $per_page_results);
        }
    }

    /**
     * Register the admin submenu page for this taxonomy.
     */
    public function admin_menu_pages() {
        $menu_page = 'workeera-dashboard';
        if (!empty($this->page_name)) {
            $menu_page = $this->page_name;
        }
        $query_args = $this->query_args;
        if (!empty($query_args) && is_array($query_args)) {
            $args = '';
            $args_counter = 0;
            foreach ($query_args as $arg_key => $arg_val) {
                $args .= $args_counter > 0 ? '&' : '?';
                $args .= sanitize_text_field(wp_unslash($arg_key)) . '=' . sanitize_text_field(wp_unslash($arg_val));
                $args_counter++;
            }
            $menu_page .= $args;
        }
        add_submenu_page($menu_page, $this->plural_name, $this->plural_name, 'manage_options', 'workeera-' . $this->plural_slug, [$this, 'list_page']);
    }

    /**
     * Render the terms listing admin page (Add New column + list table).
     */
    public function list_page() {
        global $wpdb;

        $table_name = self::table_prefix() . $this->table_name;

        if (!$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) )) {
            /* translators: %s: taxonomy plural name */
            echo '<div class="notice notice-error"><p>' . sprintf(esc_html_x('%s taxonomy is not initialized properly. Please try reactivating the plugin or contact support.', 'Admin notice', 'workeera-remote-tech-job-board'), esc_html($this->plural_name)) . '</p></div>';
            return;
        }

        $terms_total_count = $this->terms_total_count;
        $per_page_results = $this->per_page_results;
        $terms_data = $this->terms_query_data();

        $search_val = workeera_get_query_var('s');
        ?>
        <div class="wrap workeera-terms-wrap workeera-<?php echo esc_attr($this->plural_slug) ?>-wrap nosubsub">
            <h1 class="wp-heading-inline"><?php echo esc_html($this->plural_name) ?></h1>

            <?php
            if (!empty($search_val)) {
                /* translators: %s: search query */
                echo '<span class="subtitle">' . sprintf(wp_kses(_x('Search results for: <strong>%s</strong>', 'Admin terms', 'workeera-remote-tech-job-board'), ['strong' => []]), esc_html(sanitize_text_field(wp_unslash($search_val)))) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- format pre-escaped via wp_kses, arg via esc_html
            }
            ?>
            <hr class="wp-header-end">

            <form class="search-form wp-clearfix" method="get">
                <p class="search-box">
                    <?php /* translators: %s: taxonomy plural name */ ?>
                    <label class="screen-reader-text" for="tag-search-input"><?php printf(esc_html_x('Search %s:', 'Admin terms', 'workeera-remote-tech-job-board'), esc_html($this->plural_name)) ?></label>
                    <?php
                    $query_args = $this->query_args;
                    if (!empty($query_args) && is_array($query_args)) {
                        foreach ($query_args as $arg_key => $arg_val) {
                            Workeera_Form_Fields::input(array(
                                'name' => sanitize_text_field(wp_unslash($arg_key)),
                                'input_type' => 'hidden',
                                'value' => sanitize_text_field(wp_unslash($arg_val))
                            ));
                        }
                    }
                    Workeera_Form_Fields::input(array(
                        'name' => 'page',
                        'input_type' => 'hidden',
                        'value' => 'workeera-' . $this->plural_slug
                    ));
                    $orderby_var = workeera_get_query_var('orderby');
                    if (!empty($orderby_var)) {
                        Workeera_Form_Fields::input(array(
                            'name' => 'orderby',
                            'input_type' => 'hidden',
                            'value' => $orderby_var
                        ));
                    }
                    $order_var = workeera_get_query_var('order');
                    if (!empty($order_var)) {
                        Workeera_Form_Fields::input(array(
                            'name' => 'order',
                            'input_type' => 'hidden',
                            'value' => $order_var
                        ));
                    }
                    Workeera_Form_Fields::input(array(
                        'id' => 'tag-search-input',
                        'name' => 's',
                        'input_type' => 'search',
                        'value' => $search_val
                    ));
                    Workeera_Form_Fields::input(array(
                        'id' => 'search-submit',
                        'input_type' => 'submit',
                        'classes' => 'button',
                        /* translators: %s: taxonomy plural name */
                        'value' => sprintf(esc_html_x('Search %s', 'Admin terms', 'workeera-remote-tech-job-board'), esc_html($this->plural_name))
                    ));
                    ?>
                </p>
            </form>

            <div id="col-container" class="wp-clearfix">

                <div id="col-left">
                    <div class="col-wrap">
                        <div class="form-wrap">
                            <?php $this->render_import_section(); ?>
                            <?php /* translators: %s: taxonomy singular name */ ?>
                            <h2><?php printf(esc_html__('Add New %s', 'workeera-remote-tech-job-board'), esc_html($this->single_name)) ?></h2>
                            <form id="addtag" method="post" class="workeera-user-form">
                                <div class="form-field form-required term-name-wrap">
                                    <label for="tag-name"><?php echo esc_html_x('Name', 'Admin terms', 'workeera-remote-tech-job-board') ?></label>
                                    <?php
                                    Workeera_Form_Fields::input(array(
                                        'id' => 'tag-name',
                                        'name' => 'term_name',
                                        'extra_attributes' => array('required' => '', 'size' => '40'),
                                        'value' => ''
                                    ));
                                    ?>
                                    <p id="name-description"><?php echo esc_html_x('The name is how it appears on your site.', 'Admin terms', 'workeera-remote-tech-job-board') ?></p>
                                </div>
                                <div class="form-field term-slug-wrap">
                                    <label for="tag-slug"><?php echo esc_html_x('Slug', 'Admin terms', 'workeera-remote-tech-job-board') ?></label>
                                    <?php
                                    Workeera_Form_Fields::input(array(
                                        'id' => 'tag-slug',
                                        'name' => 'slug',
                                        'extra_attributes' => array('size' => '40'),
                                        'value' => ''
                                    ));
                                    ?>
                                    <p id="slug-description"><?php esc_html_e('The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'workeera-remote-tech-job-board') ?></p>
                                </div>
                                <?php
                                $tax_parents = $this->parents;
                                if (!empty($tax_parents) && is_array($tax_parents)) {
                                    foreach ($tax_parents as $parent_tax_slug => $parent_tax) {
                                        $is_multi_parent_itms = false;
                                        if ( isset($parent_tax['is_multi']) && $parent_tax['is_multi'] ) {
                                            $is_multi_parent_itms = true;
                                            $parent_tax_name = isset($parent_tax['plural_name']) ? $parent_tax['plural_name'] : '';
                                        } else {
                                            $parent_tax_name = isset($parent_tax['single_name']) ? $parent_tax['single_name'] : '';
                                        }
                                        $parent_tax_single_slug = isset($parent_tax['single_slug']) ? $parent_tax['single_slug'] : '';
                                        $parent_tax_plural_slug = isset($parent_tax['plural_slug']) ? $parent_tax['plural_slug'] : '';
                                        ?>
                                        <div class="form-field term-<?php echo esc_attr($parent_tax_single_slug) ?>-wrap">
                                            <label for="parent-<?php echo esc_attr($parent_tax_single_slug) ?>"><?php echo esc_html($parent_tax_name) ?></label>
                                            <?php
                                            Workeera_Form_Fields::select(array(
                                                'id' => 'parent-' . esc_attr($parent_tax_single_slug),
                                                'name' => esc_attr($parent_tax_single_slug) . ($is_multi_parent_itms ? '[]' : ''),
                                                'classes' => ['postform', 'workeera-parent-term-drpdwn'],
                                                /* translators: %s: parent taxonomy name */
                                                'extra_attributes' => array('data-placeholder' => sprintf(esc_html__('Select %s', 'workeera-remote-tech-job-board'), esc_html($parent_tax_name)), 'data-type' => $parent_tax_plural_slug),
                                                'options' => $is_multi_parent_itms ? [] : array('0' => esc_html__('None', 'workeera-remote-tech-job-board')),
                                                'multiple' => $is_multi_parent_itms,
                                                'value' => ''
                                            ));
                                            ?>
                                            <?php /* translators: 1: parent taxonomy name, 2: current taxonomy singular name */ ?>
                                            <p id="parent-term-description"><?php printf(esc_html__('Select %1$s for this %2$s.', 'workeera-remote-tech-job-board'), esc_html($parent_tax_name), esc_html($this->single_name)) ?></p>
                                        </div>
                                        <?php
                                    }
                                }
                                ?>

                                <div class="submit">
                                    <?php wp_nonce_field('workeera_admin_nonce', 'workeera_nonce'); ?>
                                    <input type="hidden" name="action" value="workeera_add_<?php echo esc_attr($this->single_slug) ?>_term">
                                    <?php /* translators: %s: taxonomy singular name */ ?>
                                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php printf(esc_attr__('Add New %s', 'workeera-remote-tech-job-board'), esc_attr($this->single_name)) ?>">
                                </div>
                            </form>
                        </div>
                    </div>
                </div><!-- /col-left -->

                <div id="col-right">
                    <div class="col-wrap">
                        <form id="posts-filter" method="post">
                            <?php
                            Workeera_Form_Fields::input(array(
                                'name' => 'workeera_taxonomy',
                                'input_type' => 'hidden',
                                'value' => $this->plural_slug
                            ));
                            wp_nonce_field('workeera_admin_nonce', 'workeera_nonce');
                            ?>
                            <div class="tablenav top">
                                <div class="alignleft actions bulkactions">
                                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'workeera-remote-tech-job-board') ?></label>
                                    <select name="action" id="bulk-action-selector-top">
                                        <option value=""><?php echo esc_html_x('Bulk actions', 'Admin terms', 'workeera-remote-tech-job-board') ?></option>
                                        <option value="delete"><?php echo esc_html_x('Delete', 'Admin terms', 'workeera-remote-tech-job-board') ?></option>
                                    </select>
                                    <input type="submit" id="doaction" class="button action" value="<?php echo esc_attr_x('Apply', 'Admin terms', 'workeera-remote-tech-job-board') ?>">
                                </div>
                                <?php /* translators: %s: taxonomy plural name */ ?>
                                <h2 class="screen-reader-text"><?php printf(esc_html__('%s list navigation', 'workeera-remote-tech-job-board'), esc_html($this->plural_name)) ?></h2>
                                <?php $this->terms_pagination($terms_total_count, $per_page_results) ?>
                                <br class="clear">
                            </div>
                            <?php /* translators: %s: taxonomy plural name */ ?>
                            <h2 class="screen-reader-text"><?php printf(esc_html__('%s list', 'workeera-remote-tech-job-board'), esc_html($this->plural_name)) ?></h2>
                            <table class="wp-list-table workeera-list-table widefat fixed striped table-view-list tags">
                                <thead>
                                    <?php $this->listing_labels() ?>
                                </thead>

                                <tbody id="the-list" data-wp-lists="list:tag">
                                    <?php
                                    if (!empty($terms_data)) {
                                        $primary_key = $this->primary_key;
                                        foreach ($terms_data as $term_obj) {
                                            
                                            $term_id = isset($term_obj->$primary_key) ? $term_obj->$primary_key : '';
                                            $term_name = isset($term_obj->label) ? $term_obj->label : '';
                                            $slug = isset($term_obj->slug) ? $term_obj->slug : '';
                                            $item_data = [
                                                'id' => $term_id,
                                                'label' => $term_name,
                                                'slug' => $slug,
                                            ];
                                            $this->list_item($item_data);
                                        }
                                    } else {
                                        ?>
                                        <?php /* translators: %s: taxonomy plural name */ ?>
                                        <tr class="no-items"><td class="colspanchange" colspan="4"><?php printf(esc_html__('No %s found.', 'workeera-remote-tech-job-board'), esc_html($this->plural_name)) ?></td></tr>
                                        <?php
                                    }
                                    ?>
                                </tbody>

                                <tfoot>
                                    <?php $this->listing_labels('footer') ?>
                                </tfoot>

                            </table>
                            <div class="tablenav bottom">

                                <div class="alignleft actions bulkactions">
                                    <label for="bulk-action-selector-bottom" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'workeera-remote-tech-job-board') ?></label>
                                    <select name="action2" id="bulk-action-selector-bottom">
                                    <option value=""><?php echo esc_html_x('Bulk actions', 'Admin terms', 'workeera-remote-tech-job-board') ?></option>
                                    <option value="delete"><?php echo esc_html_x('Delete', 'Admin terms', 'workeera-remote-tech-job-board') ?></option>
                                    </select>
                                    <input type="submit" id="doaction2" class="button action" value="<?php echo esc_attr_x('Apply', 'Admin terms', 'workeera-remote-tech-job-board') ?>">
                                </div>
                                <?php $this->terms_pagination($terms_total_count, $per_page_results, 'footer') ?>
                                <br class="clear">
                            </div>
                        
                        </form>
                        <div class="form-wrap edit-term-notes">
                            <?php /* translators: 1: taxonomy singular name, 2: taxonomy singular name */ ?>
                            <p><?php printf(esc_html__('Deleting a %1$s does not delete the posts in that %2$s.', 'workeera-remote-tech-job-board'), esc_html($this->single_name), esc_html($this->single_name)) ?></p>
                        </div>
                    </div>
                </div><!-- /col-right -->

            </div><!-- /col-container -->

        </div>
        <?php
    }

    /**
     * Render a single term row in the admin list table.
     *
     * @param array $item_data Row data with id, label, and slug keys.
     */
    private function list_item($item_data) {
        global $wpdb;

        $can_delete = $this->can_delete_terms();
        $can_update = $this->can_edit_terms();

        $term_id = isset($item_data['id']) ? $item_data['id'] : '';
        $label = isset($item_data['label']) ? $item_data['label'] : '';
        $slug = isset($item_data['slug']) ? $item_data['slug'] : '';

        $terms_count_table = self::table_prefix() . 'term_counts';
        ?>
        <tr id="tag-<?php echo absint($term_id) ?>" class="level-0 workeera-term-listitm" data-type="<?php echo esc_attr($this->single_slug) ?>">
            <th scope="row" class="check-column">
                <input type="checkbox" name="delete_tags[]" value="<?php echo absint($term_id) ?>" id="cb-select-<?php echo absint($term_id) ?>">
                <label for="cb-select-<?php echo absint($term_id) ?>">
                    <?php /* translators: %s: term label */ ?>
                    <span class="screen-reader-text"><?php printf(esc_html_x('Select %s', 'Term selection', 'workeera-remote-tech-job-board'), esc_html($label)) ?></span>
                </label>
            </th>
            <td class="name column-name has-row-actions column-primary">
                <strong><a class="row-title<?php echo esc_attr($can_update ? ' workeera-edit-term-item' : '') ?>" href="javascript:;"><?php echo esc_html($label) ?></a></strong>
                <br>
                <div class="row-actions">
                    <span class="edit<?php echo esc_attr($can_update ? '' : ' workeera-hide') ?>"><a href="javascript:;" class="workeera-edit-term-item"><?php echo esc_html_x('Edit', 'Admin terms', 'workeera-remote-tech-job-board') ?></a> <?php echo esc_html($can_delete ? '|' : '') ?> </span>
                    <span class="delete<?php echo esc_attr($can_delete ? '' : ' workeera-hide') ?>"><a href="javascript:;" class="delete-tag aria-button-if-js" role="button"><?php echo esc_html_x('Delete', 'Admin terms', 'workeera-remote-tech-job-board') ?></a></span>
                </div>
            </td>
            <td class="slug column-slug"><?php echo esc_html($slug) ?></td>
            <?php
            $object_counts = $this->object_counts;
            if (!empty($object_counts) && is_array($object_counts)) {
                foreach ($object_counts as $object_count) {
                    $object_count_single = isset($object_count['object_single']) ? $object_count['object_single'] : '';
                    $object_count_plural = isset($object_count['object_plural']) ? $object_count['object_plural'] : '';

                    $term_object_count = $wpdb->get_var($wpdb->prepare("SELECT count FROM `{$terms_count_table}` WHERE term_id=%d AND term_type=%s AND object_type=%s LIMIT 1", [$term_id, $this->plural_slug, $object_count_single]));
                    ?>
                    <td class="posts column-posts workeera-<?php echo esc_attr($object_count_plural) ?>-count"><?php echo absint($term_object_count) ?></td>
                    <?php
                }
            }
            ?>
        </tr>
        <?php
    }

    /**
     * Render the table header/footer (column labels and sort links).
     *
     * @param string $place Either 'header' or 'footer'.
     */
    private function listing_labels($place = 'header') {
        $cbid = '';
        if ($place == 'header') {
            $cbid = 'id="cb"';
        }

        $orderby = workeera_get_query_var('orderby');
        $order = workeera_get_query_var('order');
        if (empty($orderby)) {
            $orderby = 'name';
        }
        if (empty($order)) {
            $order = 'asc';
        }

        $name_sort_class = 'sortable desc';

        $sort_order_class = ' asc';
        if ($order == 'desc') {
            $sort_order_class = ' desc';
        }
        $name_order = 'asc';
        if ($orderby == 'name') {
            $name_sort_class = 'sorted' . $sort_order_class;
            $name_order = $order == 'asc' ? 'desc' : 'asc';
        }

        $sorting_url = remove_query_arg(array('page_num'));
        ?>
        <tr>
            <td <?php echo esc_html($cbid) ?> class="manage-column column-cb check-column">
                <input id="cb-select-all-<?php echo esc_attr($place == 'header' ? '1' : '2') ?>" type="checkbox">
                <label for="cb-select-all-<?php echo esc_attr($place == 'header' ? '1' : '2') ?>"><span class="screen-reader-text"><?php echo esc_html_x('Select All', 'Admin terms', 'workeera-remote-tech-job-board') ?></span></label>
            </td>
            <th scope="col" class="manage-column column-name column-primary <?php echo esc_attr($name_sort_class) ?>"><a href="<?php echo esc_url(add_query_arg(array('orderby' => 'name', 'order' => $name_order), $sorting_url)) ?>"><span><?php echo esc_html_x('Name', 'Admin terms', 'workeera-remote-tech-job-board') ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a></th>
            <th scope="col" class="manage-column column-slug"><a><span><?php echo esc_html_x('Slug', 'Admin terms', 'workeera-remote-tech-job-board') ?></span></a></th>
            <?php
            $object_counts = $this->object_counts;
            if (!empty($object_counts) && is_array($object_counts)) {
                foreach ($object_counts as $object_count) {
                    $object_count_plural = isset($object_count['object_plural']) ? $object_count['object_plural'] : '';
                    $object_count_label = isset($object_count['label']) ? $object_count['label'] : '';

                    $objectcount_sort_class = 'sortable desc';
                    $objectcount_order = 'asc';
                    if ($orderby == $object_count_plural . '-count') {
                        $objectcount_sort_class = 'sorted' . $sort_order_class;
                        $objectcount_order = $order == 'asc' ? 'desc' : 'asc';
                    }
                    ?>
                    <th scope="col" class="manage-column column-posts workeera-term-objects-count workeera-<?php echo esc_attr($object_count_plural) ?>-count num <?php echo esc_attr($objectcount_sort_class) ?>"><a href="<?php echo esc_url(add_query_arg(array('orderby' => $object_count_plural . '-count', 'order' => $objectcount_order), $sorting_url)) ?>"><span><?php echo esc_html($object_count_label) ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a></th>
                    <?php
                }
            }
            ?>
        </tr>
        <?php
    }

    /**
     * Fetch a single term row from the taxonomy table.
     *
     * @param  int         $term_id Term id.
     * @return object|null          Term row, or null when not found.
     */
    private function get_row_data($term_id) {
        global $wpdb;

        $primary_key  = workeera_safe_sql_identifier( $this->primary_key );
        $table_suffix = workeera_safe_sql_identifier( $this->table_name );
        if ( $primary_key === '' || $table_suffix === '' ) {
            return;
        }
        $term_table = self::table_prefix() . $table_suffix;

        if (!$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $term_table ) )) {
            return false;
        }

        $terms_query = "SELECT * FROM `{$term_table}` WHERE %i=%d";
        $placeholder_arr = [$primary_key, $term_id];
        $term_data = $wpdb->get_row($wpdb->prepare($terms_query, $placeholder_arr));
        
        if (!empty($term_data) && isset($term_data->label)) {
            return $term_data;
        }
    }

    /**
     * Count terms in this taxonomy, honoring the search query var.
     *
     * @return int Total matching terms.
     */
    private function terms_count() {
        global $wpdb;

        $table_suffix = workeera_safe_sql_identifier( $this->table_name );
        if ( $table_suffix === '' ) {
            return 0;
        }
        $table_name = self::table_prefix() . $table_suffix;
        if (!$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) )) {
            return false;
        }

        $terms_query = "SELECT COUNT(*) FROM `{$table_name}` WHERE 1=%d";
        $search_var = workeera_get_query_var('s');
        if (!empty($search_var)) {
            $terms_query .= " AND label LIKE %s";
        }
        
        $placeholder_arr = [1];
        if (!empty($search_var)) {
            $placeholder_arr[] = '%' . $wpdb->esc_like(sanitize_text_field($search_var)) . '%';
        }

        $terms_count = $wpdb->get_var($wpdb->prepare($terms_query, $placeholder_arr));

        return $terms_count;
    }

    /**
     * Query the paginated, sorted, searchable list of terms for the current screen.
     *
     * @return array Array of term row objects.
     */
    private function terms_query_data() {
        global $wpdb;

        $table_suffix = workeera_safe_sql_identifier( $this->table_name );
        $primary_key  = workeera_safe_sql_identifier( $this->primary_key );
        if ( $table_suffix === '' || $primary_key === '' ) {
            return [];
        }
        $table_name = self::table_prefix() . $table_suffix;
        if (!$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) )) {
            return false;
        }

        $sorting_order = ' DESC';
        $workeera_terms_sortby = $primary_key;
        $per_page_results = $this->per_page_results;
        $terms_total_count = $this->terms_total_count;

        $total_pages = 1;
        if ($terms_total_count > $per_page_results) {
            $total_pages = ceil($terms_total_count/$per_page_results);
        }
        $offset = 0;
        $page_num = absint(workeera_get_query_var('page_num'));
        if ($page_num > 1) {
            $page_num = $page_num > $total_pages ? $total_pages : $page_num;
            $offset = ($page_num - 1) * $per_page_results;
        }

        $orderby = workeera_get_query_var('orderby');
        $order = workeera_get_query_var('order');
        if ($orderby == 'name') {
            $workeera_terms_sortby = 'label';
        }
        $sorting_order = $order == 'desc' ? 'DESC' : 'ASC';

        $terms_query = "SELECT * FROM `{$table_name}` AS terms";

        // Placeholders collected from the optional LEFT JOIN — kept separate so
        // they can be merged into the final placeholder array in the exact
        // order the `%s` / `%d` markers appear in the assembled query string.
        $join_placeholders = [];

        $terms_count_table = self::table_prefix() . 'term_counts';
        $object_counts = $this->object_counts;
        if (!empty($object_counts) && is_array($object_counts)) {
            foreach ($object_counts as $object_count) {
                $object_count_plural = isset($object_count['object_plural']) ? $object_count['object_plural'] : '';
                $object_count_single = isset($object_count['object_single']) ? $object_count['object_single'] : '';

                if ($orderby == $object_count_plural . '-count') {
                    $terms_query        .= " LEFT JOIN %i AS terms_count ON (terms_count.term_id = terms.%i AND term_type = %s AND terms_count.object_type = %s)";
                    $join_placeholders[] = $terms_count_table;
                    $join_placeholders[] = $primary_key;
                    $join_placeholders[] = $this->plural_slug;
                    $join_placeholders[] = $object_count_single;
                    $workeera_terms_sortby = 'terms_count.count';
                    break;
                }
            }
        }

        $terms_query .= " WHERE 1=1";

        $search_var = workeera_get_query_var('s');
        if (!empty($search_var)) {
            $terms_query .= " AND label LIKE %s";
        }

        $terms_query .= " ORDER BY %s %s";
        $terms_query .= " LIMIT %d OFFSET %d";

        // Build the placeholder array in the same order the markers appear
        // in the query: LEFT JOIN values (if any) → WHERE 1=1 → search LIKE
        // (if any) → LIMIT → OFFSET.
        $placeholder_arr = $join_placeholders;
        if (!empty($search_var)) {
            $placeholder_arr[] = '%' . $wpdb->esc_like(sanitize_text_field($search_var)) . '%';
        }
        $placeholder_arr[] = workeera_safe_sql_identifier($workeera_terms_sortby);
        $placeholder_arr[] = workeera_safe_sql_identifier($sorting_order);
        $placeholder_arr[] = absint($per_page_results);
        $placeholder_arr[] = absint($offset);
        $prepared_query = apply_filters('workeera_after_prepared_qry', $wpdb->prepare($terms_query, $placeholder_arr), [$workeera_terms_sortby, $sorting_order]);
        $terms_data = $wpdb->get_results($prepared_query);

        return $terms_data;
    }

    /**
     * AJAX handler that returns the inline edit form for a term.
     */
    public function update_term_form() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_admin_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [ 'update_form' => '' ] );
        }

        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;

        $term_obj = $this->get_row_data($term_id);

        $form_id = wp_rand(1000000, 9999999);
        ob_start();
        ?>
        <form method="post" class="workeera-form-style workeera-user-form">
            <div class="workeera-form-field">
                <label for="tag-name-<?php echo absint($form_id) ?>"><?php echo esc_html_x('Name', 'Admin terms', 'workeera-remote-tech-job-board') ?></label>
                <?php
                Workeera_Form_Fields::input(array(
                    'id' => 'tag-name-' . absint($form_id),
                    'name' => 'term_name',
                    'extra_attributes' => array('required' => ''),
                    'value' => (isset($term_obj->label) ? esc_attr($term_obj->label) : '')
                ));
                ?>
            </div>
            <div class="workeera-form-field">
                <label for="slug-<?php echo absint($form_id) ?>"><?php echo esc_html_x('Slug', 'Admin terms', 'workeera-remote-tech-job-board') ?></label>
                <?php
                Workeera_Form_Fields::input(array(
                    'id' => 'slug-' . absint($form_id),
                    'name' => 'slug',
                    'value' => (isset($term_obj->slug) ? esc_attr($term_obj->slug) : '')
                ));
                ?>
            </div>
            <?php
            $tax_parents = $this->parents;
            if (!empty($tax_parents) && is_array($tax_parents)) {
                foreach ($tax_parents as $parent_tax_slug => $parent_tax) {
                    $is_multi_parent_itms = false;
                    if ( isset($parent_tax['is_multi']) && $parent_tax['is_multi'] ) {
                        $is_multi_parent_itms = true;
                        $parent_tax_name = isset($parent_tax['plural_name']) ? $parent_tax['plural_name'] : '';
                    } else {
                        $parent_tax_name = isset($parent_tax['single_name']) ? $parent_tax['single_name'] : '';
                    }
                    $parent_tax_single_slug = isset($parent_tax['single_slug']) ? $parent_tax['single_slug'] : '';
                    $parent_tax_plural_slug = isset($parent_tax['plural_slug']) ? $parent_tax['plural_slug'] : '';
                    $parent_tax_primary_key = isset($parent_tax['primary_key']) ? $parent_tax['primary_key'] : '';
                    ?>
                    <div class="form-field term-<?php echo esc_attr($parent_tax_single_slug) ?>-wrap">
                        <label for="parent-<?php echo esc_attr($parent_tax_single_slug) ?>-<?php echo absint($form_id) ?>"><?php echo esc_html($parent_tax_name) ?></label>
                        <?php
                        $selected_parent_term_id = 0;
                        $parent_terms_option = array('0' => esc_html__('None', 'workeera-remote-tech-job-board'));
                        
                        if ($is_multi_parent_itms) {
                            $parent_terms_option = [];
                            $selected_parent_term_ids = [];
                            $parent_tax_db_colname = $parent_tax_primary_key . 's';
                            if (isset($term_obj->$parent_tax_db_colname) && !empty($term_obj->$parent_tax_db_colname)) {
                                $parent_tax_terms = maybe_unserialize($term_obj->$parent_tax_db_colname);
                                if (!empty($parent_tax_terms) && is_array($parent_tax_terms)) {
                                    foreach ($parent_tax_terms as $parent_tax_term) {
                                        $parent_term_obj = self::parent_term_row_data($parent_tax_term, $parent_tax);
                                        if (isset($parent_term_obj->label)) {
                                            $selected_parent_term_id = $parent_term_obj->$parent_tax_primary_key;
                                            $selected_parent_term_ids[] = $selected_parent_term_id;
                                            $parent_terms_option[$selected_parent_term_id] = $parent_term_obj->label;
                                        }
                                    }
                                }
                            }
                        } else {
                            if (isset($term_obj->$parent_tax_primary_key) && !empty($term_obj->$parent_tax_primary_key)) {
                                $parent_term_obj = self::parent_term_row_data($term_obj->$parent_tax_primary_key, $parent_tax);
                                if (isset($parent_term_obj->label)) {
                                    $selected_parent_term_id = $term_obj->$parent_tax_primary_key;
                                    $parent_terms_option[$selected_parent_term_id] = $parent_term_obj->label;
                                }
                            }
                        }
                        Workeera_Form_Fields::select(array(
                            'id' => 'parent-' . esc_attr($parent_tax_single_slug) . '-' . absint($form_id),
                            'name' => esc_attr($parent_tax_single_slug) . ($is_multi_parent_itms ? '[]' : ''),
                            'classes' => ['postform', 'workeera-parent-term-drpdwn'],
                            /* translators: %s: parent taxonomy name */
                            'extra_attributes' => array('data-placeholder' => sprintf(esc_html__('Select %s', 'workeera-remote-tech-job-board'), esc_html($parent_tax_name)), 'data-type' => $parent_tax_plural_slug),
                            'options' => $parent_terms_option,
                            'multiple' => $is_multi_parent_itms,
                            'value' => $is_multi_parent_itms ? $selected_parent_term_ids : $selected_parent_term_id
                        ));
                        ?>
                        <?php /* translators: 1: parent taxonomy name, 2: current taxonomy singular name */ ?>
                        <p><?php printf(esc_html__('Select %1$s for this %2$s.', 'workeera-remote-tech-job-board'), esc_html($parent_tax_name), esc_html($this->single_name)) ?></p>
                    </div>
                    <?php
                }
            }
            ?>
            <div class="workeera-form-field workeera-form-submitcon">
                <?php wp_nonce_field('workeera_admin_nonce', 'workeera_nonce'); ?>
                <input type="hidden" name="term_id" value="<?php echo absint($term_id) ?>">
                <input type="hidden" name="action" value="workeera_update_<?php echo esc_attr($this->single_slug) ?>_term">
                <button type="submit" class="button button-primary"><?php echo esc_html_x('Update', 'Admin terms', 'workeera-remote-tech-job-board') ?></button>
            </div>
        </form>
        <?php

        $update_form = ob_get_clean();
        wp_send_json(array('update_form' => $update_form));
    }

    /**
     * AJAX handler returning parent-term options for a Select2 dropdown.
     */
    public function get_parent_terms_dropdown() {
        
        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_admin_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        $terms = [array('id' => '0', 'text' => esc_html__('None', 'workeera-remote-tech-job-board'))];

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json(array('results' => $terms));
        }

        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $tax_type = isset($_POST['tax_type']) ? sanitize_key( wp_unslash( $_POST['tax_type'] ) ) : '';

        $tax_parents = $this->parents;
        if (empty($tax_type) || !isset($tax_parents[$tax_type])) {
            wp_send_json(array('results' => $terms));
        }

        if (isset($tax_parents[$tax_type]['is_multi']) && $tax_parents[$tax_type]['is_multi']) {
            $terms = [];
        }

        $parent_tax_plural_slug = isset($tax_parents[$tax_type]['plural_slug']) ? $tax_parents[$tax_type]['plural_slug'] : '';
        $parent_tax_primary_key = isset($tax_parents[$tax_type]['primary_key']) ? workeera_safe_sql_identifier( $tax_parents[$tax_type]['primary_key'] ) : '';
        $parent_tax_table_name = isset($tax_parents[$tax_type]['table_name']) ? workeera_safe_sql_identifier( $tax_parents[$tax_type]['table_name'] ) : '';
        if (empty($parent_tax_plural_slug) || empty($parent_tax_primary_key) || empty($parent_tax_table_name)) {
            wp_send_json(array('results' => $terms));
        }

        global $wpdb;

        $table_name = self::table_prefix() . $parent_tax_table_name;

        if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) ) {
            wp_send_json(array('results' => $terms));
        }

        $terms_query = "SELECT %i AS term_id, label FROM %i WHERE 1=1";

        if (isset($_POST['q'])) {
            $terms_query .= " AND label LIKE %s";
        }

        $terms_query .= " ORDER BY label 
        ASC";
        $terms_query .= " 
        LIMIT 200";

        $placeholder_arr = [$parent_tax_primary_key, $table_name];

        if (isset($_POST['q'])) {
            $placeholder_arr[] = '%' . $wpdb->esc_like(sanitize_text_field($_POST['q'])) . '%';
        }

        $query_results = $wpdb->get_results($wpdb->prepare($terms_query, $placeholder_arr));

        if (!empty($query_results)) {
            foreach ($query_results as $row) {
                $id = $row->term_id;
                $terms[] = array('id' => absint($id), 'text' => esc_html($row->label));
            }
        }

        wp_send_json(array('results' => $terms));
    }

    /**
     * Fetch a parent-taxonomy term row by id.
     *
     * @param  int         $term_id    Parent term id.
     * @param  array       $parent_tax Parent taxonomy config (table_name, primary_key).
     * @return object|null             Row data, or null when not found.
     */
    public static function parent_term_row_data($term_id, $parent_tax) {
        global $wpdb;

        $term_id = workeera_absint($term_id);
        $parent_tax_table_name = isset($parent_tax['table_name']) ? workeera_safe_sql_identifier( $parent_tax['table_name'] ) : '';
        $parent_tax_primary_key = isset($parent_tax['primary_key']) ? workeera_safe_sql_identifier( $parent_tax['primary_key'] ) : '';

        if ($parent_tax_table_name === '' || $parent_tax_primary_key === '') {
            return;
        }
        $table_name = self::table_prefix() . $parent_tax_table_name;

        if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) ) {
            return false;
        }

        $terms_query = "SELECT * FROM `{$table_name}` WHERE %i=%d";
        $placeholder_arr = [$parent_tax_primary_key, $term_id];

        $term_data = $wpdb->get_row($wpdb->prepare($terms_query, $placeholder_arr));
        
        if (!empty($term_data) && isset($term_data->label)) {
            return $term_data;
        }
    }

    /**
     * AJAX handler that inserts a new term and returns its rendered list row.
     */
    public function add_new_term() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_admin_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__('You are not allowed to do this.', 'workeera-remote-tech-job-board') ] );
        }

        global $wpdb;

        $term_name = isset($_POST['term_name']) ? sanitize_text_field( wp_unslash( $_POST['term_name'] ) ) : '';
        $slug = isset($_POST['slug']) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';

        if ($term_name == '') {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('Title field cannot be empty.', 'workeera-remote-tech-job-board')));
        }
        if (strlen($term_name) > 70) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('Title is too long. Please make it shorter.', 'workeera-remote-tech-job-board')));
        }

        if (!empty($slug)) {
            if (strlen($slug) > 70) {
                wp_send_json(array('error' => '1', 'msg' => esc_html__('Slug is too long. Please make it shorter.', 'workeera-remote-tech-job-board')));
            }
            $slug = workeera_make_title_slug($slug);
        } else {
            $slug = workeera_make_title_slug($term_name);
        }

        if (empty($slug)) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('Slug cannot be empty.', 'workeera-remote-tech-job-board')));
        }

        $slug = self::verify_slug($slug, $this->plural_slug);

        $table_suffix = workeera_safe_sql_identifier( $this->table_name );
        if ( $table_suffix === '' ) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('There is some error adding this term.', 'workeera-remote-tech-job-board')));
        }
        $table_name = self::table_prefix() . $table_suffix;

        $term_data = [
            'label' => $term_name,
            'slug' => $slug,
        ];
        $tax_parents = $this->parents;
        if (!empty($tax_parents) && is_array($tax_parents)) {
            foreach ($tax_parents as $parent_tax_slug => $parent_tax) {
                $parent_tax_primary_key = isset($parent_tax['primary_key']) ? $parent_tax['primary_key'] : '';
                $parent_tax_single_slug = isset($parent_tax['single_slug']) ? $parent_tax['single_slug'] : '';
                if ( isset($parent_tax['is_multi']) && $parent_tax['is_multi'] ) {
                    $parent_term_ids = isset($_POST[$parent_tax_single_slug]) ? array_map('absint', $_POST[$parent_tax_single_slug]) : [];
                    $prent_tax_terms_data = [];
                    if (!empty($parent_term_ids) && is_array($parent_term_ids)) {
                        foreach ($parent_term_ids as $parent_term_id) {
                            $parent_term_obj = self::parent_term_row_data($parent_term_id, $parent_tax);
                            if (isset($parent_term_obj->label)) {
                                $prent_tax_terms_data[] = 'term(' . $parent_term_id . ')';
                            }
                        }
                    }
                    if (empty($prent_tax_terms_data)) {
                        $prent_tax_terms_data = '';
                    } else {
                        $prent_tax_terms_data = maybe_serialize($prent_tax_terms_data);
                    }
                    $term_data[$parent_tax_primary_key . 's'] = $prent_tax_terms_data;
                } else {
                    $parent_term_id = isset($_POST[$parent_tax_single_slug]) ? absint($_POST[$parent_tax_single_slug]) : 0;
                    $parent_term_obj = self::parent_term_row_data($parent_term_id, $parent_tax);
                    if (isset($parent_term_obj->label)) {
                        $term_data[$parent_tax_primary_key] = $parent_term_id;
                    } else {
                        $term_data[$parent_tax_primary_key] = 0;
                    }
                }
            }
        }
        $term_added = $wpdb->insert($table_name, $term_data);
        $term_id = $wpdb->insert_id;

        if (!$term_added) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('There is some error adding this term.', 'workeera-remote-tech-job-board')));
        }

        $item_data = [
            'id' => $term_id,
            'label' => stripslashes($term_name),
            'slug' => $slug,
        ];

        ob_start();
        $this->list_item($item_data);
        $adding_itm = ob_get_clean();
        
        $sending_data = array(
            'error' => '0',
            /* translators: %s: taxonomy singular name */
            'msg' => sprintf(esc_html__('%s added successfully.', 'workeera-remote-tech-job-board'), esc_html($this->single_name)),
            'callback' => 'workeera_after_term_itemadd_success',
            'term_id' => $term_id,
            'adding_itm' => $adding_itm
        );
        wp_send_json($sending_data);
    }

    /**
     * AJAX handler that updates an existing term and returns the new label/slug.
     */
    public function update_term_item() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_admin_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__('You are not allowed to do this.', 'workeera-remote-tech-job-board') ] );
        }

        global $wpdb;

        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : '';
        $term_obj = $this->get_row_data($term_id);
        if (!isset($term_obj->label)) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('No item found to update.', 'workeera-remote-tech-job-board')));
        }
        $term_name = isset($_POST['term_name']) ? sanitize_text_field( wp_unslash( $_POST['term_name'] ) ) : '';
        $slug = isset($_POST['slug']) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';

        if ($term_name == '') {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('Title field cannot be empty.', 'workeera-remote-tech-job-board')));
        }
        if (strlen($term_name) > 70) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('Title is too long. Please make it shorter.', 'workeera-remote-tech-job-board')));
        }

        if (!empty($slug)) {
            if (strlen($slug) > 70) {
                wp_send_json(array('error' => '1', 'msg' => esc_html__('Slug is too long. Please make it shorter.', 'workeera-remote-tech-job-board')));
            }
            $slug = workeera_make_title_slug($slug);
        } else {
            $slug = workeera_make_title_slug($term_name);
        }

        if (empty($slug)) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('Slug cannot be empty.', 'workeera-remote-tech-job-board')));
        }

        $slug = self::verify_slug($slug, $this->plural_slug, $term_id);

        $table_suffix = workeera_safe_sql_identifier( $this->table_name );
        $primary_key  = workeera_safe_sql_identifier( $this->primary_key );
        if ( $table_suffix === '' || $primary_key === '' ) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('There is some error updating this term.', 'workeera-remote-tech-job-board')));
        }
        $table_name = self::table_prefix() . $table_suffix;

        $term_data = [
            'label' => stripslashes($term_name),
            'slug' => $slug,
        ];
        $tax_parents = $this->parents;
        if (!empty($tax_parents) && is_array($tax_parents)) {
            foreach ($tax_parents as $parent_tax_slug => $parent_tax) {
                $parent_tax_primary_key = isset($parent_tax['primary_key']) ? $parent_tax['primary_key'] : '';
                $parent_tax_single_slug = isset($parent_tax['single_slug']) ? $parent_tax['single_slug'] : '';
                if ( isset($parent_tax['is_multi']) && $parent_tax['is_multi'] ) {
                    $parent_term_ids = isset($_POST[$parent_tax_single_slug]) ? array_map('absint', $_POST[$parent_tax_single_slug]) : [];
                    $prent_tax_terms_data = [];
                    if (!empty($parent_term_ids) && is_array($parent_term_ids)) {
                        foreach ($parent_term_ids as $parent_term_id) {
                            $parent_term_obj = self::parent_term_row_data($parent_term_id, $parent_tax);
                            if (isset($parent_term_obj->label)) {
                                $prent_tax_terms_data[] = 'term(' . $parent_term_id . ')';
                            }
                        }
                    }
                    if (empty($prent_tax_terms_data)) {
                        $prent_tax_terms_data = '';
                    } else {
                        $prent_tax_terms_data = maybe_serialize($prent_tax_terms_data);
                    }
                    $term_data[$parent_tax_primary_key . 's'] = $prent_tax_terms_data;
                } else {
                    $parent_term_id = isset($_POST[$parent_tax_single_slug]) ? absint($_POST[$parent_tax_single_slug]) : 0;
                    $parent_term_obj = self::parent_term_row_data($parent_term_id, $parent_tax);
                    if (isset($parent_term_obj->label)) {
                        $term_data[$parent_tax_primary_key] = $parent_term_id;
                    } else {
                        $term_data[$parent_tax_primary_key] = 0;
                    }
                }
            }
        }
        $updated_term = $wpdb->update($table_name, $term_data, [$primary_key => $term_id]);

        if ($updated_term === false) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('There is some error updating this term.', 'workeera-remote-tech-job-board')));
        }

        $sending_data = array(
            'error' => '0',
            /* translators: %s: taxonomy singular name */
            'msg' => sprintf(esc_html__('%s updated successfully.', 'workeera-remote-tech-job-board'), esc_html($this->single_name)),
            'callback' => 'workeera_after_term_itemupdate_success',
            'term_id' => $term_id,
            'term_name' => $term_name,
            'term_slug' => $slug
        );
        wp_send_json($sending_data);
    }

    /**
     * AJAX handler that deletes a single term.
     */
    public function delete_term_item() {

        $nonce_val = isset($_POST['workeera_nonce']) ? sanitize_key(wp_unslash($_POST['workeera_nonce'])) : '';
        if (!wp_verify_nonce($nonce_val, 'workeera_admin_nonce')) {
            wp_send_json(array('error' => 'security_failed'));
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( [ 'error' => '1', 'msg' => esc_html__('You are not allowed to do this.', 'workeera-remote-tech-job-board') ] );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('You are not allowed to do this.', 'workeera-remote-tech-job-board')));
        }

        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : '';
        $term_obj = $this->get_row_data($term_id);
        if (!isset($term_obj->label)) {
            wp_send_json(array('error' => '1', 'msg' => esc_html__('No item found to delete.', 'workeera-remote-tech-job-board')));
        }

        $this->delete_term($term_id);

        $sending_data = array(
            'error' => '0',
            /* translators: %s: taxonomy singular name */
            'msg' => sprintf(esc_html__('%s deleted successfully.', 'workeera-remote-tech-job-board'), esc_html($this->single_name)),
        );
        wp_send_json($sending_data);
    }

    /**
     * Remove a term and its relational/count rows from the database.
     *
     * @param int $term_id Term id to delete.
     */
    protected function delete_term($term_id) {
        global $wpdb;

        // Validate the config-derived table suffix / primary-key column — these
        // reach $wpdb->delete() as the (unescaped) table name and a data key.
        $table_suffix = workeera_safe_sql_identifier( $this->table_name );
        $primary_key  = workeera_safe_sql_identifier( $this->primary_key );
        if ( $table_suffix === '' || $primary_key === '' ) {
            return;
        }

        $terms_table = self::table_prefix() . $table_suffix;
        $terms_rel_table = self::table_prefix() . $table_suffix . '_relation';
        $terms_count_table = self::table_prefix() . 'term_counts';

        $where_clause = [$primary_key => $term_id];
        $wpdb->delete($terms_table, $where_clause, ['%d']);
        if ($wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $terms_rel_table ) )) {
            $wpdb->delete($terms_rel_table, [$primary_key => $term_id], ['%d']);
        }
        $wpdb->delete($terms_count_table, ['term_id' => $term_id, 'term_type' => $this->plural_slug], ['%d', '%s']);
    }

    /**
     * Render the import-data UI block inside the "Add New" column.
     *
     * Hidden when data has already been imported and terms still exist.
     * Emits data-has-parent="1" when the taxonomy has parent terms that
     * must be imported first (sectors for skills, countries for cities).
     */
    private function render_import_section() {
        $all_taxes   = Workeera_Custom_Taxonomies::list();
        $tax_cfg     = isset( $all_taxes[ $this->plural_slug ] ) ? $all_taxes[ $this->plural_slug ] : [];
        $has_parents = ! empty( $tax_cfg['parents'] );
        $has_sample  = ! empty( Workeera_Import_Data::sample_file_path( $this->plural_slug ) );

        if ( ! isset( $all_taxes[ $this->plural_slug ] ) ) {
            return;
        }

        global $wpdb;
        $cfg = $all_taxes[ $this->plural_slug ];
        $table_suffix = workeera_safe_sql_identifier( $cfg['table_name'] );
        if ( $table_suffix === '' ) {
            return;
        }
        $table_name = $wpdb->prefix . 'workeera_' . $table_suffix;

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
            return;
        }

        $terms_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table_name}` WHERE 1=%d", 1 ) );

        $download_nonce = wp_create_nonce( 'workeera_importdata_download' );
        $download_url   = add_query_arg( [
            'action'       => 'workeera_importdata_download_sample',
            'tax_slug'     => $this->plural_slug,
            'workeera_nonce' => $download_nonce,
        ], admin_url( 'admin-ajax.php' ) );
        ?>
        <div class="workeera-importdata-section"
             data-tax-slug="<?php echo esc_attr( $this->plural_slug ); ?>"
             data-tax-name="<?php echo esc_attr( $this->plural_name ); ?>"
             data-has-parent="<?php echo esc_attr( $has_parents ? '1' : '0' ); ?>"
             data-chunk-size="<?php echo absint( Workeera_Import_Data::CHUNK_SIZE ); ?>">

            <h2><?php echo esc_html_x( 'Import Data', 'Import section title', 'workeera-remote-tech-job-board' ); ?></h2>
            <?php
            if ( $has_parents && ! empty($tax_cfg['parents']) ) {
                foreach ( $tax_cfg['parents'] as $tax_parent ) {
                    ?>
                    <div class="workeera-importdata-actbtns-infocon">
                        <?php /* translators: %s: parent taxonomy plural name */ ?>
                        <em><?php printf( esc_html_x( 'Import Data will automatically import its parent %s too.', 'Import section info', 'workeera-remote-tech-job-board' ), esc_html( $tax_parent['plural_name'] ) ); ?></em>
                    </div>
                    <?php
                }
            }
            ?>
            <div class="workeera-importdata-actions">
                <?php if ( $has_sample && ( ! Workeera_Import_Data::is_imported( $this->plural_slug ) || ! $terms_count ) ) : ?>
                    <button type="button" class="button workeera-importdata-sample-btn">
                        <?php
                        /* translators: %s taxonomy plural name */
                        printf( esc_html_x( 'Import Sample %s', 'Import button', 'workeera-remote-tech-job-board' ), esc_html( $this->plural_name ) );
                        ?>
                    </button>
                <?php endif; ?>

                <label class="button workeera-importdata-upload-label" for="workeera-importdata-file-<?php echo esc_attr( $this->plural_slug ); ?>">
                    <?php echo esc_html_x( 'Upload CSV &amp; Import', 'Import button', 'workeera-remote-tech-job-board' ); ?>
                </label>
                <input type="file"
                       id="workeera-importdata-file-<?php echo esc_attr( $this->plural_slug ); ?>"
                       class="workeera-importdata-file-input"
                       accept=".csv"
                       style="display:none;">

                <?php if ( $has_sample ) : ?>
                    <a href="<?php echo esc_url( $download_url ); ?>"
                       class="button workeera-importdata-download-btn"
                       target="_blank">
                        <?php echo esc_html_x( 'Download Sample CSV', 'Download button', 'workeera-remote-tech-job-board' ); ?>
                    </a>
                <?php endif; ?>
            </div>

            <div class="workeera-importdata-progress-wrap workeera-hide">
                <div class="workeera-importdata-progress-bar-wrap">
                    <div class="workeera-importdata-progress-bar" style="width:0%"></div>
                </div>
                <span class="workeera-importdata-progress-txt">0%</span>
            </div>

        </div>
        <?php
        if ( $terms_count >= 10 ) {
            ?>
            <div class="workeera-importdata-section"
                data-tax-slug="<?php echo esc_attr( $this->plural_slug ); ?>"
                data-tax-name="<?php echo esc_attr( $this->plural_name ); ?>">
                <div class="workeera-importdata-actions workeera-importdata-remve-actions">
                    <button type="button" class="button workeera-importdata-clear-btn">
                        <?php
                        /* translators: %s taxonomy plural name */
                        printf( esc_html_x( 'Remove all %s data', 'Import button', 'workeera-remote-tech-job-board' ), esc_html( $this->plural_name ) );
                        ?>
                    </button>
                </div>
            </div>
            <?php
        }
    }
}

class Workeera_Custom_Taxonomies {
    public function __construct() {
        add_filter('workeera_custom_db_tables_list', [$this, 'tables'], 10, 2);
        add_action('init', [$this, 'create_objects'], 5);
    }

    public function tables($custom_tables, $collate) {
        global $wpdb;

        $prefix = $wpdb->prefix;

        $taxonomies_tables = [];

        $taxonomies_tables["workeera_countries"] = ["CREATE TABLE `{$prefix}workeera_countries` (
            country_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            label VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL,
            PRIMARY KEY  (country_id),
            UNIQUE KEY slug (slug)
            ) $collate;",
        ];
        $taxonomies_tables["workeera_cities"] = ["CREATE TABLE `{$prefix}workeera_cities` (
            city_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            country_id INT UNSIGNED NOT NULL,
            label VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL,
            PRIMARY KEY  (city_id),
            KEY country_id (country_id),
            UNIQUE KEY slug (slug)
            ) $collate;",
        ];
        $taxonomies_tables["workeera_sectors"] = ["CREATE TABLE `{$prefix}workeera_sectors` (
            sector_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            label VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL,
            PRIMARY KEY  (sector_id),
            UNIQUE KEY slug (slug)
            ) $collate;",
        ];
        $taxonomies_tables["workeera_sectors_relation"] = ["CREATE TABLE `{$prefix}workeera_sectors_relation` (
            sector_rel_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            sector_id INT UNSIGNED NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(100) NOT NULL DEFAULT 'post',
            PRIMARY KEY  (sector_rel_id),
            KEY object_type (object_type),
            KEY object_id (object_id),
            KEY sector_id (sector_id)
            ) $collate;",
        ];
        $taxonomies_tables["workeera_skills"] = ["CREATE TABLE `{$prefix}workeera_skills` (
            skill_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            sector_ids TEXT NULL,
            label VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL,
            PRIMARY KEY  (skill_id),
            UNIQUE KEY slug (slug)
            ) $collate;",
        ];
        $taxonomies_tables["workeera_skills_relation"] = ["CREATE TABLE `{$prefix}workeera_skills_relation` (
            skill_rel_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            skill_id INT UNSIGNED NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(100) NOT NULL DEFAULT 'post',
            PRIMARY KEY  (skill_rel_id),
            KEY object_type (object_type),
            KEY object_id (object_id),
            KEY skill_id (skill_id)
            ) $collate;",
        ];
        $taxonomies_tables["workeera_jobtypes"] = ["CREATE TABLE `{$prefix}workeera_jobtypes` (
            jobtype_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            label VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL,
            PRIMARY KEY  (jobtype_id),
            UNIQUE KEY slug (slug)
            ) $collate;",
        ];
        $taxonomies_tables["workeera_jobtypes_relation"] = ["CREATE TABLE `{$prefix}workeera_jobtypes_relation` (
            jobtype_rel_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            jobtype_id INT UNSIGNED NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(100) NOT NULL DEFAULT 'post',
            PRIMARY KEY  (jobtype_rel_id),
            KEY object_type (object_type),
            KEY object_id (object_id),
            KEY jobtype_id (jobtype_id)
            ) $collate;",
        ];
        $taxonomies_tables["workeera_timezones"] = ["CREATE TABLE `{$prefix}workeera_timezones` (
            timezone_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            label VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL,
            PRIMARY KEY  (timezone_id),
            UNIQUE KEY slug (slug)
            ) $collate;",
        ];
        $taxonomies_tables["workeera_timezones_relation"] = ["CREATE TABLE `{$prefix}workeera_timezones_relation` (
            timezone_rel_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            timezone_id INT UNSIGNED NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(100) NOT NULL DEFAULT 'post',
            PRIMARY KEY  (timezone_rel_id),
            KEY object_type (object_type),
            KEY object_id (object_id),
            KEY timezone_id (timezone_id)
            ) $collate;",
        ];
        $taxonomies_tables["workeera_benefits"] = ["CREATE TABLE `{$prefix}workeera_benefits` (
            benefit_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            label VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL,
            PRIMARY KEY  (benefit_id),
            UNIQUE KEY slug (slug)
            ) $collate;",
        ];
        $taxonomies_tables["workeera_benefits_relation"] = ["CREATE TABLE `{$prefix}workeera_benefits_relation` (
            benefit_rel_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            benefit_id INT UNSIGNED NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(100) NOT NULL DEFAULT 'post',
            PRIMARY KEY  (benefit_rel_id),
            KEY object_type (object_type),
            KEY object_id (object_id),
            KEY benefit_id (benefit_id)
            ) $collate;",
        ];
        $taxonomies_tables["workeera_experiences"] = ["CREATE TABLE `{$prefix}workeera_experiences` (
            experience_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            label VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL,
            PRIMARY KEY  (experience_id),
            UNIQUE KEY slug (slug)
            ) $collate;",
        ];
        $taxonomies_tables["workeera_experiences_relation"] = ["CREATE TABLE `{$prefix}workeera_experiences_relation` (
            experience_rel_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            experience_id INT UNSIGNED NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            object_type VARCHAR(100) NOT NULL DEFAULT 'post',
            PRIMARY KEY  (experience_rel_id),
            KEY object_type (object_type),
            KEY object_id (object_id),
            KEY experience_id (experience_id)
            ) $collate;",
        ];

        $taxonomies_tables = apply_filters('workeera_common_taxonomies_tables_list', $taxonomies_tables, $collate);

        if (!empty($taxonomies_tables) && is_array($taxonomies_tables)) {
            $custom_tables = array_merge($custom_tables, $taxonomies_tables);
        }

        return $custom_tables;
    }

    public static function list() {
        $taxononmies_list = [
            'countries' => [
                'single_slug' => 'country',
                'plural_slug' => 'countries',
                'single_name' => esc_html_x('Country', 'Admin title', 'workeera-remote-tech-job-board'),
                'plural_name' => esc_html_x('Countries', 'Admin title', 'workeera-remote-tech-job-board'),
                'table_name' => 'countries',
                'primary_key' => 'country_id',
                'per_page_terms' => 15,
                'counts' => [
                    'jobs' => [
                        'object_single' => 'job',
                        'object_plural' => 'jobs',
                        'label' => esc_html_x('Jobs Count', 'Admin title', 'workeera-remote-tech-job-board'),
                    ]
                ]
            ],
            'cities' => [
                'single_slug' => 'city',
                'plural_slug' => 'cities',
                'single_name' => esc_html_x('City', 'Admin title', 'workeera-remote-tech-job-board'),
                'plural_name' => esc_html_x('Cities', 'Admin title', 'workeera-remote-tech-job-board'),
                'table_name' => 'cities',
                'primary_key' => 'city_id',
                'per_page_terms' => 15,
                'parents' => [
                    'countries' => [
                        'single_slug' => 'country',
                        'plural_slug' => 'countries',
                        'single_name' => esc_html_x('Country', 'Admin title', 'workeera-remote-tech-job-board'),
                        'plural_name' => esc_html_x('Countries', 'Admin title', 'workeera-remote-tech-job-board'),
                        'table_name' => 'countries',
                        'primary_key' => 'country_id',
                    ]
                ],
                'counts' => [
                    'jobs' => [
                        'object_single' => 'job',
                        'object_plural' => 'jobs',
                        'label' => esc_html_x('Jobs Count', 'Admin title', 'workeera-remote-tech-job-board'),
                    ]
                ]
            ],
            'sectors' => [
                'single_slug' => 'sector',
                'plural_slug' => 'sectors',
                'single_name' => esc_html_x('Sector', 'Admin title', 'workeera-remote-tech-job-board'),
                'plural_name' => esc_html_x('Sectors', 'Admin title', 'workeera-remote-tech-job-board'),
                'table_name' => 'sectors',
                'primary_key' => 'sector_id',
                'per_page_terms' => 15,
                'counts' => [
                    'jobs' => [
                        'object_single' => 'job',
                        'object_plural' => 'jobs',
                        'label' => esc_html_x('Jobs Count', 'Admin title', 'workeera-remote-tech-job-board'),
                    ],
                    'candidates' => [
                        'object_single' => 'candidate',
                        'object_plural' => 'candidates',
                        'label' => esc_html_x('Candidates Count', 'Admin title', 'workeera-remote-tech-job-board'),
                    ]
                ]
            ],
            'skills' => [
                'single_slug' => 'skill',
                'plural_slug' => 'skills',
                'single_name' => esc_html_x('Skill', 'Admin title', 'workeera-remote-tech-job-board'),
                'plural_name' => esc_html_x('Skills', 'Admin title', 'workeera-remote-tech-job-board'),
                'table_name' => 'skills',
                'primary_key' => 'skill_id',
                'per_page_terms' => 15,
                'parents' => [
                    'sectors' => [
                        'is_multi' => true,
                        'single_slug' => 'sector',
                        'plural_slug' => 'sectors',
                        'single_name' => esc_html_x('Sector', 'Admin title', 'workeera-remote-tech-job-board'),
                        'plural_name' => esc_html_x('Sectors', 'Admin title', 'workeera-remote-tech-job-board'),
                        'table_name' => 'sectors',
                        'primary_key' => 'sector_id',
                    ]
                ],
                'counts' => [
                    'jobs' => [
                        'object_single' => 'job',
                        'object_plural' => 'jobs',
                        'label' => esc_html_x('Jobs Count', 'Admin title', 'workeera-remote-tech-job-board'),
                    ],
                    'candidates' => [
                        'object_single' => 'candidate',
                        'object_plural' => 'candidates',
                        'label' => esc_html_x('Candidates Count', 'Admin title', 'workeera-remote-tech-job-board'),
                    ]
                ]
            ],
            'jobtypes' => [
                'single_slug' => 'jobtype',
                'plural_slug' => 'jobtypes',
                'single_name' => esc_html_x('Job Type', 'Admin title', 'workeera-remote-tech-job-board'),
                'plural_name' => esc_html_x('Job Types', 'Admin title', 'workeera-remote-tech-job-board'),
                'table_name' => 'jobtypes',
                'primary_key' => 'jobtype_id',
                'page_name' => 'edit.php',
                'query_args' => ['post_type' => 'workeera_job'],
                'per_page_terms' => 15,
                'counts' => [
                    'jobs' => [
                        'object_single' => 'job',
                        'object_plural' => 'jobs',
                        'label' => esc_html_x('Jobs Count', 'Admin title', 'workeera-remote-tech-job-board'),
                    ]
                ]
            ],
            'timezones' => [
                'single_slug' => 'timezone',
                'plural_slug' => 'timezones',
                'single_name' => esc_html_x('Timezone', 'Admin title', 'workeera-remote-tech-job-board'),
                'plural_name' => esc_html_x('Timezones', 'Admin title', 'workeera-remote-tech-job-board'),
                'table_name' => 'timezones',
                'primary_key' => 'timezone_id',
                'page_name' => 'edit.php',
                'query_args' => ['post_type' => 'workeera_job'],
                'per_page_terms' => 15,
                'counts' => [
                    'jobs' => [
                        'object_single' => 'job',
                        'object_plural' => 'jobs',
                        'label' => esc_html_x('Jobs Count', 'Admin title', 'workeera-remote-tech-job-board'),
                    ]
                ]
            ],
            'benefits' => [
                'single_slug' => 'benefit',
                'plural_slug' => 'benefits',
                'single_name' => esc_html_x('Benefit', 'Admin title', 'workeera-remote-tech-job-board'),
                'plural_name' => esc_html_x('Benefits', 'Admin title', 'workeera-remote-tech-job-board'),
                'table_name' => 'benefits',
                'primary_key' => 'benefit_id',
                'page_name' => 'edit.php',
                'query_args' => ['post_type' => 'workeera_job'],
                'per_page_terms' => 15,
                'counts' => [
                    'jobs' => [
                        'object_single' => 'job',
                        'object_plural' => 'jobs',
                        'label' => esc_html_x('Jobs Count', 'Admin title', 'workeera-remote-tech-job-board'),
                    ]
                ]
            ],
            'experiences' => [
                'single_slug' => 'experience',
                'plural_slug' => 'experiences',
                'single_name' => esc_html_x('Experience Level', 'Admin title', 'workeera-remote-tech-job-board'),
                'plural_name' => esc_html_x('Experience Levels', 'Admin title', 'workeera-remote-tech-job-board'),
                'table_name' => 'experiences',
                'primary_key' => 'experience_id',
                'page_name' => 'edit.php',
                'query_args' => ['post_type' => 'workeera_candidate'],
                'per_page_terms' => 15,
                'counts' => [
                    'candidates' => [
                        'object_single' => 'candidate',
                        'object_plural' => 'candidates',
                        'label' => esc_html_x('Candidates Count', 'Admin title', 'workeera-remote-tech-job-board'),
                    ]
                ]
            ],
        ];

        return apply_filters('workeera_common_taxonomies_list', $taxononmies_list);
    }

    public function create_objects() {
        $taxononmies_list = self::list();

        if (!empty($taxononmies_list) && is_array($taxononmies_list)) {
            foreach ($taxononmies_list as $taxonomy => $taxonomy_data) {
                new Workeera_Common_Taxonomy($taxonomy_data);
            }
        }
    }
}

new Workeera_Custom_Taxonomies;
