<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Custom transient store backed by the wp_workeera_transients table.
 *
 * Provides set/get/delete/garbage-collect helpers that bypass the WP
 * options-based transient API.
 */
class Workeera_Transients {

    /**
     * Insert or update a transient row by name.
     *
     * @param array $args {
     *     @type string $name    Transient name (required).
     *     @type mixed  $value   Value to store; serialised if non-scalar.
     *     @type int    $timeout Lifetime in seconds; 0 / unset means no expiry.
     * }
     */
    public static function set($args) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'workeera_transients';

        if (isset($args['name']) && !empty($args['name'])) {
            $time = time();
            $trans_name = $args['name'];
            //
            $trans_data = [
                'trans_name' => $trans_name,
                'trans_value' => !empty($args['value']) ? maybe_serialize($args['value']) : '',
            ];
            // Column format types for $wpdb->insert / $wpdb->update.
            $trans_format = [ '%s', '%s' ];
            if (isset($args['timeout']) && $args['timeout'] > 0) {
                $trans_data['trans_timeout'] = $time + absint($args['timeout']);
                $trans_format[] = '%d';
            }
            //
            $trans_id = $wpdb->get_var($wpdb->prepare("SELECT trans_id FROM {$wpdb->prefix}workeera_transients WHERE trans_name=%s", $trans_name));
            if (!empty($trans_id)) {
                $wpdb->update($table_name, $trans_data, ['trans_id' => $trans_id], $trans_format, ['%d']);
            } else {
                $wpdb->insert($table_name, $trans_data, $trans_format);
            }
        }
    }

    /**
     * Retrieve a transient value by name, honouring its timeout.
     *
     * @param  string $trans_name Transient name.
     * @return mixed              Unserialised value, or empty string if missing/expired.
     */
    public static function get($trans_name) {
        global $wpdb;

        $trans_row = $wpdb->get_row($wpdb->prepare("SELECT trans_value,trans_timeout FROM {$wpdb->prefix}workeera_transients WHERE trans_name=%s", $trans_name), 'ARRAY_A');

        $time = time();
        $trans_val = '';
        if (isset($trans_row['trans_timeout']) && $trans_row['trans_timeout'] > $time) {
            $trans_val = maybe_unserialize($trans_row['trans_value']);
        }

        return $trans_val;
    }

    /**
     * Delete a transient row by name.
     *
     * @param string $trans_name Transient name.
     */
    public static function delete($trans_name) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'workeera_transients';

        $trans_id = $wpdb->get_var($wpdb->prepare("SELECT trans_id FROM {$wpdb->prefix}workeera_transients WHERE trans_name=%s", $trans_name));
        if (!empty($trans_id)) {
            $wpdb->delete($table_name, ['trans_id' => $trans_id], ['%d']);
        }
    }

    /**
     * Remove all transient rows whose timeout has elapsed.
     */
    public static function delete_past_transients() {
        global $wpdb;

        $time = time();
        $trans_res = $wpdb->get_col( $wpdb->prepare( "SELECT trans_name FROM {$wpdb->prefix}workeera_transients WHERE trans_timeout < %d", $time ) );

        if (!empty($trans_res) && is_array($trans_res)) {
            foreach ($trans_res as $trans_name) {
                self::delete($trans_name);
            }
        }
    }
}