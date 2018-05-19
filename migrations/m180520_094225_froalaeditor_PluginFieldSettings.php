<?php
namespace Craft;

/**
 * Class m180520_094225_froalaeditor_PluginFieldSettings
 */
class m180520_094225_froalaeditor_PluginFieldSettings extends BaseMigration
{
    /**
     * @var BasePlugin|FroalaEditorPlugin
     */
    protected $plugin;

	/**
	 * @return bool
     * @throws \Exception
	 */
	public function safeUp()
	{
	    $this->plugin = craft()->plugins->getPlugin('froalaEditor');

        $this->pluginSettings();
        $this->fieldSettings();

		return true;
	}

    /**
     * Update plugin settings
     */
    private function pluginSettings()
    {
        $settings = $this->plugin->getSettings();

        $this->convertCustomCssFile($settings);
        $this->convertCustomCssClasses($settings);

        // store plugin settings
        craft()->plugins->savePluginSettings($this->plugin, $settings->getAttributes());
    }

    /**
     * Updates all Froala fields' settings
     *
     * @throws \Exception
     */
    private function fieldSettings()
    {
        /**
         * @var FieldModel[] $fields
         */
        $fields = craft()->fields->getAllFields();
        foreach ($fields as $field) {
            if ($field->type !== 'FroalaEditor') {
                continue;
            }

            // get settings (as Model object)
            $settings = new Model($field->getFieldType()->getSettings()->getAttributeConfigs());
            $settings->setAttributes($field->settings);

            // convert
            $this->convertCustomCssFile($settings);
            $this->convertCustomCssClasses($settings);

            // set back
            $field->settings = $settings->getAttributes();

            if (!craft()->fields->saveField($field)) {
                FroalaEditorPlugin::log(sprintf('Failed to save migrated field "%s".', $field->handle), LogLevel::Error);
            }
        }
    }

    /**
     * Converts custom CSS File and it's type (plugin source) to new structure
     *
     * @param BaseModel $settings
     */
    private function convertCustomCssFile(BaseModel &$settings)
    {
        // when empty, it's a default (web-root relative)
        // otherwise the old version has a plugin handle in it
        if (isset($settings->customCssType)
            && !empty($settings->customCssType)
            && stripos($settings->customCssType, 'plugin:') === false
        ) {
            $settings->customCssType = 'plugin:' . $settings->customCssType;
        }
    }

    /**
     * Converts customCssClasses setting to new (editable table format)
     *
     * @param BaseModel $settings
     */
    private function convertCustomCssClasses(BaseModel &$settings)
    {
        if (isset($settings->customCssClasses)
            && !empty($settings->customCssClasses)
            && is_string($settings->customCssClasses)
        ) {

            $rows = [];
            $lines = explode("\n", $settings->customCssClasses);
            foreach ($lines as $line) {
                list($className, $displayName) = explode(':', trim($line));
                $rows[] = [
                    'className'   => trim($className),
                    'displayName' => trim($displayName),
                ];
            }

            $settings->customCssClasses = $rows;
        }
    }
}
