<?php

namespace FluentCommunity\App\Services;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Meta;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\XProfile;

class ProfileHelper
{

    public static function getProfile($userId = null)
    {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        return XProfile::where('user_id', $userId)->first();
    }

    public static function getXProfilePublicFields()
    {
        if (Utility::getPrivacySetting('show_last_activity') === 'yes' || Helper::isModerator()) {
            return ['user_id', 'total_points', 'is_verified', 'status', 'display_name', 'username', 'avatar', 'created_at', 'short_description', 'meta', 'last_activity'];
        }

        return ['user_id', 'total_points', 'is_verified', 'status', 'display_name', 'username', 'avatar', 'created_at', 'short_description', 'meta'];
    }

    public static function socialLinkProviders($enabledOnly = false)
    {
        $links = apply_filters('fluent_community/social_link_providers', [
            'instagram' => [
                'title'       => __('Instagram', 'fluent-community'),
                'icon_svg'    => '<svg viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 0C7.6302 0 7.8336 0.00599997 8.4732 0.036C9.1122 0.066 9.5472 0.1662 9.93 0.315C10.326 0.4674 10.6596 0.6738 10.9932 1.0068C11.2983 1.30674 11.5344 1.66955 11.685 2.07C11.8332 2.4522 11.934 2.8878 11.964 3.5268C11.9922 4.1664 12 4.3698 12 6C12 7.6302 11.994 7.8336 11.964 8.4732C11.934 9.1122 11.8332 9.5472 11.685 9.93C11.5348 10.3307 11.2987 10.6936 10.9932 10.9932C10.6932 11.2982 10.3304 11.5343 9.93 11.685C9.5478 11.8332 9.1122 11.934 8.4732 11.964C7.8336 11.9922 7.6302 12 6 12C4.3698 12 4.1664 11.994 3.5268 11.964C2.8878 11.934 2.4528 11.8332 2.07 11.685C1.6694 11.5347 1.30652 11.2986 1.0068 10.9932C0.701644 10.6933 0.465559 10.3305 0.315 9.93C0.1662 9.5478 0.066 9.1122 0.036 8.4732C0.00779997 7.8336 0 7.6302 0 6C0 4.3698 0.00599997 4.1664 0.036 3.5268C0.066 2.8872 0.1662 2.4528 0.315 2.07C0.465142 1.66931 0.701282 1.30639 1.0068 1.0068C1.3066 0.701539 1.66946 0.465438 2.07 0.315C2.4528 0.1662 2.8872 0.066 3.5268 0.036C4.1664 0.00779997 4.3698 0 6 0ZM6 3C5.20435 3 4.44129 3.31607 3.87868 3.87868C3.31607 4.44129 3 5.20435 3 6C3 6.79565 3.31607 7.55871 3.87868 8.12132C4.44129 8.68393 5.20435 9 6 9C6.79565 9 7.55871 8.68393 8.12132 8.12132C8.68393 7.55871 9 6.79565 9 6C9 5.20435 8.68393 4.44129 8.12132 3.87868C7.55871 3.31607 6.79565 3 6 3ZM9.9 2.85C9.9 2.65109 9.82098 2.46032 9.68033 2.31967C9.53968 2.17902 9.34891 2.1 9.15 2.1C8.95109 2.1 8.76032 2.17902 8.61967 2.31967C8.47902 2.46032 8.4 2.65109 8.4 2.85C8.4 3.04891 8.47902 3.23968 8.61967 3.38033C8.76032 3.52098 8.95109 3.6 9.15 3.6C9.34891 3.6 9.53968 3.52098 9.68033 3.38033C9.82098 3.23968 9.9 3.04891 9.9 2.85ZM6 4.2C6.47739 4.2 6.93523 4.38964 7.27279 4.72721C7.61036 5.06477 7.8 5.52261 7.8 6C7.8 6.47739 7.61036 6.93523 7.27279 7.27279C6.93523 7.61036 6.47739 7.8 6 7.8C5.52261 7.8 5.06477 7.61036 4.72721 7.27279C4.38964 6.93523 4.2 6.47739 4.2 6C4.2 5.52261 4.38964 5.06477 4.72721 4.72721C5.06477 4.38964 5.52261 4.2 6 4.2Z" fill="currentColor"/></svg>',
                'placeholder' => 'instagram @username',
                'domain'      => 'https://instagram.com/',
                'enabled'     => 'yes'
            ],
            'twitter'   => [
                'title'       => __('Twitter/X', 'fluent-community'),
                'icon_svg'    => '<svg viewBox="0 0 12 10" fill="none"><path d="M9.1024 0.125H10.7564L7.1429 4.255L11.3939 9.875H8.0654L5.4584 6.4665L2.47542 9.875H0.820422L4.68542 5.4575L0.607422 0.125H4.02042L6.3769 3.2405L9.1024 0.125ZM8.5219 8.885H9.4384L3.52242 1.063H2.53892L8.5219 8.885Z" fill="currentColor"/></svg>',
                'placeholder' => 'twitter/X @username',
                'domain'      => 'https://x.com/',
                'enabled'     => 'yes'
            ],
            'youtube'   => [
                'title'       => __('YouTube', 'fluent-community'),
                'icon_svg'    => '<svg viewBox="0 0 12 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11.7258 1.699C12 2.7682 12 5.0002 12 5.0002C12 5.0002 12 7.2322 11.7258 8.3014C11.5734 8.8924 11.1276 9.3574 10.563 9.5146C9.5376 9.8002 6 9.8002 6 9.8002C6 9.8002 2.4642 9.8002 1.437 9.5146C0.87 9.355 0.4248 8.8906 0.2742 8.3014C1.78814e-08 7.2322 0 5.0002 0 5.0002C0 5.0002 1.78814e-08 2.7682 0.2742 1.699C0.4266 1.108 0.8724 0.642995 1.437 0.485795C2.4642 0.200195 6 0.200195 6 0.200195C6 0.200195 9.5376 0.200195 10.563 0.485795C11.13 0.645395 11.5752 1.1098 11.7258 1.699ZM4.8 7.1002L8.4 5.0002L4.8 2.9002V7.1002Z" fill="currentColor"/></svg>',
                'placeholder' => 'youtube @username',
                'domain'      => 'https://youtube.com/',
                'enabled'     => 'yes'
            ],
            'linkedin'  => [
                'title'       => __('LinkedIn', 'fluent-community'),
                'icon_svg'    => '<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.80061 9.8035H8.20161V7.2973C8.20161 6.6997 8.18961 5.9305 7.36761 5.9305C6.53421 5.9305 6.40701 6.5809 6.40701 7.2535V9.8035H4.80741V4.6501H6.34341V5.3521H6.36441C6.57921 4.9477 7.10121 4.5199 7.88121 4.5199C9.50121 4.5199 9.80121 5.5867 9.80121 6.9745V9.8035H9.80061ZM3.00141 3.9451C2.87934 3.94526 2.75845 3.92132 2.64565 3.87466C2.53285 3.828 2.43037 3.75954 2.34408 3.6732C2.2578 3.58686 2.1894 3.48433 2.14282 3.37151C2.09623 3.25868 2.07237 3.13776 2.07261 3.0157C2.07273 2.832 2.12732 2.65246 2.22947 2.49979C2.33163 2.34711 2.47677 2.22816 2.64653 2.15797C2.81629 2.08778 3.00305 2.06951 3.1832 2.10546C3.36334 2.14142 3.52878 2.22998 3.65859 2.35996C3.78841 2.48994 3.87676 2.65549 3.91248 2.83569C3.9482 3.01588 3.92969 3.20262 3.85928 3.37229C3.78887 3.54196 3.66973 3.68694 3.51692 3.7889C3.36412 3.89086 3.18451 3.94522 3.00081 3.9451H3.00141ZM3.80301 9.8035H2.19921V4.6501H3.80361V9.8035H3.80301ZM10.6016 0.600098H1.39701C0.955409 0.600098 0.599609 0.948098 0.599609 1.3783V10.6219C0.599609 11.0521 0.956009 11.4001 1.39641 11.4001H10.5992C11.0396 11.4001 11.3996 11.0521 11.3996 10.6219V1.3783C11.3996 0.948098 11.0396 0.600098 10.5992 0.600098H10.601H10.6016Z" fill="currentColor"/></svg>',
                'placeholder' => 'linkedin username',
                'domain'      => 'https://linkedin.com/in/',
                'enabled'     => 'yes'
            ],
            'fb'        => [
                'title'       => __('Facebook', 'fluent-community'),
                'icon_svg'    => '<svg width="7" height="12" viewBox="0 0 7 12" fill="none"><path d="M4.2 6.9H5.7L6.3 4.5H4.2V3.3C4.2 2.682 4.2 2.1 5.4 2.1H6.3V0.0840001C6.1044 0.0582001 5.3658 0 4.5858 0C2.9568 0 1.8 0.9942 1.8 2.82V4.5H0V6.9H1.8V12H4.2V6.9Z" fill="currentColor"/></svg>',
                'placeholder' => 'fb_username',
                'domain'      => 'https://facebook.com/',
                'enabled'     => 'yes'
            ],
            'blue_sky'  => [
                'title'       => __('Bluesky', 'fluent-community'),
                'icon_svg'    => '<svg viewBox="0 0 24 24" width="20" height="20" color="currentColor" fill="none"><path d="M12 11.4963C11.8936 11.2963 7.45492 3 3.50417 3C1.33647 3 2.00456 8 2.50443 10.5C2.70653 11.5108 3.50417 14.5 8.003 14C8.003 14 4.00404 14.5 4.00404 17C4.00404 18.5 6.50339 21 8.50287 21C10.4606 21 11.9391 16.6859 12 16.5058C12.0609 16.6859 13.5394 21 15.4971 21C17.4966 21 19.996 18.5 19.996 17C19.996 14.5 15.997 14 15.997 14C20.4958 14.5 21.2935 11.5108 21.4956 10.5C21.9954 8 22.6635 3 20.4958 3C16.5451 3 12.1064 11.2963 12 11.4963Z" fill="currentColor" /></svg>',
                'placeholder' => 'bluesky_username',
                'domain'      => 'https://bsky.app/profile/',
                'enabled'     => 'no'
            ],
            'tiktok'    => [
                'title'       => __('TikTok', 'fluent-community'),
                'icon_svg'    => '<svg viewBox="0 0 24 24" width="20" height="20" color="currentColor" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M4.5 1.75C2.98122 1.75 1.75 2.98122 1.75 4.5V19.5C1.75 21.0188 2.98122 22.25 4.5 22.25H19.5C21.0188 22.25 22.25 21.0188 22.25 19.5V4.5C22.25 2.98122 21.0188 1.75 19.5 1.75H4.5ZM14.5 6C14.5 5.44772 14.0523 5 13.5 5C12.9477 5 12.5 5.44772 12.5 6V14.5C12.5 15.8807 11.3807 17 10 17C8.61929 17 7.5 15.8807 7.5 14.5C7.5 13.1193 8.61929 12 10 12C10.5523 12 11 11.5523 11 11C11 10.4477 10.5523 10 10 10C7.51472 10 5.5 12.0147 5.5 14.5C5.5 16.9853 7.51472 19 10 19C12.4853 19 14.5 16.9853 14.5 14.5V9.56299C15.3659 10.1552 16.4276 10.5 17.5 10.5C18.0523 10.5 18.5 10.0523 18.5 9.5C18.5 8.94772 18.0523 8.5 17.5 8.5C16.7397 8.5 15.9649 8.21074 15.3902 7.73178C14.8206 7.25714 14.5 6.64642 14.5 6Z" fill="currentColor" /></svg>',
                'placeholder' => '@tiktok_username',
                'domain'      => 'https://tiktok.com/',
                'enabled'     => 'no'
            ],
            'pinterest' => [
                'title'       => __('Pinterest', 'fluent-community'),
                'icon_svg'    => '<svg viewBox="0 0 24 24" width="20" height="20" color="currentColor" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 1.25C6.06294 1.25 1.25 6.06294 1.25 12C1.25 16.0389 3.47738 19.5576 6.77079 21.3947L11.0721 10.629C11.277 10.1162 11.8589 9.86654 12.3717 10.0714C12.8846 10.2764 13.1343 10.8582 12.9293 11.3711L11.1187 15.9028C11.4018 15.9664 11.6966 16 12 16C14.2091 16 16 14.2091 16 12C16 9.79086 14.2091 8 12 8C9.79086 8 8 9.79086 8 12C8 12.7302 8.19488 13.4118 8.53476 13.9992C8.81136 14.4772 8.64807 15.0889 8.17005 15.3655C7.69202 15.6421 7.08027 15.4789 6.80367 15.0008C6.29247 14.1174 6 13.0914 6 12C6 8.68629 8.68629 6 12 6C15.3137 6 18 8.68629 18 12C18 15.3137 15.3137 18 12 18C11.4361 18 10.8893 17.9219 10.3704 17.7758L8.60199 22.2019C9.67002 22.5575 10.8125 22.75 12 22.75C17.9371 22.75 22.75 17.9371 22.75 12C22.75 6.06294 17.9371 1.25 12 1.25Z" fill="currentColor" /></svg>',
                'placeholder' => 'pinterest_username',
                'domain'      => 'https://pinterest.com/',
                'enabled'     => 'no'
            ],
            'telegram'  => [
                'title'       => __('Telegram', 'fluent-community'),
                'icon_svg'    => '<svg viewBox="0 0 24 24" width="20" height="20" color="currentColor" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M22.7317 3.16269C22.792 2.89122 22.6972 2.60865 22.4853 2.42854C22.2734 2.24844 21.9792 2.20037 21.721 2.30366L1.72146 10.3037C1.45174 10.4115 1.26858 10.6655 1.25133 10.9555C1.23408 11.2455 1.38584 11.5193 1.64087 11.6584L6.82025 14.4836L7.76021 20.1233C7.8077 20.4083 8.01463 20.6406 8.2922 20.7206C8.56977 20.8007 8.86864 20.7142 9.06056 20.4983L11.6108 17.6293L9.49049 15.2837C9.21881 14.9831 9.23494 14.5211 9.52692 14.2403L13.7768 10.1519L14.6708 9.23818C14.9604 8.94211 15.4353 8.93693 15.7314 9.22661C16.0274 9.5163 16.0326 9.99114 15.7429 10.2872L14.841 11.209C14.8357 11.2144 14.8303 11.2197 14.8249 11.225L11.0908 14.8172L14.2359 18.2966L17.4697 21.5303C17.6632 21.7239 17.9469 21.7966 18.2097 21.7201C18.4725 21.6436 18.6728 21.4299 18.7321 21.1627L22.7317 3.16269Z" fill="currentColor" /></svg>',
                'placeholder' => 'telegram_username',
                'domain'      => 'https://telegram.me/',
                'enabled'     => 'no'
            ],
            'snapchat'  => [
                'title'       => __('Snapchat', 'fluent-community'),
                'icon_svg'    => '<svg viewBox="0 0 24 24" width="20" height="20" color="currentColor" fill="none"><path d="M2 16.5C5.82356 14.0006 5.82356 11.5404 2.95589 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M22 16.5C18.1764 14.0006 18.1764 11.5404 21.0441 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M6.57556 7.42444C6.57556 4.42861 9.00416 2 12 2C14.9958 2 17.4244 4.42861 17.4244 7.42444C17.4244 12.1722 17.6611 14.5456 22 16.4444C19.7778 17 19.0556 17.2778 18.5 19.5C14.6111 19.5 14.2222 22 12 22C9.77778 22 9.38889 19.5 5.5 19.5C4.94444 17.2778 4.22222 17.0556 2 16.5C6.33889 14.6011 6.57556 12.1722 6.57556 7.42444Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>',
                'placeholder' => 'snapchat_username',
                'domain'      => 'https://snapchat.com/add/',
                'enabled'     => 'no'
            ],
            'reddit'    => [
                'title'       => __('Reddit', 'fluent-community'),
                'icon_svg'    => '<svg viewBox="0 0 24 24" width="20" height="20" color="currentColor" fill="none"><ellipse cx="12" cy="15.5" rx="9" ry="6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M15.5 16.7803C14.5149 17.548 13.3062 18.0002 12 18.0002C10.6938 18.0002 9.48512 17.548 8.5 16.7803" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><circle cx="19" cy="4" r="2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M18 10.0694C18.3687 9.43053 19.0634 9 19.8595 9C21.0417 9 22 9.94921 22 11.1201C22 11.937 21.5336 12.6459 20.8502 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M6 10.0694C5.63125 9.43053 4.93663 9 4.14048 9C2.95833 9 2 9.94921 2 11.1201C2 11.937 2.4664 12.6459 3.14981 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M12 9V6C12 4.89543 12.8954 4 14 4H17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M9.00801 13L8.99902 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /><path d="M15.008 13L14.999 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>',
                'placeholder' => 'techjewel',
                'domain'      => 'https://www.reddit.com/user/',
                'enabled'     => 'no'
            ],
            'twitch'    => [
                'title'       => __('Twitch', 'fluent-community'),
                'icon_svg'    => '<svg viewBox="0 0 24 24" width="20" height="20" color="currentColor" fill="none"><path d="M16 7V11M12 7V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M16 3H8C6.11438 3 5.17157 3 4.58579 3.58358C4 4.16716 4 5.10641 4 6.98492V13.56C4 13.9302 4 14.1153 4.02462 14.2702C4.16017 15.1228 4.83135 15.7914 5.68713 15.9265C5.8426 15.951 6.0284 15.951 6.4 15.951C6.4929 15.951 6.53935 15.951 6.57822 15.9571C6.79216 15.9909 6.95996 16.158 6.99384 16.3712C7 16.4099 7 16.4562 7 16.5487V18.0921C7 19.2742 7 19.8653 7.3345 19.9822C7.66899 20.0991 8.03962 19.6375 8.78087 18.7144L10.6998 16.3249C10.8473 16.1412 10.921 16.0493 11.0237 16.0002C11.1264 15.951 11.2445 15.951 11.4806 15.951H15.3431C16.1606 15.951 16.5694 15.951 16.9369 15.7993C17.3045 15.6477 17.5935 15.3597 18.1716 14.7838L18.8284 14.1295C19.4065 13.5536 19.6955 13.2656 19.8478 12.8995C20 12.5333 20 12.1261 20 11.3117V6.98492C20 5.10641 20 4.16716 19.4142 3.58358C18.8284 3 17.8856 3 16 3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>',
                'placeholder' => 'twitch_username',
                'domain'      => 'https://www.twitch.tv/',
                'enabled'     => 'no'
            ],
            'vk'        => [
                'title'       => __('VK', 'fluent-community'),
                'icon_svg'    => '<svg viewBox="0 0 24 24" width="20" height="20" color="currentColor" fill="none"><path d="M2.00053 5.5H5.50053C5.50053 13.5 10.0005 14.5 10.0005 14.5L10.0015 5.5H13.5015L13.4995 10.5C17.9995 8.5 18.4995 5.5 18.4995 5.5H21.9995C21.9995 5.5 20.9995 10 17.0926 12.1534C19.1115 13.3511 21.2684 15.3315 21.9995 18.5H18.4995C18.4995 18.5 17.4995 15.5 13.4995 14L13.5015 18.5C1.88755 18.5 2.00232 7.5 2.00053 5.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>',
                'placeholder' => 'vk useranme',
                'domain'      => 'https://vk.com/',
                'enabled'     => 'yes'
            ],
            'github'    => [
                'title'       => __('Github', 'fluent-community'),
                'icon_svg'    => '<svg height="32" viewBox="0 0 24 24" version="1.1" width="32" color="currentColor"><path d="M12.5.75C6.146.75 1 5.896 1 12.25c0 5.089 3.292 9.387 7.863 10.91.575.101.79-.244.79-.546 0-.273-.014-1.178-.014-2.142-2.889.532-3.636-.704-3.866-1.35-.13-.331-.69-1.352-1.18-1.625-.402-.216-.977-.748-.014-.762.906-.014 1.553.834 1.769 1.179 1.035 1.74 2.688 1.25 3.349.948.1-.747.402-1.25.733-1.538-2.559-.287-5.232-1.279-5.232-5.678 0-1.25.445-2.285 1.178-3.09-.115-.288-.517-1.467.115-3.048 0 0 .963-.302 3.163 1.179.92-.259 1.897-.388 2.875-.388.977 0 1.955.13 2.875.388 2.2-1.495 3.162-1.179 3.162-1.179.633 1.581.23 2.76.115 3.048.733.805 1.179 1.825 1.179 3.09 0 4.413-2.688 5.39-5.247 5.678.417.36.776 1.05.776 2.128 0 1.538-.014 2.774-.014 3.162 0 .302.216.662.79.547C20.709 21.637 24 17.324 24 12.25 24 5.896 18.854.75 12.5.75Z"></path></svg>',
                'placeholder' => 'github username',
                'domain'      => 'https://github.com/',
                'enabled'     => 'yes'
            ],
            'mastodon'  => [
                'title'       => __('Mastodon', 'fluent-community'),
                'icon_svg'    => '<svg viewBox="0 0 24 24" width="20" height="20" color="currentColor" fill="none"><path d="M17 13.5V8C17 6.61929 15.8807 5.5 14.5 5.5C13.1193 5.5 12 6.61929 12 8M12 8V11.5M12 8C12 6.61929 10.8807 5.5 9.5 5.5C8.11929 5.5 7 6.61929 7 8V13.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M13 16.9954C15.0099 16.9954 16.89 16.6876 18.4949 16.1525C20.1275 15.6081 21 13.9512 21 12.2302V7.52349C21 5.34784 19.8297 3.2779 17.7281 2.715C16.0259 2.25905 14.0744 2 12 2C9.9256 2 7.97414 2.25905 6.27189 2.715C4.17033 3.2779 3 5.34785 3 7.52349V14.4961C3 22.4937 11 21.9938 11 21.9938C13.5 21.9938 15 21 15 21V20C15 20 13.5 20.4943 11 20.4943C5.68009 20.4943 7.06011 15.9957 7.06011 15.9957C8.75781 16.627 10.8012 16.9954 13 16.9954Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>',
                'placeholder' => '@mastodon_username',
                'domain'      => 'https://mastodon.social/',
                'enabled'     => 'yes'
            ]
        ]);

        $enabledKeys = self::getEnabledLinkProviderKeys();

        foreach ($links as $key => $provider) {
            if (in_array($key, $enabledKeys)) {
                $links[$key]['enabled'] = 'yes';
            } else {
                $links[$key]['enabled'] = 'no';
            }
        }

        if ($enabledOnly) {
            $links = array_filter($links, function ($link) {
                return $link['enabled'] === 'yes';
            });
        }

        return $links;
    }

