<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Reusable form-field renderers used across admin screens, settings UI,
 * and the front-end user dashboard.
 *
 * Each public renderer accepts an associative `$args` array and either
 * echoes or returns HTML filtered through `wp_kses` with a permissive
 * (but tag-restricted) allow-list.
 */
class Workeera_Form_Fields {

    /**
     * Build the `wp_kses` allow-list for form-field markup.
     *
     * @param  array $ext_attr_list Extra attribute names to permit on every tag.
     * @return array                Tag => attribute-allow-list map, filterable.
     */
    public static function allowed_tags_attributes($ext_attr_list = []) {

        $attr_list = [
            'type' => array(),
            'id' => array(),
            'name' => array(),
            'value' => array(),
            'readonly' => array(),
            'required' => array(),
            'class' => array(),
            'for' => array(),
            'multiple' => array(),
            'selected' => array(),
            'checked' => array(),
            'src' => array(),
            'alt' => array(),
            'style' => array(),
        ];

        if (!empty($ext_attr_list)) {
            foreach ($ext_attr_list as $attr_name => $attr_val) {
                $attr_list[$attr_name] = array();
            }
        }

        $tags_list = [
            'div' => $attr_list,
            'label' => $attr_list,
            'span' => $attr_list,
            'strong' => $attr_list,
            'img' => $attr_list,
            'button' => $attr_list,
            'input' => $attr_list,
            'select' => $attr_list,
            'option' => $attr_list,
            'textarea' => $attr_list,
        ];

        return apply_filters('workeera_fromfield_allowed_tags_attributes', $tags_list);
    }

    /**
     * Build the `class` attribute value with the shared `workeera-form-field` base.
     *
     * @param  string|array $classes Extra class names.
     * @return string                Space-separated class list.
     */
    private static function render_classes_attr($classes) {
        $classes_str = 'workeera-form-field';
        if (!empty($classes)) {
            if (is_array($classes)) {
                $classes_str .= ' ' . implode(' ', $classes);
            } else {
                $classes_str .= ' ' . $classes;
            }
        }

        return $classes_str;
    }

    /**
     * Serialise an attribute => value map into an HTML attribute string.
     *
     * @param  array $attr_list Attribute name => value pairs.
     * @return string           Leading-space-prefixed attribute fragment.
     */
    private static function render_extra_attr($attr_list) {
        $attr_str = '';
        if (!empty($attr_list) && is_array($attr_list)) {
            foreach ($attr_list as $attr_name => $attr_val) {
                $attr_str .= ' ' . esc_attr($attr_name) . ($attr_val != '' ? '="' . esc_attr($attr_val) . '"' : '');
            }
        }

        return $attr_str;
    }

    /**
     * Sanitise and escape an input value based on `validation_type`.
     *
     * @param  string $value Raw value.
     * @param  array  $args  Field args; recognised: 'validation_type' = 'email'|'url'|other.
     * @return string        Escaped value safe for an attribute.
     */
    private static function input_esc_sanitize($value, $args = []) {
        if (isset($args['validation_type']) && $args['validation_type'] == 'email') {
            $value = esc_html(sanitize_email($value));
        } else if (isset($args['validation_type']) && $args['validation_type'] == 'url') {
            $value = esc_url(sanitize_url($value));
        } else {
            $value = esc_html(sanitize_text_field($value));
        }

        return esc_attr($value);
    }
    
