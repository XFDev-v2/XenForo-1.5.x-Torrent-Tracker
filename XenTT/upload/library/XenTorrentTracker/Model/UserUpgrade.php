<?php

class XenTorrentTracker_Model_UserUpgrade extends XFCP_XenTorrentTracker_Model_UserUpgrade
{
	public function upgradeUser($userId, array $upgrade, $allowInsertUnpurchasable = false, $endDate = null)
	{
		$upgradeRecordId = parent::upgradeUser($userId, $upgrade, $allowInsertUnpurchasable, $endDate);
		if (!$upgradeRecordId || empty($upgrade['xentt_options']))
		{
			return $upgradeRecordId;
		}

		$active = $this->getActiveUserUpgradeRecordById($upgradeRecordId);
		$xenttOptions = unserialize($upgrade['xentt_options']);

		if ($active && !empty($xenttOptions))
		{
			$db = $this->_getDb();
			$userInfo = $db->fetchRow('
				SELECT wait_time, can_leech, freeleech, peers_limit FROM xf_user
				WHERE user_id = ?
			', $userId);

			$activeExtra = unserialize($active['extra']);
			$activeExtra += array(
				'xenttOptions' => $userInfo
			);

			$db->update('xf_user_upgrade_active', array(
				'extra' => serialize($activeExtra)
			), 'user_upgrade_record_id = ' . $db->quote($upgradeRecordId));

			$db->update('xf_user', array(
				'can_leech' => (empty($xenttOptions['can_leech']) && $userInfo['can_leech'] == 0) ? 0 : 1,
				'freeleech' => (empty($xenttOptions['freeleech']) && $userInfo['freeleech'] == 0) ? 0 : 1,
				'wait_time' => ($userInfo['wait_time'] > 0) ? $xenttOptions['wait_time'] : 0,
				'peers_limit' => ($userInfo['peers_limit'] > 0) ? $xenttOptions['peers_limit'] : 0,
			), 'user_id = ' . $db->quote($userId));
		}

		return $upgradeRecordId;
	}

	public function downgradeUserUpgrades(array $upgrades, $sendAlert = true)
	{
		if (!$upgrades)
		{
			return;
		}

		parent::downgradeUserUpgrades($upgrades, $sendAlert);
		$db = $this->_getDb();

		foreach ($upgrades AS $upgrade)
		{
			$extra = unserialize($upgrade['extra']);
			if (!empty($extra['xenttOptions']))
			{
				$db->update('xf_user', $extra['xenttOptions'], 'user_id = ' . $db->quote($upgrade['user_id']));
			}
		}
	}
}