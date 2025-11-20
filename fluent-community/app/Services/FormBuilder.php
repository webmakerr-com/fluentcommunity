<?php

namespace FluentCommunity\App\Services;

class FormBuilder
{
    private $formFields = [];

    public function __construct($formFields)
    {
        $this->formFields = $formFields;
    }

    public function render()
    {
        foreach ($this->formFields as $name => $field) {
            if (empty($field['disabled'])) {
                $field['name'] = $name;
                $this->renderField($field);
            }
        }
    }

    private function renderField($field)
    {
        $type = $field['type'];
        $label = $field['label'] ?? '';
        $name = $field['name'];
        $required = $field['required'] ?? false;
        $options = $field['options'] ?? [];
        $value = $field['value'] ?? '';
        $readonly = $field['readonly'] ?? false;

        $atts = array_filter([
            'type'        => in_array($type, ['text', 'email', 'password']) ? $type : '',
            'id'          => 'fcom_' . $name,
            'name'        => $name,
            'value'       => $value,
            'required'    => '',//$required ? 'required' : '',
            'readonly'    => $readonly ? 'readonly' : '',
            'placeholder' => $field['placeholder'] ?? '',
            'class'       => $field['input_class'] ?? '',
        ]);

        echo "<div id='fcom_group_" . esc_attr($name) . "' class='fcom_form-group'>";
        if ($label):
            echo "<div class='fcom_form_label'><label for='" . esc_attr($atts['id']) . "'>" . esc_html($label) . "</label></div>";
        endif;

        echo "<div class='fcom_form_input'>";

        if (isset($atts['type'])) {
            echo "<input".$this->printAtts($atts).">"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === 'select') {
            echo "<select id='" . esc_attr($name) . "' name='" . esc_attr($name) . "' " . ($required ? 'required' : '') . ">"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            foreach ($options as $option) {
                echo "<option value='" . esc_attr($option) . "'>" . esc_html($option) . "</option>";
            }
            echo "</select>";
        } else if ($type === 'inline_checkbox') {
            echo "<div class='fcom_inline_checkbox'>";
            echo "<input type='checkbox' " . $this->printAtts($atts) . ">"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo "<label for='" . esc_attr($atts['id']) . "'>" . wp_kses_post($field['inline_label']) . "</label>";
            echo "</div>";
        } else if ($type === 'textarea') {
            echo "<textarea " . $this->printAtts($atts) . "></textarea>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo "</div></div>";
    }

    private function printAtts($atts)
    {
        $result = '';
        foreach ($atts as $key => $value) {
            $result .= " " . esc_attr($key) . "='" . esc_attr($value) . "'";
        }
        return $result;
    }
}