    /**
     * Render an `<input>` field of arbitrary type with sanitised attributes.
     *
     * @param  array       $args Field args (id, name, value/default, classes, extra_attributes, input_type, return, validation_type).
     * @return string|void       HTML when `$args['return']` is truthy; otherwise echoes.
     */
    public static function input($args) {

        extract($args);

        $input_type_str = 'text';
        if (isset($input_type) && $input_type != '') {
            $input_type_str = $input_type;
        }

        $attr_id = '';
        if (isset($id) && $id != '') {
            $attr_id = ' id="' . esc_attr($id) . '"';
        }

        $attr_name = '';
        if (isset($name) && $name != '') {
            $attr_name = ' name="' . esc_attr($name) . '"';
        }

        $classes_arg = isset($classes) ? $classes : '';
        $attr_class = self::render_classes_attr($classes_arg);

        $attr_value = '';
        if (isset($default) && $default != '') {
            $attr_value = ' value="' . self::input_esc_sanitize($default, $args) . '"';
        }
        if (isset($value)) {
            $attr_value = ' value="' . self::input_esc_sanitize($value, $args) . '"';
        }

        $attr_list = isset($extra_attributes) ? $extra_attributes : '';
        $extra_attr = self::render_extra_attr($attr_list);

        $html = '<input type="' . esc_attr($input_type_str) . '"' . $attr_id . $attr_name . ' class="' . esc_attr($attr_class) . '"' . $attr_value . $extra_attr . '>';

        $allowed_tags = self::allowed_tags_attributes($attr_list);

        if (isset($return) && $return) {
            return wp_kses($html, $allowed_tags);
        } else {
            echo wp_kses($html, $allowed_tags);
        }
    }
    
    /**
     * Render a `<textarea>` field.
     *
     * @param  array       $args Field args (id, name, value/default, classes, extra_attributes, return).
     * @return string|void       HTML when `$args['return']` is truthy; otherwise echoes.
     */
    public static function textarea($args) {

        extract($args);

        $attr_id = '';
        if (isset($id) && $id != '') {
            $attr_id = ' id="' . esc_attr($id) . '"';
        }

        $attr_name = '';
        if (isset($name) && $name != '') {
            $attr_name = ' name="' . esc_attr($name) . '"';
        }

        $classes_arg = isset($classes) ? $classes : '';
        $attr_class = self::render_classes_attr($classes_arg);

        $text_value = '';
        if (isset($default) && $default != '') {
            $text_value = $default;
        }
        if (isset($value)) {
            $text_value = $value;
        }

        $attr_list = isset($extra_attributes) ? $extra_attributes : '';
        $extra_attr = self::render_extra_attr($attr_list);

        $html = '<textarea' . $attr_id . $attr_name . ' class="' . esc_attr($attr_class) . '"' . $extra_attr . '>' . esc_textarea($text_value) . '</textarea>';

        $allowed_tags = self::allowed_tags_attributes($attr_list);

        if (isset($return) && $return) {
            return wp_kses($html, $allowed_tags);
        } else {
            echo wp_kses($html, $allowed_tags);
        }
    }
    
    /**
     * Render an on/off toggle switch backed by a hidden input + checkbox pair.
     *
     * @param  array       $args Field args (id, name, value/default ('on'|'off'), classes, extra_attributes, return).
     * @return string|void       HTML when `$args['return']` is truthy; otherwise echoes.
     */
    public static function switch_button($args) {
        
        extract($args);

        $attr_id = '';
        if (isset($id) && $id != '') {
            $attr_id = ' id="' . esc_attr($id) . '"';
        }

        $attr_name = '';
        if (isset($name) && $name != '') {
            $attr_name = ' name="' . esc_attr($name) . '"';
        }

        $classes_arg = isset($classes) ? $classes : '';
        $attr_class = self::render_classes_attr($classes_arg);

        $text_value = '';
        if (isset($default) && $default != '') {
            $text_value = $default;
        }
        if (isset($value)) {
            $text_value = $value;
        }

        if ($text_value != 'on') {
            $text_value = 'off';
        }

        $attr_list = isset($extra_attributes) ? $extra_attributes : '';
        $extra_attr = self::render_extra_attr($attr_list);

        $html = '
        <label class="workeera-form-switch">
            <input type="hidden"' . $attr_name . ' value="' . self::input_esc_sanitize($text_value, $args) . '">
            <input type="checkbox"' . $attr_id . ' class="' . esc_attr($attr_class) . '"' . $extra_attr . ($text_value == 'on' ? ' checked' : '') . '>
            <span class="workeera-btn-slider"></span>
        </label>';

        $allowed_tags = self::allowed_tags_attributes($attr_list);

        if (isset($return) && $return) {
            return wp_kses($html, $allowed_tags);
        } else {
            echo wp_kses($html, $allowed_tags);
        }
    }
    
