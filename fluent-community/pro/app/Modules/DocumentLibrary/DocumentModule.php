<?php

namespace FluentCommunityPro\App\Modules\DocumentLibrary;


use FluentCommunity\App\Hooks\Handlers\PortalHandler;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Media;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Course\Model\CourseLesson;
use FluentCommunity\Modules\Course\Services\CourseHelper;

class DocumentModule
{
    public function register($app)
    {
        add_filter('fluent_community/space_header_links', [$this, 'addToSpaceMenu'], 1, 2);
        add_filter('fluent_community/feed/new_feed_data_type_document', [$this, 'validateAndSaveDocuments'], 1, 2);

        add_filter('fluent_community/feed/update_feed_data_type_document', [$this, 'validateAndUpdateDocuments'], 1, 3);

        // We are just removing the old content types
        add_action('fluent_community/feed/updating_content_type_old_document', function ($feed) {
            $documents = Media::where('feed_id', $feed->id)
                ->where('is_active', 1)
                ->where('object_source', 'space_document')
                ->get();

            do_action('fluent_community/feed/media_deleted', $documents);
        });

        /*
        * register the routes
        */
        $app->router->group(function ($router) {
            $router->prefix('documents')->namespace('\FluentCommunityPro\App\Modules\DocumentLibrary\Http')->withPolicy(\FluentCommunity\App\Http\Policies\PortalPolicy::class)->group(function ($router) {
                $router->get('/', 'DocumentController@index');
                $router->post('/upload', 'DocumentController@upload');
                $router->post('/update', 'DocumentController@updateDocument');
                $router->post('/delete', 'DocumentController@deleteDocument');
            });
        });

        add_action('fluent_community/portal_action_download_document', [$this, 'downloadDocument']);
    }

    public function addToSpaceMenu($links, $space)
    {
        if (!Arr::get($space->permissions, 'can_view_documents')) {
            return $links;
        }

        $links[] = [
            'title' => apply_filters('fluent_community/space_document_title_label', __('Documents', 'fluent-community-pro'), $space),
            'route' => [
                'name' => 'space_documents',
            ]
        ];

        return $links;
    }

    public function validateAndSaveDocuments($data, $resquestData)
    {
        $docuemntArr = Arr::get($resquestData, 'document_ids', []);

        if (!$docuemntArr) {
            return new \WP_Error('no_documents', __('No documents found to save. Please try again', 'fluent-community-pro'));
        }

        $mediaIds = [];
        foreach ($docuemntArr as $document) {
            $mediaIds[] = Arr::get($document, 'media_key');
        }

        $docuemntIds = array_filter($mediaIds);

        if (!$docuemntIds) {
            return new \WP_Error('no_documents', __('No documents found to save. Please try again', 'fluent-community-pro'));
        }


        $documents = Media::whereIn('media_key', $docuemntIds)
            ->where('is_active', 0)
            ->where('user_id', $data['user_id'])
            ->get();

        if ($documents->isEmpty()) {
            return new \WP_Error('invalid_documents', __('Invalid documents found to save. Please try again', 'fluent-community-pro'));
        }

        $data['content_type'] = 'document';
        if (Arr::get($resquestData, 'status') == 'unlisted') {
            $data['status'] = 'unlisted';
        } else {
            $data['status'] = 'published';
        }

        if(empty($data['meta'])) {
            $data['meta'] = [];
        }

        add_action('fluent_community/feed/just_created_type_document', function ($feed) use ($docuemntIds) {
            $documents = Media::whereIn('media_key', $docuemntIds)
                ->where('is_active', 0)
                ->where('user_id', $feed->user_id)
                ->get();

            $metaItems = [];
            foreach ($documents as $document) {
                $document->is_active = 1;
                $document->feed_id = $feed->id;
                $document->save();
                $metaItems[] = $document->getPrivateFileMeta();
            }

            if ($metaItems) {
                $existingMeta = $feed->meta;
                $existingMeta['document_lists'] = $metaItems;
                $feed->meta = $existingMeta;
                $feed->save();
            }
        }, 10, 2);

        return $data;
    }

