<?php

namespace Craft;

/**
 * Class FroalaEditor_SettingsController
 */
class FroalaEditor_SettingsController extends BaseController
{
    /**
     * @param array $variables
     * @return null
     * @throws \CException
     * @throws HttpException
     */
    public function actionShow(array $variables = [])
    {
        if (!isset($variables['settingsType'])) {
            $this->redirect('froala-editor/settings/general');
        }

        // when tab not exists, redirect to general
        $possiblePages = ['general', 'plugins', 'customcss'];
        if (!in_array($variables['settingsType'], $possiblePages)) {
            $this->redirect('froala-editor/settings/general');
        }

        /**
         * @var FroalaEditorPlugin $plugin
         */
        $plugin = craft()->plugins->getPlugin('froalaEditor');
        $variables['settings'] = $plugin->getSettings();

        switch ($variables['settingsType']) {
            case 'general':
                $variables['purifierConfigOptions'] = $plugin->getCustomConfigOptions('htmlpurifier');
                break;

            case 'plugins':
                $variables['plugins'] = craft()->froalaEditor_field->getAllEditorPlugins();
                break;

            case 'customcss':
                // nothing yet
                break;
        }

        $this->renderTemplate('froalaEditor/settings/' . $variables['settingsType'], $variables);
    }
}