    public static function getEnabledLinkProviderKeys()
    {
        $keys = Utility::getOption('enabled_profile_link_keys', NULL);
        if ($keys === NULL) {
            return ['instagram', 'twitter', 'youtube', 'linkedin', 'fb'];
        }

        return $keys;
    }

    public static function getReservedUserNames()
    {
        return apply_filters('fluent_community/reserved_usernames', [
            'admin', 'administrator', 'me', 'moderator', 'mod', 'superuser', 'root', 'system', 'official', 'staff', 'support', 'helpdesk', 'user', 'guest', 'anonymous', 'everyone', 'anybody', 'someone', 'webmaster', 'postmaster', 'hostmaster', 'abuse', 'security', 'ssl', 'firewall', 'no-reply', 'noreply', 'mail', 'email', 'mailer', 'smtp', 'pop', 'imap', 'ftp', 'sftp', 'ssh', 'ceo', 'cfo', 'cto', 'founder', 'cofounder', 'owner', 'president', 'vicepresident', 'director', 'manager', 'supervisor', 'executive', 'info', 'contact', 'sales', 'marketing', 'support', 'billing', 'accounting', 'finance', 'hr', 'humanresources', 'legal', 'compliance', 'it', 'itsupport', 'customerservice', 'customersupport', 'dev', 'developer', 'api', 'sdk', 'app', 'bot', 'chatbot', 'sysadmin', 'devops', 'infosec', 'security', 'test', 'testing', 'beta', 'alpha', 'staging', 'production', 'development', 'home', 'about', 'contact', 'faq', 'help', 'news', 'blog', 'forum', 'community', 'events', 'calendar', 'shop', 'store', 'cart', 'checkout', 'social', 'follow', 'like', 'share', 'tweet', 'post', 'status', 'privacy', 'terms', 'copyright', 'trademark', 'legal', 'policy', 'all', 'none', 'null', 'undefined', 'true', 'false', 'default', 'example', 'sample', 'demo', 'temporary', 'delete', 'remove', 'profanity', 'explicit', 'offensive', 'yourappname', 'yourbrandname', 'yourdomain',
        ]);
    }

