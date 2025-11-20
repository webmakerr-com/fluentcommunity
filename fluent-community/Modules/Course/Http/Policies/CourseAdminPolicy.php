<?php

namespace FluentCommunity\Modules\Course\Http\Policies;

use FluentCommunity\App\Http\Policies\BasePolicy;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunity\App\Services\Helper;

class CourseAdminPolicy extends BasePolicy
{
    /**
     * Check user permission for any method
     * @param \FluentCommunity\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        $user = Helper::getCurrentUser(true);

        if (!$user) {
            return false;
        }

        return $user->hasCourseCreatorAccess();
    }

    public function findCourse(Request $request)
    {
        return $this->canManageCourse($request);
    }

    public function updateCourse(Request $request)
    {
        return $this->canManageCourse($request);
    }

    public function deleteCourse(Request $request)
    {
        return $this->canManageCourse($request);
    }

    public function getOtherUsers(Request $request)
    {
        return $this->canManageCourse($request);
    }

    public function addStudent(Request $request)
    {
        return $this->canManageCourse($request);
    }

    public function removeStudent(Request $request)
    {
        return $this->canManageCourse($request);
    }

    public function updateLockscreenSettings(Request $request)
    {
        return $this->canManageCourse($request);
    }

    public function updateLinks(Request $request)
    {
        return $this->canManageCourse($request);
    }

    protected function canManageCourse(Request $request)
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        $user = Helper::getCurrentUser(true);

        if ($courseId = $request->get('course_id')) {
            $course = Course::find($courseId);
            if (!$course) {
                return false;
            }

            return $course->isCourseAdmin($user);
        }

        return $user->hasSpaceManageAccess();
    }
}
