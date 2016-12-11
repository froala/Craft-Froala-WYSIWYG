<?php
/**
 * The Froala Editor Field Type
 *
 * @package froalaeditor
 * @author Bert Oost
 */

namespace Craft;

class FroalaEditorFieldType extends BaseFieldType
{
    /**
     * Return the plugin instance
     * @return FroalaEditorPlugin
     */
    public function getPlugin()
    {
        $plugin = craft()->plugins->getPlugin('froalaeditor');
        return $plugin;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Craft::t('Rich Text (Froala Editor)');
    }

    /**
     * {@inheritdoc}
     */
    public function onAfterElementSave()
    {
        mail('b@b.b', 'test', 'test content');

        parent::onAfterElementSave();
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
        $sourceOptions = array();
        foreach (craft()->assetSources->getAllSources() as $source) {
            $sourceOptions[] = array('label' => $source->name, 'value' => $source->id);
        }

        return craft()->templates->render('froalaeditor/fieldtype/settings', array(
            'settings' => $this->getSettings(),
            'sourceOptions' => $sourceOptions,
            'editorPlugins' => $this->getEditorPlugins(),
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function defineSettings()
    {
        return array(
            'assetsImagesSource' => array(AttributeType::Number, 'min' => 0),
            'assetsImagesSubPath' => array(AttributeType::String),
            'assetsFilesSource' => array(AttributeType::Number, 'min' => 0),
            'assetsFilesSubPath' => array(AttributeType::String),
            'enabledPlugins' => array(AttributeType::Mixed),
        );
    }

    /**
     * @param string $size
     * @param string|array $enabledPlugins
     * @return array
     */
    public function getToolbarButtons($size = 'lg', $enabledPlugins = '*')
    {
        $buttons = [
            'fullscreen', 'bold', 'italic', 'underline', 'strikeThrough', 'subscript', 'superscript', 'fontFamily', 'fontSize', '|',
            'color', 'emoticons', 'inlineStyle', 'paragraphStyle', '|', 'paragraphFormat', 'align', 'formatOL', 'formatUL', 'outdent',
            'indent', 'quote', 'insertHR', '-', 'insertLink', 'insertImage', 'insertVideo', 'insertFile', 'insertTable', 'undo', 'redo',
            'clearFormatting', 'selectAll', 'html'
        ];

        switch ($size) {
            case 'md':
                $buttons = 	[
                    'fullscreen', 'bold', 'italic', 'underline', 'fontFamily', 'fontSize', 'color', 'paragraphStyle', 'paragraphFormat',
                    'align', 'formatOL', 'formatUL', 'outdent', 'indent', 'quote', 'insertHR', 'insertLink', 'insertImage', 'insertVideo',
                    'insertFile', 'insertTable', 'undo', 'redo', 'clearFormatting'
                ];
                break;
            case 'sm':
                $buttons = [
                    'fullscreen', 'bold', 'italic', 'underline', 'fontFamily', 'fontSize',
                    'insertLink', 'insertImage', 'insertTable', 'undo', 'redo'
                ];
                break;
            case 'xs':
                $buttons = [
                    'bold', 'italic', 'insertLink', 'insertImage', 'insertFile', 'undo', 'redo'
                ];
                break;

            case 'quick':
                $buttons = [
                    'ul', 'ol', 'insertLink', 'insertImage', 'insertFile'
                ];
                break;
        }

        // compare against enabled plugins
        if ($enabledPlugins != '*' && is_array($enabledPlugins)) {

            // @TODO filter buttons against enabled plugins
        }

        // -------------------------------
        // Craft's replacements

        foreach ($buttons as $key => $button) {
            switch ($button) {
                case 'link':
                case 'insertLink':
                    $buttons[$key] = 'insertLinkEntry';
                    break;
                case 'image':
                case 'insertImage':
                    $buttons[$key] = 'insertAssetImage';
                    break;
                case 'file':
                case 'insertFile':
                    $buttons[$key] = 'insertAssetFile';
                    break;
            }
        }

        return $buttons;
    }

    /**
     * {@inheritdoc}
     */
    public function getInputHtml($name, $value)
    {
        // Get settings from the plugin
        $pluginSettings = $this->getPlugin()->getSettings();

        // Get settings from the editor
        $fieldSettings = $this->getSettings();

//        $imageSource = $this->getAssetSourceByType('image');
//
//var_dump($imageSource);
//exit;
        // Figure out the enabled plugins
        $enabledPlugins = $pluginSettings->getAttribute('enabledPlugins');
        $fieldEnabledPlugins = $fieldSettings->getAttribute('enabledPlugins');
        if (!empty($fieldEnabledPlugins) && $fieldEnabledPlugins != '*') {
            $enabledPlugins = $fieldEnabledPlugins;
        }

        // Reformat the input name into something that looks more like an ID
        $id = craft()->templates->formatInputId($name);

        // Figure out what that ID is going to look like once it has been namespaced
        $namespacedId = craft()->templates->namespaceInputId($id);

        // Get the used Froala Version
        $froalaVersion = $this->getPlugin()->getFroalaVersion();

        // Include our assets
        craft()->templates->includeCssFile('//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.4.0/css/font-awesome.min.css');

        craft()->templates->includeCssResource('froalaeditor/lib/v' . $froalaVersion . '/css/froala_editor.pkgd.min.css');
        craft()->templates->includeCssResource('froalaeditor/lib/v' . $froalaVersion . '/css/froala_style.min.css');
        craft()->templates->includeCssResource('froalaeditor/css/cp.css');

        craft()->templates->includeJsResource('froalaeditor/lib/v' . $froalaVersion . '/js/froala_editor.pkgd.min.js');

        craft()->templates->includeJsResource('froalaeditor/js/icons.js');
        craft()->templates->includeJsResource('froalaeditor/js/buttons.js');
        craft()->templates->includeJsResource('froalaeditor/js/quickInsert.js');

        // Activate editor
        craft()->templates->includeJs("$('#{$namespacedId}').froalaEditor({
            key: '" . $pluginSettings->getAttribute('licenseKey') . "'
            " . ((!empty($enabledPlugins) && $enabledPlugins != '*') ? ", pluginsEnabled: ['" . implode("','", $enabledPlugins) . "']" : "") . "
            , toolbarButtons: ['" . implode("','", $this->getToolbarButtons('lg', $enabledPlugins)) . "']
            , toolbarButtonsMD: ['" . implode("','", $this->getToolbarButtons('md', $enabledPlugins)) . "']
            , toolbarButtonsSM: ['" . implode("','", $this->getToolbarButtons('sm', $enabledPlugins)) . "']
            , toolbarButtonsXS: ['" . implode("','", $this->getToolbarButtons('xs', $enabledPlugins)) . "']
            , quickInsertButtons: ['" . implode("','", $this->getToolbarButtons('quick', $enabledPlugins)) . "']
        });");

        return craft()->templates->render('froalaeditor/fieldtype/input', array(
            'id'  => $id,
            'name'  => $name,
            'value' => $value,
        ));
    }

    /**
     * Returns a list with all possible editor plugins
     *
     * @return array
     */
    public function getEditorPlugins()
    {
        $editorPlugins = $this->getPlugin()->getEditorPlugins();
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
     * @param string $type
     * @return array
     * @throws InvalidSubpathException
     */
    public function getAssetSourceByType($type)
    {
        $sources = [];
        $settings = $this->getSettings();

        $assetSourceId = ($type == 'image') ? $settings->assetsImagesSource : $settings->assetsFilesSource;
        $assetSourceSubPath = ($type == 'image') ? $settings->assetsImagesSubPath : $settings->assetsFilesSubPath;

        if (!$assetSourceId) {
            $assetSourceId = craft()->assetSources->getPublicSourceIds($assetSourceId);
        }

        // Prepare the path by parsing tokens and normalizing slashes.
        try {
            $renderedSubPath = craft()->templates->renderObjectTemplate($assetSourceSubPath, $this->element);
        } catch (\Exception $e) {
            throw new InvalidSubpathException($assetSourceSubPath);
        }

        // Did any of the tokens return null?
        if (
            strlen($renderedSubPath) === 0 ||
            trim($renderedSubPath, '/') != $renderedSubPath ||
            strpos($renderedSubPath, '//') !== false
        ) {
            throw new InvalidSubpathException($assetSourceSubPath);
        }

        $subPath = IOHelper::cleanPath($renderedSubPath, craft()->config->get('convertFilenamesToAscii'));

        $folders = craft()->assets->findFolders([
            'sourceId' => $assetSourceId,
            'parentId' => ':empty:',
            'path' => $subPath . '/',
        ]);

        foreach ($folders as $folder) {
            $sources[] = 'folder:' . $folder->id;
        }

        return $sources;
    }
}