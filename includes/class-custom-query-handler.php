<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Thin wrapper around $wpdb for the plugin's custom tables.
 *
 * Provides generic insert-or-update, lookup, and delete helpers keyed by
 * the `wp_workeera_*` table-name suffix.
 */
class Workeera_Query_Handler {

    /**
     * Return the `wp_workeera_` table-name prefix for the current site.
     *
     * @return string Fully qualified prefix including the WP table prefix.
     */
    private static function table_prefix() {
        global $wpdb;

        return $wpdb->prefix . 'workeera_';
    }

    /**
     * Resolve which $wpdb fetch method to use for a column-spec argument.
     *
     * @param  string|array $get_cols 'all' / column list to select.
     * @return array{0:string,1:string} Tuple of [SELECT column list, method ('results'|'col')].
     */
    private static function get_cols_method($get_cols) {
        $get_method = 'results';
        $cols_str = '*';
        if (is_array($get_cols)) {
            if (count($get_cols) == 1) {
                $get_method = 'col';
            }
            // Validate every requested column name — identifiers can't be bound
            // via prepare(). If any column is not a bare identifier, fall back to
            // SELECT * rather than risk interpolating an unsafe token.
            $safe_cols = array();
            foreach ($get_cols as $col) {
                $safe_col = workeera_safe_sql_identifier($col);
                if ($safe_col === '') {
                    $safe_cols = array();
                    break;
                }
                $safe_cols[] = '`' . $safe_col . '`';
            }
            $cols_str = ! empty($safe_cols) ? implode(',', $safe_cols) : '*';
        }

        return [$cols_str, $get_method];
    }

    /**
     * Upsert a row into one of the plugin's custom tables.
     *
     * When `$update` is true and a matching row exists (matched by `$item_id`
     * and, for the metadata table, the `object_id` / `object_type` / `meta_key`
     * triple), the existing row is updated; otherwise a new row is inserted.
     *
     * @param  string $custom_table_name Suffix after `wp_workeera_`.
     * @param  string $item_id_col       Primary-key column name.
     * @param  int    $item_id           Existing row ID, or 0 to insert.
     * @param  array  $data              Column => value map (meta_value is auto-serialised).
     * @param  bool   $update            Update existing row when found.
     * @return int                       ID of the inserted/updated row.
     */
    public static function save_item_data($custom_table_name, $item_id_col, $item_id, $data, $update = true) {
        global $wpdb;

        // Validate the table suffix and primary-key column (both supplied by the
        // calling code) before interpolating — identifiers can't be bound via prepare.
        $table_suffix = workeera_safe_sql_identifier( $custom_table_name );
        $item_id_col  = workeera_safe_sql_identifier( $item_id_col );
        if ( $table_suffix === '' || $item_id_col === '' ) {
            return 0;
        }

        $table_prefix = self::table_prefix();
        $table_name = $table_prefix . $table_suffix;
        $placeholder_arr = [$item_id_col];
        $dataitm_query = "SELECT %i FROM `{$table_name}` WHERE 1=1";
        if (!empty($item_id)) {
            $dataitm_query .= " AND %i=%d";
            $placeholder_arr[] = $item_id_col;
            $placeholder_arr[] = $item_id;
        }
        if (isset($data['meta_key'])) {
            $data['meta_value'] = maybe_serialize($data['meta_value']);
            $meta_key = $data['meta_key'];
            $dataitm_query .= " AND object_id=%d AND object_type=%s AND meta_key=%s";
            $placeholder_arr[] = $data['object_id'];
            $placeholder_arr[] = $data['object_type'];
            $placeholder_arr[] = $meta_key;
        }

        // Build explicit format arrays for $wpdb->insert / $wpdb->update so
        // every value is type-bound (%d for *_id columns, %s otherwise).
        $data_format = self::build_format_array( $data );

        // Without an item ID or a meta-key scope the lookup query has no WHERE
        // condition and would match an arbitrary first row — skip it and treat
        // the call as an insert instead.
        $dataitm_id = 0;
        if ( ! empty( $item_id ) || isset( $data['meta_key'] ) ) {
            $dataitm_id = $wpdb->get_var($wpdb->prepare($dataitm_query, $placeholder_arr));
        }

        if ($dataitm_id > 0 && $update === true) {

            $dataitm_row_id = $dataitm_id;

            $where = array($item_id_col => $dataitm_row_id);
            if (isset($meta_key)) {
                $where['object_id'] = $data['object_id'];
                $where['object_type'] = $data['object_type'];
                $where['meta_key'] = $meta_key;
            }
            $where_format = self::build_format_array( $where );
            $wpdb->update($table_name, $data, $where, $data_format, $where_format);

            $item_data_id = $dataitm_row_id;
        } else {

            // Carry the looked-up key column into the insert so identifying
            // columns (e.g. job_id) never fall back to the column default of 0.
            if ( ! empty( $item_id ) && ! array_key_exists( $item_id_col, $data ) ) {
                $data[ $item_id_col ] = $item_id;
                $data_format = self::build_format_array( $data );
            }

            // Safety net: a job_fields row must always belong to a real job —
            // inserting without job_id would collide on the unique job_id=0 key.
            if ( $table_suffix === 'job_fields' && empty( $data['job_id'] ) ) {
                return 0;
            }

            $wpdb->insert($table_name, $data, $data_format);

            $item_data_id = $wpdb->insert_id;
        }

        return $item_data_id;
    }

