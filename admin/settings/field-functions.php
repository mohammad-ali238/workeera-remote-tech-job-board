<?php

namespace Workeera_Options;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Settings-field render callbacks shared across all Workeera admin option tabs.
 */
class Field_Functions {

    /**
     * Sanitise a Workeera settings option blob before it is written to wp_options.
     *
     * Registered as the `sanitize_callback` for every `register_setting()` call
     * in the plugin so user-submitted settings can't smuggle raw HTML / scripts
     * into the option array. The callback recurses through nested arrays and
     * keeps keys but coerces scalar leaves through `sanitize_text_field()`.
     *
     * Non-array inputs (defensive — WP normally hands us an array) are passed
     * through `sanitize_text_field()` and returned. `null` is normalised to an
     * empty array so a wiped-clean settings form doesn't end up storing NULL.
     *
     * @param  mixed $value Raw value submitted by the Settings API.
     * @return mixed        Sanitised value safe to persist.
     */
    public static function sanitize_options( $value ) {
        if ( null === $value ) {
            return array();
        }
        if ( is_array( $value ) ) {
            $clean = array();
            foreach ( $value as $key => $item ) {
                $clean_key = sanitize_key( $key );
                if ( $clean_key === '' ) {
                    continue;
                }
                $clean[ $clean_key ] = self::sanitize_options( $item );
            }
            return $clean;
        }
        if ( is_scalar( $value ) ) {
            return sanitize_text_field( (string) $value );
        }
        // Objects, resources, etc. — drop them.
        return '';
    }

    /**
     * Sanitise a settings option blob against a per-field schema.
     *
     * This is the type-aware counterpart to {@see sanitize_options()}. Each
     * settings group passes a `field_name => rule` map so numeric fields go
     * through `absint`, enums are whitelisted, multi-selects keep only allowed
     * values, switches normalise to on/off, and the currency / salary-range
     * composites are validated per sub-key. Any submitted key that is NOT in
     * the schema (e.g. a field added by a third party via the
     * `workeera_admin_*_settings_list` filters) falls back to the generic
     * recursive {@see sanitize_options()} so nothing is silently dropped.
     *
     * @param  mixed $value  Raw value submitted by the Settings API.
     * @param  array $schema field_name => rule-array map.
     * @return array         Sanitised option array.
     */
    public static function sanitize_with_schema( $value, array $schema ) {
        if ( ! is_array( $value ) ) {
            // Defensive: a wiped-clean form / unexpected scalar becomes an
            // empty array rather than a NULL / stray string in wp_options.
            return array();
        }

        $clean = array();
        foreach ( $value as $key => $item ) {
            $key = sanitize_key( $key );
            if ( $key === '' ) {
                continue;
            }
            if ( isset( $schema[ $key ] ) ) {
                $clean[ $key ] = self::apply_rule( $item, $schema[ $key ] );
            } else {
                // Unknown / filtered-in field — keep today's generic behaviour.
                $clean[ $key ] = self::sanitize_options( $item );
            }
        }

        return $clean;
    }

