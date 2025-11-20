<?php

namespace FluentCommunityPro\App\Modules\DocumentLibrary\Http;

use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Media;
use FluentCommunity\App\Models\Space;

use FluentCommunity\App\Http\Controllers\Controller;
use FluentCommunity\App\Services\Libs\FileSystem;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Course\Model\CourseLesson;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $space = Space::findOrFail($request->get('space_id'));
        if (!$space->verifyUserPermisson($this->getUser(false), 'can_view_documents', false)) {
            return $this->sendError([
                'message'           => __('You do not have permission to view documents in this space.', 'fluent-community-pro'),
                'permission_failed' => true
            ]);
        }

        $documents = Feed::where('space_id', $space->id)
            ->select(Feed::$publicColumns)
            ->where('content_type', 'document')
            ->with([
                    'xprofile' => function ($q) {
                        $q->select(ProfileHelper::getXProfilePublicFields());
                    },
                    'terms'    => function ($q) {
                        $q->select(['title', 'slug'])
                            ->where('taxonomy_name', 'post_topic');
                    }
                ]
            )
            ->searchBy($request->get('search'), (array)$request->get('search_in', ['post_content']))
            ->orderBy('created_at', 'DESC')
            ->paginate();

        return [
            'documents' => $documents
        ];
    }

    public function upload(Request $request)
    {
        if ($lessonId = $request->get('lesson_id')) {
            return $this->handleLessonDocumentUpload($request, $lessonId);
        }

        $space = Space::findOrFail($request->get('space_id'));
        $space->verifyUserPermisson($this->getUser(true), 'can_upload_documents');

        $mediaData = $this->processSpaceFiles($request->files());

        if (is_wp_error($mediaData)) {
            return $this->sendError([
                'message' => $mediaData->get_error_message(),
                'errors'  => $mediaData->get_error_data()
            ]);
        }

        if (!$mediaData) {
            return $this->sendError([
                'message' => 'Error while uploading the media'
            ]);
        }

        unset($mediaData['acl']);

        // Let's create the media now
        $media = Media::create($mediaData);

        return [
            'file' => $media->getPrivateFileMeta()
        ];
    }

    public function handleLessonDocumentUpload(Request $request, $lessonId)
    {
        $user = $this->getUser(true);
        $lesson = CourseLesson::findOrFail($lessonId);
        $course = $lesson->course;

        if (!$course->isCourseAdmin($user)) {
            return $this->sendError([
                'message' => __('You do not have permission to upload documents to this lesson.', 'fluent-community-pro')
            ]);
        }

        $mediaData = $this->processSpaceFiles($request->files());

        if (is_wp_error($mediaData)) {
            return $this->sendError([
                'message' => $mediaData->get_error_message(),
                'errors'  => $mediaData->get_error_data()
            ]);
        }

        if(!is_array($mediaData)) {
            return $mediaData;
        }

        if (!$mediaData) {
            return $this->sendError([
                'message' => 'Error while uploading the media'
            ]);
        }

        unset($mediaData['acl']);
        $mediaData['object_source'] = 'lesson_document';

        $mediaData['is_active'] = 1;
        $mediaData['feed_id'] = $lesson->id;
        $media = Media::create($mediaData);


        $lessonMeta = $lesson->meta;
        $docs = Media::where('feed_id', $lesson->id)
            ->where('object_source', 'lesson_document')
            ->get();

        $mediaIds = [];
        foreach ($docs as $doc) {
            $mediaIds[] = $doc->getPrivateFileMeta();
        }
        $lessonMeta['document_lists'] = $mediaIds;
        $lesson->meta = $lessonMeta;
        $lesson->save();

        return [
            'file' => $media->getPrivateFileMeta()
        ];
    }

    public function updateDocument(Request $request)
    {
        $newMediaTitle = sanitize_text_field($request->get('title'));
        if (!$newMediaTitle) {
            return $this->sendError([
                'message' => __('Document title is required', 'fluent-community-pro')
            ]);
        }

        $docuemntId = $request->get('id');
        [$media, $editingSource] = $this->resolveDcoumentSource($docuemntId);

        if (is_wp_error($editingSource)) {
            return $this->sendError([
                'message' => $editingSource->get_error_message()
            ]);
        }

        $mediaSettings = $media->settings;
        $mediaSettings['title'] = $newMediaTitle;
        $media->settings = $mediaSettings;
        $media->save();

        if ($editingSource) {
            $mediaKey = $media->media_key;
            $sourceItemMeta = $editingSource->meta;
            $mediaIds = $sourceItemMeta['document_lists'] ?? [];
            $mediaIds = array_map(function ($mediaId) use ($mediaKey, $newMediaTitle) {
                if ($mediaId['media_key'] == $mediaKey) {
                    $mediaId['title'] = $newMediaTitle;
                }
                return $mediaId;
            }, $mediaIds);

            $sourceItemMeta['document_lists'] = array_values($mediaIds);
            $editingSource->meta = $sourceItemMeta;
            $editingSource->save();
        }

        return [
            'message' => __('Document updated successfully', 'fluent-community-pro'),
            'file'    => [
                'id'        => $media->id,
                'url'       => $media->getPrivateDownloadUrl(),
                'media_key' => $media->media_key,
                'title'     => $newMediaTitle,
                'type'      => $media->media_type
            ]
        ];
    }

    public function deleteDocument(Request $request)
    {
        $docuemntId = $request->get('id');
        [$media, $editingSource] = $this->resolveDcoumentSource($docuemntId);

        if (is_wp_error($editingSource)) {
            return $this->sendError([
                'message' => $editingSource->get_error_message()
            ]);
        }

        do_action('fluent_community/feed/media_deleted', $media);

        if ($editingSource) {
            $mediaKey = $media->media_key;
            $sourceItemMeta = $editingSource->meta;
            $mediaIds = $sourceItemMeta['document_lists'] ?? [];
            $mediaIds = array_filter($mediaIds, function ($mediaId) use ($mediaKey) {
                return $mediaId['media_key'] != $mediaKey;
            });
            $sourceItemMeta['document_lists'] = array_values($mediaIds);
            $editingSource->meta = $sourceItemMeta;
            $editingSource->save();
        }

        return [
            'message' => __('Selected document has been deleted', 'fluent-community-pro')
        ];
    }

    private function processSpaceFiles($files)
    {
        add_filter('fluent_community/upload_folder_name', function ($path) {
            return $path . '/space_documents';
        });

        $uploadedFiles = FileSystem::put($files);
        $file = $uploadedFiles[0];

        if (is_wp_error($file)) {
            return $this->sendError([
                'message' => $file->get_error_message()
            ], 422);
        }

        $fileSettings = Arr::get($file, 'meta', []);
        $fileSettings['original_name'] = $file['original_name'];

        $upload_dir = wp_upload_dir();
        $file['path'] = $upload_dir['basedir'] . '/fluent-community/space_documents/' . $file['file'];

        $mediaData = [
            'media_type'    => $file['type'],
            'driver'        => 'local',
            'media_path'    => $file['path'],
            'media_url'     => $file['url'],
            'settings'      => $fileSettings,
            'object_source' => 'space_document',
            'acl'           => 'private'
        ];

        $mediaData = apply_filters('fluent_community/media_upload_data', $mediaData, $file);

        return $mediaData;
    }

    private function resolveDcoumentSource($docuemntId)
    {
        $user = $this->getUser(true);
        $media = Media::findOrFail($docuemntId);

        if (!in_array($media->object_source, ['lesson_document', 'space_document'])) {
            return [
                $media,
                new \WP_Error('invalid_source', __('Invalid document source', 'fluent-community-pro'))
            ];
        }

        $editingSource = null;
        if ($media->object_source == 'lesson_document') {
            $editingSource = CourseLesson::findOrFail($media->feed_id);
            if (!$editingSource->course->isCourseAdmin($user)) {
                return [
                    $media,
                    new \WP_Error('permission_failed', __('You do not have permission to edit this document', 'fluent-community-pro'))
                ];
            }
        } else if ($media->feed_id) {
            $editingSource = Feed::find($media->feed_id);
            if ($editingSource) {
                if (!$editingSource->space || !$editingSource->space->verifyUserPermisson($user, 'can_upload_documents', false)) {
                    return [
                        $media,
                        new \WP_Error('permission_failed', __('You do not have permission to edit this document', 'fluent-community-pro'))
                    ];
                }
            }
        }

        if ($media->object_source == 'space_document' && $media->is_active && !$editingSource) {
            return [
                $media,
                new \WP_Error('permission_failed', __('You do not have permission to edit this document', 'fluent-community-pro'))
            ];
        }

        return [$media, $editingSource];
    }
}