    public static function isUsernameAvailable($userName, $targetUserId = null)
    {
        $userName = strtolower($userName);

        if (strlen($userName) < 3) {
            return false;
        }

        $reservedUserNames = self::getReservedUserNames();
        if (in_array($userName, $reservedUserNames)) {
            return false;
        }

        $user = get_user_by('login', $userName);

        if ($user) {
            if ($targetUserId && $user->ID != $targetUserId) {
                return false;
            }
        }

        $xProfile = XProfile::where('username', $userName)
            ->when($targetUserId, function ($query) use ($targetUserId) {
                return $query->where('user_id', '!=', $targetUserId);
            })
            ->exists();

        if ($xProfile) {
            return false;
        }

        return true;
    }

    public static function generateUserName($user, $useUserName = false)
    {
        if (!$user instanceof \WP_User) {
            $user = get_user_by('ID', $user);
        }

        return self::createUserNameFromStrings($user->user_login, array_filter([
            $user->user_nicename,
            $user->first_name,
            $user->last_name,
            $user->display_name
        ]), $user->ID);
    }

    public static function createUserNameFromStrings($maybeEmail, $fallbacks = [], $userId = null)
    {
        $emailParts = explode('@', $maybeEmail);
        $userName = $emailParts[0];

        $userName = CustomSanitizer::sanitizeUserName($userName);

        if (self::isUsernameAvailable($userName, $userId)) {
            return $userName;
        }

        foreach ($fallbacks as $fallback) {
            // only take alphanumeric characters and _ -
            $fallback = preg_replace('/[^a-zA-Z0-9_-]/', '', $fallback);
            $userName = CustomSanitizer::sanitizeUserName($fallback);
            if (self::isUsernameAvailable($userName, $userId)) {
                return $userName;
            }
        }

        $userName = strtolower($emailParts[0]);

        $finalUserName = $userName;
        // loop until we find a unique username
        $counter = 2;
        while (!self::isUsernameAvailable($userName, $userId)) {
            $userName = $finalUserName . $counter;
            $counter++;
            if ($counter % 100 === 0) {
                $finalUserName = $finalUserName . $userId . '-';
            }
        }

        return $userName;
    }

