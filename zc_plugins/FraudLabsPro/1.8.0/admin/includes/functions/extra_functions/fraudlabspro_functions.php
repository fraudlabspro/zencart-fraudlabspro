<?php

if (!function_exists('fraudlabspro_cfg_yes_no')) {
    function fraudlabspro_cfg_yes_no($select_array, $key_value, $key = '')
    {
        $name = (zen_not_null($key)) ? 'configuration[' . $key . ']' : 'configuration_value';
        $string = '';

        foreach (['true' => 'Yes', 'false' => 'No'] as $value => $label) {
            $element_id = preg_replace('/[^a-z0-9_-]/', '-', strtolower($value . '-' . $name));
            $string .= '<div class="radio"><label>'
                . zen_draw_radio_field($name, $value, ($key_value == $value), '', 'id="' . $element_id . '" class="inputSelect"')
                . $label . '</label></div>';
        }

        return $string;
    }
}