    /**
     * Render a horizontally-laid-out group of styled radio/checkbox option buttons.
     *
     * @param  array       $args Field args (id, name, options, value/default, multiple, classes, extra_attributes, return).
     * @return string|void       HTML when `$args['return']` is truthy; otherwise echoes.
     */
    public static function option_buttons($args) {
        
        extract($args);

        $attr_id = '';
        if (isset($id) && $id != '') {
            $attr_id = ' id="' . esc_attr($id) . '"';
        }

        $is_multi = false;
        if (isset($multiple) && $multiple) {
            $is_multi = true;
        }

        $attr_name = '';
        if (isset($name) && $name != '') {
            $attr_name = ' name="' . esc_attr($name) . '"';
        }

        $classes_arg = isset($classes) ? $classes : '';
        $attr_class = self::render_classes_attr($classes_arg);

        $btns_value = '';
        if (isset($default) && $default != '') {
            $btns_value = $default;
        }
        if (isset($value) && !empty($value)) {
            $btns_value = $value;
        }

        $attr_list = isset($extra_attributes) ? $extra_attributes : '';
        $extra_attr = self::render_extra_attr($attr_list);
        
        $html = '<div' . $attr_id . ' class="workeera-form-optbtns ' . esc_attr($is_multi ? 'workeera-checkbox-optbtns' : 'workeera-radio-optbtns') . '">' . "\n";
        if (isset($options) && is_array($options)) {
            foreach ($options as $option_key => $option_itm) {
                $rand = wp_rand( 10000000, 99999999 );
                if (is_array($option_itm) && isset($option_itm['label'])) {
                    $option_label = $option_itm['label'];
                } else {
                    $option_label = $option_itm;
                }
                $checked = '';
                if (!empty($btns_value)) {
                    if (is_array($btns_value) && in_array($option_key, $btns_value)) {
                        $checked = ' checked';
                    } else if (!is_array($btns_value) && $option_key == $btns_value) {
                        $checked = ' checked';
                    }
                }
                $btn_lbl_for = isset($id) && $id != '' ? sanitize_title($id) : 'workeera-optbtn';

                $html .= '<div class="workeera-form-optbtn' . (!empty($checked) ? ' workeera-chked-optbtn' : '') . '">
                    <input id="' . esc_attr($btn_lbl_for . '-' . $rand) . '" type="' . esc_attr($is_multi ? 'checkbox' : 'radio') . '" class="' . esc_attr($attr_class) . '"' . $attr_name . $extra_attr . ' value="' . esc_html($option_key) . '"' . esc_html($checked) . ' />';
                    if (is_array($option_itm) && isset($option_itm['image_url'])) {
                        $html .= '<div class="workeera-chkinp-chkimg-hcon"><span style="background-image:url(\'' . $option_itm['image_url'] . '\')"></span></div>';
                    } else {
                        $html .= '<span class="workeera-chkinp-chkmrk"></span>';
                    }
                    $html .= '
                    <label for="' . esc_attr($btn_lbl_for . '-' . $rand) . '" class="workeera-form-optlbl">
                        <span>' . esc_html($option_label) . '</span>
                    </label>
                </div>' . "\n";
            }
        }
        $html .= '</div>' . "\n";

        $allowed_tags = self::allowed_tags_attributes($attr_list);

        if (isset($return) && $return) {
            return wp_kses($html, $allowed_tags);
        } else {
            echo wp_kses($html, $allowed_tags);
        }
    }

