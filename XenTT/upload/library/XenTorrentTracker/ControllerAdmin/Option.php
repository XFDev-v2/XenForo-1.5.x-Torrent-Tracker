<?php

class XenTorrentTracker_ControllerAdmin_Option extends XFCP_XenTorrentTracker_ControllerAdmin_Option
{
	public function actionSave()
	{
		$response = parent::actionSave();

		$groupId = $this->_input->filterSingle('group_id', XenForo_Input::STRING);
		if ($groupId == 'xenTorrentTrackerOptions') 
		{
			$this->_getTrackerModel()->updateTrackerOptions();
		}

		return $response;
	}

	protected function _getTrackerModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Tracker');
	}
}
