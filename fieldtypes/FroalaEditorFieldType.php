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
     * {@inheritdoc}
     */
    public function getName()
    {
        return Craft::t('Rich Text (Froala Editor)');
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
            'settings'      => $this->getSettings(),
            'sourceOptions' => $sourceOptions,
            'editorPlugins' => $this->getEditorPlugins(),
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
            'enabledPlugins'      => [AttributeType::Mixed],
        ];
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

        // Reformat the input name into something that looks more like an ID
        $id = craft()->templates->formatInputId($name);

        // Render input HTML javascript
        $this->getInputHtmlJavascript($id, $pluginSettings, $fieldSettings);

        // Get images & files source folder ID
        $imageFolderId = $this->determineUploadImagesFolderId($fieldSettings);
        $filesFolderId = $this->determineUploadFilesFolderId($fieldSettings);

        // Return view
        $variables = [
            'id'      => $id,
            'name'    => $name,
            'value'   => $value,
            'sources' => [
                'images' => $imageFolderId,
                'files'  => $filesFolderId,
            ],
        ];

        return craft()->templates->render('froalaeditor/fieldtype/input', $variables);
    }

    // Private Methods
    // =========================================================================

    /**
     * @param BaseModel $settings
     * @return string
     */
    private function determineUploadImagesFolderId($settings)
    {
        $folderId = $this->determineUploadFolderId($settings->assetsImagesSource, $settings->assetsImagesSubPath);
        $folderPath = 'folder:' . $folderId . ':single';

        return $folderPath;
    }

    /**
     * @param BaseModel $settings
     * @return string
     */
    private function determineUploadFilesFolderId($settings)
    {
        $folderId = $this->determineUploadFolderId($settings->assetsFilesSource, $settings->assetsFilesSubPath);
        $folderPath = 'folder:' . $folderId . ':single';

        return $folderPath;
    }

    /**
     * @param int    $folderSourceId
     * @param string $folderSubPath
     * @param bool   $createDynamicFolders
     * @return int
     * @throws InvalidSubpathException
     */
    private function determineUploadFolderId($folderSourceId, $folderSubPath, $createDynamicFolders = true)
    {
        try {

            $folderId = $this->resolveSourcePathToFolderId($folderSourceId, $folderSubPath, $createDynamicFolders);

        } catch (InvalidSubpathException $e) {

            // If this is a new element, the sub path probably just contained a token that returned null, like {id}
            // so use the user's upload folder instead
            if (empty($this->element->id) || !$createDynamicFolders) {

                $userModel = craft()->userSession->getUser();
                $userFolder = craft()->assets->getUserFolder($userModel);
                $folderName = 'field_' . $this->model->id;

                $folder = craft()->assets->findFolder([
                    'parentId' => $userFolder->id,
                    'name'     => $folderName
                ]);

                if ($folder) {
                    $folderId = $folder->id;
                } else {
                    $folderId = $this->_createSubFolder($userFolder, $folderName);
                }

                IOHelper::ensureFolderExists(craft()->path->getAssetsTempSourcePath() . $folderName);
            } else {
                // Existing element, so this is just a bad subpath
                throw $e;
            }
        }

        return $folderId;
    }

    /**
     * @param      $sourceId
     * @param      $subPath
     * @param bool $createDynamicFolders
     * @return int
     * @throws InvalidSourceException
     * @throws InvalidSubpathException
     */
    private function resolveSourcePathToFolderId($sourceId, $subPath, $createDynamicFolders = true)
    {
        // Get the root folder in the source
        $rootFolder = craft()->assets->getRootFolderBySourceId($sourceId);

        // Make sure the root folder actually exists
        if (!$rootFolder) {
            throw new InvalidSourceException();
        }

        // Are we looking for a sub folder?
        $subPath = is_string($subPath) ? trim($subPath, '/') : '';

        if (strlen($subPath) === 0) {
            $folder = $rootFolder;
        } else {
            // Prepare the path by parsing tokens and normalizing slashes.
            try {
                $renderedSubPath = craft()->templates->renderObjectTemplate($subPath, $this->element);
            } catch (\Exception $e) {
                throw new InvalidSubpathException($subPath);
            }

            // Did any of the tokens return null?
            if (
                strlen($renderedSubPath) === 0 ||
                trim($renderedSubPath, '/') != $renderedSubPath ||
                strpos($renderedSubPath, '//') !== false
            ) {
                throw new InvalidSubpathException($subPath);
            }

            $subPath = IOHelper::cleanPath($renderedSubPath, craft()->config->get('convertFilenamesToAscii'));

            $folder = craft()->assets->findFolder([
                'sourceId' => $sourceId,
                'path'     => $subPath . '/'
            ]);

            // Ensure that the folder exists
            if (!$folder) {
                if (!$createDynamicFolders) {
                    throw new InvalidSubpathException($subPath);
                }

                // Start at the root, and, go over each folder in the path and create it if it's missing.
                $parentFolder = $rootFolder;

                $segments = explode('/', $subPath);

                foreach ($segments as $segment) {
                    $folder = craft()->assets->findFolder([
                        'parentId' => $parentFolder->id,
                        'name'     => $segment
                    ]);

                    // Create it if it doesn't exist
                    if (!$folder) {
                        $folderId = $this->_createSubFolder($parentFolder, $segment);
                        $folder = craft()->assets->getFolderById($folderId);
                    }

                    // In case there's another segment after this...
                    $parentFolder = $folder;
                }
            }
        }

        return $folder->id;
    }

    /**
     * @param AssetFolderModel $currentFolder
     * @param string           $folderName
     * @return integer
     */
    private function _createSubFolder(AssetFolderModel $currentFolder, $folderName)
    {
        $response = craft()->assets->createFolder($currentFolder->id, $folderName);

        if ($response->isError() || $response->isConflict()) {
            // If folder doesn't exist in DB, but we can't create it, it probably exists on the server.
            $newFolder = new AssetFolderModel(
                [
                    'parentId' => $currentFolder->id,
                    'name'     => $folderName,
                    'sourceId' => $currentFolder->sourceId,
                    'path'     => ($currentFolder->parentId ? $currentFolder->path . $folderName : $folderName) . '/'
                ]
            );
            $folderId = craft()->assets->storeFolder($newFolder);

            return $folderId;
        } else {

            $folderId = $response->getDataItem('folderId');

            return $folderId;
        }
    }

    /**
     * @param int       $id
     * @param BaseModel $pluginSettings
     * @param BaseModel $fieldSettings
     */
    private function getInputHtmlJavascript($id, BaseModel $pluginSettings, BaseModel $fieldSettings)
    {
        // Figure out the enabled plugins
        $enabledPlugins = $pluginSettings->getAttribute('enabledPlugins');
        $fieldEnabledPlugins = $fieldSettings->getAttribute('enabledPlugins');
        if (!empty($fieldEnabledPlugins) && $fieldEnabledPlugins != '*') {
            $enabledPlugins = $fieldEnabledPlugins;
        }

        // Figure out what that ID is going to look like once it has been namespaced
        $namespacedId = craft()->templates->namespaceInputId($id);

        // Get the used Froala Version
        $froalaVersion = $this->getPlugin()->getFroalaVersion();

        // Include our assets
        craft()->templates->includeCssFile('//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.4.0/css/font-awesome.min.css');

        craft()->templates->includeCssResource('froalaeditor/lib/v' . $froalaVersion . '/css/froala_editor.pkgd.min.css');
        craft()->templates->includeCssResource('froalaeditor/lib/v' . $froalaVersion . '/css/froala_style.min.css');

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
    }

    /**
     * @param string       $size
     * @param string|array $enabledPlugins
     * @return array
     */
    private function getToolbarButtons($size = 'lg', $enabledPlugins = '*')
    {
        $buttons = [
            'fullscreen',
            'bold',
            'italic',
            'underline',
            'strikeThrough',
            'subscript',
            'superscript',
            'fontFamily',
            'fontSize',
            '|',
            'color',
            'emoticons',
            'inlineStyle',
            'paragraphStyle',
            '|',
            'paragraphFormat',
            'align',
            'formatOL',
            'formatUL',
            'outdent',
            'indent',
            'quote',
            'insertHR',
            '-',
            'insertLink',
            'insertImage',
            'insertVideo',
            'insertFile',
            'insertTable',
            'undo',
            'redo',
            'clearFormatting',
            'selectAll',
            'html'
        ];

        switch ($size) {
            case 'md':
                $buttons = [
                    'fullscreen',
                    'bold',
                    'italic',
                    'underline',
                    'fontFamily',
                    'fontSize',
                    'color',
                    'paragraphStyle',
                    'paragraphFormat',
                    'align',
                    'formatOL',
                    'formatUL',
                    'outdent',
                    'indent',
                    'quote',
                    'insertHR',
                    'insertLink',
                    'insertImage',
                    'insertVideo',
                    'insertFile',
                    'insertTable',
                    'undo',
                    'redo',
                    'clearFormatting'
                ];
                break;
            case 'sm':
                $buttons = [
                    'fullscreen',
                    'bold',
                    'italic',
                    'underline',
                    'fontFamily',
                    'fontSize',
                    'insertLink',
                    'insertImage',
                    'insertTable',
                    'undo',
                    'redo'
                ];
                break;
            case 'xs':
                $buttons = [
                    'bold',
                    'italic',
                    'insertLink',
                    'insertImage',
                    'insertFile',
                    'undo',
                    'redo'
                ];
                break;

            case 'quick':
                $buttons = [
                    'ul',
                    'ol',
                    'insertLink',
                    'insertImage',
                    'insertFile'
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
     * Returns a list with all possible editor plugins
     *
     * @return array
     */
    private function getEditorPlugins()
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
}