<?php

class XenTorrentTracker_DataWriter_UserUpgrade extends XFCP_XenTorrentTracker_DataWriter_UserUpgrade
{
	protected function _getFields()
	{
		$fields = parent::_getFields();
		$fields['xf_user_upgrade']['xentt_options'] = array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}');

		return $fields;
	}

	protected function _preSave()
	{
		$input = new XenForo_Input(XenForo_Application::getFc()->getRequest());
		$xenttOptions = $input->filterSingle('xentt_options', XenForo_Input::ARRAY_SIMPLE);

		if (!empty($xenttOptions))
		{
			$this->set('xentt_options', $xenttOptions);
		}

		parent::_preSave();
	}
}