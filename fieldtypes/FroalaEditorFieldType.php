<?php
/**
 * The Froala Editor Field Type
 *
 * @package froalaeditor
 * @author  Bert Oost
 */

namespace Craft;

/**
 * Class FroalaEditorFieldType
 */
class FroalaEditorFieldType extends BaseFieldType
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Craft::t('Froala WYSIWYG');
    }

    /**
     * @inheritDoc IFieldType::defineContentAttribute()
     *
     * @return mixed
     */
    public function defineContentAttribute()
    {
        return [AttributeType::String, 'column' => ColumnType::Text];
    }

    /**
     * {@inheritdoc}
     */
    public function prepValue($value)
    {
        if (!empty($value)) {

            // Prevent everyone from having to use the |raw filter when outputting RTE content
            $charset = craft()->templates->getTwig()->getCharset();

            return new RichTextData($value, $charset);

        } else {
            return null;
        }
    }

    /**
     * @inheritDoc IFieldType::prepValueFromPost()
     *
     * @param string $value
     *
     * @return string
     */
    public function prepValueFromPost($value)
    {
        return craft()->froalaEditor_field->prepValueFromPost($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getSettingsHtml()
    {
        $sourceOptions = [];
        foreach (craft()->assetSources->getAllSources() as $source) {
            $sourceOptions[] = ['label' => $source->name, 'value' => $source->id];
        }

        return craft()->templates->render('froalaeditor/fieldtype/settings', [
            'settings'       => $this->getSettings(),
            'pluginSettings' => craft()->froalaEditor_field->getPlugin()->getSettings(),
            'sourceOptions'  => $sourceOptions,
            'editorPlugins'  => craft()->froalaEditor_field->getEditorPlugins(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function defineSettings()
    {
        return [
            'cleanupHtml'              => [AttributeType::Bool, 'default' => false],
            'purifyHtml'               => [AttributeType::Bool, 'default' => true],
            'assetsImagesSource'       => [AttributeType::Number, 'min' => 0],
            'assetsImagesSubPath'      => [AttributeType::String],
            'assetsFilesSource'        => [AttributeType::Number, 'min' => 0],
            'assetsFilesSubPath'       => [AttributeType::String],
            'customCssType'            => [AttributeType::String],
            'customCssFile'            => [AttributeType::String],
            'customCssClasses'         => [AttributeType::String],
            'customCssClassesOverride' => [AttributeType::Bool],
            'enabledPlugins'           => [AttributeType::Mixed],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getInputHtml($name, $value)
    {
        // Get settings from the plugin
        $pluginSettings = craft()->froalaEditor_field->getPlugin()->getSettings();

        // Get settings from the editor
        $fieldSettings = $this->getSettings();

        // Reformat the input name into something that looks more like an ID
        $id = craft()->templates->formatInputId($name);

        // Render input HTML javascript
        $this->getInputHtmlJavascript($id, $pluginSettings, $fieldSettings);

        // parse references
        $value = craft()->froalaEditor_field->parseRefs($value);

        // Return view
        $variables = [
            'id'    => $id,
            'name'  => $name,
            'value' => craft()->froalaEditor_field->parseRefs($value),
        ];

        return craft()->templates->render('froalaeditor/fieldtype/input', $variables);
    }

    /**
     * @param int $id
     * @param BaseModel $pluginSettings
     * @param BaseModel $fieldSettings
     */
    private function getInputHtmlJavascript($id, BaseModel $pluginSettings, BaseModel $fieldSettings)
    {
        // Figure out what that ID is going to look like once it has been namespaced
        $namespacedId = craft()->templates->namespaceInputId($id);

        // Get the used Froala Version
        $froalaVersion = craft()->froalaEditor_field->getPlugin()->getEditorVersion();

        // Include our assets
        craft()->templates->includeCssFile('//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.4.0/css/font-awesome.min.css');

        craft()->templates->includeCssResource('froalaeditor/lib/v' . $froalaVersion . '/css/froala_editor.pkgd.min.css');
        craft()->templates->includeCssResource('froalaeditor/lib/v' . $froalaVersion . '/css/froala_style.min.css');
        craft()->templates->includeCssResource('froalaeditor/css/theme.css');

        craft()->templates->includeJsResource('froalaeditor/lib/v' . $froalaVersion . '/js/froala_editor.pkgd.min.js');

        // custom replacements
        craft()->templates->includeJsResource('froalaeditor/js/plugins/craft.js');
        craft()->templates->includeJsResource('froalaeditor/js/FroalaEditorConfig.js');
        craft()->templates->includeJsResource('froalaeditor/js/FroalaEditorInput.js');

        // Include a custom css files (per field or plugin-wide)
        $this->includeCustomCSSFile($pluginSettings, $fieldSettings);

        $settings = [
            'id'             => $namespacedId,
            'isAdmin'        => craft()->userSession->isAdmin(),
            'editorConfig'   => [
                'craftLinkElementType'       => 'Entry',
                'craftLinkElementRefHandle'  => 'entry',
                'craftAssetElementType'      => 'Asset',
                'craftAssetElementRefHandle' => 'asset',
                'craftImageTransforms'       => craft()->froalaEditor_field->getTransforms(),
                'craftImageSources'          => [
                    craft()->froalaEditor_field->determineFolderId(
                        $fieldSettings->assetsImagesSource,
                        $fieldSettings->assetsImagesSubPath
                    ),
                ],
                'craftFileSources'           => [
                    craft()->froalaEditor_field->determineFolderId(
                        $fieldSettings->assetsFilesSource,
                        $fieldSettings->assetsFilesSubPath
                    ),
                ],
            ],
            'pluginSettings' => $pluginSettings,
            'fieldSettings'  => $fieldSettings,
            'corePlugins'    => FroalaEditor_FieldService::CORE_PLUGINS,
        ];

        // Activate editor
        craft()->templates->includeJs('new Craft.FroalaEditorInput(' . JsonHelper::encode($settings) . ');');
    }

    /**
     * @param BaseModel $pluginSettings
     * @param BaseModel $fieldSettings
     */
    private function includeCustomCSSFile(BaseModel $pluginSettings, BaseModel $fieldSettings)
    {
        $customCssType = $fieldSettings->getAttribute('customCssType');
        $customCssFile = $fieldSettings->getAttribute('customCssFile');
        if (empty($customCssFile)) {
            $customCssType = $pluginSettings->getAttribute('customCssType');
            $customCssFile = $pluginSettings->getAttribute('customCssFile');
        }

        if (!empty($customCssFile)) {

            switch ($customCssType) {
                case (substr($customCssType, 0, 6) === 'plugin'):
                    $pluginHandle = substr($customCssType, 7);
                    craft()->templates->includeCssResource($pluginHandle . '/' . $customCssFile);
                    break;

                case (substr($customCssType, 0, 6) === 'source'):
                    $sourceId = substr($customCssType, 7);
                    $source = craft()->assetSources->getSourceById($sourceId);
                    $customCssFile = rtrim($source->settings['url'], '/') . '/' . ltrim($customCssFile, '/');
                // no-break
                default:
                    // strip left slash, to be sure
                    craft()->templates->includeCssFile('/' . ltrim($customCssFile, '/'));
                    break;
            }
        }
    }
}