    /**
     * Render a list of radio/checkbox options as labelled inputs in a group container.
     *
     * @param  array       $args Field args (id, name, options, value/default, multiple, extra_attributes, return).
     * @return string|void       HTML when `$args['return']` is truthy; otherwise echoes.
     */
    public static function options_group($args) {
        
        extract($args);

        $attr_id = '';
        if (isset($id) && $id != '') {
            $attr_id = ' id="' . esc_attr($id) . '"';
        }

        $is_multi = false;
        if (isset($multiple) && $multiple) {
            $is_multi = true;
        }

        $attr_name = '';
        if (isset($name) && $name != '') {
            $attr_name = ' name="' . esc_attr($name) . '"';
        }

        $btns_value = '';
        if (isset($default) && $default != '') {
            $btns_value = $default;
        }
        if (isset($value) && !empty($value)) {
            $btns_value = $value;
        }

        $attr_list = isset($extra_attributes) ? $extra_attributes : '';
        $extra_attr = self::render_extra_attr($attr_list);
        
        $html = '<div' . $attr_id . $extra_attr . ' class="workeera-form-opts-group ' . esc_attr($is_multi ? 'workeera-checkbox-group' : 'workeera-radio-group') . '">' . "\n";
        if (isset($options) && is_array($options)) {
            foreach ($options as $option_key => $option_itm) {
                $rand = wp_rand( 10000000, 99999999 );
                if (is_array($option_itm) && isset($option_itm['label'])) {
                    $option_label = $option_itm['label'];
                } else {
                    $option_label = $option_itm;
                }
                $checked = '';
                if (!empty($btns_value)) {
                    if (is_array($btns_value) && in_array($option_key, $btns_value)) {
                        $checked = ' checked';
                    } else if (!is_array($btns_value) && $option_key == $btns_value) {
                        $checked = ' checked';
                    }
                }
                $btn_lbl_for = isset($id) && $id != '' ? sanitize_title($id) : 'workeera-optitm';

                $html .= '<label for="' . esc_attr($btn_lbl_for . '-' . $rand) . '" class="' . ($is_multi ? 'workeera-checkbox' : 'workeera-radio') . '">
                    <input id="' . esc_attr($btn_lbl_for . '-' . $rand) . '" type="' . esc_attr($is_multi ? 'checkbox' : 'radio') . '" ' . $attr_name . ' value="' . esc_html($option_key) . '"' . esc_html($checked) . ' />';
                    $html .= '<span class="workeera-' . ($is_multi ? 'checkmark' : 'radiomark') . '"></span>';
                    $html .= '<span>' . esc_html($option_label) . '</span>
                </label>' . "\n";
            }
        }
        $html .= '</div>' . "\n";

        $allowed_tags = self::allowed_tags_attributes($attr_list);

        if (isset($return) && $return) {
            return wp_kses($html, $allowed_tags);
        } else {
            echo wp_kses($html, $allowed_tags);
        }
    }
    
    /**
     * Render a `<select>` dropdown, optionally multi-select.
     *
     * @param  array       $args Field args (id, name, options, value/default, multiple, classes, extra_attributes, return).
     * @return string|void       HTML when `$args['return']` is truthy; otherwise echoes.
     */
    public static function select($args) {
        
        extract($args);

        $attr_id = '';
        if (isset($id) && $id != '') {
            $attr_id = ' id="' . esc_attr($id) . '"';
        }

        $is_multi = false;
        if (isset($multiple) && $multiple) {
            $is_multi = true;
        }

        $attr_name = '';
        if (isset($name) && $name != '') {
            $attr_name = ' name="' . esc_attr($name) . '"';
        }

        $classes_arg = isset($classes) ? $classes : '';
        $attr_class = self::render_classes_attr($classes_arg);

        $dropdown_value = '';
        if (isset($default) && $default != '') {
            $dropdown_value = $default;
        }
        if (isset($value)) {
            $dropdown_value = $value;
        }

        $attr_list = isset($extra_attributes) ? $extra_attributes : '';
        $extra_attr = self::render_extra_attr($attr_list);

        $html = '<div class="workeera-select-field' . ($is_multi ? ' workeera-multiselect-field' : '') . '">' . "\n";
        $html .= '<select' . $attr_id . $attr_name . ($is_multi ? ' multiple' : '') . ' class="' . esc_attr($attr_class) . '"' . $extra_attr . '>' . "\n";
        if (isset($options) && is_array($options) && !empty($options)) {
            foreach ($options as $option_key => $option_val) {
                $selected = '';
                if ($is_multi) {
                    if (is_array($dropdown_value) && in_array($option_key, $dropdown_value)) {
                        $selected = ' selected';
                    }
                } else {
                    if (!is_array($dropdown_value)) {
                        $selected = $dropdown_value == $option_key ? ' selected' : '';
                    }
                }
                $html .= '<option' . $selected . ' value="' . self::input_esc_sanitize($option_key) . '">' . $option_val . '</option>' . "\n";
            }
        }
        $html .= '</select>' . "\n";
        $html .= '</div>' . "\n";

        $allowed_tags = self::allowed_tags_attributes($attr_list);

        if (isset($return) && $return) {
            return wp_kses($html, $allowed_tags);
        } else {
            echo wp_kses($html, $allowed_tags);
        }
    }
    