    /**
     * Apply a single schema rule to one submitted field value.
     *
     * @param  mixed $item Raw submitted value for the field.
     * @param  array $rule Rule descriptor: at minimum a `type` key, plus
     *                     `allowed` (choice/choices) and `default` (choice).
     * @return mixed       Sanitised value of the correct type/shape.
     */
    private static function apply_rule( $item, array $rule ) {
        $type = isset( $rule['type'] ) ? $rule['type'] : 'text';

        switch ( $type ) {

            case 'int':
                // absint keeps 0, which several fields use as "unlimited".
                return absint( $item );

            case 'switch':
                return ( is_scalar( $item ) && (string) $item === 'on' ) ? 'on' : 'off';

            case 'choice':
                $allowed = isset( $rule['allowed'] ) && is_array( $rule['allowed'] ) ? $rule['allowed'] : array();
                $default = isset( $rule['default'] ) ? $rule['default'] : '';
                $val     = is_scalar( $item ) ? sanitize_text_field( (string) $item ) : '';
                return in_array( $val, $allowed, true ) ? $val : $default;

            case 'choices':
                $allowed = isset( $rule['allowed'] ) && is_array( $rule['allowed'] ) ? $rule['allowed'] : array();
                $items   = is_array( $item ) ? array_map( 'sanitize_text_field', $item ) : array();
                return array_values( array_intersect( $items, $allowed ) );

            case 'range':
                $item = is_array( $item ) ? $item : array();
                return array(
                    'min'      => isset( $item['min'] ) ? absint( $item['min'] ) : 0,
                    'max'      => isset( $item['max'] ) ? absint( $item['max'] ) : 0,
                    'interval' => isset( $item['interval'] ) ? absint( $item['interval'] ) : 0,
                );

            case 'sortable_switch':
                // A drag-sortable list of on/off items → { order, items } shape.
                $allowed = isset( $rule['allowed'] ) && is_array( $rule['allowed'] ) ? $rule['allowed'] : array();
                return \Workeera_Form_Fields::sortable_switch_normalize( $item, $allowed, 'items', 'order' );

            case 'currency':
                global $workeera_currencies_list;
                $item = is_array( $item ) ? $item : array();

                $code = isset( $item['currency'] ) ? sanitize_text_field( $item['currency'] ) : 'USD';
                if ( empty( $workeera_currencies_list ) || ! is_array( $workeera_currencies_list ) || ! isset( $workeera_currencies_list[ $code ] ) ) {
                    $code = 'USD';
                }

                $position = isset( $item['position'] ) ? sanitize_text_field( $item['position'] ) : 'left';
                if ( ! in_array( $position, array( 'left', 'left_space', 'right', 'right_space' ), true ) ) {
                    $position = 'left';
                }

                $separator = isset( $item['thousand_separator'] ) ? sanitize_text_field( $item['thousand_separator'] ) : ',';
                $separator = substr( $separator, 0, 1 ); // single character only

                return array(
                    'currency'           => $code,
                    'position'           => $position,
                    'thousand_separator' => $separator,
                );

            case 'text':
            default:
                return is_scalar( $item ) ? sanitize_text_field( (string) $item ) : '';
        }
    }

    /**
     * Extract the shared field args (id, classes, default, attributes) from $args.
     *
     * @param  array $args Raw field args.
     * @return array       Normalised field args.
     */
    private function common_field_args($args) {
        $field_args = array();
        if (isset($args['label_for'])) {
            $field_args['id'] = $args['label_for'];
        }
        //
        if (isset($args['classes'])) {
            $field_args['classes'] = $args['classes'];
        }
        if (isset($args['default'])) {
            $field_args['default'] = $args['default'];
        }
        if (isset($args['extra_attributes'])) {
            $field_args['extra_attributes'] = $args['extra_attributes'];
        }

        return $field_args;
    }

    /**
     * Output the info-tip block below a settings field, when provided.
     *
     * @param array $args Field args; may contain an "info" key.
     */
    private function field_info_str($args) {
        if (isset($args['info']) && !empty($args['info'])) {
            echo '<div class="workeera-setting-field-info">' . wp_kses_post($args['info']) . '</div>';
        }
    }

    /**
     * Render an <input> settings field.
     *
     * @param array $args Field args.
     */
    public function input_field_callback($args) {
        $options_data = $args['options_data'];
        $options_name = $args['options_name'];
        $field_name = $args['field_name'];
        $value = isset($options_data[$field_name]) ? $options_data[$field_name] : '';
        
        $field_args = $this->common_field_args($args);
        
        $field_args['name'] = $options_name . '[' . $field_name . ']';
        if ($value != '') {
            $field_args['value'] = $value;
        }
        if (isset($args['input_type'])) {
            $field_args['input_type'] = $args['input_type'];
        }
        if (isset($args['validation_type'])) {
            $field_args['validation_type'] = $args['validation_type'];
        }
        \Workeera_Form_Fields::input($field_args);

        $this->field_info_str($args);
    }

