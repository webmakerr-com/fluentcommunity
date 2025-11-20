<?php

namespace FluentCommunity\App\Services\Libs;

class FileSystem
{
    /**
     * Read file content from custom upload dir of this application
     * @return string [path]
     */
    public function _get($file)
    {
        $arr = explode('/', $file);
        $fileName = end($arr);
        return file_get_contents($this->getDir() . '/' . $fileName); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    }

    /**
     * Get custom upload dir name of this application
     * @return string [directory path]
     */
    public function _getDir()
    {
        $uploadDir = wp_upload_dir();

        $folderName = apply_filters('fluent_community/upload_folder_name', FLUENT_COMMUNITY_UPLOAD_DIR);

        return $uploadDir['basedir'] . $folderName;
    }

    /**
     * Get absolute path of file using custom upload dir name of this application
     * @return string [file path]
     */
    public function _getAbsolutePathOfFile($file)
    {
        return $this->_getDir() . '/' . $file;
    }

    /**
     * Upload files into custom upload dir of this application
     * @return array
     */
    public function _uploadFromRequest()
    {
        return $this->_put(fluentCommunityApp('request')->files());
    }

    /**
     * Upload files into custom upload dir of this application
     * @param array $files
     * @return array
     */
    public function _put($files)
    {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $this->overrideUploadDir();

        $uploadOverrides = ['test_form' => false];

        $uploadedFiles = [];

        foreach ((array)$files as $file) {
            $filesArray = $file->toArray();
            $originalName = $filesArray['name'];
            $uploadedFile = \wp_handle_upload($filesArray, $uploadOverrides);

            if (isset($uploadedFile['error'])) {
                $uploadedFiles[] = new \WP_Error('upload_error', $uploadedFile['error']);
            }

            $uploadedFile['original_name'] = $originalName;

            $uploadedFiles[] = $uploadedFile;
        }

        return $uploadedFiles;
    }

    /**
     * Delete a file from custom upload directory of this application
     * @param array $files
     * @return void
     */
    public function _delete($files)
    {
        $files = (array)$files;

        foreach ($files as $file) {
            $arr = explode('/', $file);
            $fileName = end($arr);
            wp_delete_file($this->getDir() . '/' . $fileName);
        }
    }

    /**
     * Register filters for custom upload dir
     */
    public function _overrideUploadDir()
    {
        add_filter('wp_handle_upload_prefilter', function ($file) {
            add_filter('upload_dir', [$this, '_setCustomUploadDir']);

            add_filter('wp_handle_upload', function ($fileinfo) {
                remove_filter('upload_dir', [$this, '_setCustomUploadDir']);
                $fileinfo['file'] = basename($fileinfo['file']);
                return $fileinfo;
            });

            return $this->_renameFileName($file);
        });
    }

    /**
     * Set plugin's custom upload dir
     * @param array $param
     * @return array $param
     */
    public function _setCustomUploadDir($param)
    {

        $folderName = apply_filters('fluent_community/upload_folder_name', FLUENT_COMMUNITY_UPLOAD_DIR);

        $param['url'] = $param['baseurl'] . '/' . $folderName;

        $param['path'] = $param['basedir'] . '/' . $folderName;

        if (!is_dir($param['path'])) {
            mkdir($param['path'], 0755); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
            $htaccessContent = '# END FluentCommunity\n# Disable parsing of PHP for some server configurations.\n<Files *>\nSetHandler none\nSetHandler default-handler\nOptions -ExecCGI\nRemoveHandler .cgi .php .php3 .php4 .php5 .phtml .pl .py .pyc .pyo\n</Files>\n<IfModule mod_php5.c>\nphp_flag engine off\n</IfModule>\n# END FluentCommunity';

            file_put_contents($param['basedir'] . '/' . $folderName . '/.htaccess', $htaccessContent); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }

        if (!file_exists($param['basedir'] . '/' . $folderName . '/index.php')) {

            $indexStubContent = "<?php\n// Silence is golden.\n";

            file_put_contents($param['basedir'] . '/' . $folderName . '/index.php', $indexStubContent); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }

        return $param;
    }

    /**
     * Rename the uploaded file name before saving
     * @param array $file
     * @return array $file
     */
    public function _renameFileName($file)
    {
        $prefix = 'fluentcom-' . md5(wp_generate_uuid4()) . '-fluentcom-';
        $originalName = $file['name'];
        $file['name'] = $prefix . $file['name'];
        $file['name'] = apply_filters('fluent_community/generated_upload_file_name', $file['name'], $originalName, $file);
        return $file;
    }

    public static function __callStatic($method, $params)
    {
        $instance = new static;

        return call_user_func_array([$instance, $method], $params);
    }

    public function __call($method, $params)
    {
        $hiddenMethod = "_" . $method;

        $method = method_exists($this, $hiddenMethod) ? $hiddenMethod : $method;

        return call_user_func_array([$this, $method], $params);
    }
}
