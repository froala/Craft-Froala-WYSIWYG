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
        if ($value) {

            $plugin = craft()->froalaEditor_field->getPlugin();
            $pluginSettings = $plugin->getSettings();

            if ($pluginSettings->purifyHtml) {

                $purifier = new \CHtmlPurifier();
                $purifier->setOptions($plugin->getCustomConfig('purifierConfig', 'htmlpurifier'));
                $value = $purifier->purify($value);
            }

            if ($pluginSettings->cleanupHtml) {

                // Remove <span> and <font> tags
                $value = preg_replace('/<(?:span|font)\b[^>]*>/', '', $value);
                $value = preg_replace('/<\/(?:span|font)>/', '', $value);

                // Remove inline styles
                $value = preg_replace('/(<(?:h1|h2|h3|h4|h5|h6|p|div|blockquote|pre|strong|em|b|i|u|a)\b[^>]*)\s+style="[^"]*"/', '$1', $value);

                // Remove empty tags
                $value = preg_replace('/<(h1|h2|h3|h4|h5|h6|p|div|blockquote|pre|strong|em|a|b|i|u)\s*><\/\1>/', '', $value);
            }
        }

        // Find any element URLs and swap them with ref tags
        $value = preg_replace_callback('/(href=|src=)([\'"])[^\'"#]+?(#[^\'"#]+)?(?:#|%23)(\w+):(\d+)(:' . HandleValidator::$handlePattern . ')?\2/', function ($matches) {

            // Create the ref tag, and make sure :url is in there
            $refTag = '{' . $matches[4] . ':' . $matches[5] . (!empty($matches[6]) ? $matches[6] : ':url') . '}';
            $hash = (!empty($matches[3]) ? $matches[3] : '');

            if ($hash) {

                // Make sure that the hash isn't actually part of the parsed URL
                // (someone's Entry URL Format could be "#{slug}", etc.)
                $url = craft()->elements->parseRefs($refTag);

                if (mb_strpos($url, $hash) !== false) {
                    $hash = '';
                }
            }

            return $matches[1] . $matches[2] . $refTag . $hash . $matches[2];

        }, $value);

        // Encode any 4-byte UTF-8 characters.
        $value = StringHelper::encodeMb4($value);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getSettingsHtml()
    {
        $plugin = craft()->froalaEditor_field->getPlugin();
        $sourceOptions = [];
        foreach (craft()->assetSources->getAllSources() as $source) {
            $sourceOptions[] = ['label' => $source->name, 'value' => $source->id];
        }

        return craft()->templates->render('froalaeditor/fieldtype/settings', [
            'settings'            => $this->getSettings(),
            'pluginSettings'      => $plugin->getSettings(),
            'sourceOptions'       => $sourceOptions,
            'editorPlugins'       => craft()->froalaEditor_field->getEditorPlugins(),
            'editorConfigOptions' => $plugin->getCustomConfigOptions('froalaeditor'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function defineSettings()
    {
        return [
            'assetsImagesSource'       => [AttributeType::Number, 'min' => 0],
            'assetsImagesSubPath'      => [AttributeType::String],
            'assetsFilesSource'        => [AttributeType::Number, 'min' => 0],
            'assetsFilesSubPath'       => [AttributeType::String],
            'editorConfig'             => [AttributeType::String],
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
        $value = $this->parseRefs($value);

        // Return view
        $variables = [
            'id'    => $id,
            'name'  => $name,
            'value' => $value,
        ];

        return craft()->templates->render('froalaeditor/fieldtype/input', $variables);
    }

    /**
     * @param integer   $id
     * @param BaseModel $pluginSettings
     * @param BaseModel $fieldSettings
     *
     * @throws InvalidSourceException
     * @throws InvalidSubpathException
     */
    private function getInputHtmlJavascript($id, BaseModel $pluginSettings, BaseModel $fieldSettings)
    {
        // Figure out what that ID is going to look like once it has been namespaced
        $namespacedId = craft()->templates->namespaceInputId($id);

        // Get the used Froala Version
        $froalaVersion = craft()->froalaEditor_field->getPlugin()->getEditorVersion();
        $froalaLanguage = craft()->froalaEditor_field->getLanguage();

        // Include our assets
        craft()->templates->includeCssFile('//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.4.0/css/font-awesome.min.css');

        craft()->templates->includeCssResource('froalaeditor/lib/v' . $froalaVersion . '/css/froala_editor.pkgd.min.css');
        craft()->templates->includeCssResource('froalaeditor/lib/v' . $froalaVersion . '/css/froala_style.min.css');
        craft()->templates->includeCssResource('froalaeditor/css/theme.css');

        craft()->templates->includeJsResource('froalaeditor/lib/v' . $froalaVersion . '/js/froala_editor.pkgd.min.js');
        craft()->templates->includeJsResource('froalaeditor/lib/v' . $froalaVersion . '/js/languages/' . $froalaLanguage . '.js');

        // custom replacements
        craft()->templates->includeJsResource('froalaeditor/js/plugins/craft.js');
        craft()->templates->includeJsResource('froalaeditor/js/FroalaEditorConfig.js');
        craft()->templates->includeJsResource('froalaeditor/js/FroalaEditorInput.js');

        // Include a custom css files (per field or plugin-wide)
        $this->includeCustomCSSFile($pluginSettings);

        // get custom config (field OR plugin)
        $customEditorConfig = craft()->froalaEditor_field
            ->getCustomConfig($fieldSettings, 'editorConfig', 'froalaeditor');

        if (empty($customEditorConfig)) {

            $customEditorConfig = craft()->froalaEditor_field->getPlugin()
                ->getCustomConfig('editorConfig', 'froalaeditor');
        }

        $settings = [
            'id'             => $namespacedId,
            'isAdmin'        => craft()->userSession->isAdmin(),
            'editorConfig'   => array_merge([
                'craftLinkElementType'       => 'Entry',
                'craftLinkElementRefHandle'  => 'entry',
                'craftAssetElementType'      => 'Asset',
                'craftAssetElementRefHandle' => 'asset',
                'craftImageTransforms'       => craft()->froalaEditor_field->getTransforms(),
                'craftImageSources'          => [
                    $this->determineFolderId(
                        $fieldSettings->assetsImagesSource,
                        $fieldSettings->assetsImagesSubPath
                    ),
                ],
                'craftFileSources'           => [
                    $this->determineFolderId(
                        $fieldSettings->assetsFilesSource,
                        $fieldSettings->assetsFilesSubPath
                    ),
                ],
                'language' => $froalaLanguage,
            ], $customEditorConfig),
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
    private function includeCustomCSSFile(BaseModel $pluginSettings)
    {
        $customCssType = $pluginSettings->getAttribute('customCssType');
        $customCssFile = $pluginSettings->getAttribute('customCssFile');

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

    /**
     * @param $value
     * @return null|string|string[]
     */
    public function parseRefs($value)
    {
        if ($value instanceof RichTextData) {

            $value = $value->getRawContent();
        }

        if (strpos($value, '{') !== false) {

            // Preserve the ref tags with hashes {type:id:url} => {type:id:url}#type:id
            $value = preg_replace_callback('/(href=|src=)([\'"])(\{(\w+\:\d+\:' . HandleValidator::$handlePattern . ')\})(#[^\'"#]+)?\2/', function ($matches) {
                return $matches[1] . $matches[2] . $matches[3] . (!empty($matches[5]) ? $matches[5] : '') . '#' . $matches[4] . $matches[2];
            }, $value);

            // Now parse 'em
            $value = craft()->elements->parseRefs($value);
        }

        return $value;
    }

    /**
     * @param int $folderSourceId
     * @param string $folderSubPath
     * @param bool $createDynamicFolders
     * @return string
     * @throws \Exception
     * @throws InvalidSubpathException
     * @throws InvalidSourceException
     */
    public function determineFolderId($folderSourceId, $folderSubPath, $createDynamicFolders = true)
    {
        try {

            $folderId = $this->resolveSourcePathToFolderId($folderSourceId, $folderSubPath, $createDynamicFolders);
            $folderId = 'folder:' . $folderId . ':single';

        } catch (InvalidSubpathException $e) {

            // If this is a new element, the sub path probably just contained a token that returned null, like {id}
            // so use the user's upload folder instead
            if (empty($this->element->id) || !$createDynamicFolders) {

                $userModel = craft()->userSession->getUser();
                $userFolder = craft()->assets->getUserFolder($userModel);
                $folderName = 'field_' . $this->model->id;

                $folder = craft()->assets->findFolder([
                    'parentId' => $userFolder->id,
                    'name'     => $folderName,
                ]);

                if ($folder) {
                    $folderId = $folder->id;
                } else {
                    $folderId = $this->createSubFolder($userFolder, $folderName);
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
                'path'     => $subPath . '/',
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
                        'name'     => $segment,
                    ]);

                    // Create it if it doesn't exist
                    if (!$folder) {
                        $folderId = $this->createSubFolder($parentFolder, $segment);
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
     * @param string $folderName
     * @return integer
     */
    private function createSubFolder(AssetFolderModel $currentFolder, $folderName)
    {
        $response = craft()->assets->createFolder($currentFolder->id, $folderName);

        if ($response->isError() || $response->isConflict()) {
            // If folder doesn't exist in DB, but we can't create it, it probably exists on the server.
            $newFolder = new AssetFolderModel(
                [
                    'parentId' => $currentFolder->id,
                    'name'     => $folderName,
                    'sourceId' => $currentFolder->sourceId,
                    'path'     => ($currentFolder->parentId ? $currentFolder->path . $folderName : $folderName) . '/',
                ]
            );
            $folderId = craft()->assets->storeFolder($newFolder);

            return $folderId;
        } else {

            $folderId = $response->getDataItem('folderId');

            return $folderId;
        }
    }
}