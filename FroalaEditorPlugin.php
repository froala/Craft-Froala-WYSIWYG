<?php
/**
 * Froala Editor for Craft
 *
 * @package froalaeditor
 * @author Bert Oost
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
        return 'Froala Editor';
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion()
    {
        return '2.8.1';
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
     * {@inheritdoc}
     */
    public function onAfterInstall()
    {
        // Convert all existing Rich Text fields to Froala Editor
        craft()->db->createCommand()->update(
            'fields',
            array('type' => 'FroalaEditor'),
            array('type' => 'RichText')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function onBeforeUninstall()
    {
        // Convert all existing Froala Editor fields back to Rich Text
        craft()->db->createCommand()->update(
            'fields',
            array('type' => 'RichText'),
            array('type' => 'FroalaEditor')
        );
    }

    /**
     * {@inheritdoc
     */
    public function getSettingsHtml()
    {
        return craft()->templates->render('froalaeditor/settings', array(
            'settings' => $this->getSettings(),
            'editorPlugins' => $this->getEditorPlugins(),
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function defineSettings()
    {
        return [
            'licenseKey'       => [AttributeType::String],
            'customCssType'    => [AttributeType::String],
            'customCssFile'    => [AttributeType::String],
            'customCssClasses' => [AttributeType::String],
            'enabledPlugins'   => [AttributeType::Mixed],
            'cleanupHtml'      => [AttributeType::Bool, 'default' => false],
            'purifyHtml'       => [AttributeType::Bool, 'default' => true],
            'purifierConfig'   => [AttributeType::Mixed],
        ];
    }

    /**
     * Returns the HTML Purifier config used by this field.
     *
     * @return array
     */
    public function getPurifierConfig()
    {
        $file = $this->getSettings()->purifierConfig;
        $path = craft()->path->getConfigPath() . 'htmlpurifier/' . $file;

        if (!$file || !IOHelper::fileExists($path)) {
            return array(
                'Attr.AllowedFrameTargets' => ['_blank'],
            );
        }

        $json = IOHelper::getFileContents($path);

        return JsonHelper::decode($json);
    }

    /**
     * Returns all possible plugins for the editor
     * @return array
     */
    public function getEditorPlugins()
    {
        $pluginDir = __DIR__ . DIRECTORY_SEPARATOR;
        $pluginDir .= implode(DIRECTORY_SEPARATOR, array(
            'resources', 'lib', 'v' . $this->getVersion(), 'js', 'plugins'
        )) . DIRECTORY_SEPARATOR;

        $plugins = array();
        foreach (glob($pluginDir . '*.min.js') as $pluginFile) {
            $fileName = basename($pluginFile);
            $pluginName = str_replace('.min.js', '', $fileName);

            $pluginLabel = str_replace('_', ' ', $pluginName);
            $pluginLabel = ucwords($pluginLabel);

            $plugins[$pluginName] = $pluginLabel;
        }

        return $plugins;
    }

    /**
     * @param string $msg
     * @param string $logLevel
     * @param bool $force
     * @return void
     */
    public static function log($msg, $logLevel = LogLevel::Info, $force = false)
    {
        Craft::log($msg, $logLevel, $force, 'application', 'FroalaEditor');
    }
}