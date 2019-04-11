<?php

class XenTorrentTracker_Model_User extends XFCP_XenTorrentTracker_Model_User
{
	public function resetPassKey($userId)
	{
		$torrent_pass_version = mt_rand();

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$dw->setExistingData($userId);
		$dw->set('torrent_pass_version', $torrent_pass_version);

		return $dw->save();
	}

	public function prepareUserFetchOptions(array $fetchOptions)
	{
		$response = parent::prepareUserFetchOptions($fetchOptions);

		if (!empty($fetchOptions['join']) && XenTorrentTracker_Helper_Version::isCorrectPluginVersion())
		{
			if ($fetchOptions['join'] & XenForo_Model_User::FETCH_USER_FULL)
			{
				$response['selectFields'] .= ',
					IF(active_torrent.up IS NULL, 0, active_torrent.up) AS active_torrent_up, 
					IF(active_torrent.down IS NULL, 0, active_torrent.down) AS active_torrent_down
				';

				$response['joinTables'] .= '
					LEFT JOIN xftt_active_torrent AS active_torrent ON
						(active_torrent.user_id = user.user_id)';
			}
		}

		return $response;
	}

	public function prepareUser(array $userinfo)
	{
		$userinfo = parent::prepareUser($userinfo);
		$userinfo['ratio'] = !empty($userinfo['downloaded']) ? number_format($userinfo['uploaded'] / $userinfo['downloaded'], 2) : '-';

		if (!empty($userinfo['seedbonus']))
		{
			$userinfo['seedbonus'] = $userinfo['seedbonus'] / 10;
		}

		return $userinfo;
	}

	public function getVisitingGuestUser()
	{
		$userinfo = parent::getVisitingGuestUser();

		$userinfo = array_merge($userinfo, array(
			'torrent_pass_version' => 0,
			'downloaded' => 0,
			'uploaded' => 0,
			'ratio' => 0,
			'active_torrent_up' => 0,
			'active_torrent_down' => 0
 		));

		return $userinfo;
	}
}