<?php

namespace FluentCommunity\App\Services;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Media;
use FluentCommunity\App\Models\Reaction;
use FluentCommunity\App\Models\Term;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Framework\Validator\Validator;

class FeedsHelper
{
    static protected $currentRelatedUserIds = [];

    public static function setCurrentRelatedUserId($userId)
    {
        self::$currentRelatedUserIds[] = $userId;
    }

    public static function getCurrentRelatedUserIds()
    {
        return array_values(array_unique(self::$currentRelatedUserIds));
    }

    public static function getSpaceSlugsByUserId($userId)
    {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return [];
        }

        $user = User::find($userId);

        return $user->spaces()->pluck('slug')->toArray();
    }

    public static function getLastFeedId()
    {
        $lastItem = Feed::where('status', 'published')
            ->byUserAccess(get_current_user_id())
            ->orderBy('id', 'DESC')
            ->first();

        if ($lastItem) {
            return $lastItem->id;
        }

        return 1;
    }

    public static function mdToHtml($text, $options = [])
    {
        if (!$text) {
            return '';
        }

        $text = str_replace('&#x20;', '', $text); // hide markdown empty content

        $html = (new \FluentCommunity\App\Services\Parsedown([
        ]))
            ->setBreaksEnabled(true)
            ->setUrlsLinked(false)
            //  ->setSafeMode(true)
            ->text($text);

        if (!Arr::get($options, 'disable_link_process')) {
            // add nofollow to all links. But check if nofollow is already there
            $html = self::addNoFollowToLinks($html);
        }

        $html = wp_kses($html, array(
            'p'          => array(),
            'br'         => array(),
            'strong'     => array(),
            'em'         => array(),
            'hr'         => array(),
            'h1'         => array(),
            'h2'         => array(),
            'h3'         => array(),
            'h4'         => array(),
            'h5'         => array(),
            'h6'         => array(),
            'ul'         => array(),
            'b'          => array(),
            'ol'         => array(),
            'li'         => array(),
            'span'       => array(),
            'a'          => array(
                'href'    => true,
                'title'   => true,
                'rel'     => true,
                'target'  => true,
            ),
            'img'        => array(
                'src' => true,
                'alt' => true,
            ),
            'code'       => array(),
            'pre'        => array(),
            'blockquote' => array(),
        ));

        return self::maybeTransformDynamicCodes($html);
    }

    public static function maybeTransformDynamicCodes($html)
    {
        // check if there has {{
        if (strpos($html, '{{') === false) {
            return $html;
        }

        return preg_replace_callback(
            '/{{utc:(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})}}/',
            function ($match) {
                // Extract the datetime string (e.g., 2025-06-01 15:06:59)
                $datetimeStr = $match[1];

                try {
                    // Create a DateTime object from the UTC string
                    $date = new \DateTime($datetimeStr, new \DateTimeZone('UTC'));
                    // Get the Unix timestamp for the data-timestamp attribute
                    $timestamp = $date->getTimestamp();
                    // Format the display string
                    $displayFormat = $date->format('d F Y, H:i') . ' (UTC)';

                    // Return the formatted HTML
                    return '<span class="fcom_dynamic_prop" data-type="timestamp" data-timestamp="' . $timestamp . '">' . $displayFormat . '</span>';
                } catch (\Exception $e) {
                    // Return original match if parsing fails
                    return $match[0];
                }
            },
            $html
        );
    }

    public static function addNoFollowToLinks($html)
    {
        if (!$html) {
            return '';
        }

        $current_domain = wp_parse_url(home_url(), PHP_URL_HOST);

        // Regular expression to match <a> tags
        $pattern = '/<a\s[^>]*href=("|\')(https?:\/\/(?!' . preg_quote($current_domain, '/') . ').*?)("|\')\s?([^>]*)>/i';

        // Callback function to modify each matched <a> tag
        $callback = function ($matches) {
            $url = $matches[2];
            $attr = $matches[4];

            // Remove existing rel attribute if present
            $attr = preg_replace('/\srel=("|\').*?("|\')/i', '', $attr);

            // Add nofollow
            return '<a href="' . $url . '" rel="nofollow" ' . trim($attr) . '>';
        };

        // Perform the replacement
        return preg_replace_callback($pattern, $callback, $html);
    }

    public static function addNewTabToLinks($html)
    {
        if (empty($html) || !is_string($html)) {
            return '';
        }

        // return is there has no href
        if (strpos($html, 'href=') === false) {
            return $html;
        }

        // More comprehensive regex to capture existing attributes
        $pattern = '/<a\s+([^>]*)>/i';

        // Callback function to modify each matched <a> tag
        $callback = function ($matches) {
            $full_tag = $matches[0];
            $attributes = $matches[1];

            // Extract href
            preg_match('/href=("|\')([^"\']+)("|\')/', $full_tag, $href_matches);
            if (empty($href_matches)) {
                return $full_tag;
            }
            $url = $href_matches[2];

            // Check if it's an external URL and not an image
            if (preg_match('/^https?:\/\//i', $url) && !preg_match('/\.(jpg|jpeg|png|gif|svg)$/i', $url)) {
                // Check if target already exists
                if (!preg_match('/\btarget=/i', $full_tag)) {
                    // Preserve existing attributes, add target="_blank"
                    return '<a ' . $attributes . ' target="_blank" rel="noopener noreferrer">';
                }
            }

            // Return original tag if no modification needed
            return $full_tag;
        };

        // Perform the replacement
        return preg_replace_callback($pattern, $callback, $html);
    }

    public static function findFirstUrl($html)
    {
        // use regular expression to find the first URL in a href tag
        // do not take the url which contains /u/ in it
        $pattern = '/<a\s+(?:[^>]*?\s+)?href=([\'"])(?!.*\/u\/)(.*?)\1/';
        preg_match($pattern, $html, $matches);

        if (isset($matches[2])) {
            return $matches[2];
        }

        return '';
    }

    public static function extractHashTags($text, $limit = 5)
    {
        // Extract hashtag including - and _
        preg_match_all('/#([a-zA-Z0-9_-]+)/', $text, $matches);

        $tags = array_unique($matches[1]);

        if (!$tags) {
            return [];
        }

        $tags = array_slice($tags, 0, $limit);

        $lowerCaseTags = array_map('strtolower', $tags);

        $terms = Term::whereIn('slug', $lowerCaseTags)
            ->where('taxonomy_name', 'hashtag')
            ->get();

        $termIds = [];

        foreach ($terms as $term) {
            $termIds[$term->slug] = $term->id;
        }

        if (count($termIds) == count($tags)) {
            return array_values($termIds);
        }

        $excepts = array_diff($tags, array_keys($termIds));

        foreach ($excepts as $except) {
            $term = Term::create([
                'taxonomy_name' => 'hashtag',
                'slug'          => strtolower($except),
                'title'         => $except
            ]);

            $termIds[$term->slug] = $term->id;
        }

        return array_values($termIds);
    }

    public static function getMentions($text, $spaceId = null, $withUsers = false)
    {
        // the mention may have . or _ or - in the username
        preg_match_all('/@([a-zA-Z0-9_.-]+)/', $text, $matches);
        $mentions = array_unique($matches[1]);

        if (!$mentions) {
            return null;
        }

        if ($spaceId) {
            $xProfiles = XProfile::whereIn('username', $mentions)
                ->whereHas('spaces', function ($query) use ($spaceId) {
                    $query->where('space_id', $spaceId);
                })
                ->get();
        } else {
            $xProfiles = XProfile::whereIn('username', $mentions)
                ->get();
        }

        if ($xProfiles->isEmpty()) {
            return null;
        }

        $userMentions = [];

        $userIds = [];

        foreach ($xProfiles as $xProfile) {
            $userIds[] = $xProfile->user_id;
            $html = '<a data-user_name="' . $xProfile->username . '" class="fcom_mention fcom_route" href="' . Helper::baseUrl('u/' . $xProfile->username . '/') . '">' . $xProfile->display_name . '</a>';
            $userMentions['@' . $xProfile->username] = $html;
        }

        $data = [
            'user_ids' => $userIds,
            'text'     => strtr($text, $userMentions)
        ];

        if ($withUsers) {
            $data['users'] = User::whereIn('ID', $userIds)->get();
        }

        return $data;
    }

    public static function getLikedIdsByUserFeedId($feedId, $userId)
    {
        return Reaction::select('object_id')
            ->where('object_type', 'comment')
            ->where('parent_id', $feedId)
            ->where('user_id', $userId)
            ->get()
            ->pluck('object_id')
            ->toArray();
    }

    public static function castSurveyVote($newVoteIndexes, Feed $feed, $userId)
    {
        $surveyConfig = Arr::get($feed->meta, 'survey_config', []);

        $slugs = array_map(function ($item) {
            return $item['slug'];
        }, $surveyConfig['options']);

        $newVoteIndexes = array_filter(array_intersect($slugs, $newVoteIndexes));

        $previousVotes = Reaction::where('type', 'survey_vote')
            ->where('user_id', $userId)
            ->where('object_id', $feed->id)
            ->get();

        $removedIndexes = [];
        $alreadyIndexes = [];

        foreach ($previousVotes as $previousVote) {
            if (!in_array($previousVote->object_type, $newVoteIndexes)) {
                // This vote need to be deleted
                $removedIndexes[] = $previousVote->object_type;
                $previousVote->delete();
            } else {
                $alreadyIndexes[] = $previousVote->object_type;
            }
        }

        $newSyncIndexes = array_diff($newVoteIndexes, $alreadyIndexes);

        foreach ($newSyncIndexes as $newSyncIndex) {
            Reaction::create([
                'user_id'     => $userId,
                'object_id'   => $feed->id,
                'type'        => 'survey_vote',
                'object_type' => $newSyncIndex
            ]);
        }

        if (!empty($newSyncIndexes)) {
            do_action('fluent_community/feed/cast_survey_vote', $newSyncIndexes, $feed, $userId);
        }

        foreach ($surveyConfig['options'] as $index => $option) {
            $slug = $option['slug'];

            if (in_array($slug, $removedIndexes)) {
                $newCount = (int)Arr::get($option, 'vote_counts', 0) - 1;
                $option['vote_counts'] = $newCount > 0 ? $newCount : 0;
            } else if (in_array($slug, $newSyncIndexes)) {
                $newCount = (int)Arr::get($option, 'vote_counts', 0) + 1;
                $option['vote_counts'] = $newCount > 0 ? $newCount : 0;
            }

            $surveyConfig['options'][$index] = $option;
        }

        $surveyConfig = apply_filters('fluent_community/feed/updated_survey_config', $surveyConfig, $feed, $userId);

        $meta = $feed->meta;
        $meta['survey_config'] = $surveyConfig;
        $feed->meta = $meta;
        $feed->save();

        Utility::forgetCache('survey_cast_' . $feed->id . '_' . $userId);

        return $feed;
    }

    /**
     * Create a new feed programmatically
     * @param array $allData
     * @return \FluentCommunity\App\Models\Feed|\WP_Error
     **/
    public static function createFeed($allData)
    {
        if (!is_array($allData)) {
            return new \WP_Error('invalid_data', 'Invalid data. The data need to be array', ['status' => 400]);
        }

        $acceptedKeys = ['message', 'title', 'user_id', 'space_id', 'type'];
        $feedData = Arr::only($allData, $acceptedKeys);

        // Let's validate the data
        $validation = Validator::make($feedData, [
            'message'  => 'required',
            'title'    => 'nullable|string',
            'user_id'  => 'required|integer|exists:users,ID',
            'space_id' => 'nullable|integer|exists:fcom_spaces,id'
        ]);

        if ($validation->fails()) {
            return new \WP_Error('validation_failed', 'Validation failed', $validation->errors());
        }

        $sanitizedData = self::sanitizeAndValidateData($feedData);

        $feedData = wp_parse_args($sanitizedData, $feedData);

        $user = User::find($feedData['user_id']);

        if (!$user) {
            return new \WP_Error('user_not_found', 'User not found', ['status' => 400]);
        }
        $user->syncXProfile();
        if ($user->xprofile->status != 'active') {
            return new \WP_Error('user_inactive', 'User status is not active', $validation->errors());
        }

        $markdown = $feedData['message'];
        $mentions = null;

        // Extra Validaton for space_id
        if (!empty($feedData['space_id'])) {
            if (!Helper::isUserInSpace($feedData['user_id'], $feedData['space_id'])) {
                return new \WP_Error('invalid_space', 'User is not in the space', ['status' => 400]);
            }
            $mentions = FeedsHelper::getMentions($markdown, Arr::get($feedData, 'space_id'), true);
            if ($mentions) {
                $markdown = $mentions['text'];
            }
        } else if (!Helper::hasGlobalPost()) {
            return new \WP_Error('global_post_disabled', 'User is not allowed to post in global', ['status' => 400]);
        }

        $feedData['message_rendered'] = wp_kses_post(self::mdToHtml($markdown));
        $feedData['status'] = 'published';

        if (Arr::get($allData, 'meta.media_preview.provider') == 'inline') {
            $allData['meta']['media_preview']['provider'] = 'giphy';
        }

        [$feedData, $mediaItems] = self::processFeedMetaData($feedData, $allData);

        if ($mentions) {
            $feedData['meta']['mentioned_user_ids'] = Arr::get($mentions, 'user_ids', []);
        }

        $data = apply_filters('fluent_community/feed/new_feed_data', $feedData, $allData);
        $feed = new Feed();
        $feed->fill($data);
        $feed->save();

        if ($mentions) {
            do_action('fluent_community/feed_mentioned', $feed, $mentions['users']);
        }

        if ($mediaItems) {
            foreach ($mediaItems as $media) {
                $media->feed_id = $feed->id;
                $media->is_active = 1;
                $media->object_source = 'feed';
                $media->save();
            }
        }

        do_action('fluent_community/feed/created', $feed);

        if ($feed->space_id) {
            do_action('fluent_community/space_feed/created', $feed);
        }

        return $feed;
    }

    public static function sanitizeAndValidateData($data)
    {
        $message = CustomSanitizer::unslashMarkdown(trim(Arr::get($data, 'message')));

        $processedData = [
            'message' => $message,
            'type'    => 'text'
        ];

        $survey = Arr::get($data, 'survey', []);

        if ($survey) {
            $options = Arr::get($survey, 'options', []);
            $formattedOptions = [];
            foreach ($options as $index => $option) {
                if (empty($option['label'])) {
                    continue;
                }

                $formattedOptions[] = [
                    'label' => sanitize_text_field($option['label']),
                    'slug'  => 'opt_' . ($index + 1)
                ];
            }

            $endDate = Arr::get($survey, 'end_date', '');
            if ($endDate) {
                $endDate = gmdate('Y-m-d H:i:s', strtotime($endDate));
            } else {
                $endDate = '';
            }

            if ($formattedOptions) {
                $processedData['survey'] = [
                    'type'     => Arr::get($survey, 'type') == 'single_choice' ? 'single_choice' : 'multi_choice',
                    'options'  => $formattedOptions,
                    'end_date' => $endDate
                ];
            }
        }

        $maxlen = apply_filters('fluent_community/max_post_length', 15000);
        if (\strlen($message) > $maxlen) {
            throw new \Exception(esc_html__('Post message is too long', 'fluent-community'));
        }

        $titlePref = Utility::postTitlePref();

        if ($titlePref) {
            $processedData['title'] = sanitize_text_field(Arr::get($data, 'title'));
            if ($titlePref == 'required' && empty($processedData['title'])) {
                throw new \Exception(esc_html__('Title is required. Please provide a title', 'fluent-community'));
            }
            // trim the title if it's too long to 150 char
            if (\strlen($processedData['title']) > 192) {
                $processedData['title'] = substr($processedData['title'], 0, 192);
            }
        }

        return $processedData;
    }

    public static function transformForEdit($feed)
    {
        $topicsConfig = Helper::getTopicsConfig();

        $terms = $feed->terms;
        $feed->topic_ids = $terms->where('taxonomy_name', 'post_topic')->pluck('id')->toArray();
        if ($topicsConfig['max_topics_per_post'] == 1) {
            if ($feed->topic_ids) {
                $feed->topic_ids = Arr::first($feed->topic_ids);
            } else {
                $feed->topic_ids = '';
            }
        }

        if (Arr::get($feed->meta, 'send_announcement_email') == 'yes') {
            $feed->send_announcement_email = 'yes';
        }

        if ($feed->content_type == 'document') {
            $documents = Media::where('object_source', 'space_document')
                ->where('feed_id', $feed->id)
                ->where('is_active', 1)
                ->get();
            $mediaIds = [];
            foreach ($documents as $document) {
                $mediaIds[] = $document->getPrivateFileMeta();
            }
            $feed->document_ids = $mediaIds;
            $feed->load('space');
            return $feed;
        }

        $surveyConfig = Arr::get($feed->meta, 'survey_config', []);

        if ($surveyConfig) {
            $feed->survey = [
                'type'     => Arr::get($surveyConfig, 'type'),
                'options'  => Arr::get($surveyConfig, 'options', []),
                'end_date' => Arr::get($surveyConfig, 'end_date', '')
            ];
        }

        $mediaImages = Arr::get($feed->meta, 'media_items', []);
        $meta = $feed->meta;
        unset($feed->meta);

        if ($mediaImages) {
            $feed->media_images = $mediaImages;
        } else if ($mediaPreview = Arr::get($meta, 'media_preview')) {
            $type = Arr::get($mediaPreview, 'type');
            if ($type == 'oembed' || $type == 'iframe_html') {
                $feed->media = $mediaPreview;
            }

            $feedMedias = Media::where('object_source', 'feed')
                ->where('feed_id', $feed->id)
                ->where('is_active', 1)
                ->get();

            if (!$feedMedias->isEmpty()) {
                $mediaItems = [];
                foreach ($feedMedias as $media) {
                    $mediaItems[] = [
                        'url'      => $media->public_url,
                        'type'     => 'image',
                        'media_id' => $media->id,
                        'width'    => Arr::get($media->settings, 'width'),
                        'height'   => Arr::get($media->settings, 'height'),
                        'provider' => Arr::get($media->settings, 'provider', 'uploader')
                    ];
                }
                $feed->media_images = $mediaItems;
            } else if ($type != 'meta_data') {
                $feed->meta = $meta;
            }
        }

        $feed->load('space');
        return $feed;
    }

    public static function processFeedMetaData($data, $requestData, $existingFeed = null)
    {
        if (empty($data['meta'])) {
            $data['meta'] = [];
        }

        $uplaodedDocs = [];
        // Handle Survey
        if (!empty($data['survey'])) {
            $surveyConfig = $data['survey'];
            if ($existingFeed) {
                $surveyConfig = Arr::get($existingFeed->meta, 'survey_config', []);
                if ($surveyConfig) {
                    $oldOptions = Arr::get($surveyConfig, 'options', []);
                    $newOptions = Arr::get($data['survey'], 'options', []);
                    $oldKeyedOptions = [];
                    foreach ($oldOptions as $option) {
                        $oldKeyedOptions[$option['slug']] = $option;
                    }
                    foreach ($newOptions as $index => $option) {
                        $slug = Arr::get($option, 'slug', '');
                        if (isset($oldKeyedOptions[$slug])) {
                            $newOptions[$index]['vote_counts'] = Arr::get($oldKeyedOptions[$slug], 'vote_counts', 0);
                        }
                    }
                    $surveyConfig['options'] = $newOptions;
                } else {
                    $surveyConfig = $data['survey'];
                }
            }

            if ($endDate = Arr::get($data['survey'], 'end_date', '')) {
                $surveyConfig['end_date'] = gmdate('Y-m-d H:i:s', strtotime($endDate));
            } else {
                $surveyConfig['end_date'] = '';
            }

            $data['meta']['survey_config'] = $surveyConfig;
            $data['content_type'] = 'survey';
            unset($data['survey']);
        }

        // Handle Giphy
        if (Arr::get($requestData, 'meta.media_preview.provider') == 'giphy') {
            $url = Arr::get($requestData, 'meta.media_preview.image');
            if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                return [$data, $uplaodedDocs];
            }

            $data['meta']['media_preview'] = array_filter([
                'image'    => sanitize_url($url),
                'type'     => sanitize_text_field(Arr::get($requestData, 'meta.media_preview.type', 'image')),
                'provider' => 'giphy',
                'height'   => (int)Arr::get($requestData, 'meta.media_preview.height', 0),
                'width'    => (int)Arr::get($requestData, 'meta.media_preview.width', 0),
            ]);

            return [$data, $uplaodedDocs];
        }

        // Handling Video Embed
        if (Arr::get($requestData, 'media') && (Arr::get($requestData, 'media.type') == 'oembed' || Arr::get($requestData, 'media.type') == 'iframe_html')) {
            if (Arr::get($requestData, 'media.type') == 'iframe_html') {
                $data['meta']['media_preview'] = array_filter(Arr::get($requestData, 'media', []));
                return [$data, $uplaodedDocs];
            }

            $media = Arr::get($requestData, 'media');
            $url = Arr::get($media, 'url');
            $metaData = RemoteUrlParser::parse($url);
            if ($metaData && !is_wp_error($metaData) && (!empty($metaData['image']) || !empty($metaData['html']))) {
                $data['meta']['media_preview'] = $metaData;
            }

            return [$data, $uplaodedDocs];
        }

        // Let's handle the uploaded media
        if ($mediaImages = Arr::get($requestData, 'media_images', [])) {
            $uploadedImages = Helper::getMediaByProvider($mediaImages);
            if (!$existingFeed) {
                $uploadedMediaItems = Helper::getMediaItemsFromUrl($uploadedImages);
            } else {
                $uploadedMediaItems = [];
                foreach ($mediaImages as $mediaImage) {
                    $url = sanitize_url(Arr::get($mediaImage, 'url', ''));
                    if (!$url) {
                        continue;
                    }
                    $mediaItem = Helper::getMediaFromUrl($mediaImage);
                    if ($mediaItem) {
                        $uploadedMediaItems[] = $mediaItem;
                    } else {
                        // maybe this is a previously uploaded image
                        $media = Media::where('media_url', $url)
                            ->where('object_source', 'feed')
                            ->where('feed_id', $existingFeed->id)
                            ->where('is_active', 1)
                            ->first();

                        if ($media) {
                            $uploadedMediaItems[] = $media;
                        }
                    }
                }
            }

            if (count($uploadedMediaItems) == 1) {
                $singleMedia = $uploadedMediaItems[0];
                $data['meta']['media_preview'] = [
                    'is_uploaded' => true,
                    'image'       => $singleMedia->public_url,
                    'type'        => 'meta_data',
                    'provider'    => 'uploader',
                    'width'       => Arr::get($singleMedia->settings, 'width'),
                    'height'      => Arr::get($singleMedia->settings, 'height'),
                    'media_id'    => $singleMedia->id,
                ];
            } else if ($uploadedMediaItems) {
                $mediaPreviews = [];
                foreach ($uploadedMediaItems as $mediaItem) {
                    $mediaData = [
                        'media_id' => $mediaItem->id,
                        'url'      => $mediaItem->public_url,
                        'type'     => 'image',
                        'width'    => Arr::get($mediaItem->settings, 'width'),
                        'height'   => Arr::get($mediaItem->settings, 'height'),
                        'provider' => Arr::get($mediaItem->settings, 'provider', 'uploader')
                    ];
                    $mediaPreviews[] = array_filter($mediaData);
                }
                $data['meta']['media_items'] = $mediaPreviews;
            }

            return [$data, $uploadedMediaItems];
        }

        // Let's handle the fallback here
        $firstUrl = FeedsHelper::findFirstUrl(Arr::get($data, 'message_rendered'));

        // check if this is another post or not
        if (strpos($firstUrl, Helper::baseUrl()) === 0) {
            // this is an internal URL
            if (Helper::getRouteNameByRequestPath($firstUrl) === 'feed_view') {
                $uriParts = explode('/', $firstUrl);
                if (count($uriParts) >= 2) {
                    $postSlug = end($uriParts);
                    $feed = Feed::where('slug', $postSlug)->first();
                    if ($feed) {
                        $firstUrl = null;
                        $data['meta']['custom_app_preview'] = [
                            'app_name' => 'child_post',
                            'feed_id'  => $feed->id
                        ];
                    }
                }
            }
        }

        if ($firstUrl) {
            $metaData = RemoteUrlParser::parse($firstUrl);
            if ($metaData && !is_wp_error($metaData) && (!empty($metaData['image']) || !empty($metaData['html']))) {
                $data['meta']['media_preview'] = $metaData;
            }
        }

        return [$data, $uplaodedDocs];
    }

    protected static function tranformFeedData(Feed $feed, $config = [])
    {
        $userId = Arr::get($config, 'user_id', 0);

        if ($userId) {
            $interactions = Arr::get($config, 'interactions', []);

            if ($interactions) {
                $feed->has_user_react = Arr::get($interactions, 'like', false);
                $feed->bookmarked = Arr::get($interactions, 'bookmark', false);
            }

            $commentLikeIds = Arr::get($config, 'comment_like_ids', []);

            $feed->comments->each(function ($comment) use ($commentLikeIds) {
                self::setCurrentRelatedUserId($comment->user_id);
                if ($commentLikeIds && in_array($comment->id, $commentLikeIds)) {
                    $comment->liked = 1;
                }
            });

            if ($feed->content_type == 'survey') {
                $votedOptions = $feed->getSurveyCastsByUserId($userId);
                if ($votedOptions) {
                    $surveyConfig = Arr::get($feed->meta, 'survey_config', []);
                    foreach ($surveyConfig['options'] as $index => $option) {
                        if (in_array($option['slug'], $votedOptions)) {
                            $surveyConfig['options'][$index]['voted'] = true;
                        }
                    }
                    $meta = $feed->meta;
                    $meta['survey_config'] = $surveyConfig;
                    $feed->meta = $meta;
                }
            }
        }

        if ($feed->content_type == 'document') {
            $feedMeta = $feed->meta;
            $documentLists = Arr::get($feedMeta, 'document_lists', []);
            foreach ($documentLists as $index => $document) {
                $documentLists[$index]['url'] = Helper::baseUrl('?fcom_action=download_document&media_key=' . $document['media_key'] . '&media_id=' . $document['id']);
            }
            $feedMeta['document_lists'] = $documentLists;
            $feed->meta = $feedMeta;
        }

        self::setCurrentRelatedUserId($feed->user_id);

        return apply_filters('fluent_community/rendering_feed_model', $feed, $config);
    }

    public static function transformFeed(Feed $feed)
    {
        $userId = get_current_user_id();

        $config = apply_filters('fluent_community/feed_general_config', [
            'user_id'          => $userId,
            'interactions'     => [],
            'comment_like_ids' => [],
            'is_collection'    => false
        ], $feed, $userId);

        if ($userId) {
            $config['interactions'] = [
                'like'             => $feed->hasUserReact($userId, 'like'),
                'bookmark'         => $feed->hasUserReact($userId, 'bookmark'),
            ];
            $config['comment_like_ids'] = self::getLikedIdsByUserFeedId($feed->id, $userId);
        }

        return self::tranformFeedData($feed, $config);
    }

    public static function transformFeedsCollection($feeds)
    {
        if ($feeds->isEmpty()) {
            return $feeds;
        }

        $userId = get_current_user_id();
        $commentLikeIds = [];
        $formattedInteractions = [];
        $feedIds = $feeds->pluck('id')->toArray();

        if ($userId) {
            $interactions = Reaction::query()
                ->select(['user_id', 'type', 'object_id'])
                ->whereIn('object_id', $feedIds)
                ->where('object_type', 'feed')
                ->where('user_id', $userId)
                ->whereIn('type', ['like', 'bookmark'])
                ->get();

            $formattedInteractions = [];
            foreach ($interactions as $interaction) {
                $objectId = (int)$interaction->object_id;

                if (!isset($formattedInteractions[$objectId])) {
                    $formattedInteractions[$objectId] = [];
                }
                $formattedInteractions[$objectId][$interaction->type] = true;
            }

            $commentLikeIds = Reaction::select('object_id')
                ->where('object_type', 'comment')
                ->whereIn('parent_id', $feedIds)
                ->where('user_id', $userId)
                ->get()
                ->pluck('object_id')
                ->toArray();
        }

        $generalConfig = apply_filters('fluent_community/feed_general_config', [
            'user_id'          => $userId,
            'interactions'     => [],
            'comment_like_ids' => $commentLikeIds,
            'is_collection'    => true
        ], $feeds, $feedIds);

        $feeds->each(function ($feed) use ($userId, $generalConfig, $formattedInteractions) {
            $config = $generalConfig;
            if ($userId) {
                $config['interactions'] = Arr::get($formattedInteractions, $feed->id, []);
            }
            return self::tranformFeedData($feed, $config);
        });

        return $feeds;
    }

    public static function getMediaHtml($meta, $postPermalink)
    {
        $mediaImage = Arr::get($meta, 'media_preview.image');
        $mediaCount = 0;
        if (!$mediaImage) {
            $mediaItems = Arr::get($meta, 'media_items', []);
            if ($mediaItems) {
                $mediaImage = Arr::get($mediaItems[0], 'url');
                $mediaCount = count($mediaItems);
            }
        }

        $feedHtml = '';

        if ($mediaImage) {
            $feedHtml .= '<div class="fcom_media" style="margin-top: 20px;">';
            $feedHtml .= '<a href="' . $postPermalink . '"><img src="' . $mediaImage . '" style="max-width: 100%; height: auto; display: block; margin: 0 auto 0px;" /></a>';
            if ($mediaCount > 1) {
                /* translators: %d is the number of additional images not shown in the preview. */
                $feedHtml .= '<p style="text-align: center; font-size: 14px; color: #666; margin-top: 10px;">' . sprintf(_n('+%d more image', '+%d more images', $mediaCount - 1, 'fluent-community'), $mediaCount - 1) . '</p>';
            }
            $feedHtml .= '</div>';
        }

        return $feedHtml;
    }

    public static function hasEveryoneTag($message)
    {
        // Updated regular expression to match @everyone with more flexibility
        $pattern = '/(?<=^|\W)@everyone(?=\W|\z)/iu';

        return preg_match($pattern, $message) === 1;
    }
}