    public static function getUserAuthHash($userId = null)
    {
        if (!$userId) {
            return '';
        }

        if (Utility::getPrivacySetting('email_auto_login') === 'no') {
            return '';
        }

        static $cached = [];

        if (isset($cached[$userId])) {
            return $cached[$userId];
        }

        $exist = Meta::byType('user')
            ->byMetaKey('auth_hash')
            ->byObjectId($userId)
            ->first();

        $validTil = strtotime('+1 day');

        if ($exist) {
            $hashes = (array)$exist->value;

            $validHashes = array_filter($hashes, function ($hash) use ($validTil) {
                return $hash['valid_til'] > $validTil;
            });

            if ($validHashes) {
                $lastHash = end($validHashes);
                return $lastHash['hash'] . '__' . $exist->id;
            }
            $newHash = md5(wp_generate_uuid4() . '_' . $userId . '_' . '_' . time() . '__' . $userId);

            $hashes[] = [
                'hash'      => $newHash,
                'valid_til' => strtotime('+2 days')
            ];

            // remove expired hashes
            $hashes = array_filter($hashes, function ($hash) use ($validTil) {
                return $hash['valid_til'] > time();
            });

            $exist->value = array_values($hashes);
            $exist->save();

            $cached[$userId] = $newHash . '__' . $exist->id;

            return $cached[$userId];
        }

        $newHash = md5(wp_generate_uuid4() . '_' . $userId . '_' . '_' . time() . '__' . $userId);

        $meta = Meta::create([
            'object_type' => 'user',
            'object_id'   => $userId,
            'meta_key'    => 'auth_hash', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'value'       => [
                [
                    'hash'      => $newHash,
                    'valid_til' => strtotime('+2 days')
                ]
            ]
        ]);

        $cached[$userId] = $newHash . '__' . $meta->id;

        return $cached[$userId];
    }

