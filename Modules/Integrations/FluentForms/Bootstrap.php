<?php

namespace FluentCommunity\Modules\Integrations\FluentForms;

use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Services\ProfileHelper;
use FluentForm\App\Helpers\Helper;
use \FluentForm\App\Http\Controllers\IntegrationManagerController;
use FluentCommunity\Framework\Support\Arr;


class Bootstrap extends IntegrationManagerController
{

    public $hasGlobalMenu = false;
    public $disableGlobalSettings = 'yes';

    public function __construct()
    {
        parent::__construct(
            null,
            'FluentCommunity',
            'fluent_community',
            '_fluentform_fluent_community_settings',
            'fluent_community_feeds',
            10
        );

        $this->logo = FLUENT_COMMUNITY_PLUGIN_URL . 'assets/images/logo.png';

        $this->description = __('Connect Fluent Forms with FluentCommunity', 'fluent-community');

        $this->registerAdminHooks();
        add_filter("fluentform/save_integration_value_{$this->integrationKey}", array($this, 'validateSettings'), 10, 2);
    
        add_filter('fluentform/notifying_async_fluent_community', '__return_false');
    }


    public function getIntegrationDefaults($settings, $formId)
    {
        $fields = [
            'name'               => '',
            'space_ids'          => [],
            'Email'              => '',
            'username'           => '',
            'enableAutoLogin'    => false,
            'sendEmailToNewUser' => false,
            'conditionals'       => [
                'conditions' => [],
                'status'     => false,
                'type'       => 'all'
            ],
            'enabled'            => true
        ];


        return apply_filters('fluent_community/fluentform__defaults', $fields, $formId);
    }

    public function getMergeFields($list, $listId, $formId)
    {
        return [];
    }

    public function getSettingsFields($settings, $formId)
    {
        $fieldSettings = [
            'fields'              => [
                [
                    'key'         => 'name',
                    'label'       => __('Name', 'fluent-community'),
                    'required'    => true,
                    'placeholder' => __('Your Feed Name', 'fluent-community'),
                    'component'   => 'text'
                ],
                [
                    'key'         => 'space_ids',
                    'label'       => __('Select Spaces or Courses to Enroll', 'fluent-community'),
                    'placeholder' => __('Choose options', 'fluent-community'),
                    'required'    => true,
                    'is_multiple' => true,
                    'component'   => 'select',
                    'options'     => $this->getAllSpacesCourses()
                ],
                [
                    'key'                => 'custom_fields',
                    'require_list'       => false,
                    'label'              => __('Map Fields', 'fluent-community'),
                    'tips'               => __('Associate your user fields to the appropriate Fluent Forms fields by selecting the appropriate form field from the list.', 'fluent-community'),
                    'component'          => 'map_fields',
                    'field_label_remote' => __('User Profile  Field', 'fluent-community'),
                    'field_label_local'  => __('Form Field', 'fluent-community'),
                    'primary_fileds'     => $this->communityMapFields()
                ],
                [
                    'require_list' => false,
                    'title'        => __('For new users', 'fluent-community'),
                    'html_info'    => '<h4 style="font-size: 16px;">' . __('For New Users - Data mapping', 'fluent-community') . '</h4><p>' . __('These settings will apply only if the provided email address is not a registered WordPress user', 'fluent-community') . '</p>',
                    'component'    => 'html_info',
                ],
                [
                    'require_list' => false,
                    'key'          => 'full_name',
                    'component'    => 'value_text',
                    'label'        => __('Full Name (Only for new users)', 'fluent-community')
                ],
                [
                    'require_list' => false,
                    'key'          => 'password',
                    'component'    => 'value_text',
                    'label'        => __('Password (Only for new users)', 'fluent-community'),
                    'inline_tip'   => __('Keep empty to auto generated password', 'fluent-community')
                ],
                [
                    'require_list'   => false,
                    'key'            => 'enableAutoLogin',
                    'label'          => __('Auto Login & Password Reset Email (For New Users Only)', 'fluent-community'),
                    'checkbox_label' => __('Allow the user login automatically after Form submission', 'fluent-community'),
                    'component'      => 'checkbox-single',
                ],
                [
                    'require_list'   => false,
                    'key'            => 'sendEmailToNewUser',
                    'checkbox_label' => __('Send default WordPress welcome email to user after registration',
                        'fluent-community'),
                    'component'      => 'checkbox-single',
                ],
                [
                    'require_list' => false,
                    'key'          => 'conditionals',
                    'label'        => __('Conditional Logics', 'fluent-community'),
                    'tips'         => __('Allow this integration conditionally based on your submission values',
                        'fluent-community'),
                    'component'    => 'conditional_block'
                ],
            ],
            'button_require_list' => false,
            'integration_title'   => $this->title
        ];


        return $fieldSettings;
    }
    
    public function validateSettings($settings)
    {
        $message = __('Validation Failed', 'fluent-community');
        $errors = [];
        if (empty($settings['space_ids'])) {
            $errors['space_ids'] = [__('Please Select Space or Course', 'fluent-community')];
        }
        if (empty($settings['email'])) {
            $errors['custom_fields'] = [__('Please Select a Email', 'fluent-community')];
        }
        
        if ($errors) {
            wp_send_json_error([
                'message' => $message,
                'errors'  => $errors
            ], 423);
        }
        
        return $settings;
    }

    public function getAllSpacesCourses()
    {
        $allSpaces = \FluentCommunity\App\Models\BaseSpace::query()->withoutGlobalScopes()
            ->whereIn('type', ['course', 'community'])
            ->select(['id', 'title', 'type'])
            ->orderBy('title', 'ASC')
            ->get();

        $formattedSpaces = [];
        foreach ($allSpaces as $space) {
            $type = $space->type == 'course' ? __('Course', 'fluent-community') : __('Space', 'fluent-community');
            $formattedSpaces[$space->id] = "{$space->title} ({$type})";
        }
        return $formattedSpaces;
    }

