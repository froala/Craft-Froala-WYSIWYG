<?php
/**
 * The Froala Editor Field Type
 *
 * @package froalaeditor
 * @author  Bert Oost
 */

namespace Craft;

class FroalaEditorFieldType extends BaseFieldType
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Craft::t('Rich Text (Froala Editor)');
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
            'pluginSettings' => [
                'customCssFile'    => craft()->froalaEditor_field->getPlugin()->getSettings()->getAttribute('customCssFile'),
                'customCssClasses' => craft()->froalaEditor_field->getPlugin()->getSettings()->getAttribute('customCssClasses'),
            ],
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
            'assetsImagesSource'  => [AttributeType::Number, 'min' => 0],
            'assetsImagesSubPath' => [AttributeType::String],
            'assetsFilesSource'   => [AttributeType::Number, 'min' => 0],
            'assetsFilesSubPath'  => [AttributeType::String],
            'customCssType'       => [AttributeType::String],
            'customCssFile'       => [AttributeType::String],
            'customCssClasses'    => [AttributeType::String],
            'enabledPlugins'      => [AttributeType::Mixed],
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

        // Return view
        $variables = [
            'id'    => $id,
            'name'  => $name,
            'value' => $value,
        ];

        return craft()->templates->render('froalaeditor/fieldtype/input', $variables);
    }

    /**
     * @param int       $id
     * @param BaseModel $pluginSettings
     * @param BaseModel $fieldSettings
     */
    private function getInputHtmlJavascript($id, BaseModel $pluginSettings, BaseModel $fieldSettings)
    {
        // Figure out what that ID is going to look like once it has been namespaced
        $namespacedId = craft()->templates->namespaceInputId($id);

        // Get the used Froala Version
        $froalaVersion = craft()->froalaEditor_field->getPlugin()->getVersion();

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
        $customCssType = $fieldSettings->getAttribute('customCssType');
        $customCssFile = $fieldSettings->getAttribute('customCssFile');
        if (empty($customCssFile)) {
            $customCssType = $pluginSettings->getAttribute('customCssType');
            $customCssFile = $pluginSettings->getAttribute('customCssFile');
        }

        if (!empty($customCssFile)) {

            // when not empty css type, it is a plugin resource
            if (!empty($customCssType)) {
                craft()->templates->includeCssResource($customCssType . '/' . $customCssFile);
            } else {
                // strip left slash, to be sure
                craft()->templates->includeCssFile('/' . ltrim($customCssFile, '/'));
            }
        }

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
                    )
                ],
                'craftFileSources'           => [
                    craft()->froalaEditor_field->determineFolderId(
                        $fieldSettings->assetsFilesSource,
                        $fieldSettings->assetsFilesSubPath
                    )
                ],
            ],
            'pluginSettings' => $pluginSettings,
            'fieldSettings'  => $fieldSettings,
            'corePlugins'    => FroalaEditor_FieldService::CORE_PLUGINS,
        ];

        // Activate editor
        craft()->templates->includeJs('new Craft.FroalaEditorInput(' . JsonHelper::encode($settings) . ');');
    }
}