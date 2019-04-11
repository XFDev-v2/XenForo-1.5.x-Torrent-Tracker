<?php

class XenTorrentTracker_ControllerAdmin_UserUpgrade extends XFCP_XenTorrentTracker_ControllerAdmin_UserUpgrade
{
	protected function _getUpgradeAddEditResponse(array $upgrade)
	{
		if (empty($upgrade['xentt_options']))
		{
			$upgrade = array_merge($upgrade, array(
				'freeleech' => 1,
				'can_leech' => 1,
				'peers_limit' => 0,
				'wait_time' => 0
			));
		}
		else
		{
			$xenttOptions = unserialize($upgrade['xentt_options']);
			if (!empty($xenttOptions)) 
			{
				$upgrade = array_merge($upgrade, $xenttOptions);
			}
		}

		return parent::_getUpgradeAddEditResponse($upgrade);
	}
}