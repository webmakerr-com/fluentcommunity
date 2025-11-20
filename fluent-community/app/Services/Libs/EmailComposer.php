<?php

namespace FluentCommunity\App\Services\Libs;

use FluentCommunity\App\App;
use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;

class EmailComposer
{
    private $blocks = [];

    private $headingBlocks = [];

    private $footerBlocks = [];

    private $logo = '';

    public function addBlock($type, $content, $options = [])
    {
        if ($content) {
            $this->blocks[] = [
                'type'    => $type,
                'content' => $content,
                'options' => $options
            ];
        }

        return $this;
    }

    public function addHeadingBlock($type, $content, $options = [])
    {
        $this->headingBlocks[] = [
            'type'    => $type,
            'content' => $content,
            'options' => $options
        ];

        return $this;
    }

    private function compileBody()
    {
        $html = '';

        foreach ($this->blocks as $block) {
            $html .= $this->compileBlock($block);
        }

        return $html;
    }

    private function complileHeadingBlocks()
    {
        $html = '';

        foreach ($this->headingBlocks as $block) {
            $html .= $this->compileBlock($block);
        }

        return $html;
    }

    public function compileBlock($block)
    {
        if ($block['type'] == 'paragraph') {
            return '<p style="font-family: Arial, sans-serif; line-height: 1.6; font-size: 16px; font-weight: normal; margin: 0; margin-bottom: 10px;">' . $block['content'] . '</p>';
        }

        if ($block['type'] == 'heading') {
            return '<h2 style="font-family: Arial, sans-serif; font-size: 20px; font-weight: bold; margin: 0; margin-bottom: 10px;">' . $block['content'] . '</h2>';
        }

        if ($block['type'] == 'button') {
            return (string)App::make('view')->make('email.Default._button', [
                'link'    => Arr::get($block['options'], 'link'),
                'btnText' => $block['content']
            ]);
        }

        if ($block['type'] == 'boxed_content') {
            return (string)App::make('view')->make('email.Default._user_box_content', [
                'user_name'    => $block['options']['user']->display_name,
                'user_avatar'  => !(empty($block['options']['user']->xprofile->avatar)) ? $block['options']['user']->xprofile->avatar : $block['options']['user']->photo,
                'content'      => $block['content'],
                'permalink'    => $block['options']['permalink'],
                'post_content' => $block['options']['post_content']
            ]);
        }

        if ($block['type'] == 'post_boxed_content') {
            return (string)App::make('view')->make('email.Default._user_post_content', [
                'user_name'   => $block['options']['user']->display_name,
                'user_avatar' => !(empty($block['options']['user']->xprofile->avatar)) ? $block['options']['user']->xprofile->avatar : $block['options']['user']->photo,
                'content'     => $block['content'],
                'title'       => Arr::get($block['options'], 'title'),
                'permalink'   => $block['options']['permalink'],
                'space_name'  => $block['options']['space_name']
            ]);
        }

        if ($block['type'] == 'html_content') {
            return (string)$block['content'];
        }

        return $block['content'];
    }

    public function setLogo($logo)
    {
        if ($logo) {
            $this->logo = $logo;
        }

        return $this;
    }

    public function setDefaultLogo()
    {
        $emailSettings = Utility::getEmailNotificationSettings();
        if (!empty($emailSettings['logo'])) {
            $this->logo = $emailSettings['logo'];
        }

        return $this;
    }

    public function addFooterLine($type, $line)
    {
        $this->footerBlocks[] = [
            'type'    => $type,
            'content' => $line
        ];

        return $this;
    }

    public function setDefaultFooter($withPlaceHolder = true)
    {
        $settings = Utility::getEmailNotificationSettings();
        $footerTextHtml = Arr::get($settings, 'email_footer_rendered', '');

        if (!$footerTextHtml) {
            if ($settings['disable_powered_by'] == 'no') {
                $utmParams = ['utm_campaign' => 'email', 'utm_source'   => 'footer', 'utm_medium'   => 'email'];
                $poweredBy = '<a href="' . Utility::getProductUrl(true, $utmParams) . '" target="_blank" style="font-size: 12px; margin: 15px 0; display: block; text-decoration: none;color: #9a9ea6;">Powered by FluentCommunity</a>';
                $this->addFooterLine('paragraph', $poweredBy);
            }
            return $this;
        }

        $generalSettings = Helper::generalSettings();

        $replaces = [
            '{{site_name_with_url}}' => '<a target="_blank" href="' . Helper::baseUrl('/') . '">' . Arr::get($generalSettings, 'site_title') . '</a>',
            '{{site_name}}'          => Arr::get($generalSettings, 'site_title')
        ];

        $footerTextHtml = str_replace(array_keys($replaces), array_values($replaces), $footerTextHtml);

        // find pattern like this: {{manage_email_notification_url|Manage Your Email Notifications Preference}}
        $footerTextHtml = preg_replace_callback('/{{(.*?)\|(.*?)}}/', function ($matches) use ($withPlaceHolder) {
            $match1 = $matches[1];
            if ($match1 != 'manage_email_notification_url') {
                return $matches[0];
            }

            if ($withPlaceHolder) {
                $url = '##email_notification_url##';
            } else {
                $url = Helper::baseUrl('fcom_route?route=user_notification_settings&auth=yes');
            }

            $text = $matches[2];
            return '<a target="_blank" rel="noopenner" href="' . $url . '">' . $text . '</a>';
        }, $footerTextHtml);

        $this->addFooterLine('paragraph', $footerTextHtml);

        if ($settings['disable_powered_by'] == 'no') {
            $utmParams = ['utm_campaign' => 'email', 'utm_source'   => 'footer', 'utm_medium'   => 'email'];
            $poweredBy = '<p style="margin-top: 10px;" class="powered_by"><a href="' . Utility::getProductUrl(true, $utmParams) . '" target="_blank" style="font-size: 12px; margin: 15px 0; text-decoration: none;color: #9a9ea6; display: block;">Powered by FluentCommunity</a></p>';
            $this->addFooterLine('paragraph', $poweredBy);
        }

        return $this;
    }

    public function getHtml()
    {
        $generalSettings = Helper::generalSettings();

        $data = [
            'logo'        => [],
            'bodyContent' => $this->compileBody()
        ];

        if ($this->logo) {
            $data['logo'] = [
                'url' => $this->logo,
                'alt' => Arr::get($generalSettings, 'site_title')
            ];
        }

        $footerLines = [];
        foreach ($this->footerBlocks as $footerBlock) {
            $footerLines[] = $footerBlock['content'];
        }

        $data['footerLines'] = $footerLines;

        if ($this->headingBlocks) {
            $data['headingContent'] = $this->complileHeadingBlocks();
        }

        return (string)App::make('view')->make('email.template', $data);
    }

}
