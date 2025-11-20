<?php

namespace FluentCommunity\App\Functions;

class  FluentExtendApi
{
    /**
     * Add a meta box for the space provider.
     *
     * @param string $provider The provider name.
     * @param array $args Arguments for the meta box.
     *       $args['section_title'] string The title of the meta box.
     *       $args['fields_callback'] callable A callback function to get the form fields array
     *       $args['data_callback'] callable A callback function to get the data for the meta box form fields
     *       $args['save_callback'] callable A callback function to save the data from the form fields
     * $@param array $objectTypes The object types to which the meta box should be added. Possible values are 'space' and 'course'.
     * @return void
     */
    public function addMetaBox(string $provider, array $args = [], array $objectTypes = []): void
    {
        if (!$objectTypes) {
            return;
        }

        $validObjectTypes = ['space', 'course'];
        $objectTypes = array_intersect($objectTypes, $validObjectTypes);
        if (!$objectTypes) {
            return;
        }

        foreach ($objectTypes as $objectType) {
            if (!in_array($objectType, $validObjectTypes)) {
                continue;
            }

            if (!isset($args['section_title']) || !isset($args['fields_callback']) || !isset($args['data_callback']) || !isset($args['save_callback'])) {
                throw new \InvalidArgumentException('Invalid arguments provided for addMetaBox.');
            }

            $hookPrefix = 'fluent_community/' . $objectType . '/';
            $this->addObjectMeta($hookPrefix, $provider, $args);
        }
    }

    private function addObjectMeta($hookPrefix, $provider, $args)
    {
        add_filter($hookPrefix . 'meta_fields', function ($fields, $model) use ($provider, $args) {
            $formFields = \is_callable($args['fields_callback']) ? \call_user_func($args['fields_callback'], $model) : [];
            if (!$formFields) {
                return $fields;
            }

            $settings = \is_callable($args['data_callback']) ? \call_user_func($args['data_callback'], $model) : [];

            $fields[$provider] = [
                'section_title' => $args['section_title'],
                'settings'      => $settings,
                'fields'        => $formFields
            ];

            return $fields;
        }, 10, 2);

        add_action($hookPrefix . 'update_meta_settings_' . $provider, function ($settings, $model) use ($args) {
            if (\is_callable($args['save_callback'])) {
                \call_user_func($args['save_callback'], $settings, $model);
            }
        }, 10, 2);
    }

}