    /**
     * Render a <textarea> settings field.
     *
     * @param array $args Field args.
     */
    public function textarea_field_callback($args) {
        $options_data = $args['options_data'];
        $options_name = $args['options_name'];
        $field_name = $args['field_name'];
        $value = isset($options_data[$field_name]) ? $options_data[$field_name] : '';
        
        $field_args = $this->common_field_args($args);

        $field_args['name'] = $options_name . '[' . $field_name . ']';
        if ($value != '') {
            $field_args['value'] = $value;
        }
        \Workeera_Form_Fields::textarea($field_args);

        $this->field_info_str($args);
    }

    /**
     * Render a <select> settings field (single or multi-select).
     *
     * @param array $args Field args.
     */
    public function select_field_callback($args) {
        $options_data = $args['options_data'];
        $options_name = $args['options_name'];
        $field_name = $args['field_name'];
        $value = isset($options_data[$field_name]) ? $options_data[$field_name] : '';
        
        $field_args = $this->common_field_args($args);
        
        $field_args['name'] = $options_name . '[' . $field_name . ']' . (isset($args['multiple']) && $args['multiple'] ? '[]' : '');
        if ($value != '') {
            $field_args['value'] = $value;
        }
        if (isset($args['options'])) {
            $field_args['options'] = $args['options'];
        }
        if (isset($args['multiple']) && $args['multiple']) {
            $field_args['multiple'] = true;
        }
        \Workeera_Form_Fields::select($field_args);

        $this->field_info_str($args);
    }

    /**
     * Render a toggle-switch settings field.
     *
     * @param array $args Field args.
     */
    public function switch_field_callback($args) {
        $options_data = $args['options_data'];
        $options_name = $args['options_name'];
        $field_name = $args['field_name'];
        $value = isset($options_data[$field_name]) ? $options_data[$field_name] : '';
        
        $field_args = $this->common_field_args($args);

        $field_args['name'] = $options_name . '[' . $field_name . ']';
        if ($value != '') {
            $field_args['value'] = $value;
        }
        \Workeera_Form_Fields::switch_button($field_args);

        $this->field_info_str($args);
    }

    /**
     * Render a grid of option-button choices (single or multi-select).
     *
     * @param array $args Field args.
     */
    public function option_buttons_field_callback($args) {
        $options_data = $args['options_data'];
        $options_name = $args['options_name'];
        $field_name = $args['field_name'];
        $value = isset($options_data[$field_name]) ? $options_data[$field_name] : '';
        
        $field_args = $this->common_field_args($args);

        $field_args['name'] = $options_name . '[' . $field_name . ']' . (isset($args['multiple']) && $args['multiple'] ? '[]' : '');
        if (!empty($value)) {
            $field_args['value'] = $value;
        }
        if (isset($args['options'])) {
            $field_args['options'] = $args['options'];
        }
        if (isset($args['multiple']) && $args['multiple']) {
            $field_args['multiple'] = true;
        }
        ?>
        <div class="workeera-grid-optbtns-main">
            <?php
            \Workeera_Form_Fields::option_buttons($field_args);
            ?>
        </div>
        <?php
        $this->field_info_str($args);
    }

