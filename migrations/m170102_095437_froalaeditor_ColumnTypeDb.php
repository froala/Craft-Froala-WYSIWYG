<?php

namespace Craft;

class m170102_095437_froalaeditor_columnTypeDb extends BaseMigration
{
	/**
	 * {@inheritdoc}
	 */
	public function safeUp()
	{
        $fields = craft()->fields->getAllFields();
        foreach ($fields as $field) {
            if ($field->type == 'FroalaEditor') {

                // re-save to trigger db column transformation
                craft()->fields->saveField($field);
            }
        }

		return true;
	}
}
