<?php

namespace FluentCommunityPro\App\Http\Controllers;

use FluentCommunity\App\Models\SpaceGroup;
use FluentCommunity\App\Services\CustomSanitizer;
use FluentCommunity\App\Models\SidebarLink;
use FluentCommunityPro\App\Modules\Followers\FollowerHelper;
use FluentCommunityPro\App\Modules\Webhooks\WebhookModel;
use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\Meta;
use FluentCommunity\App\Models\Term;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Modules\Auth\AuthHelper;
use FluentCommunity\App\Services\LockscreenService;
use FluentCommunity\App\Services\AuthenticationService;
use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunityPro\App\Services\ProHelper;
use FluentMessaging\App\Services\ChatHelper;

class ProAdminController extends Controller
{
    public function getManagers(Request $request)
    {
        $query = User::whereHas('community_role')
            ->whereHas('xprofile', function ($q) {
                $q->where('status', 'active');
            })
            ->with(['community_role', 'xprofile']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('display_name', 'LIKE', "%{$search}%")
                    ->orWhere('user_email', 'LIKE', "%{$search}%")
                    ->orWhere('user_login', 'LIKE', "%{$search}%");
            });
        }

        $managers = $query->paginate();

        return [
            'managers' => $managers,
            'total'    => $managers->total()
        ];
    }

    public function addOrUpdateManager(Request $request)
    {
        $userId = $request->getSafe('user_id', 'intval');
        $roles = $request->get('roles', []);

        $this->validate($request->all(), [
            'user_id' => 'required',
            'roles'   => 'required|array'
        ]);

        if (in_array('admin', $roles)) {
            $roles = ['admin'];
        } elseif (in_array('course_admin', $roles) && in_array('course_creatror', $roles)) {
            unset($roles[array_search('course_creatror', $roles)]);
        }

        $roles = array_values($roles);

        $user = User::find($userId);
        $user->syncXProfile();

        if ($user->community_role) {
            if ($user->community_role->value != $roles) {
                $user->community_role()->update([
                    'value' => \maybe_serialize($roles)
                ]);
                do_action('fluent_community/manager/updated', $user, $roles);
            }

            return [
                'message' => __('Manager has been updated successfully', 'fluent-community-pro'),
                'manager' => $user
            ];
        }

        $user->community_role()->create([
            'value'       => $roles,
            'meta_key'    => '_user_community_roles',
            'object_type' => 'user',
            'object_id'   => $user->ID
        ]);

        $user->load('community_role');
        do_action('fluent_community/manager/added', $user, $roles);

        return [
            'message' => 'Manager has been added successfully',
            'manager' => $user
        ];
    }

    public function deleteManager(Request $request, $user_id)
    {
        $user = User::with('community_role')->find($user_id);

        if (!$user) {
            return $this->sendError('User not found');
        }

        if (!$user->community_role) {
            return $this->sendError('User is not a manager');
        }

        do_action('fluent_community/manager/before_remove', $user);

        $user->community_role->delete();

        do_action('fluent_community/managed/after_remove', $user);

        return [
            'message' => 'Manager has been removed successfully'
        ];
    }

    public function getUsers(Request $request)
    {
        $userQuery = User::searchBy($request->getSafe('search'));

        if ($request->get('context') == 'add_manager') {
            $userQuery->whereDoesntHave('community_role');
        }

        if (is_multisite()) {
            global $wpdb;
            $blogId = get_current_blog_id();
            $blogPrefix = $wpdb->get_blog_prefix($blogId);
            $userQuery->whereHas('usermeta', function ($q) use ($blogPrefix) {
                $q->where('meta_key', $blogPrefix . 'capabilities');
            });
        }

        $users = $userQuery->paginate();

        return [
            'users' => $users
        ];
    }

    public function getTopics(Request $request)
    {
        if ($request->has('optionsOnly')) {
            $topis = Utility::getTopics();
            $formattedTopics = [];
            foreach ($topis as $topic) {

                $formattedTopics[] = [
                    'id'          => $topic['id'],
                    'title'       => $topic['title'],
                    'description' => $topic['description'] ?? '',
                ];
            }

            return [
                'topics' => $formattedTopics
            ];
        }

        $search = $request->getSafe('search', 'sanitize_text_field');
        $topics = Utility::getTopics();

        if ($search) {
            $topics = array_filter($topics, function ($topic) use ($search) {
                return strpos(strtolower($topic['title']), strtolower($search)) !== false;
            });
        }

        return [
            'all_spaces' => BaseSpace::query()->withoutGlobalScopes()->orderBy('serial', 'ASC')->get(),
            'topics'     => array_values($topics)
        ];
    }

    public function saveTopics(Request $request)
    {
        $updateData = $request->all();
        $this->validate($updateData, [
            'title' => 'required',
        ]);

        $id = $request->getSafe('id', 'intval');

        if ($id) {
            $topic = Term::where('taxonomy_name', 'post_topic')->find($id);
            if (!$topic) {
                return $this->sendError('Topic not found');
            }
            $topic->title = sanitize_text_field($updateData['title']);
            $topic->description = sanitize_text_field($updateData['description'] ?? '');

            $settings = $topic->settings;
            $settings['admin_only'] = Arr::get($updateData, 'admin_only', 'no');
            $topic->settings = $settings;
            $topic->save();
        } else {
            // Validate the slug
            $slug = sanitize_title(Arr::get($updateData, 'slug'));
            if (!$slug) {
                $slug = sanitize_title($updateData['title']);
            }

            $exist = Term::where('taxonomy_name', 'post_topic')->where('slug', $slug)->first();

            if ($exist) {
                return $this->sendError([
                    'message' => __('Slug already exist. Please use a different slug.', 'fluent-community-pro')
                ]);
            }

            $topic = Term::create([
                'taxonomy_name' => 'post_topic',
                'title'         => sanitize_text_field($updateData['title']),
                'description'   => sanitize_text_field($updateData['description'] ?? ''),
                'slug'          => $slug,
                'settings'      => [
                    'admin_only' => Arr::get($updateData, 'admin_only', 'no') == 'yes' ? 'yes' : 'no'
                ]
            ]);
        }

        $spaceIds = (array)$request->get('space_ids', []);

        $topicSpaceRelations = Meta::select(['id', 'object_id', 'meta_key'])
            ->where('object_id', $topic->id)
            ->where('object_type', 'term_space_relation')
            ->get();

        foreach ($topicSpaceRelations as $relation) {
            if (in_array($relation->id, $spaceIds)) {
                $spaceIds = array_diff($spaceIds, [$relation->id]);
            } else {
                $relation->delete();
            }
        }

        if ($spaceIds) {
            foreach ($spaceIds as $spaceId) {
                if (!BaseSpace::withoutGlobalScopes()->find($spaceId)) {
                    continue;
                }
                Meta::create([
                    'object_id'   => $topic->id,
                    'meta_key'    => $spaceId,
                    'object_type' => 'term_space_relation'
                ]);
            }
        }

        Utility::forgetCache('fluent_community_post_topics');

        return [
            'message' => __('Topic has been saved successfully', 'fluent-community-pro'),
            'topic'   => $topic
        ];
    }

    public function getSnippetsSettings(Request $request)
    {
        return [
            'snippets' => ProHelper::getSnippetsSettings()
        ];
    }

    public function updateSnippetsSettings(Request $request)
    {
        $settings = $request->get('snippets', []);

        $formattedSettings = [
            'custom_css' => ProHelper::sanitizeCSS(Arr::get($settings, 'custom_css', '')),
            'custom_js'  => Arr::get($settings, 'custom_js', ''),
        ];

        ProHelper::updateSnippetsSettings($formattedSettings);

        return [
            'message' => __('Snippets settings have been saved successfully.', 'fluent-community-pro')
        ];
    }

    public function deleteTopic(Request $request, $topicId)
    {
        $topic = Term::where('taxonomy_name', 'post_topic')->findOrFail($topicId);
        $topic->delete();

        // remove the relations
        Meta::where('object_id', $topicId)
            ->where('object_type', 'term_space_relation')
            ->delete();

        Utility::forgetCache('fluent_community_post_topics');

        return [
            'message' => __('Topic has been deleted successfully', 'fluent-community-pro')
        ];
    }

    public function updateTopicConfig(Request $request)
    {
        $config = $request->get('config', []);
        $prev = Helper::getTopicsConfig();
        $config = Arr::only($config, array_keys($prev));

        $config['max_topics_per_post'] = (int)Arr::get($config, 'max_topics_per_post', 1);
        $config['max_topics_per_space'] = (int)Arr::get($config, 'max_topics_per_space', 10);
        $config['show_on_post_card'] = Arr::get($config, 'show_on_post_card') == 'yes' ? 'yes' : 'no';

        Utility::updateOption('topics_config', $config);
        Utility::forgetCache('topics_config');

        return [
            'message' => __('Topics configuration has been updated successfully', 'fluent-community-pro'),
            'config'  => $config
        ];
    }

    public function saveColorConfig(Request $request)
    {
        $darkSchema = $request->get('dark_schema', 'default');
        $lightSchema = $request->get('light_schema', 'default');

        $allSchemas = Utility::getColorSchemas();

        if (!isset($allSchemas['darkSkins'][$darkSchema]) || !isset($allSchemas['lightSkins'][$lightSchema])) {
            return $this->sendError([
                'message' => __('Invalid color schema selected', 'fluent-community-pro')
            ]);
        }

        $updateData = [
            'dark_schema'  => $darkSchema,
            'light_schema' => $lightSchema,
            'version'      => FLUENT_COMMUNITY_PLUGIN_VERSION
        ];

        if ($darkSchema === 'custom') {
            $updateData['dark_config'] = $request->get('dark_config', []);
            $allSchemas['darkSkins']['custom']['selectors'] = $updateData['dark_config'];
        } else {
            $updateData['dark_config'] = [];
        }

        if ($lightSchema === 'custom') {
            $updateData['light_config'] = $request->get('light_config', []);
            $allSchemas['lightSkins']['custom']['selectors'] = $updateData['light_config'];
        } else {
            $updateData['light_config'] = [];
        }

        $lightCss = Utility::generateCss(Arr::get($allSchemas, "lightSkins.$lightSchema.selectors"));
        $darkCss = Utility::generateCss(Arr::get($allSchemas, "darkSkins.$darkSchema.selectors"), 'html.dark');

        $updateData['cached_css'] = $lightCss . ' ' . $darkCss;

        Utility::updateOption('portal_color_config', $updateData);

        return [
            'message' => __('Color configuration has been updated successfully', 'fluent-community-pro'),
        ];

    }

    public function saveAuthSettings(Request $request)
    {
        $settings = $request->get('settings', []);

        $formattedSettings = AuthenticationService::formatAuthSettings($settings);

        $formattedSettings = apply_filters('fluent_community/update_auth_settings', $formattedSettings);

        Utility::updateOption('auth_settings', $formattedSettings);

        Utility::setCache('auth_settings', $formattedSettings, WEEK_IN_SECONDS);

        $formattedSettings['login']['form']['fields'] = AuthHelper::getLoginFormFields();
        $formattedSettings['signup']['form']['fields'] = AuthHelper::getFormFields();

        return [
            'message'  => __('Auth settings have been updated successfully', 'fluent-community-pro'),
            'settings' => $formattedSettings
        ];
    }

    public function updateCourseLockscreenSettings(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);

        $settingFields = $request->get('lockscreen', []);

        $formattedFields = LockscreenService::formatLockscreenFields($settingFields, $course);

        $formattedFields = apply_filters('fluent_community/update_lockscreen_settings', $formattedFields, $course);

        $course->setLockscreen($formattedFields);

        return [
            'message' => __('Lockscreen settings have been updated successfully.', 'fluent-community-pro'),
            'course'  => $course
        ];
    }

    public function updateSpaceLockscreenSettings(Request $request, $spaceSlug)
    {
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $settingFields = $request->get('lockscreen', []);

        $formattedFields = LockscreenService::formatLockscreenFields($settingFields, $space);

        $formattedFields = apply_filters('fluent_community/update_lockscreen_settings', $formattedFields, $space);

        $space->setLockscreen($formattedFields);

        return [
            'message'   => 'Lockscreen settings have been updated successfully.',
            'community' => $space
        ];
    }

    public function saveCrmTaggingConfig(Request $request)
    {
        $rawSettings = $request->get('settings', []);
        $taggingMaps = array_filter(Arr::get($rawSettings, 'tagging_maps', []), function ($value) {
            return $value && is_numeric($value);
        });

        $linedMaps = array_filter(Arr::get($rawSettings, 'linked_maps', []));
        $linkedMaps = Arr::only($linedMaps, array_keys($taggingMaps));

        $settings = [
            'is_enabled'         => Arr::get($rawSettings, 'is_enabled') == 'yes' ? 'yes' : 'no',
            'tagging_maps'       => $taggingMaps,
            'linked_maps'        => $linkedMaps,
            'create_crm_contact' => Arr::get($rawSettings, 'create_crm_contact') == 'yes' ? 'yes' : 'no',
            'create_user'        => Arr::get($rawSettings, 'create_user') == 'yes' ? 'yes' : 'no',
            'send_welcome_email' => Arr::get($rawSettings, 'send_welcome_email') == 'yes' ? 'yes' : 'no',
        ];

        if (Arr::get($settings, 'is_enabled') != 'yes') {
            update_option('_fcom_crm_tagging', $settings, 'yes');
            $featureSettings = Utility::getFeaturesConfig();
            $featureSettings['has_crm_sync'] = 'no';
            Utility::updateOption('fluent_community_features', $featureSettings);
            return [
                'message'  => __('CRM Tagging settings have been updated successfully', 'fluent-community-pro'),
                'settings' => $settings
            ];
        }

        if ($taggingMaps) {
            $spaceCourseIds = array_keys($taggingMaps);
            $hasSpaceTagging = BaseSpace::whereIn('id', $spaceCourseIds)->withoutGlobalScopes()->exists();
            if (!$hasSpaceTagging) {
                $taggingMaps = [];
            }
        }
        if ($linkedMaps) {
            $spaceCourseIds = array_keys($linkedMaps);
            $hasSpaceSyncTagging = BaseSpace::whereIn('id', $spaceCourseIds)->withoutGlobalScopes()->exists();
            if (!$hasSpaceSyncTagging) {
                $linkedMaps = [];
            }
        }

        $settings['is_enabled'] = 'yes';
        $settings['tagging_maps'] = $taggingMaps;
        $settings['linked_maps'] = $linkedMaps;

        update_option('_fcom_crm_tagging', $settings, 'yes');

        $featureSettings = Utility::getFeaturesConfig();
        $featureSettings['has_crm_sync'] = (!empty($taggingMaps) || !empty($linkedMaps)) ? 'yes' : 'no';
        Utility::updateOption('fluent_community_features', $featureSettings);

        return [
            'message'  => __('CRM Tagging settings have been updated successfully', 'fluent-community-pro'),
            'settings' => $settings
        ];
    }

    public function getWebhooks(Request $request)
    {
        $webhooks = WebhookModel::query()->searchBy($request->getSafe('search', 'sanitize_text_field'))
            ->paginate();

        $data = [
            'webhooks' => $webhooks
        ];

        if ($request->get('page') == 1) {

            if (Helper::isFeatureEnabled('course_module')) {
                $courses = Course::query()->select(['id', 'title', 'slug', 'logo', 'settings'])->orderBy('title', 'ASC')->get();
                $data['courses'] = $courses;
            }

            $spaces = Space::query()->select(['id', 'title', 'slug', 'logo', 'settings'])->orderBy('title', 'ASC')->get();
            $data['spaces'] = $spaces;
        }

        return $data;
    }

    public function saveWebhook(Request $request)
    {
        $this->validate($request->all(), [
            'title' => 'required'
        ]);

        $data = [
            'title'                 => sanitize_text_field($request->get('title')),
            'course_ids'            => (array)$request->get('course_ids', []),
            'space_ids'             => (array)$request->get('space_ids', []),
            'remove_course_ids'     => (array)$request->get('remove_course_ids', []),
            'remove_space_ids'      => (array)$request->get('remove_space_ids', []),
            'send_wp_welcome_email' => $request->get('send_wp_welcome_email') == 'yes' ? 'yes' : 'no',
        ];

        if (Helper::isFeatureEnabled('course_module')) {
            if ($data['course_ids']) {
                $data['course_ids'] = Course::whereIn('id', $data['course_ids'])->get()->pluck('id')->toArray();
            }

            if ($data['remove_course_ids']) {
                $data['remove_course_ids'] = Course::whereIn('id', $data['remove_course_ids'])->get()->pluck('id')->toArray();
            }

        } else {
            $data['course_ids'] = [];
            $data['remove_course_ids'] = [];
        }

        if ($data['space_ids']) {
            $data['space_ids'] = Space::whereIn('id', $data['space_ids'])->get()->pluck('id')->toArray();
        }

        if ($data['remove_space_ids']) {
            $data['remove_space_ids'] = Space::whereIn('id', $data['remove_space_ids'])->get()->pluck('id')->toArray();
        }

        $id = $request->getSafe('id', 'intval');

        if ($id) {
            $webhook = WebhookModel::find($id);
            $runningCount = Arr::get($webhook->value, 'running_count', 0);
            $data['running_count'] = $runningCount;

            $webhook->value = $data;
            $webhook->save();
        } else {
            $webhook = WebhookModel::create([
                'value' => $data
            ]);
        }

        return [
            'message' => __('Webhook has been saved successfully', 'fluent-community-pro'),
            'webhook' => $webhook
        ];
    }

    public function deleteWebhook(Request $request)
    {
        $id = $request->getSafe('id', 'intval');

        if (!$id) {
            return $this->sendError('Webhook not found');
        }

        $webhook = WebhookModel::findOrFail($id);

        $webhook->delete();

        return [
            'message' => __('Webhook has been deleted successfully', 'fluent-community-pro')
        ];
    }

    public function getMessagingSettings(Request $request)
    {

        if (!method_exists(ChatHelper::class, 'getMessagingConfig')) {
            return $this->sendError([
                'message' => __('Please update Fluent Messaging plugin to latest version', 'fluent-community-pro')
            ]);
        }

        return [
            'settings' => ChatHelper::getMessagingConfig()
        ];
    }

    public function updateMessagingSettings(Request $request)
    {
        $prevSettings = ChatHelper::getMessagingConfig();

        $settings = $request->get('settings', []);
        $settings = Arr::only($settings, array_keys($prevSettings));

        $settings = wp_parse_args($settings, $prevSettings);

        ChatHelper::updateMessagingConfig($settings);

        return [
            'message'  => __('Messaging settings have been updated successfully', 'fluent-community-pro'),
            'settings' => $settings
        ];
    }

    public function saveSidebarLink(Request $request)
    {
        $link = $request->get('link', []);

        $existingLink = null;
        if (!empty($link['id'])) {
            $existingLink = SidebarLink::query()->withoutGlobalScopes()
                ->where('type', 'sidebar_link')
                ->findOrFail($link['id']);
        }

        $this->validate($link, [
            'title'                   => 'required',
            'parent_id'               => 'required',
            'privacy'                 => 'required|in:public,logged_in,members_only,logged_out_only',
            'settings.permalink'      => 'required|url',
            'settings.membership_ids' => 'required_if:privacy,members_only|array',
        ], [
            'title.required'                      => __('Title is required', 'fluent-community-pro'),
            'parent_id.required'                  => __('Please select Space Group', 'fluent-community-pro'),
            'privacy.required'                    => __('Please select privacy', 'fluent-community-pro'),
            'settings.permalink.required'         => __('Permalink is required', 'fluent-community-pro'),
            'settings.permalink.url'              => __('Please provide a valid URL', 'fluent-community-pro'),
            'settings.membership_ids.required_if' => __('Please select at least one membership for members only link', 'fluent-community-pro'),
        ]);

        $fromattedData = [
            'title'     => sanitize_text_field($link['title']),
            'status'    => 'published',
            'parent_id' => (int)$link['parent_id'],
            'slug'      => $existingLink->slug ?? 'custom_' . (Utility::slugify($link['title'], 'custom_link') . '_' . time()),
            'privacy'   => $link['privacy'],
            'settings'  => Arr::only($link['settings'], [
                'permalink',
                'new_tab',
                'emoji',
                'shape_svg',
                'membership_ids',
            ]),
        ];

        $settings = $fromattedData['settings'];

        $spaceGroup = SpaceGroup::findOrFail($fromattedData['parent_id']);
        $serial = BaseSpace::query()->withoutGlobalScopes()->where('parent_id', $spaceGroup->id)->max('serial') + 1;

        if ($fromattedData['privacy'] == 'members_only') {
            $membershipIds = (array)Arr::get($fromattedData, 'settings.membership_ids', []);
            $spaces = BaseSpace::query()->withoutGlobalScopes()
                ->whereIn('id', $membershipIds)
                ->get();

            $settings['membership_ids'] = array_map('sanitize_text_field', $spaces->pluck('id')->toArray());

            if (empty($settings['membership_ids'])) {
                return $this->sendError([
                    'message' => __('Please select at least one membership for members only link', 'fluent-community-pro')
                ]);
            }
        } else {
            $settings['membership_ids'] = [];
        }

        $fromattedData['serial'] = $serial;

        $settings['shape_svg'] = CustomSanitizer::sanitizeSvg(Arr::get($settings, 'shape_svg', ''));
        if (empty($settings['shape_svg'])) {
            $settings['emoji'] = CustomSanitizer::sanitizeEmoji(Arr::get($settings, 'emoji', ''));
        } else {
            $settings['emoji'] = '';
        }

        $settings['permalink'] = sanitize_url($settings['permalink']);

        $settings['new_tab'] = Arr::get($settings, 'new_tab') === 'yes' ? 'yes' : 'no';

        $fromattedData['settings'] = $settings;

        if ($existingLink) {
            $existingLink->update($fromattedData);
        } else {
            $existingLink = SidebarLink::create($fromattedData);
        }

        $imageTypes = ['cover_photo', 'logo'];
        $metaData = [];
        foreach ($imageTypes as $type) {
            if (!empty($link[$type])) {
                $media = Helper::getMediaFromUrl($link[$type]);
                if (!$media || $media->is_active) {
                    continue;
                }
                $metaData[$type] = $media->public_url;
                $media->update([
                    'is_active'     => true,
                    'user_id'       => get_current_user_id(),
                    'sub_object_id' => $existingLink->id,
                    'object_source' => 'space_' . $type
                ]);
            }
        }

        if ($metaData) {
            $existingLink->updateCustomData($metaData, false);
        }

        return [
            'link'    => $existingLink,
            'message' => __('The link has been saved successfully', 'fluent-community-pro')
        ];
    }

    public function deleteSidebarLink(Request $request, $id)
    {
        $link = SidebarLink::query()->findOrFail($id);

        do_action('fluent_community/sidebar_link/before_delete', $link);

        $link->delete();

        do_action('fluent_community/sidebar_link/after_delete', $link);

        return [
            'message' => __('The link has been deleted successfully', 'fluent-community-pro')
        ];
    }


    public function getFollowersSettings(Request $request)
    {
        return [
            'settings' => FollowerHelper::getSettings()
        ];
    }

    public function saveFollowersSettings(Request $request)
    {
        $settings = $request->get('settings', []);

        return [
            'message'  => __('Followers settings have been updated successfully', 'fluent-community-pro'),
            'settings' => FollowerHelper::updateSettings($settings)
        ];
    }
}