    /**
     * Render a color-picker text input (paired with a JS color widget).
     *
     * @param  array       $args Field args (id, name, value/default, classes, extra_attributes, return).
     * @return string|void       HTML when `$args['return']` is truthy; otherwise echoes.
     */
    public static function color_pickr($args) {
        
        extract($args);

        $attr_id = '';
        if (isset($id) && $id != '') {
            $attr_id = ' id="' . esc_attr($id) . '"';
        }

        $attr_name = '';
        if (isset($name) && $name != '') {
            $attr_name = ' name="' . esc_attr($name) . '"';
        }

        $classes_arg = isset($classes) ? $classes : '';
        if (is_array($classes_arg)) {
            $classes_arg[] = 'workeera-color-field';
        } else {
            $classes_arg .= 'workeera-color-field';
        }
        $attr_class = self::render_classes_attr($classes_arg);

        $color_value = '';
        if (isset($default) && $default != '') {
            $color_value = $default;
        }
        if (isset($value)) {
            $color_value = $value;
        }

        $attr_list = isset($extra_attributes) ? $extra_attributes : '';
        $extra_attr = self::render_extra_attr($attr_list);

        $html = '<input type="text"' . $attr_id . $attr_name . ' class="' . esc_attr($attr_class) . '" value="' . self::input_esc_sanitize($color_value) . '"' . $extra_attr . '>';

        $allowed_tags = self::allowed_tags_attributes($attr_list);

        if (isset($return) && $return) {
            return wp_kses($html, $allowed_tags);
        } else {
            echo wp_kses($html, $allowed_tags);
        }
    }
    
    /**
     * Render an image-picker field: preview, URL input, hidden attachment ID, browse/remove buttons.
     *
     * @param  array       $args Field args (id, name, id_name, value/default, id_value, classes, extra_attributes, return).
     * @return string|void       HTML when `$args['return']` is truthy; otherwise echoes.
     */
    public static function image($args) {

        extract($args);

        $attr_id = '';
        if (isset($id) && $id != '') {
            $attr_id = ' id="' . esc_attr($id) . '"';
        }

        $attr_name = '';
        if (isset($name) && $name != '') {
            $attr_name = ' name="' . esc_attr($name) . '"';
        }
        $attr_id_name = '';
        if (isset($id_name) && $id_name != '') {
            $attr_id_name = ' name="' . esc_attr($id_name) . '"';
        }

        $classes_arg = isset($classes) ? $classes : '';
        $attr_class = self::render_classes_attr($classes_arg);

        $img_value = '';
        if (isset($default) && $default != '') {
            $img_value = $default;
        }
        if (isset($value)) {
            $img_value = $value;
        }
        
        $img_id_value = '';
        if (isset($id_value)) {
            $img_id_value = $id_value;
        }

        $attr_list = isset($extra_attributes) ? $extra_attributes : '';
        $extra_attr = self::render_extra_attr($attr_list);

        $url_valid_args = ['validation_type' => 'url'];

        $html = "\n" . '<div class="workeera-form-imgfield">
            <div class="workeera-field-imgcon' . esc_attr($img_value == '' ? ' workeera-hide' : '') . '">
                <img src="' . self::input_esc_sanitize($img_value, $url_valid_args) . '" alt="">
            </div>
            <div class="workeera-field-inputcon">
                <input type="text"' . $attr_id . $attr_name . ' class="' . esc_attr($attr_class) . '"' . ' value="' . self::input_esc_sanitize($img_value, $url_valid_args) . '" readonly' . $extra_attr . ' />
                <input type="hidden"' . $attr_id_name . ' value="' . absint($img_id_value) . '" />
            </div>
            <div class="workeera-field-btnscon">
                <button type="button" class="workeera-upload-imgbtn button workeera-button">' . esc_html__('Browse Image', 'workeera-remote-tech-job-board') . '</button>
                <button type="button" class="workeera-remvupload-imgbtn button workeera-button' . esc_attr($img_value == '' ? ' workeera-hide' : '') . '">' . esc_html__('Remove', 'workeera-remote-tech-job-board') . '</button>
            </div>
        </div>' . "\n";

        $allowed_tags = self::allowed_tags_attributes($attr_list);

        if (isset($return) && $return) {
            return wp_kses($html, $allowed_tags);
        } else {
            echo wp_kses($html, $allowed_tags);
        }
    }
    