    public function validateAndUpdateDocuments($data, $resquestData, Feed $feed)
    {
        $docuemntArr = Arr::get($resquestData, 'document_ids', []);

        $mediaIds = [];
        foreach ($docuemntArr as $document) {
            $mediaIds[] = Arr::get($document, 'media_key');
        }

        $docuemntIds = array_filter($mediaIds);

        if (!$docuemntIds) {
            // remove the existing documents for this post
            $documents = Media::where('feed_id', $feed->id)
                ->where('is_active', 1)
                ->where('object_source', 'space_document')
                ->get();

            do_action('fluent_community/feed/media_deleted', $documents);

            if (empty($data['content_type']) || $data['content_type'] == 'document') {
                $data['content_type'] = 'text';
            }

            return $data;
        }

        $deletedDocuments = Media::where('feed_id', $feed->id)
            ->where('is_active', 1)
            ->where('object_source', 'space_document')
            ->whereNotIn('media_key', $docuemntIds)
            ->get();

        if (!$deletedDocuments->isEmpty()) {
            do_action('fluent_community/feed/media_deleted', $deletedDocuments);
        }

        $newDocuments = Media::whereIn('media_key', $docuemntIds)
            ->where('is_active', 0)
            ->where('object_source', 'space_document')
            ->where('user_id', get_current_user_id())
            ->get();

        if (!$newDocuments->isEmpty()) {
            foreach ($newDocuments as $newDocument) {
                $newDocument->is_active = 1;
                $newDocument->feed_id = $feed->id;
                $newDocument->save();
            }
        }

        $allFeedDocuments = Media::where('feed_id', $feed->id)
            ->where('is_active', 1)
            ->where('object_source', 'space_document')
            ->get();

        $documentItems = [];
        foreach ($allFeedDocuments as $feedDocument) {
            $documentItems[] = $feedDocument->getPrivateFileMeta();
        }

        $oldMeta = $feed->meta;
        if ($documentItems) {
            $oldMeta['document_lists'] = $documentItems;
            $data['meta'] = $oldMeta;
            $data['content_type'] = 'document';
        } else {
            $data['content_type'] = 'text';
            unset($oldMeta['document_lists']);
            $data['meta'] = $oldMeta;
        }

        return $data;
    }

    public function downloadDocument($data)
    {
        $fileKey = Arr::get($data, 'media_key');
        $mediaId = Arr::get($data, 'media_id');

        $document = Media::where('media_key', $fileKey)
            ->where('id', $mediaId)
            ->first();

        if (!$document) {
            (new PortalHandler())->viewErrorPage(
                __('Document not found', 'fluent-community-pro'),
                __('The document you are trying to download is not found. Please try again', 'fluent-community-pro')
            );
        }

        $canViewDocument = $this->canAccessDocument($document);

        if (!$canViewDocument) {
            $message = __('You do not have permission to view this document', 'fluent-community-pro');
            $userId = get_current_user_id();
            if (!$userId) {
                $authUrl = Helper::getAuthUrl();
                $currentUrl = home_url(add_query_arg($_REQUEST, $GLOBALS['wp']->request));
                $authUrl = add_query_arg('redirect_to', urlencode($currentUrl), $authUrl);

                $message = __('Please login to view this document.', 'fluent-community-pro');
                $message .= ' <a href="' . $authUrl . '">' . __('Please login.', 'fluent-community-pro') . '</a>';
            }

            (new PortalHandler())->viewErrorPage(
                __('Permission Denied', 'fluent-community-pro'),
                $message,
                !!$userId
            );
        }

        $fileName = Arr::get($document->settings, 'original_name', '');
        if ($fileName) {
            $fileName = basename($fileName);
        } else {
            $fileName = basename($document->media_url);
        }

        $mediaType = $document->media_type;

        $forceDownload = Arr::get($data, 'force_download', '');

        if ($document->driver == 'local') {
            $filePath = $document->media_path;
            
            if (!file_exists($filePath)) {
                (new PortalHandler())->viewErrorPage(
                    __('Document not found', 'fluent-community-pro'),
                    __('The document you are trying to download is not found. Please try again', 'fluent-community-pro')
                );
            }

            do_action('fluent_community/document/local_file_access', $document, $forceDownload);

            if (!$forceDownload) {
                // if media type is pdf then open in browser
                if ($mediaType == 'application/pdf') {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="' . $fileName . '"');
                    header('Content-Transfer-Encoding: binary');
                    header('Accept-Ranges: bytes');
                    @readfile($filePath);
                    exit;
                }

                // if media type is image then open in browser
                if (strpos($mediaType, 'image') !== false) {
                    header('Content-Type: ' . $mediaType);
                    header('Content-Disposition: inline; filename="' . $fileName . '"');
                    header('Content-Transfer-Encoding: binary');
                    header('Accept-Ranges: bytes');
                    @readfile($filePath);
                    exit;
                }
            }

            // Download the file
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            @readfile($filePath);
            exit;
        }

        $remoteUrl = $document->getSignedPublicUrl(900); // for 15 minutes

        wp_redirect($remoteUrl);
        exit();
    }

    private function canAccessDocument($document)
    {
        $userId = get_current_user_id();

        $user = User::find($userId);

        if ($document->object_source == 'space_document') {
            $feed = $document->feed;
            if (!$feed) {
                return false;
            }
            $space = $feed->space;
            // check has permission
            return !$space || $space->verifyUserPermisson($user, 'can_view_documents', false);
        }

        if ($document->object_source != 'lesson_document') {
            return false;
        }

        $lesson = CourseLesson::find($document->feed_id);

        if (!$lesson) {
            return false;
        }

        $course = $lesson->course;
        if ($user && $course->isCourseAdmin($user) || $lesson->is_free_preview) {
            return true;
        }

        return CourseHelper::isEnrolled($course->id, $userId);
    }
}
