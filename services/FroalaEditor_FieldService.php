<?php

namespace Craft;

/**
 * Class FroalaEditor_FieldService
 */
class FroalaEditor_FieldService extends BaseApplicationComponent
{
    const CORE_PLUGINS = [
        'bold'            => 'Bold',
        'italic'          => 'Italic',
        'underline'       => 'Underline',
        'strikeTrough'    => 'Strike Through',
        'subscript'       => 'Subscript',
        'superscript'     => 'Superscript',
        'outdent'         => 'Outdent',
        'indent'          => 'Indent',
        'undo'            => 'Undo',
        'redo'            => 'Redo',
        'insertHR'        => 'Insert HR',
        'clearFormatting' => 'Clear Formatting',
        'selectAll'       => 'Select All',
    ];

    const CORE_LANGUAGES = [
        'ar'    => 'Arabic',
        'bs'    => 'Bosnian',
        'cs'    => 'Czech',
        'da'    => 'Danish',
        'de'    => 'German',
        'en_ca' => 'English Canada',
        'en_gb' => 'English United Kingdom',
        'es'    => 'Spanish',
        'et'    => 'Estionian',
        'fa'    => 'Persian',
        'fi'    => 'Finish',
        'fr'    => 'French',
        'he'    => 'Hebrew',
        'hr'    => 'Croatian',
        'hu'    => 'Hungarian',
        'id'    => 'Idonesian',
        'it'    => 'Italian',
        'ja'    => 'Japanese',
        'ko'    => 'Korean',
        'me'    => 'Montenegrin',
        'nb'    => 'Norwegian',
        'nl'    => 'Dutch',
        'pl'    => 'Polish',
        'pt_br' => 'Portuguese Brazil',
        'pt_pt' => 'Portuguese Portugal',
        'ro'    => 'Romanian',
        'ru'    => 'Russian',
        'sk'    => 'Slovak',
        'sr'    => 'Serbian',
        'sv'    => 'Swedish',
        'th'    => 'Thai',
        'tr'    => 'Turkish',
        'uk'    => 'Ukrainian',
        'vi'    => 'Vietnamese',
        'zh_cn' => 'Chinese China',
        'zh_tw' => 'Chinese Taiwan',
    ];

    /**
     * @var FroalaEditorPlugin
     */
    protected $plugin;

    /**
     * Return the plugin instance
     *
     * @return FroalaEditorPlugin
     */
    public function getPlugin()
    {
        if (empty($this->plugin)) {

            $this->plugin = craft()->plugins->getPlugin('froalaeditor');
        }

        return $this->plugin;
    }

    /**
     * Returns the custom config used by this field.
     *
     * @param BaseModel $settings
     * @param string    $settingsKey
     * @param string    $subDir
     *
     * @return array
     */
    public function getCustomConfig(BaseModel $settings, $settingsKey, $subDir)
    {
        $file = $settings->$settingsKey;
        $path = craft()->path->getConfigPath() . $subDir . DIRECTORY_SEPARATOR . $file;

        if (!$file || !IOHelper::fileExists($path)) {

            if ($settingsKey === 'purifierConfig') {
                return $this->getPlugin()->getCustomConfig($settingsKey, $subDir);
            }

            return [];
        }

        $json = IOHelper::getFileContents($path);

        return json_decode($json, true);
    }

    /**
     * Returns all possible plugins for the editor
     *
     * @return array
     * @throws \CException
     */
    public function getAllEditorPlugins()
    {
        $pluginDir = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        $pluginDir .= implode(DIRECTORY_SEPARATOR, [
                'resources',
                'lib',
                'v' . $this->getPlugin()->getEditorVersion(),
                'js',
                'plugins',
            ]) . DIRECTORY_SEPARATOR;

        $plugins = [];
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
     * Returns a list with all possible editor plugins
     *
     * @return array
     * @throws \CException
     */
    public function getEditorPlugins()
    {
        $editorPlugins = $this->getAllEditorPlugins();
        $pluginsEnabled = $this->getPlugin()->getSettings()->getAttribute('enabledPlugins');
        if (!empty($pluginsEnabled) && is_array($pluginsEnabled)) {
            foreach ($editorPlugins as $pluginName => $pluginLabel) {
                if (!in_array($pluginName, $pluginsEnabled)) {
                    unset($editorPlugins[$pluginName]);
                }
            }
        }

        return $editorPlugins;
    }

    /**
     * @return array
     */
    public function getTransforms()
    {
        $allTransforms = craft()->assetTransforms->getAllTransforms();
        $transformList = [];

        foreach ($allTransforms as $transform) {
            $transformList[] = [
                'handle' => HtmlHelper::encode($transform->handle),
                'name'   => HtmlHelper::encode($transform->name),
            ];
        }

        return $transformList;
    }

    /**
     * Returns the language string applicable to the Froala Editor.
     *
     * @return string
     */
    public function getLanguage()
    {
        $craftLanguage = craft()->getTargetLanguage();
        $craftLanguage = $language = strtolower(str_replace('-', '_', $craftLanguage));

        if (!array_key_exists($language, self::CORE_LANGUAGES)) {
            $language = substr($language, 0, strpos($language, '_'));
        }

        if (array_key_exists(strtolower($language), self::CORE_LANGUAGES)) {

            return $language;
        }

        return $craftLanguage;
    }
}