    /**
     * Render a drag-sortable list of on/off items (the "sortable switch list"
     * reusable field). Stores a { order, items } shape under the field name.
     *
     * Extra args: `options` (key => label), `default_off` (keys off by default
     * on a fresh install), and `info`.
     *
     * @param array $args Field args.
     */
    public function sortable_switch_field_callback($args) {
        $options_data = $args['options_data'];
        $options_name = $args['options_name'];
        $field_name   = $args['field_name'];
        $options      = isset($args['options']) && is_array($args['options']) ? $args['options'] : [];
        $default_off  = isset($args['default_off']) && is_array($args['default_off']) ? $args['default_off'] : [];

        $known = array_keys($options);
        $saved = isset($options_data[$field_name]) && is_array($options_data[$field_name]) ? $options_data[$field_name] : [];

        $order          = isset($saved['order']) && is_array($saved['order']) ? $saved['order'] : $known;
        $has_saved_items = isset($saved['items']) && is_array($saved['items']);

        $enabled = [];
        foreach ($known as $key) {
            if ($has_saved_items) {
                $enabled[$key] = (isset($saved['items'][$key]['enabled']) && 'off' === $saved['items'][$key]['enabled']) ? 'off' : 'on';
            } else {
                // No saved value yet — everything on except the default-off keys.
                $enabled[$key] = in_array($key, $default_off, true) ? 'off' : 'on';
            }
        }

        \Workeera_Form_Fields::sortable_switch_list([
            'name_base' => $options_name . '[' . $field_name . ']',
            'options'   => $options,
            'order'     => $order,
            'enabled'   => $enabled,
        ]);

        $this->field_info_str($args);
    }

    /**
     * Render a colour-picker settings field.
     *
     * @param array $args Field args.
     */
    public function color_field_callback($args) {
        $options_data = $args['options_data'];
        $options_name = $args['options_name'];
        $field_name = $args['field_name'];
        $value = isset($options_data[$field_name]) ? $options_data[$field_name] : '';
        
        $field_args = $this->common_field_args($args);

        $field_args['name'] = $options_name . '[' . $field_name . ']';
        if ($value != '') {
            $field_args['value'] = $value;
        }
        \Workeera_Form_Fields::color_pickr($field_args);

        $this->field_info_str($args);
    }

    /**
     * Render a media-library image-picker settings field.
     *
     * @param array $args Field args.
     */
    public function image_field_callback($args) {
        $options_data = $args['options_data'];
        $options_name = $args['options_name'];
        $field_name = $args['field_name'];
        $value = isset($options_data[$field_name]) ? $options_data[$field_name] : '';
        
        $field_args = $this->common_field_args($args);
        
        $field_args['name'] = $options_name . '[' . $field_name . ']';
        if ($value != '') {
            $field_args['value'] = $value;
        }
        \Workeera_Form_Fields::image($field_args);

        $this->field_info_str($args);
    }

