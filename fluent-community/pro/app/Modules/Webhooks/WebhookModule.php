<?php

namespace FluentCommunityPro\App\Modules\Webhooks;

use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\App\Models\User;
use FluentCommunity\Modules\Course\Services\CourseHelper;

class WebhookModule
{
    public function register()
    {
        add_action('fluent_community/portal_action_incoming_webhook', [$this, 'handleIncomingWebhook']);
    }

    public function handleIncomingWebhook()
    {
        // check if request a post request
        if (empty($_POST)) {
            return;
        }


        $webhookKey = Arr::get($_GET, 'webhook');

        if (!$webhookKey) {
            return;
        }


        $webhook = WebhookModel::where('meta_key', $webhookKey)->first();

        if (!$webhook) {
            wp_send_json(['status' => 'error', 'message' => __('Webhook not found', 'fluent-community-pro')], 404);
        }

        $data = $_POST;

        if (!is_array($data)) {
            $data = json_decode($data, true);
        }

        if (empty($data['email']) || !is_email($data['email'])) {
            wp_send_json(['status' => 'error', 'message' => __('Email is required', 'fluent-community-pro')], 423);
        }

        $userId = $this->maybeCreateUser($webhook, $data);

        if (is_wp_error($userId)) {
            wp_send_json(['status' => 'error', 'message' => $userId->get_error_message()], 423);
        }

        if (!$userId) {
            wp_send_json(['status' => 'error', 'message' => __('User not found', 'fluent-community-pro')], 423);
        }

        $user = User::find($userId);
        $user->syncXProfile();

        // xprofile is now created

        if (Helper::isFeatureEnabled('course_module')) {
            $addCourseIds = Arr::get($webhook->value, 'course_ids', []);
            foreach ($addCourseIds as $courseId) {
                CourseHelper::enrollCourse($courseId, $userId, 'by_admin');
            }

            $removeCourseIds = Arr::get($webhook->value, 'remove_course_ids', []);

            foreach ($removeCourseIds as $courseId) {
                CourseHelper::leaveCourse($courseId, $userId, 'by_admin');
            }
        }

        $addSpaceIds = Arr::get($webhook->value, 'space_ids', []);
        $removeSpaceIds = Arr::get($webhook->value, 'remove_space_ids', []);

        if ($addSpaceIds) {
            foreach ($addSpaceIds as $spaceId) {
                Helper::addToSpace($spaceId, $userId, 'member', 'by_admin');
            }
        }

        if ($removeSpaceIds) {
            foreach ($removeSpaceIds as $spaceId) {
                Helper::removeFromSpace($spaceId, $userId, 'by_admin');
            }
        }

        $value = $webhook->value;

        $runningCount = (int)Arr::get($value, 'running_count', 0);

        $value['running_count'] = $runningCount + 1;
        $webhook->value = $value;
        $webhook->save();

        wp_send_json([
            'status'              => 'success',
            'message'             => __('webhook has been successfully executied', 'fluent-community-pro'),
            'execution_timestamp' => time(),
            'user_id' => $userId
        ], 200);
    }

    protected function maybeCreateUser($webhook, $inputData)
    {
        $email = Arr::get($inputData, 'email');

        $user = get_user_by('email', $email);

        if ($user) {
            return $user->ID;
        }

        // Let's create the user
        $userData = [
            'first_name' => Arr::get($inputData, 'first_name', ''),
            'last_name'  => Arr::get($inputData, 'last_name', ''),
        ];

        $userName = null;

        if ($useLogin = Arr::get($inputData, 'user_login', '')) {
            $useLogin = sanitize_user($useLogin);
            if (!username_exists($useLogin)) {
                $userName = $useLogin;
            }
        }

        if (!$userName) {
            $userName = ProfileHelper::createUserNameFromStrings($email, array_filter($userData));
        }

        $userPassword = Arr::get($inputData, 'user_password', '');

        if (!$userPassword) {
            $userPassword = wp_generate_password(8, false);
        }

        $userData = array_filter([
            'role'       => get_option('default_role', 'subscriber'),
            'user_email' => $email,
            'user_login' => $userName,
            'user_pass'  => $userPassword,
            'first_name' => $userData['first_name'],
            'last_name'  => $userData['last_name']
        ]);

        $userId = wp_insert_user($userData);

        if (is_wp_error($userId)) {
            return $userId;
        }

        if (Arr::get($webhook->value, 'send_wp_welcome_email', 'yes') === 'yes') {
            wp_new_user_notification($userId, null, 'user');
        }

        return $userId;
    }
}