    public static function signUserUrlWithAuthHash($url, $userId = null)
    {
        $hash = self::getUserAuthHash($userId);
        if (!$hash) {
            return $url;
        }

        return add_query_arg([
            'fcom_action'   => 'signed_url',
            'fcom_url_hash' => $hash
        ], $url);
    }

    public static function getSignedNotificationPrefUrl($userId)
    {
        $notificationPref = Helper::baseUrl('fcom_route?route=user_notification_settings&auth=yes');
        return self::signUserUrlWithAuthHash($notificationPref, $userId);
    }

    /*
     * Get WP User by URL Hash
     * @param string $hash
     * @return \WP_User|null
     */
    public static function getUserByUrlHash($hash)
    {
        $hashParts = explode('__', $hash);
        if (count($hashParts) !== 2) {
            return null;
        }

        $metaId = $hashParts[1];
        $meta = Meta::where('object_type', 'user')
            ->where('meta_key', 'auth_hash')
            ->where('id', $metaId)
            ->first();

        if (!$meta) {
            return null;
        }

        $hashes = (array)$meta->value;
        $hash = $hashParts[0];

        $validHash = array_filter($hashes, function ($hashData) use ($hash) {
            return $hashData['hash'] == $hash;
        });

        if (!$validHash) {
            return null;
        }

        return get_user_by('ID', $meta->object_id);
    }

