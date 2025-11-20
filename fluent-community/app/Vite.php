<?php

namespace FluentCommunity\App;

use FluentCommunity\App\Functions\Utility;

class Vite
{
    protected static $moduleScripts = [];
    protected static $resourceURL = 'https://localhost:4444/src/';
    protected static $assetsURL = FLUENT_COMMUNITY_PLUGIN_URL . 'assets/';

    private static function isDev()
    {
        return Utility::isDev();
    }

    public static function enqueueScript($handle, $src, $dependency = [], $version = false, $inFooter = false)
    {
        static::$moduleScripts [] = $handle;
        $src = static::generateSrc($src);
        wp_enqueue_script($handle, $src, $dependency, $version, $inFooter);
        static::addModuleToScript();
    }

    public static function enqueueStyle($handle, $src, $dependency = [], $version = false, $media = 'all')
    {
        $src = static::generateSrc($src);
        wp_enqueue_style($handle, $src, $dependency, $version, $media);
    }

    public static function enqueueStaticScript($handle, $src, $dependency = [], $version = false, $inFooter = false)
    {
        $src = static::getStaticSrcUrl($src);
        wp_enqueue_script($handle, $src, $dependency, $version, $inFooter);
    }

    public static function enqueueStaticStyle($handle, $src, $dependency = [], $version = false, $media = 'all')
    {
        $src = static::getStaticSrcUrl($src);
        wp_enqueue_style($handle, $src, $dependency, $version, $media);
    }

    private static function parseManifest()
    {
        static $manifest;
        if ($manifest) {
            return $manifest;
        }

        $config = App::getInstance()->config;
        $manifest = $config->get('app.manifest');

        if(!$manifest) {
            throw new \Exception('Manifest config could not be found on the app config');
        }

        return $manifest;
    }

    private static function addModuleToScript()
    {
        add_filter('script_loader_tag', function ($tag, $handle, $src) {
            return $tag;
        }, 10, 3);
    }

    private static function generateSrc($src, $isRtl = null)
    {
        if (!static::isDev()) {
            $manifest = static::parseManifest();
            $src = 'src/' . $src;
            $mainSrc = isset($manifest[$src]) ? $manifest[$src] : false;

            if ($mainSrc) {
                if (isset($mainSrc['css'])) {
                    foreach ($mainSrc['css'] as $css) {
                        wp_enqueue_style($css . '_css', static::$assetsURL . $css, [], '1.0.0', 'all');
                    }
                }

                if($isRtl) {
                    $file = $manifest[$src]['file'];
                    $file = str_replace('.css', '.rtl.css', $file);
                    return static::$assetsURL . $file;
                }

                return static::$assetsURL . $manifest[$src]['file'];
            }
        }

        return static::$resourceURL . $src;
    }

    public static function getStaticSrcUrl($src)
    {
        if (!static::isDev()) {
            return static::$assetsURL . $src;
        }

        return static::$resourceURL . $src;
    }

    public static function getDynamicSrcUrl($src, $isRtl = null)
    {
        return static::generateSrc($src, $isRtl);
    }

    public static function getDynamicProductionSrcUrl($src, $isRtl = null)
    {
        if (!static::isDev()) {
            return static::generateSrc($src, $isRtl);
        }

        return false;
    }

    public static function getAssetsUrl()
    {
        if (!static::isDev()) {
            return static::$assetsURL;
        }
        return static::$resourceURL;
    }
}
