<?php

namespace FluentCommunity\App\Services\Libs;

use FluentCommunity\App\App;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Notification;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\CustomSanitizer;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Support\Arr;

class DailyDigest
{
    private $user;

    private $unreadNotifications = null;

    public $lastSent = null;

    public $displayCount = 7;

    private $fromDate = null;

    private $toDate = null;

    public function __construct(User $user, $days = 7)
    {
        $this->user = $user;
        $this->lastSent = $user->getUserMeta('_fcom_last_digest_sent');

        $this->fromDate = current_time('mysql', strtotime('-' . $days . ' days'));
        $this->toDate = current_time('mysql');
    }

    public function send()
    {
        if (!$this->willSend()) {
            return false;
        }

        update_user_meta($this->user->ID, '_fcom_last_digest_sent', current_time('mysql'));
        $emailBody = $this->getEmailBody();
        if (!$emailBody) {
            return false;
        }

        $eamilSubject = $this->getEmailSubject();
        $mailer = new Mailer('', $eamilSubject, $emailBody);
        $mailer->to($this->user->user_email, $this->user->display_name);
        $mailer->send();

        return true;
    }

    public function getEmailBody()
    {
        $settings = Helper::generalSettings();
        $emailComposer = new \FluentCommunity\App\Services\Libs\EmailComposer();

        $headingHtml = \sprintf(
            /* translators: %s is replaced by the title of the site */
            '<h2 style="font-family: Arial, sans-serif; font-size: 20px; font-weight: bold; margin: 0; margin-bottom: 5px;">' . __('This week on %s', 'fluent-community') . '</h2>',
            Arr::get($settings, 'site_title')
        );

        /* translators: %1$s is replaced by the start date of the week, %2$s is replaced by the end date of the week */
        $headingHtml .= '<p style="font-family: Arial, sans-serif; font-size: 12px; font-weight: normal; margin: 0; margin-bottom: 16px;">' . sprintf(__('%1$s - %2$s', 'fluent-community'), date_i18n('F d', strtotime('-7 days')), date_i18n('F d, Y')) . '</p>';

        $emailComposer->addBlock('html_content', $headingHtml);
        $emailComposer->addBlock('paragraph', __('Here are some of the most popular posts and notifications you may have missed from the last week 🔥.', 'fluent-community'));

        $popularPostsHtml = $this->getPoppularPostsHtml();

        // Let's add popular posts here
        $emailComposer->addBlock('html_content', $popularPostsHtml);

        $notificationsHtml = $this->getNotificationsHtml();
        if (!$notificationsHtml && !$popularPostsHtml) {
            return '';
        }

        if ($notificationsHtml) {
            $emailComposer->addBlock('html_content', $notificationsHtml);
            if ($this->displayCount < count($this->unreadNotifications)) {
                $emailComposer->addBlock('button', __('View all the notifications', 'fluent-community'), [
                    'link' => ProfileHelper::signUserUrlWithAuthHash(Helper::baseUrl('notifications/'), $this->user->ID)
                ]);
            }
        }

        $emailComposer->setDefaultLogo();

        $emailComposer->setDefaultFooter();

        $emailBody = $emailComposer->getHtml();

        $emailBody = str_replace([
            '##email_notification_url##'
        ], [
            ProfileHelper::getSignedNotificationPrefUrl($this->user->ID)
        ], $emailBody);

        $hooksSections = apply_filters('fluent_community/digest_notification/email_sections', [
            'before_content' => '',
            'after_content'  => ''
        ], $this->user);

        if(!empty($hooksSections['before_content'])) {
            $emailBody = str_replace('<!--email_content_before-->', $hooksSections['before_content'], $emailBody);
        }

        if(!empty($hooksSections['after_content'])) {
            $emailBody = str_replace('<!--email_content_after-->', $hooksSections['after_content'], $emailBody);
        }

        return apply_filters('fluent_community/digest_email_body', $emailBody, $this->user);
    }

    public function willSend($hours = 20)
    {
        if (!$this->lastSent) {
            return true;
        }

        return (current_time('timestamp') - strtotime($this->lastSent)) > ($hours * 3600);
    }

    public function getEmailSubject()
    {
        $settings = Helper::generalSettings();

        $emailSubject = \sprintf(
            /* translators: %1$s is replaced by the name of the user, %2$s is replaced by the title of the site */
            __('Hey %1$s, here\'s your weekly digest from %2$s', 'fluent-community'),
            $this->user->display_name,
            Arr::get($settings, 'site_title')
        );

        $notificationCount = count($this->getUnreadNotifications());

        if ($notificationCount > 0) {
            /* translators: %d is replaced by the number of unread notifications for digest email subject */
            $emailSubject .= ' ' . \sprintf(__('(🔔%d)', 'fluent-community'), $notificationCount);
        }

        return apply_filters('fluent_community/digest_email_subject', $emailSubject, $this->user, $notificationCount);
    }