    /**
     * Render the composite currency settings field (code, position, thousand separator).
     *
     * @param array $args Field args.
     */
    public function currency_fields_callback($args) {
        global $workeera_currencies_list;

        $currencies_selection_list = [];
        if (!empty($workeera_currencies_list) && is_array($workeera_currencies_list)) {
            foreach ($workeera_currencies_list as $currency_code => $currency_set) {
                $currencies_selection_list[$currency_code] = $currency_set['name'] . ' (' . $currency_set['symbol'] . ') — ' . $currency_code;
            }
        }

        $options_data = $args['options_data'];
        $options_name = $args['options_name'];
        $field_name = $args['field_name'];
        ?>
        <div class="workeera-currency-fields-options">
            <div class="workeera-currency-field-item">
                <div class="workeera-currency-field-label"><strong><?php esc_html_e('Currency', 'workeera-remote-tech-job-board'); ?></strong></div>
                <?php
                $field_args = [
                    'name' => $options_name . '[' . $field_name . '][currency]',
                    'classes' => ['workeera-select2-dropdown', 'workeera-select2-wsearch'],
                    'options' => $currencies_selection_list,
                    'value' => isset($options_data[$field_name]['currency']) ? $options_data[$field_name]['currency'] : 'USD',
                ];
                \Workeera_Form_Fields::select($field_args);
                ?>
            </div>
            <div class="workeera-currency-field-item">
                <div class="workeera-currency-field-label"><strong><?php esc_html_e('Currency Position', 'workeera-remote-tech-job-board'); ?></strong></div>
                <?php
                $currencies_pos_list = [
                    'left' => esc_html__('Left without space', 'workeera-remote-tech-job-board'),
                    'left_space' => esc_html__('Left with space', 'workeera-remote-tech-job-board'),
                    'right' => esc_html__('Right without space', 'workeera-remote-tech-job-board'),
                    'right_space' => esc_html__('Right with space', 'workeera-remote-tech-job-board'),
                ];
                $field_args = [
                    'name' => $options_name . '[' . $field_name . '][position]',
                    'classes' => ['workeera-select2-dropdown'],
                    'options' => $currencies_pos_list,
                    'value' => isset($options_data[$field_name]['position']) ? $options_data[$field_name]['position'] : 'left',
                ];
                \Workeera_Form_Fields::select($field_args);
                ?>
            </div>
            <div class="workeera-currency-field-item">
                <div class="workeera-currency-field-label"><strong><?php esc_html_e('Thousand separator', 'workeera-remote-tech-job-board'); ?></strong></div>
                <?php
                $field_args = [
                    'name' => $options_name . '[' . $field_name . '][thousand_separator]',
                    'value' => isset($options_data[$field_name]['thousand_separator']) ? $options_data[$field_name]['thousand_separator'] : ',',
                ];
                \Workeera_Form_Fields::input($field_args);
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a min/max/interval numeric range settings field.
     *
     * @param array $args Field args.
     */
    public function range_fields_callback($args) {

        $options_data = $args['options_data'];
        $options_name = $args['options_name'];
        $field_name = $args['field_name'];
        $default_vals = isset($args['default_vals']) ? $args['default_vals'] : [];
        ?>
        <div class="workeera-range-fields-options">
            <div class="workeera-range-field-item">
                <div class="workeera-range-field-label"><strong><?php esc_html_e('Min', 'workeera-remote-tech-job-board'); ?></strong></div>
                <?php
                $field_args = [
                    'input_type' => 'number',
                    'name' => $options_name . '[' . $field_name . '][min]',
                    'extra_attributes' => ['min' => 1, 'step' => 1, 'required' => ''],
                    'default' => isset($default_vals['min']) ? $default_vals['min'] : ''
                ];
                if (isset($options_data[$field_name]['min'])) {
                    $field_args['value'] = absint($options_data[$field_name]['min']);
                }
                \Workeera_Form_Fields::input($field_args);
                ?>
            </div>
            <div class="workeera-range-field-item">
                <div class="workeera-range-field-label"><strong><?php esc_html_e('Max', 'workeera-remote-tech-job-board'); ?></strong></div>
                <?php
                $field_args = [
                    'input_type' => 'number',
                    'name' => $options_name . '[' . $field_name . '][max]',
                    'extra_attributes' => ['min' => 1, 'step' => 1, 'required' => ''],
                    'default' => isset($default_vals['max']) ? $default_vals['max'] : ''
                ];
                if (isset($options_data[$field_name]['max'])) {
                    $field_args['value'] = absint($options_data[$field_name]['max']);
                }
                \Workeera_Form_Fields::input($field_args);
                ?>
            </div>
            <div class="workeera-range-field-item">
                <div class="workeera-range-field-label"><strong><?php esc_html_e('Interval/Step', 'workeera-remote-tech-job-board'); ?></strong></div>
                <?php
                $field_args = [
                    'input_type' => 'number',
                    'name' => $options_name . '[' . $field_name . '][interval]',
                    'extra_attributes' => ['min' => 1, 'step' => 1, 'required' => ''],
                    'default' => isset($default_vals['interval']) ? $default_vals['interval'] : ''
                ];
                if (isset($options_data[$field_name]['interval'])) {
                    $field_args['value'] = absint($options_data[$field_name]['interval']);
                }
                \Workeera_Form_Fields::input($field_args);
                ?>
            </div>
        </div>
        <?php
    }

}