    public function communityMapFields()
    {
        $fields = [
            [
                'key'           => 'email',
                'label'         => __('Email Address', 'fluent-community'),
                'input_options' => 'emails',
                'required'      => true,
            ]
        ];

        return apply_filters('fluent-community/fluentform_map_fields', $fields);
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'category'                => 'wp_core',
            'disable_global_settings' => 'yes',
            'logo'                    => '',
            /* translators: %s is replaced by the title of the integration */
            'title'                   => sprintf(__(' %s Integration', 'fluent-community'), 'FluentCommunity'),
            'is_active'               => $this->isConfigured()
        ];

        return $integrations;
    }

    public function isConfigured()
    {
        return true;
    }

    public function isEnabled()
    {
        return true;
    }

    public function notify($feed, $formData, $entry, $form)
    {
        $processedValues = Arr::get($feed, 'processedValues', []);
        $spaceIds = Arr::get($processedValues, 'space_ids', []);

        if (!$spaceIds) {
            return $this->addLog(
                $feed['settings']['name'],
                'failed',
                __('No Course/Space selected', 'fluent-community'),
                $form->id,
                $entry->id,
                $this->integrationKey
            );
        }

        $spaces = BaseSpace::query()->onlyMain()->whereIn('id', $spaceIds)->get();
        if ($spaces->isEmpty()) {
            return $this->addLog(
                $feed['settings']['name'],
                'failed',
                __('No Course/Space found', 'fluent-community'),
                $form->id,
                $entry->id,
                $this->integrationKey
            );
        }

        $emailKey = Arr::get($processedValues, 'email');
        $userFullName = Arr::get($processedValues, 'full_name');
        $password = Arr::get($processedValues, 'password');

        $isNewUser = false;

        if ($entry->user_id) {
            $userId = $entry->user_id;
        } else {
            $emailAddress = Arr::get($formData, $emailKey);
            $existingUser = get_user_by('ID', $emailAddress);

            if ($existingUser) {
                $userId = $existingUser->ID;
            } else {
                $userId = $this->registerUser([
                    'email'     => $emailAddress,
                    'full_name' => $userFullName,
                    'password'  => $password
                ]);
                if (is_wp_error($userId)) {
                    return $this->addLog(
                        $feed['settings']['name'],
                        'failed',
                        /* translators: %s is replaced by the error message */
                        __('Failed to create user. Reason: %s', 'fluent-community'), $userId->get_error_message(),
                        $form->id,
                        $entry->id,
                        $this->integrationKey
                    );
                    return false;
                }

                $isNewUser = true;

                if (Arr::isTrue($processedValues, 'sendEmailToNewUser')) {
                    // This will send an email with password setup link
                    \wp_new_user_notification($userId, null, 'user');
                }
            }
        }

        $successSpaces = [];

        foreach ($spaces as $space) {
            if (\FluentCommunity\App\Services\Helper::addToSpace($space, $userId)) {
                $successSpaces[] = $space->title;
            }
        }

        if ($isNewUser && Arr::isTrue($processedValues, 'enableAutoLogin')) {
            $this->maybeLogin($userId, $entry);
        }

        return $this->addLog(
            $feed['settings']['name'],
            'success',
            'Joined Course/Spaces: ' . implode(',', $successSpaces),
            $form->id,
            $entry->id,
            $this->integrationKey
        );
    }

    /**
     * Create a new user
     *
     * @param array $feed
     * @param array $formData
     * @param object $entry
     * @param object $form
     * @param string $integrationKey
     *
     * @return int|\WP_Error
     */
    protected function registerUser($userData)
    {
        $email = Arr::get($userData, 'email');
        if (!is_email($email)) {
            return new \WP_Error('invalid_email', 'Invalid email address');
        }

        $password = trim((string)Arr::get($userData, 'password',''));
        if (!$password) {
            $password = wp_generate_password(8);
        }

        $nameArray = explode(' ', (string)Arr::get($userData, 'full_name'));

        $firstName = array_shift($nameArray);
        $lastName = implode(' ', $nameArray);

        $userName = ProfileHelper::createUserNameFromStrings($email, array_filter([
            $firstName,
            $lastName
        ]));

        $userData = [
            'role'       => get_option('default_role', 'subscriber'),
            'user_email' => $email,
            'user_login' => $userName,
            'user_pass'  => $password,
            'first_name' => $firstName,
            'last_name'  => $lastName
        ];

        $userId = wp_insert_user($userData);

        if (is_wp_error($userId)) {
            return $userId;
        }

        return $userId;
    }

    protected function addLog($title, $status, $description, $formId, $entryId, $integrationKey)
    {
        $logData = [
            'title'            => $title,
            'status'           => $status,
            'description'      => $description,
            'parent_source_id' => $formId,
            'source_id'        => $entryId,
            'component'        => $integrationKey,
            'source_type'      => 'submission_item'
        ];

        do_action('fluentform/log_data', $logData);

        return true;
    }

    protected function maybeLogin($userId, $entry = null)
    {
        // check if it's payment success page
        // or direct url
        if (isset($_REQUEST['fluentform_payment_api_notify']) && $entry) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            // This payment IPN request so let's keep a reference for real request
            Helper::setSubmissionMeta($entry->id, '_make_auto_login', $userId, $entry->form_id);
            return;
        }

        wp_clear_auth_cookie();
        wp_set_current_user($userId);
        wp_set_auth_cookie($userId);
    }
}