    /**
     * Derive a `$wpdb->insert/update` format array from an associative data
     * array. Column names containing `_id` are bound as `%d`; everything else
     * defaults to `%s`. Mirrors the convention used elsewhere in the plugin.
     *
     * @param  array $data Column => value map about to be written.
     * @return array       Positional format array matching `$data` key order.
     */
    private static function build_format_array( $data ) {
        $format = array();
        foreach ( array_keys( $data ) as $key ) {
            $format[] = ( is_string( $key ) && strpos( $key, '_id' ) !== false ) ? '%d' : '%s';
        }
        return $format;
    }

    /**
     * Fetch one or more rows from a custom table with a simple WHERE clause.
     *
     * Column names containing `_id` are bound as `%d`; everything else as `%s`.
     * Returned values are passed through `maybe_unserialize`.
     *
     * @param  string       $custom_table_name Suffix after `wp_workeera_`.
     * @param  array        $where_clause      Column => value WHERE constraints.
     * @param  string|array $get_cols          'all' or list of columns to SELECT.
     * @param  int|string   $limit             LIMIT count, or '-1' for unbounded.
     * @param  bool         $get_var           Return a single scalar via `get_var`.
     * @return mixed                            Row, array of rows, scalar value, or null.
     */
    public static function get_saved_record($custom_table_name, $where_clause = [], $get_cols = 'all', $limit = 1, $get_var = false) {
        global $wpdb;

        // Validate the table-name suffix supplied by the caller before interpolating.
        $table_suffix = workeera_safe_sql_identifier( $custom_table_name );
        if ( $table_suffix === '' ) {
            return null;
        }
        $table_prefix = self::table_prefix();
        $table_name = $table_prefix . $table_suffix;

        $cols_method = self::get_cols_method($get_cols);
        $cols_str = $cols_method[0];
        $get_method = $cols_method[1];

        // $cols_str is either '*' or a backticked, validated column list.
        $data_query = "SELECT {$cols_str} FROM `{$table_name}` WHERE 1=%d";

        $placeholder_arr = [1];
        if (!empty($where_clause)) {
            foreach ($where_clause as $where_key => $where_val) {
                // Whitelist the WHERE column name — identifiers can't be bound
                // through $wpdb->prepare, so any non-[a-z_0-9] value here
                // would be a SQL-injection vector. Skip silently if invalid.
                $safe_where_key = workeera_safe_sql_identifier( $where_key );
                if ($safe_where_key === '') {
                    continue;
                }
                $data_query .= " AND `{$safe_where_key}` = " . (strpos($safe_where_key, '_id') !== false ? '%d' : '%s');
                $placeholder_arr[] = $where_val;
            }
        }

        // Cast $limit to int so a malicious string can't break out of the
        // LIMIT clause. The `-1` sentinel keeps its existing "no LIMIT" meaning.
        if ($limit != '-1') {
            $data_query .= ' LIMIT ' . absint($limit);
        }

        if ($get_var === true && $get_cols != 'all') {
            $var_rec = $wpdb->get_var($wpdb->prepare($data_query, $placeholder_arr));
            return maybe_unserialize($var_rec);
        }

        if ($get_method == 'col') {
            $query_result = $wpdb->get_col($wpdb->prepare($data_query, $placeholder_arr));
        } else {
            $query_result = $wpdb->get_results($wpdb->prepare($data_query, $placeholder_arr), 'ARRAY_A');
        }

        if (isset($query_result[0])) {
            if ($limit != 1) {
                return $query_result;
            }

            $record_data = $query_result[0];
            if (!empty($record_data) && is_array($record_data)) {
                return array_map('maybe_unserialize', $record_data);
            } else {
                return maybe_unserialize($record_data);
            }
        }
    }

    /**
     * Delete rows from a custom table matching the given WHERE clause.
     *
     * @param string $custom_table_name Suffix after `wp_workeera_`.
     * @param array  $where_clause      Column => value WHERE constraints.
     * @param array  $placeholders      $wpdb->delete format placeholders.
     */
    public static function delete_saved_record($custom_table_name, $where_clause, $placeholders = ['%d']) {
        global $wpdb;

        $table_prefix = self::table_prefix();
        $table_name = $table_prefix . $custom_table_name;

        $wpdb->delete($table_name, $where_clause, $placeholders);
    }
}
