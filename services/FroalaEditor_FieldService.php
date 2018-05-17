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
     * Returns a list with all possible editor plugins
     *
     * @return array
     * @throws \CException
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
     * @param $value
     * @return mixed|null|string|string[]
     */
    public function prepValueFromPost($value)
    {
        if ($value) {

            if ($this->getPlugin()->getSettings()->purifyHtml) {

                $purifier = new \CHtmlPurifier();
                $purifier->setOptions($this->getPlugin()->getPurifierConfig());
                $value = $purifier->purify($value);
            }

            if ($this->getPlugin()->getSettings()->cleanupHtml) {

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