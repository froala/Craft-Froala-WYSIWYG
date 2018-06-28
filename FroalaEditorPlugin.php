<?php
/**
 * Froala Editor for Craft
 *
 * @package froalaeditor
 * @author  Bert Oost
 */

namespace Craft;

/**
 * Class FroalaEditorPlugin
 */
class FroalaEditorPlugin extends BasePlugin
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'Froala WYSIWYG Editor';
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion()
    {
        return '2.8.1-RC2';
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaVersion()
    {
        return $this->getVersion();
    }

    /**
     * @return boolean|string
     */
    public function getEditorVersion()
    {
        $version = $this->getVersion();
        if (false !== ($basePos = strpos($version, '-'))) {
            $version = substr($version, 0, $basePos);
        }

        return $version;

    }

    /**
     * {@inheritdoc}
     */
    public function getReleaseFeedUrl()
    {
        return 'https://raw.githubusercontent.com/froala/Craft-Froala-WYSIWYG/master/releases.json';
    }

    /**
     * {@inheritdoc}
     */
    public function getDeveloper()
    {
        return 'Bert Oost';
    }

    /**
     * {@inheritdoc}
     */
    public function getDeveloperUrl()
    {
        return 'http://bertoost.com';
    }

    /**
     * {@inheritdoc}
     */
    public function getDocumentationUrl()
    {
        return 'https://github.com/froala/Craft-Froala-WYSIWYG/blob/master/README.md';
    }

    /**
     * {@inheritdoc
     */
    public function getSettingsUrl()
    {
        return 'froala-editor/settings/general';
    }

    /**
     * {@inheritdoc}
     */
    protected function defineSettings()
    {
        return [
            'migrated'         => [AttributeType::String],
            'licenseKey'       => [AttributeType::String],
            'customCssType'    => [AttributeType::String],
            'customCssFile'    => [AttributeType::String],
            'customCssClasses' => [AttributeType::Mixed],
            'enabledPlugins'   => [AttributeType::Mixed],
            'cleanupHtml'      => [AttributeType::Bool, 'default' => false],
            'purifyHtml'       => [AttributeType::Bool, 'default' => true],
            'purifierConfig'   => [AttributeType::String],
            'editorConfig'     => [AttributeType::String],
        ];
    }

    /**
     * {@inheritdoc
     */
    public function registerCpRoutes()
    {
        return [
            'froala-editor/settings'                            => ['action' => 'froalaEditor/settings/show'],
            'froala-editor/settings/(?P<settingsType>{handle})' => ['action' => 'froalaEditor/settings/show'],
        ];
    }

    /**
     * Returns the custom config used by this plugin.
     *
     * @return array
     */
    public function getCustomConfig($settingsKey, $subDir)
    {
        $file = $this->getSettings()->$settingsKey;
        $path = craft()->path->getConfigPath() . $subDir . DIRECTORY_SEPARATOR . $file;

        if (!$file || false === IOHelper::fileExists($path)) {

            if ($settingsKey === 'purifierConfig') {
                return [
                    'Attr.AllowedFrameTargets' => ['_blank'],
                ];
            }

            return [];
        }

        $json = IOHelper::getFileContents($path);

        return json_decode($json, true);
    }

    /**
     * @param string $dir
     * @return array
     */
    public function getCustomConfigOptions($dir)
    {
        $options = ['' => Craft::t('Default')];
        $path = craft()->path->getConfigPath() . DIRECTORY_SEPARATOR . rtrim($dir, '/') . DIRECTORY_SEPARATOR;

        if (is_dir($path)) {

            $files = FileHelper::findFiles($path, [
                'only'      => ['*.json'],
                'recursive' => false,
            ]);

            foreach ($files as $file) {
                $basename = pathinfo($file, PATHINFO_BASENAME);
                $options[$basename] = Craft::t(ucfirst(pathinfo($file, PATHINFO_FILENAME)));
            }
        }

        return $options;
    }

    /**
     * @param string $msg
     * @param string $logLevel
     * @param bool   $force
     * @return void
     */
    public static function log($msg, $logLevel = LogLevel::Info, $force = false)
    {
        Craft::log($msg, $logLevel, $force, 'application', 'FroalaEditor');
    }
}