    public static function canViewUserSpaces($targetUserId, $currentUser = null)
    {
        $status = Utility::getPrivacySetting('user_space_visibility', 'everybody');

        if ($status == 'everybody') {
            return true;
        }

        if ($status == 'logged_in') {
            return !!$currentUser;
        }

        if (is_numeric($currentUser)) {
            $currentUser = User::find($currentUser);
        }

        if (!$currentUser) {
            return false;
        }

        if ($targetUserId == $currentUser->ID) {
            return true;
        }

        return Helper::isModerator($currentUser);
    }


    /**
     * Sends a confirmation request email when a change of user email address is attempted.
     *
     * @param \WP_User $current_user The current user object.
     * @param $new_email string The new email address.
     * @return  \WP_Error|boolean $error WP_Error object.
     */
    public static function sendConfirmationOnProfileEmailChange(\WP_User $current_user, $newEmail)
    {
        if ($current_user->user_email == $newEmail) {
            return false;
        }

        if (!is_email($newEmail)) {
            return new \WP_Error('user_email', __('Error: The email address is not correct.', 'fluent-community'));
        }

        if (email_exists($newEmail)) {
            delete_user_meta($current_user->ID, '_new_email');
            return new \WP_Error('user_email', __('Error: The email address is already used.', 'fluent-community'));
        }

        $hash = md5($newEmail . time() . wp_rand());
        $new_user_email = array(
            'hash'     => $hash,
            'newemail' => $newEmail
        );
        update_user_meta($current_user->ID, '_new_email', $new_user_email);

        $sitename = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        /* translators: Do not translate USERNAME, ADMIN_URL, EMAIL, SITENAME, SITEURL: those are placeholders. */
        $email_text = __(
            'Howdy ###USERNAME###,

You recently requested to have the email address on your account changed.

If this is correct, please click on the following link to change it:
###ADMIN_URL###

You can safely ignore and delete this email if you do not want to
take this action.

This email has been sent to ###EMAIL###

Regards,
All at ###SITENAME###
###SITEURL###', 'fluent-community'
        );

        $content = apply_filters('new_user_email_content', $email_text, $new_user_email); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        $content = str_replace('###USERNAME###', $current_user->user_login, $content);
        $content = str_replace('###ADMIN_URL###', esc_url(self_admin_url('profile.php?newuseremail=' . $hash)), $content);
        $content = str_replace('###EMAIL###', $newEmail, $content);
        $content = str_replace('###SITENAME###', $sitename, $content);
        $content = str_replace('###SITEURL###', home_url(), $content);

        /* translators: New email address notification email subject. %s: Site title. */
        return wp_mail($newEmail, sprintf(__('[%s] Email Change Request', 'fluent-community'), $sitename), $content);
    }
}
