<?php

class XenTorrentTracker_Install_Controller
{
	public static function install($previous)
	{
		$db = XenForo_Application::getDb();

		if (XenForo_Application::$versionId < 1030070)
		{
			throw new XenForo_Exception('This add-on requires XenForo 1.3.0 or higher.', true);
		}

		if (PHP_VERSION_ID < 50300)
		{
			throw new XenForo_Exception('This add-on requires PHP 5.3.0 or higher.', true);
		}

		$tables = self::getTables();
		$data = self::getData();

		if (!$previous)
		{
			foreach ($tables AS $tableSql)
			{
				try
				{
					$db->query($tableSql);
				}
				catch (Zend_Db_Exception $e) {}
			}

			foreach (self::getAlters() AS $alterSql)
			{
				try
				{
					$db->query($alterSql);
				}
				catch (Zend_Db_Exception $e) {}
			}

			foreach (self::getData() AS $dataSql)
			{
				$db->query($dataSql);
			}
		}
		else 
		{
			foreach ($tables AS $tableSql)
			{
				try
				{
					$db->query($tableSql);
				}
				catch (Zend_Db_Exception $e) {}
			}

			if ($previous['version_id'] < 9)
			{
				try
				{
					$db->query("
						UPDATE xftt_config SET value = '60' WHERE name = 'read_db_interval'
					");	

					$db->query("
						ALTER TABLE xftt_torrent ADD `last_reseed_request` int(10) unsigned NOT NULL DEFAULT '0'
					");
				}

				catch (Zend_Db_Exception $e) {}
			}
		}

		self::applyPermissionDefaults($previous ? $previous['version_id'] : false);

		XenForo_Model::create('XenTorrentTracker_Model_Tracker')->buildConfig();

		// Add torrent to attachment extension list
		$attachmentExtensions = explode("\n", XenForo_Application::get('options')->attachmentExtensions);
		if (!in_array('torrent', $attachmentExtensions))
		{
			$attachmentExtensions[] = 'torrent';

			$db->update('xf_option', array(
				'option_value' => implode("\n", $attachmentExtensions)
			), 'option_id = \'attachmentExtensions\'');

			XenForo_Model::create('XenForo_Model_Option')->rebuildOptionCache();
		}

		//XenForo_Application::setSimpleCacheData('xenTTStats', array());
	}

	public static function uninstall()
	{
		$db = XenForo_Application::get('db');

		foreach (self::getTables() AS $tableName => $tableSql)
		{
			try
			{
				$db->query("DROP TABLE IF EXISTS `$tableName`");
			}
			catch (Zend_Db_Exception $e) {}
		}

		try
		{
			$db->query("ALTER TABLE xf_node DROP is_torrent_category");
			$db->query("ALTER TABLE xf_forum DROP torrent_category_image");
			$db->query("ALTER TABLE xf_thread DROP torrent_id");
			
			$db->query("ALTER TABLE xf_user DROP peers_limit");
			$db->query("ALTER TABLE xf_user DROP wait_time");
			$db->query("ALTER TABLE xf_user DROP uploaded");
			$db->query("ALTER TABLE xf_user DROP downloaded");
			$db->query("ALTER TABLE xf_user DROP torrent_pass_version");
			$db->query("ALTER TABLE xf_user DROP torrent_pass");
			$db->query("ALTER TABLE xf_user DROP seedbonus");
			$db->query("ALTER TABLE xf_user DROP freeleech");
			$db->query("ALTER TABLE xf_user DROP can_leech");

			$db->query("ALTER TABLE xf_user_upgrade DROP xentt_options");
		}
		catch (Zend_Db_Exception $e) {}

		XenForo_Db::beginTransaction($db);

		$db->delete('xf_admin_permission_entry', "admin_permission_id = 'manageXenTracker'");
		$db->delete('xf_permission_entry', "permission_group_id = 'xenTorrentTracker'");
		$db->delete('xf_permission_entry_content', "permission_group_id = 'xenTorrentTracker'");

		XenForo_Db::commit($db);

		XenForo_Application::setSimpleCacheData('xenTTStats', false);
	}

	public static function getTables()
	{
		$tables = array();

		$tables['xftt_announce_log'] = "
			CREATE TABLE IF NOT EXISTS `xftt_announce_log` (
			  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `ipa` int(10) unsigned NOT NULL,
			  `port` int(10) NOT NULL,
			  `event` int(10) NOT NULL,
			  `info_hash` binary(20) NOT NULL,
			  `peer_id` binary(20) NOT NULL,
			  `downloaded` bigint(20) unsigned NOT NULL,
			  `left0` bigint(20) unsigned NOT NULL,
			  `uploaded` bigint(20) unsigned NOT NULL,
			  `uid` int(10) unsigned NOT NULL,
			  `mtime` int(10) unsigned NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8
		";

		$tables['xftt_cheat_log'] = "
			CREATE TABLE IF NOT EXISTS `xftt_cheat_log` (
			  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `user_id` int(10) unsigned NOT NULL,
			  `ipa` int(10) unsigned NOT NULL,
			  `peer_id` binary(20) NOT NULL,
			  `upspeed` bigint(20) unsigned NOT NULL,
			  `tstamp` int(10) unsigned NOT NULL,
			  `uploaded` bigint(20) unsigned NOT NULL,
			  `useragent` varchar(30) NOT NULL,
			  PRIMARY KEY (`log_id`),
			  KEY `uid` (`user_id`,`tstamp`),
			  KEY `tstamp` (`tstamp`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		";

		$tables['xftt_config'] = "
			CREATE TABLE IF NOT EXISTS `xftt_config` (
			  `name` varchar(255) NOT NULL,
			  `value` varchar(255) NOT NULL,
			  PRIMARY KEY (`name`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		";

		$tables['xftt_deny_from_clients'] = "
			CREATE TABLE IF NOT EXISTS `xftt_deny_from_clients` (
			  `peer_id` char(20) NOT NULL,
			  `comment` varchar(200) NOT NULL,
			  PRIMARY KEY (`peer_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		";

		$tables['xftt_deny_from_hosts'] = "
			CREATE TABLE IF NOT EXISTS `xftt_deny_from_hosts` (
			  `begin` int(10) unsigned NOT NULL,
			  `end` int(10) unsigned NOT NULL,
			  `comment` varchar(200) NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		";	

		$tables['xftt_torrent'] = "
			CREATE TABLE IF NOT EXISTS `xftt_torrent` (
			  `torrent_id` int(10) unsigned NOT NULL COMMENT 'Attachment id from xf_attachment',
			  `info_hash` binary(20) NOT NULL,
			  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
			  `thread_id` int(10) unsigned NOT NULL DEFAULT '0',
			  `category_id` int(10) unsigned NOT NULL DEFAULT '0',
			  `freeleech` tinyint(1) unsigned NOT NULL DEFAULT '0',
			  `flags` tinyint(1) unsigned NOT NULL DEFAULT '0',
			  `leechers` int(10) unsigned NOT NULL DEFAULT '0',
			  `seeders` int(10) unsigned NOT NULL DEFAULT '0',
			  `completed` int(10) unsigned NOT NULL DEFAULT '0',
			  `mtime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'last update time',
			  `ctime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'torrent creation time',
			  `size` bigint(20) unsigned NOT NULL DEFAULT '0',
			  `number_files` int(10) unsigned NOT NULL DEFAULT '0',
			  `balance` bigint(20) unsigned NOT NULL DEFAULT '0',
			  `last_reseed_request` int(10) unsigned NOT NULL DEFAULT '0',
			  PRIMARY KEY (`torrent_id`),
			  UNIQUE KEY `info_hash` (`info_hash`),
			  KEY `freeleech` (`freeleech`),
			  KEY `thread_id` (`thread_id`),
			  KEY `category_time` (`category_id`,`ctime`),
			  KEY `ctime` (`ctime`),
			  KEY `user_time` (`user_id`,`ctime`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		";

		$tables['xftt_torrent_info'] = "
			CREATE TABLE IF NOT EXISTS `xftt_torrent_info` (
			  `torrent_id` int(10) unsigned NOT NULL,
			  `file_details` mediumtext NOT NULL,
			  PRIMARY KEY (`torrent_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		";

		$tables['xftt_peer'] = "
			CREATE TABLE IF NOT EXISTS `xftt_peer` (
			  `torrent_id` int(10) unsigned NOT NULL,
			  `user_id` int(10) unsigned NOT NULL,
			  `active` tinyint(1) unsigned NOT NULL,
			  `announced` int(10) unsigned NOT NULL,
			  `completed` int(10) unsigned NOT NULL,
			  `downloaded` bigint(20) unsigned NOT NULL,
			  `uploaded` bigint(20) unsigned NOT NULL,
			  `corrupt` bigint(20) NOT NULL DEFAULT '0',
			  `left` bigint(20) unsigned NOT NULL,
			  `leechtime` int(10) unsigned NOT NULL DEFAULT '0',
			  `seedtime` int(10) unsigned NOT NULL DEFAULT '0',
			  `mtime` int(10) unsigned NOT NULL COMMENT 'last announced',
			  `down_rate` bigint(20) unsigned NOT NULL DEFAULT '0',
			  `up_rate` bigint(20) unsigned NOT NULL DEFAULT '0',
			  `useragent` varchar(30) NOT NULL DEFAULT '',
			  `peer_id` binary(20) NOT NULL,
			  `ipa` int(10) unsigned NOT NULL DEFAULT '0',
			  UNIQUE KEY `torrent_uid` (`torrent_id`,`user_id`),
			  KEY `user_id` (`user_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		";

		$tables['xftt_freeleech_request'] = "
			CREATE TABLE IF NOT EXISTS `xftt_freeleech_request` (
			  `request_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `torrent_id` int(10) unsigned NOT NULL,
			  `open` tinyint(1) unsigned NOT NULL DEFAULT '1',
			  `action` enum('accept','reject','') NOT NULL DEFAULT '',
			  `date` int(10) unsigned NOT NULL DEFAULT '0',
			  PRIMARY KEY (`request_id`),
			  UNIQUE KEY `torrent_id` (`torrent_id`),
			  KEY `date` (`date`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8
		";

		$tables['xftt_log'] = "
			CREATE TABLE IF NOT EXISTS `xftt_log` (
			  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `log_date` int(10) unsigned NOT NULL,
			  `message` text NOT NULL,
			  `params` text NOT NULL,
			  `action` varchar(50) NOT NULL,
			  `is_error` tinyint(1) unsigned NOT NULL DEFAULT '0',
			  PRIMARY KEY (`log_id`),
			  KEY `log_date` (`log_date`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8
		";

		$tables['xftt_scrap_log'] = "
			CREATE TABLE IF NOT EXISTS `xftt_scrape_log` (
			  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `ipa` int(10) unsigned NOT NULL,
			  `uid` int(10) unsigned NOT NULL,
			  `mtime` int(10) unsigned NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8
		";

		$tables['xftt_snatched'] = "
			CREATE TABLE IF NOT EXISTS `xftt_snatched` (
			  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
			  `mtime` int(10) unsigned NOT NULL DEFAULT '0',
			  `torrent_id` int(10) unsigned NOT NULL DEFAULT '0',
			  `ipa` int(10) unsigned NOT NULL DEFAULT '0',
			  KEY `torrent_id` (`torrent_id`),
			  KEY `user_id` (`user_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		";

		$tables['xftt_active_torrent'] = "
			CREATE TABLE IF NOT EXISTS `xftt_active_torrent` (
				`user_id` int(10) unsigned NOT NULL DEFAULT '0',
				`up` smallint(5) unsigned NOT NULL DEFAULT '0',
				`down` smallint(5) unsigned NOT NULL DEFAULT '0',
				PRIMARY KEY (`user_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		";

		$tables['xftt_request_reseed'] = "
			CREATE TABLE IF NOT EXISTS `xftt_request_reseed` (
				`user_id` int(10) unsigned NOT NULL DEFAULT '0',
				`torrent_id` int(10) unsigned NOT NULL DEFAULT '0',
				KEY `user_id` (`user_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		";

		return $tables;
	}

	public static function getData()
	{
		$data = array();
		$db = XenForo_Application::getDb();

		$data['xftt_config'] = "
			INSERT INTO `xftt_config` 
				(`name`, `value`) 
			VALUES
				('announce_interval', '1800'),
				('anonymous_announce', '0'),
				('anonymous_scrape', '0'),
				('auto_register', '0'),
				('cheat_system', '0'),
				('clean_up_interval', '60'),
				('column_files_fid', 'torrent_id'),
				('column_users_uid', 'user_id'),
				('daemon', '1'),
				('debug', '0'),
				('freeleech', '0'),
				('full_scrape', '0'),
				('gzip_scrape', '1'),
				('listen_port', '2710'),
				('log_access', '0'),
				('log_announce', '0'),
				('log_scrape', '0'),
				('offline_message', ''),
				('pid_file', ''),
				('query_log', ''),
				('read_config_interval', '60'),
				('read_db_interval', '60'),
				('read_db_files_interval', '30'),
				('read_db_users_interval', '30'),
				('redirect_url', " . $db->quote(XenForo_Application::get('options')->boardUrl) . "),
				('scrape_interval', '0'),
				('seedbonus', '1'),
				('seedbonus_interval', '1800'),
				('table_files', 'xftt_torrent'),
				('table_files_users', 'xftt_peer'),
				('table_users', 'xf_user'),
				('torrent_pass_private_key', " . $db->quote(self::makeSecret(27)) . "),
				('write_db_interval', '15'),
				('cloudfare', 0)
			";

		return $data;
	}

	public static function getAlters()
	{
		$alters = array();

		$alters['xf_forum'] = "
			ALTER TABLE xf_forum ADD `torrent_category_image` varchar(100) NOT NULL DEFAULT ''
		";

		$alters['xf_node'] = "
			ALTER TABLE xf_node ADD `is_torrent_category` tinyint(1) unsigned NOT NULL DEFAULT '0',
				ADD INDEX (  `is_torrent_category` )
		";

		$alters['xf_thread'] = "
			ALTER TABLE xf_thread ADD `torrent_id` int(10) unsigned NOT NULL DEFAULT '0'
		";

		$alters['xf_user'] = "
			ALTER TABLE xf_user ADD (
				`torrent_pass_version` int(10) unsigned NOT NULL DEFAULT '0',
				`downloaded` bigint(20) unsigned NOT NULL DEFAULT '0',
				`uploaded` bigint(20) unsigned NOT NULL DEFAULT '0',
				`can_leech` tinyint(1) unsigned NOT NULL DEFAULT '1',
				`wait_time` int(10) unsigned NOT NULL DEFAULT '0',
				`peers_limit` int(10) unsigned NOT NULL DEFAULT '0',
				`torrent_pass` char(32) NOT NULL DEFAULT '',
				`seedbonus` bigint(20) unsigned NOT NULL DEFAULT '0',
				`freeleech` tinyint(1) unsigned NOT NULL DEFAULT '0'
			)
		";

		$alters['xf_user_upgrade'] = "
			ALTER TABLE xf_user_upgrade ADD `xentt_options` text
		";

		return $alters;
	}

	public static function applyPermissionDefaults($previousVersion)
	{
		if (!$previousVersion)
		{
			self::applyGlobalPermission('xenTorrentTracker', 'view', 'forum', 'viewOthers', false);
			self::applyGlobalPermission('xenTorrentTracker', 'download', 'forum', 'viewAttachment', false);
			self::applyGlobalPermission('xenTorrentTracker', 'upload', 'forum', 'uploadAttachment', false);
			self::applyGlobalPermission('xenTorrentTracker', 'viewSnatchList', 'forum', 'viewContent', false);
			self::applyGlobalPermission('xenTorrentTracker', 'viewPeerList', 'forum', 'viewContent', false);
			self::applyGlobalPermission('xenTorrentTracker', 'canMakeFreeleech', 'forum', 'hardDeleteAnyPost', true);
		}

		if (!$previousVersion || $previousVersion < 6)
		{
			$db = XenForo_Application::getDb();

			XenForo_Db::beginTransaction($db);

			$db->query("
				INSERT IGNORE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, ?, ?, 'use_int', -1
				FROM xf_permission_entry
			", array('xenTorrentTracker', 'maxReseedRequest'));

			XenForo_Db::commit($db);
		}
	}

	protected static $_globalModPermCache = null;

	protected static function _getGlobalModPermissions()
	{
		if (self::$_globalModPermCache === null)
		{
			$moderators = XenForo_Application::getDb()->fetchPairs('
				SELECT user_id, moderator_permissions
				FROM xf_moderator
			');
			foreach ($moderators AS &$permissions)
			{
				$permissions = unserialize($permissions);
			}

			self::$_globalModPermCache = $moderators;
		}

		return self::$_globalModPermCache;
	}

	protected static function _updateGlobalModPermissions($userId, array $permissions)
	{
		self::$_globalModPermCache[$userId] = $permissions;

		XenForo_Application::getDb()->query('
			UPDATE xf_moderator
			SET moderator_permissions = ?
			WHERE user_id = ?
		', array(serialize($permissions), $userId));
	}

	public static function applyGlobalPermission($applyGroupId, $applyPermissionId, $dependGroupId = null, $dependPermissionId = null, $checkModerator = true)
	{
		$db = XenForo_Application::getDb();

		XenForo_Db::beginTransaction($db);

		if ($dependGroupId && $dependPermissionId)
		{
			$db->query("
				INSERT IGNORE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT user_group_id, user_id, ?, ?, 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = ?
					AND permission_id = ?
					AND permission_value = 'allow'
			", array($applyGroupId, $applyPermissionId, $dependGroupId, $dependPermissionId));
		}
		else
		{
			$db->query("
				INSERT IGNORE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, ?, ?, 'allow', 0
				FROM xf_permission_entry
			", array($applyGroupId, $applyPermissionId));
		}

		if ($checkModerator)
		{
			$moderators = self::_getGlobalModPermissions();
			foreach ($moderators AS $userId => $permissions)
			{
				if (!$dependGroupId || !$dependPermissionId || !empty($permissions[$dependGroupId][$dependPermissionId]))
				{
					$permissions[$applyGroupId][$applyPermissionId] = '1'; // string 1 is stored by the code
					self::_updateGlobalModPermissions($userId, $permissions);
				}
			}
		}

		XenForo_Db::commit($db);
	}

	protected static function makeSecret($length = 32) 
	{
		$secret = '';
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$charLen = strlen($chars) - 1;
		for ($i = 0; $i < $length; ++$i) 
		{
			$secret .= $chars[mt_rand(0, $charLen)];
		}
		
		return $secret;
	}
}