<?php

namespace Craft;

/**
 * Class FroalaEditorVariable
 */
class FroalaEditorVariable
{
    /**
     * @return string
     */
    public function getName()
    {
        return craft()->plugins->getPlugin('froalaEditor')->getName();
    }

    /**
     * @return array
     */
    public function getAssetSources()
    {
        $sourceOptions = [];
        foreach (craft()->assetSources->getPublicSources() as $source) {
            $sourceOptions[] = ['label' => HtmlHelper::encode($source->name), 'value' => $source->id];
        }

        return $sourceOptions;
    }
}