    /**
     * Render a file-URL input paired with a "browse file" button.
     *
     * @param  array       $args Field args (id, name, value/default, btn_txt, classes, extra_attributes, return).
     * @return string|void       HTML when `$args['return']` is truthy; otherwise echoes.
     */
    public static function file($args) {

        extract($args);

        $attr_id = '';
        if (isset($id) && $id != '') {
            $attr_id = ' id="' . esc_attr($id) . '"';
        }

        $attr_name = '';
        if (isset($name) && $name != '') {
            $attr_name = ' name="' . esc_attr($name) . '"';
        }

        $classes_arg = isset($classes) ? $classes : '';
        $attr_class = self::render_classes_attr($classes_arg);

        $browse_txt = esc_html__('Browse File', 'workeera-remote-tech-job-board');
        if (isset($btn_txt)) {
            $browse_txt = $btn_txt;
        }

        $file_value = '';
        if (isset($default) && $default != '') {
            $file_value = $default;
        }
        if (isset($value)) {
            $file_value = $value;
        }

        $attr_list = isset($extra_attributes) ? $extra_attributes : '';
        $extra_attr = self::render_extra_attr($attr_list);

        $url_valid_args = ['validation_type' => 'url'];

        $html = "\n" . '<div class="workeera-form-filefield">
            <div class="workeera-field-inputcon">
                <input type="text"' . $attr_id . $attr_name . ' class="' . esc_attr($attr_class) . '"' . ' value="' . self::input_esc_sanitize($file_value, $url_valid_args) . '" ' . $extra_attr . ' />
            </div>
            <div class="workeera-field-btnscon">
                <button type="button" class="workeera-upload-filebtn button workeera-button">' . $browse_txt . '</button>
            </div>
        </div>' . "\n";

        $allowed_tags = self::allowed_tags_attributes($attr_list);

        if (isset($return) && $return) {
            return wp_kses($html, $allowed_tags);
        } else {
            echo wp_kses($html, $allowed_tags);
        }
    }

    // -------------------------------------------------------------------------
    // Sortable switch list — a reusable field: a drag-sortable list of items,
    // each with an on/off switch and a hidden order[] input. The order and the
    // switches submit with the normal Save button (no AJAX on drag/toggle).
    // Used by the core "Active Filters" setting and the Smart Resume Builder
    // "Resume sections" setting. The markup/JS/CSS live in the core plugin so
    // both consumers share one implementation.
    // -------------------------------------------------------------------------