    public function getUnreadNotifications()
    {
        if ($this->unreadNotifications) {
            return $this->unreadNotifications;
        }

        $this->unreadNotifications = Notification::whereHas('subscribers', function ($query) {
            return $query->where('user_id', $this->user->ID)
                  ->where('is_read', 0)
                ->when($this->lastSent, function ($q) {
                    $q->where('created_at', '>=', $this->lastSent);
                });
        })
            ->limit(10)
            ->with(['subscriber', 'xprofile'])
            ->orderBy('updated_at', 'DESC')
            ->get();

        return $this->unreadNotifications;
    }

    public function getNotificationsHtml()
    {
        $notifications = $this->getUnreadNotifications();

        if ($notifications->isEmpty()) {
            return '';
        }

        $notifications = $notifications->take($this->displayCount);

        $totalCount = count($notifications);

        ob_start();
        ?>
        <h4 style="font-family: Arial, sans-serif; font-size: 18px; font-weight: bold; margin: 20px 0 5px; margin-bottom: 5px;""><?php esc_html_e('Unread Notifications', 'fluent-community'); ?></h4>
        <table style="background: #f8f8f8;border-radius: 5px;margin: 10px 0 20px;border: 1px solid #dedede;"
               width="100%"
               cellspacing="0" cellpadding="0" border="0">
            <?php
            foreach ($notifications as $index => $notification):
                $permalink = ProfileHelper::signUserUrlWithAuthHash(Helper::getUrlByJsRoute($notification->route), $this->user->ID);
                $isLast = $totalCount == ($index + 1);
                ?>
                <tr>
                    <td valign="top">
                        <table class="fcom_each_item"
                               style="padding: 10px;<?php echo $isLast ? '' : 'border-bottom: 1px solid #dedede;'; ?>"
                               cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td valign="top" style="border-radius: 50%; padding: 4px; vertical-align: top; height: 32px; width: 32px;">
                                    <a href="<?php echo esc_url($permalink); ?>">
                                        <img alt="<?php echo esc_html($notification->xprofile ? $notification->xprofile->display_name : ''); ?>"
                                            src="<?php echo esc_url($notification->xprofile->avatar); ?>" height="32" width="32"
                                            style="border-radius: 50%; height: 32px; width: 32px; display: block;">
                                    </a>
                                </td>
                                <td style="font-family: Arial, sans-serif; font-size: 16px;color: #3c434a; padding-left: 5px; vertical-align: middle;">
                                    <a style="color: #3c434a; text-decoration: none;" target="_blank"
                                       href="<?php echo esc_url($permalink); ?>">
                                        <?php echo wp_kses_post($notification->content); ?>
                                    </a>
                                    <p style="font-family: Arial, sans-serif; font-size: 12px; font-weight: normal; margin: 0; margin-top: 5px;">
                                        <?php /* translators: %s is replaced by the time ago */ ?>
                                        <?php echo esc_html( sprintf(__('%s ago', 'fluent-community'), esc_html(human_time_diff(strtotime($notification->created_at), current_time('timestamp'))))); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
        return ob_get_clean();
    }

    public function getPoppularPostsHtml($count = 5, $days = 7)
    {
        $trendingPosts = Feed::byUserAccess($this->user->ID)
            ->where('status', 'published')
            ->with(['xprofile' => function ($q) {
                $q->select(ProfileHelper::getXProfilePublicFields());
            }, 'space'])
            ->whereHas('xprofile', function ($q) {
                $q->where('status', 'active');
            })
            ->where('created_at', '>=', gmdate('Y-m-d H:i:s', strtotime('-' . $days . ' days')))
            ->orderByRaw('(reactions_count + (comments_count * 2)) DESC')
            ->limit($count)
            ->get();

        if ($trendingPosts->isEmpty()) {
            return '';
        }

        $html = '<h4 style="font-family: Arial, sans-serif; border-bottom: 2px solid #e7e7e5; font-size: 18px; font-weight: bold; padding-bottom: 10px; margin: 20px 0 10px;">' . __('Trending Posts', 'fluent-community') . '</h4>';

        $blockStart = '<table style="background-color: #f7f7f7; margin: 10px 0 5px; padding: 15px 15px 0; border-radius: 5px;" bgcolor="#f7f7f7" width="100%" style="margin-top: 0px;" cellspacing="0" cellpadding="0" border="0"><tr><td>';

        $blockEnd = '</td></tr></table>';

        foreach ($trendingPosts as $post) {
            $permalink = ProfileHelper::signUserUrlWithAuthHash($post->getPermalink(), $this->user->ID);

            $html .= $blockStart;
            $html .= (string)App::make('view')->make('email.Default._user_post_content', [
                'user_name'      => $post->xprofile->display_name,
                'user_avatar'    => $post->xprofile->avatar,
                'content'        => Helper::getHumanExcerpt(CustomSanitizer::unslashMarkdown($post->message), 200),
                'title'          => $post->title,
                'permalink'      => $permalink,
                'space_name'     => $post->space ? $post->space->title : '',
                'hide_publish'   => 'yes',
                'show_read_more' => 'yes',
                'timestamp'      => human_time_diff(strtotime($post->created_at), current_time('timestamp'))
            ]);

            $html .= $blockEnd;
        }


        return $html;

    }
}
