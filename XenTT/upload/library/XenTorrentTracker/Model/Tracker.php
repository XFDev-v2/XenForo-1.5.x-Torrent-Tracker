<?php

class XenTorrentTracker_Model_Tracker extends XenForo_Model
{
	public function updateTrackerOptions()
	{
		$options = $this->_getDataRegistryModel()->get('options');

		$anonymousAnnounce =  ($options['xenTorrentPrivateFlag'] ? 0 : 1);
		$listenPort = $options['xenTorrentTracker']['port'];
		$freeleech = $options['xenTorrentGlobalFreeleech'];
		$cloudfare = $options['xenTorrentCloudfare'];

		$this->_getDb()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'anonymous_announce\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $anonymousAnnounce);

		$this->_getDb()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'listen_port\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $listenPort);

		$this->_getDb()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'freeleech\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $freeleech);

		$this->_getDb()->query('
			INSERT INTO xftt_config (name, value) VALUES (\'cloudfare\', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)
		', $cloudfare);

		$this->buildConfig();
	}

	public function buildConfig()
	{
		$config = $this->_getDb()->fetchPairs('
			SELECT name, value FROM xftt_config
		'); 

		$this->_getDataRegistryModel()->set('xenTTConfig', $config);

		return $config;
	}

	public function getConfig()
	{
		if (XenForo_Application::isRegistered('xenTTConfig')) 
		{
			return XenForo_Application::get('xenTTConfig');
		}

		if (!($config = $this->_getDataRegistryModel()->get('xenTTConfig'))) 
		{
			$config = $this->buildConfig();
		}

		XenForo_Application::set('xenTTConfig', $config);
		
		return $config;
	}

	public function getAllBanClients()
	{
		return $this->fetchAllKeyed('
			SELECT * FROM xftt_deny_from_clients
		', 'peer_id');
	}

	public function banClient($peerId, $comment = '')
	{
		$this->_getDb()->insert('xftt_deny_from_clients', array(
			'peer_id'	=> $peerId,
			'comment' => $comment
		));
	}

	public function removeBanClient($peerId)
	{
		$this->_getDb()->query('
			DELETE FROM xftt_deny_from_clients WHERE peer_id = ?
		', $peerId);
	}

	public function getAllBanIps()
	{
		return $this->fetchAllKeyed('
			SELECT * FROM xftt_deny_from_hosts
		', '');
	}

	public function banIp($begin, $end, $comment = '')
	{
		$begin = ip2long($begin);
		$end = ip2long($end);
		$comment = trim($comment);

		if ($begin !== false && $end)
		{
			$this->_getDb()->insert('xftt_deny_from_hosts', array(
				'begin'	=> $begin,
				'end'	=> $end,
				'comment' => $comment
			));

			return true;
		}
		else
		{
			throw new XenForo_Exception("Invalid Ip Address");
		}
	}

	public function removeBanIp($begin, $end)
	{
		$this->_getDb()->query('
			DELETE FROM xftt_deny_from_hosts WHERE begin = ? AND end = ?
		', array($begin, $end));
	}
}