    /**
     * Render a drag-sortable list of switch rows.
     *
     * @param array $args {
     *     @type string $name_base  Field name prefix, e.g. "job_listings_settings[listing_active_filters]"
     *                              or an option name for a whole-option field.
     *     @type array  $options    key => translated label (defines the item set).
     *     @type array  $order      Current key order (missing keys are appended in $options order).
     *     @type array  $enabled    key => 'on'|'off' current switch states (default 'on').
     *     @type string $items_key  Sub-key holding the enabled flags (default 'items'; addon uses 'sections').
     *     @type string $order_key  Sub-key holding the order[] list (default 'order').
     * }
     */
    public static function sortable_switch_list( $args ) {
        $name_base = isset( $args['name_base'] ) ? $args['name_base'] : '';
        $options   = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : [];
        $order     = isset( $args['order'] ) && is_array( $args['order'] ) ? $args['order'] : array_keys( $options );
        $enabled   = isset( $args['enabled'] ) && is_array( $args['enabled'] ) ? $args['enabled'] : [];
        $items_key = isset( $args['items_key'] ) ? $args['items_key'] : 'items';
        $order_key = isset( $args['order_key'] ) ? $args['order_key'] : 'order';

        // Always render a full permutation of the known keys.
        $known = array_keys( $options );
        $full  = [];
        foreach ( $order as $key ) {
            if ( isset( $options[ $key ] ) && ! in_array( $key, $full, true ) ) {
                $full[] = $key;
            }
        }
        foreach ( $known as $key ) {
            if ( ! in_array( $key, $full, true ) ) {
                $full[] = $key;
            }
        }
        ?>
        <ul class="workeera-sortable-switchlist">
            <?php foreach ( $full as $key ) :
                $label = isset( $options[ $key ] ) ? $options[ $key ] : $key;
                $state = ( isset( $enabled[ $key ] ) && 'off' === $enabled[ $key ] ) ? 'off' : 'on';
                ?>
                <li class="workeera-sortsw-row" data-key="<?php echo esc_attr( $key ); ?>">
                    <span class="workeera-sortsw-handle" title="<?php echo esc_attr__( 'Drag to reorder', 'workeera-remote-tech-job-board' ); ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="5" r="1.7" /><circle cx="15" cy="5" r="1.7" /><circle cx="9" cy="12" r="1.7" /><circle cx="15" cy="12" r="1.7" /><circle cx="9" cy="19" r="1.7" /><circle cx="15" cy="19" r="1.7" /></svg>
                    </span>
                    <span class="workeera-sortsw-label"><?php echo esc_html( $label ); ?></span>
                    <span class="workeera-sortsw-switch">
                        <?php
                        self::switch_button( [
                            'name'  => $name_base . '[' . $items_key . '][' . $key . '][enabled]',
                            'value' => $state,
                        ] );
                        ?>
                    </span>
                    <input type="hidden" name="<?php echo esc_attr( $name_base . '[' . $order_key . '][]' ); ?>" value="<?php echo esc_attr( $key ); ?>">
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /**
     * Normalize submitted sortable-switch data to a clean { order, items } shape.
     *
     * The order is reduced to a full permutation of the known keys (unknown keys
     * dropped, dupes removed, missing keys appended in default order) and every
     * enabled flag is whitelisted to on/off. Reused by every sanitize callback.
     *
     * @param  mixed  $value     Raw submitted value.
     * @param  array  $known     Allowed keys in default order.
     * @param  string $items_key Sub-key holding the enabled flags (default 'items').
     * @param  string $order_key Sub-key holding the order list (default 'order').
     * @return array             [ $order_key => [...], $items_key => [ key => ['enabled'=>on/off] ] ].
     */
    public static function sortable_switch_normalize( $value, $known, $items_key = 'items', $order_key = 'order' ) {
        $value = is_array( $value ) ? $value : [];
        $known = is_array( $known ) ? $known : [];

        $order = [];
        if ( isset( $value[ $order_key ] ) && is_array( $value[ $order_key ] ) ) {
            foreach ( $value[ $order_key ] as $key ) {
                $key = sanitize_key( wp_unslash( (string) $key ) );
                if ( in_array( $key, $known, true ) && ! in_array( $key, $order, true ) ) {
                    $order[] = $key;
                }
            }
        }
        foreach ( $known as $key ) {
            if ( ! in_array( $key, $order, true ) ) {
                $order[] = $key;
            }
        }

        $items = [];
        foreach ( $known as $key ) {
            $raw           = isset( $value[ $items_key ][ $key ]['enabled'] ) ? sanitize_key( wp_unslash( (string) $value[ $items_key ][ $key ]['enabled'] ) ) : 'on';
            $items[ $key ] = [ 'enabled' => ( 'off' === $raw ) ? 'off' : 'on' ];
        }

        return [
            $order_key => $order,
            $items_key => $items,
        ];
    }

    /**
     * Return the ENABLED keys in their saved order from a sortable-switch value.
     *
     * @param  mixed  $value     Saved { order, items } value.
     * @param  array  $known     Allowed keys in default order.
     * @param  string $items_key Sub-key holding the enabled flags (default 'items').
     * @param  string $order_key Sub-key holding the order list (default 'order').
     * @return array             List of enabled keys, in order.
     */
    public static function sortable_switch_enabled_in_order( $value, $known, $items_key = 'items', $order_key = 'order' ) {
        $normalized = self::sortable_switch_normalize( $value, $known, $items_key, $order_key );

        $enabled = [];
        foreach ( $normalized[ $order_key ] as $key ) {
            if ( 'off' !== $normalized[ $items_key ][ $key ]['enabled'] ) {
                $enabled[] = $key;
            }
        }

        return $enabled;
    }

}
