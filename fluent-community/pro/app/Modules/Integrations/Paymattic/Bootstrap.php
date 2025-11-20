<?php

namespace FluentCommunityPro\App\Modules\Integrations\Paymattic;

use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Services\ProfileHelper;
use \WPPayForm\App\Services\Integrations\IntegrationManager;
use FluentCommunity\Framework\Support\Arr;
use \WPPayForm\Framework\Foundation\App;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\Form;
use \WPPayForm\App\Models\Meta;


class Bootstrap extends IntegrationManager
{

    public $hasGlobalMenu = false;

    public $disableGlobalSettings = 'yes';

    public function __construct()
    {
        parent::__construct(
            App::getInstance(),
            'Fluent Community',
            'fluent_community',
            '_wppayform_fluent_community_settings',
            'fluent_community_feeds',
            10
        );

        $this->logo = FLUENT_COMMUNITY_PLUGIN_URL . 'assets/images/logo.png';

        $this->description = __('Connect Paymattic with Fluent Community', 'fluent-community-pro');

        $this->registerAdminHooks();

        add_filter('wppayform_notifying_async_fluent_community', '__return_false');

        (new RemoveFromSpaceAction())->init();
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        $fields = [
            'name'               => '',
            'space_ids'          => [],
            'email'              => '',
            'username'           => '',
            'enableAutoLogin'    => false,
            'sendEmailToNewUser' => true,
            'remove_on_subscription_cancel' => false,
            'remove_on_refund' => false,
            'trigger_on_payment' => false,
            'conditionals'       => [
                'conditions' => [],
                'status'     => false,
                'type'       => 'all'
            ],
            'enabled'            => true
        ];


        return apply_filters('fluent_community/wppayform__defaults', $fields, $formId);
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
                    'label'       => __('Name', 'fluent-community-pro'),
                    'required'    => true,
                    'placeholder' => __('Your Feed Name', 'fluent-community-pro'),
                    'component'   => 'text'
                ],
                [
                    'key'         => 'space_ids',
                    'label'       => __('Select Spaces or Courses to Enroll', 'fluent-community-pro'),
                    'placeholder' => __('Choose options', 'fluent-community-pro'),
                    'required'    => true,
                    'is_multiple' => true,
                    'component'   => 'select',
                    'options'     => $this->getAllSpacesCourses()
                ],
                [
                    'key'                => 'custom_fields',
                    'require_list'       => false,
                    'label'              => __('Map Fields', 'fluent-community-pro'),
                    'tips'               => __('Associate your user fields to the appropriate Paymattic fields by selecting the appropriate form field from the list.', 'fluent-community-pro'),
                    'component'          => 'map_fields',
                    'field_label_remote' => __('User Profile  Field', 'fluent-community-pro'),
                    'field_label_local'  => __('Form Field', 'fluent-community-pro'),
                    'primary_fileds'     => $this->communityMapFields()
                ],
                [
                    'require_list' => false,
                    'title'        => __('For new users', 'fluent-community-pro'),
                    'html_info'    => '<h4 style="font-size: 16px;">' . __('For New Users - Data mapping', 'fluent-community-pro') . '</h4><p>' . __('These settings will apply only if the provided email address is not a registered WordPress user', 'fluent-community-pro') . '</p>',
                    'component'    => 'html_info',
                ],
                [
                    'require_list' => false,
                    'key'          => 'full_name',
                    'component'    => 'value_text',
                    'label'        => __('Full Name (Only for new users)', 'fluent-community-pro')
                ],
                [
                    'require_list' => false,
                    'key'          => 'password',
                    'component'    => 'value_text',
                    'label'        => __('Password (Only for new users)', 'fluent-community-pro'),
                    'inline_tip'   => __('Keep empty to auto generated password', 'fluent-community-pro')
                ],
                // [
                //     'require_list' => false,
                //     'required'     => true,
                //     'key'          => 'userRole',
                //     'label'        => __('Default User Role (Only for new users)', 'fluent-community-pro'),
                //     'tips'         => "Set default user role when registering a new user.",
                //     'component'    => 'radio_choice',
                //     'options'      => $this->getUserRoles()
                // ],
                [
                    'require_list'   => false,
                    'key'            => 'enableAutoLogin',
                    'label'          => __('Auto Login & Password Reset Email (For New Users Only)', 'fluent-community-pro'),
                    'checkbox_label' => __('Allow the user login automatically after Form submission', 'fluent-community-pro'),
                    'component'      => 'checkbox-single',
                ],
                [
                    'require_list'   => false,
                    'key'            => 'sendEmailToNewUser',
                    'checkbox_label' => __('Send default WordPress welcome email to user after registration', 'fluent-community-pro'),
                    'component'      => 'checkbox-single',
                ],
                [
                    'key' => 'trigger_on_payment',
                    'require_list' => false,
                    'checkbox_label' => __('Join/Enroll space/course on payment success only', 'fluent-community-pro'),
                    'component' => 'checkbox-single'
                ],
                [
                    'key' => 'remove_on_refund',
                    'require_list' => false,
                    'checkbox_label' => __('Remove from sapce/course if payment refunded', 'fluent-community-pro'),
                    'component' => 'checkbox-single'
                ],
                [
                    'key' => 'remove_on_subscription_cancel',
                    'require_list' => false,
                    'checkbox_label' => __('Remove from sapce/course if subscription canceled', 'fluent-community-pro'),
                    'component' => 'checkbox-single'
                ],
                // [
                //     'require_list' => false,
                //     'key'          => 'manage_subscription',
                //     'label'        => __('Manage Subscription', 'fluent-community'),
                //     'component'    => 'checkbox-single',
                //     'checkbox_label' => __('Remove from space after subscription ends.', 'fluent-community'),
                // ],
                [
                    'require_list' => false,
                    'key'          => 'conditionals',
                    'label'        => __('Conditional Logics', 'fluent-community-pro'),
                    'tips'         => __('Allow this integration conditionally based on your submission values','fluent-community-pro'),
                    'component'    => 'conditional_block'
                ]
            ],
            'button_require_list' => false,
            'integration_title'   => $this->title
        ];


        return $fieldSettings;
    }

    public function getAllSpacesCourses()
    {
        $allSpaces = \FluentCommunity\App\Models\BaseSpace::query()->withoutGlobalScopes()
            ->whereIn('type', ['course', 'community'])
            ->where('status', 'published')
            ->select(['id', 'title', 'type'])
            ->orderBy('title', 'ASC')
            ->get();

        $formattedSpaces = [];
        foreach ($allSpaces as $space) {
            $type = $space->type == 'course' ? __('Course', 'fluent-comunity') : __('Space', 'fluent-community-pro');
            $formattedSpaces[$space->id] = "{$space->title} ({$type})";
        }
        return $formattedSpaces;
    }

    public function communityMapFields()
    {
        $fields = [
            [
                'key'           => 'email',
                'label'         => 'Email Address',
                'required'      => true,
                'input_options' => 'emails'
            ],
        ];

        return apply_filters('wpppayform/user_registration_map_fields', $fields);
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'category'                => 'wp_core',
            'disable_global_settings' => 'yes',
            'logo'                    => '',
            'title'                   => sprintf(__(' %s Integration', 'fluent-community-pro'), 'Fluent Community'),
            'is_active'               => $this->isConfigured(),
            'enabled'                 => $this->isEnabled(),
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

    public function notify($feed, $formData, $entry, $formId)
    {
        $processedValues = Arr::get($feed, 'processedValues', []);
        $spaceIds = Arr::get($processedValues, 'space_ids', []);
 
        if (empty($spaceIds)) {
            return $this->addLog(
                'No Course/Space selected',
                $formId,
                $entry->id,
                'failed',
            );
        }

        $spaces = BaseSpace::query()->withoutGlobalScopes()->whereIn('id', $spaceIds)->get();

        if ($spaces->isEmpty()) {
            return $this->addLog(
                'No Course/Space selected',
                $formId,
                $entry->id,
                'failed',
            );
        }

        $emailKey = Arr::get($processedValues, 'email');
        $userFullName = Arr::get($processedValues, 'full_name');
        $password = trim((string)Arr::get($processedValues, 'password',''));

        $isNewUser = false;
     
        $existingUser = get_user_by('email', $emailKey);
    
        if ($existingUser) {
            $userId = $existingUser->ID;
        } else {
            $formData['user_id'] = '';
            $userId = $this->registerUser([
                'email'     => $emailKey,
                'full_name' => $userFullName,
                'password'  => $password,
                'userRole'  => Arr::get($processedValues, 'userRole')
            ]);
            if (is_wp_error($userId)) {
                return $this->addLog(
                    'Failed to create user. Reason: ' . $userId->get_error_message(),
                    $formId,
                    $entry->id,
                    'failed',
                );
                return false;
            }

             // save here user id in DB table where user access will added
            (new Submission())->updateSubmission($entry->id, [
                'user_id' => $userId
            ]);
            do_action('wppayform_created_user', $userId, $feed, $entry, $formId);
            (new Meta())->updateOrderMeta('formSettings', $entry->id, '__created_user_id', '', $formId);

            $isNewUser = true;
            if (Arr::isTrue($processedValues, 'sendEmailToNewUser')) {
                // This will send an email with password setup link
                \wp_new_user_notification($userId, null, 'user');
                
            }
        }

        $successSpaces = [];
        foreach ($spaces as $space) {
            if (\FluentCommunity\App\Services\Helper::addToSpace($space, $userId)) {
                $successSpaces[] = $space->title;
            }
        }

        if(Arr::has($formData, 'recurring_payment_item')) {
            if (defined('WPPAYFORMHASPRO')) {
                $form_id = Arr::get($formData, '__wpf_form_id');
                $formattedElements = Form::getFormattedElements($form_id);
                $recurringPaymentItem = Arr::get($formData, 'recurring_payment_item');
                $recurring_payment_options = Arr::get($formattedElements, 'payment.recurring_payment_item.options.recurring_payment_options.pricing_options');
                $recurring_payment_option = Arr::get($recurring_payment_options, $recurringPaymentItem);
                $get_user_next_billing_date = $this->getUserNextBillingDate($recurring_payment_option);
          
                if ($recurring_payment_option) {
                    // add to wp user meta for global user, also in case of updating this meta maintain the same key and order
                    // If any better idea comes to mind, please update the code accordingly (in global socpe)
                    update_user_meta($userId, 'wpf_fcom_subscription_' . $userId, [
                        'expiry_date' => $get_user_next_billing_date,
                        'space_ids' => $spaceIds,
                        'entry_id' => $entry->id,
                        'status' => 'active'
                    ]);

                }
                
            }
        }
        /**
         * If the user is new, auto-login is enabled, and the user is not already logged in, then log the user in
         */
        if ($isNewUser && Arr::isTrue($processedValues, 'enableAutoLogin') && !is_user_logged_in()) {
            $this->maybeLogin($userId, $entry);
        }

        return $this->addLog(
            'Joined Course/Spaces: ' . implode(',', $successSpaces),
            $formId,
            $entry->id,
            'success',
        );
    }

    public function getUserNextBillingDate($recurring_payment_option)
    {
        $duration = $recurring_payment_option['billing_interval'];
        $trial_days = $recurring_payment_option['trial_days'];

        // Get the current date
        $current_date = new \DateTime();

        // Add trial days to the current date
        $trial_end_date = clone $current_date;
        $trial_end_date->modify("+$trial_days days");

        // Determine the duration
        switch ($duration) {
            case 'daily':
                $interval = '1 day';
                break;
            case 'week':
                $interval = '1 week';
                break;
            case 'month':
                $interval = '1 month';
                break;
            case 'year':
                $interval = '1 year';
                break;
            case 'quarter':
                $interval = '3 months';
                break;
            case 'fortnight':
                $interval = '2 weeks';
                break;
            case 'half_year':
                $interval = '6 months';
                break;
            default:
                throw new \InvalidArgumentException('Invalid billing interval: ' . esc_html($duration));
        }

        // Add the interval to the trial end date
        $final_date = clone $trial_end_date;
        $final_date->modify("+$interval");

        // Return the final date
        return $final_date->format('Y-m-d');
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
            'role'       => get_role( 'paymattic_user' ) ? 'paymattic_user' : 'subscriber',
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

    protected function addLog($content, $formId, $entryId, $type = 'activity')
    {
        do_action('wppayform_log_data', [
            'form_id' => $formId,
            'submission_id' => $entryId,
            'type' => $type,
            'created_by' => 'Paymattic BOT',
            'content' => $content
        ]);
    }

    protected function maybeLogin($userId, $entry = false)
    {
        wp_clear_auth_cookie();
        wp_set_current_user($userId);
        wp_set_auth_cookie($userId);
    }
}
