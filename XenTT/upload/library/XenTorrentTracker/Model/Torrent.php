<?php

class XenTorrentTracker_Model_Torrent extends XenForo_Model
{
	const FETCH_THREAD = 0x01;
	const FETCH_USER = 0x02;
	const FETCH_FORUM = 0x04;
	const FETCH_INFO = 0x08;
	const FETCH_ATTACHMENT = 0x10;
	const FETCH_REQUEST = 0x20;

	public function getTorrentByInfoHash($infoHash)
	{
		return $this->_getDb()->fetchRow('
			SELECT * FROM xftt_torrent
			WHERE info_hash = UNHEX(?)
		', $infoHash);
	}

	public function getTorrentByThreadId($threadId)
	{
		return $this->_getDb()->fetchRow('
			SELECT * FROM xftt_torrent
			WHERE thread_id = ?
		', $threadId);
	}

	public function getTorrentById($torrentId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareTorrentFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT torrent.*, torrent.torrent_id as attachment_id
				' . $joinOptions['selectFields'] . '
			FROM xftt_torrent AS torrent
			' . $joinOptions['joinTables'] . '
			WHERE torrent.torrent_id = ?
		', $torrentId);
	}

	public function getTorrentsByIds(array $torrentIds, array $fetchOptions = array())
	{
		if (empty($torrentIds))
		{
			return array();
		}

		$joinOptions = $this->prepareTorrentFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT torrent.*, torrent.torrent_id as attachment_id
				' . $joinOptions['selectFields'] . '
			FROM xftt_torrent AS torrent
			' . $joinOptions['joinTables'] . '
			WHERE torrent.torrent_id IN (' . $this->_getDb()->quote($torrentIds) . ')
			' . $joinOptions['orderClause'] . $joinOptions['orderDirection'] . '
		', 'torrent_id');
	}

	public function countTorrents(array $conditions, array $fetchOptions = array())
	{
		$whereConditions = $this->prepareTorrentConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareTorrentFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xftt_torrent AS torrent
			' . $sqlClauses['joinTables'] . '
			WHERE ' . $whereConditions . '
		');
	}

	public function getTorrentIdsInRange(array $conditions, array $fetchOptions)
	{
		$db = $this->_getDb();

		$whereConditions = $this->prepareTorrentConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareTorrentFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $db->fetchCol($this->limitQueryResults('
			SELECT torrent.torrent_id
			FROM xftt_torrent AS torrent
			' . $sqlClauses['joinTables'] . '
			WHERE ' . $whereConditions . '
			' . $sqlClauses['orderClause'] . $sqlClauses['orderDirection'] . '
			', $limitOptions['limit'], $limitOptions['offset']
		));
	}
	
	public function getTorrents(array $conditions, array $fetchOptions)
	{
		$whereConditions = $this->prepareTorrentConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareTorrentFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults('
				SELECT torrent.*, torrent.torrent_id as attachment_id
				' . $sqlClauses['selectFields'] . '
				FROM xftt_torrent AS torrent
				' . $sqlClauses['joinTables'] . '
				WHERE ' . $whereConditions . ' 
				' . $sqlClauses['orderClause'] . $sqlClauses['orderDirection'] . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'torrent_id');
	}

	public function getTorrentsForUserId(array $conditions, array $fetchOptions = array())
	{
		$fetchOptions['forUserId'] = true;
		$fetchOptions['join'] = self::FETCH_THREAD |  self::FETCH_FORUM;

		if ($conditions['filter'] == 'my')
		{
			$fetchOptions['orderBy'] = 'torrent_id';
			return $this->getTorrents($conditions, $fetchOptions);
		}
		else
		{
			$whereConditions = $this->prepareTorrentConditions($conditions, $fetchOptions);
			$sqlClauses = $this->prepareTorrentFetchOptions($fetchOptions);
			$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

			return $this->fetchAllKeyed($this->limitQueryResults('
					SELECT peer.*, torrent.torrent_id as attachment_id, torrent.thread_id, 
						torrent.size, torrent.seeders, torrent.leechers, torrent.completed AS snatched, torrent.freeleech
						' . $sqlClauses['selectFields'] . ' 
					FROM xftt_peer AS peer
					INNER JOIN xftt_torrent AS torrent ON (torrent.torrent_id = peer.torrent_id)
					' . $sqlClauses['joinTables'] . '
					WHERE ' . $whereConditions . '
					ORDER BY peer.mtime DESC
				', $limitOptions['limit'], $limitOptions['offset']
			), 'torrent_id');
		}
	}

	public function countTorrentsForUserId(array $conditions, array $fetchOptions = array())
	{
		$fetchOptions['forUserId'] = true;

		$whereConditions = $this->prepareTorrentConditions($conditions, $fetchOptions);

		if ($conditions['filter'] == 'my')
		{
			return $this->_getDb()->fetchOne('
				SELECT COUNT(*) 
				FROM xftt_torrent AS torrent
				WHERE ' .  $whereConditions . '
			');
		}
		else
		{
			return $this->_getDb()->fetchOne('
				SELECT COUNT(*)
				FROM xftt_peer AS peer 
				INNER JOIN xftt_torrent AS torrent ON (torrent.torrent_id = peer.torrent_id)
				WHERE ' .  $whereConditions . '
			');
		}
	}

	public function prepareTorrentConditions(array $conditions, array $fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (isset($fetchOptions['forUserId'])) 
		{
			if (isset($conditions['userId']))
			{
				$sqlConditions[] = 'peer.user_id = ' . $db->quote($conditions['userId']);
			}

			switch($conditions['filter'])
			{
				case 'seed':
					$sqlConditions[] = 'peer.active = 1 AND peer.left = 0';
					break;
				case 'leech':
					$sqlConditions[] = 'peer.active = 1 AND peer.left > 0';
					break;
				case 'inactive':
					$sqlConditions[] = 'peer.active = 0';
					break;
				case 'my':
				default:
					$sqlConditions = array('torrent.user_id = ' . $db->quote($conditions['userId']));
			}

			return $this->getConditionsForClause($sqlConditions);
		}

		if (isset($conditions['category_id']))
		{
			if (is_array($conditions['category_id']))
			{
				if (!$conditions['category_id'])
				{
					$sqlConditions[] = 'torrent.category_id IS NULL';
				}
				else
				{
					$sqlConditions[] = 'torrent.category_id IN (' . $db->quote($conditions['category_id']) . ')';
				}
			}
			else
			{
				$sqlConditions[] = 'torrent.category_id = ' . $db->quote($conditions['category_id']);
			}			
		}

		if (isset($conditions['thread_id']))
		{
			if (is_array($conditions['thread_id']))
			{
				if (!$conditions['thread_id'])
				{
					$sqlConditions[] = 'torrent.thread_id IS NULL';
				}
				else
				{
					$sqlConditions[] = 'torrent.thread_id IN (' . $db->quote($conditions['thread_id']) . ')';
				}
			}
			else
			{
				$sqlConditions[] = 'torrent.thread_id = ' . $db->quote($conditions['thread_id']);
			}			
		}

		if (!empty($conditions['freeleech']))
		{
			$sqlConditions[] = 'torrent.freeleech = 1';
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function prepareTorrentFetchOptions(array $fetchOptions) 
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';
		$orderDirection = '';

		if (!empty($fetchOptions['join']))
		{		
			if ($fetchOptions['join'] & self::FETCH_INFO)
			{
				$selectFields .= ', torrent_info.file_details AS other_details';
				$joinTables .= '
					LEFT JOIN xftt_torrent_info AS torrent_info ON
						(torrent_info.torrent_id = torrent.torrent_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_ATTACHMENT)
			{
				$selectFields .= ', attachment.*';
				$joinTables .= '
					LEFT JOIN xf_attachment AS attachment ON
						(attachment.attachment_id = torrent.torrent_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_REQUEST)
			{
				$selectFields .= ', request.request_id, request.action as request_status';
				$joinTables .= '
					LEFT JOIN xftt_freeleech_request AS request ON
						(request.torrent_id = torrent.torrent_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					user.user_id, user.email, user.user_group_id,
					user.torrent_pass_version, 
					user.downloaded,
					user.uploaded,
					user.can_leech,
					user.wait_time,
					user.peers_limit,
					user.torrent_pass,
					user.seedbonus,
					IF(user.username IS NULL, thread.username, user.username) AS username,
					user.freeleech AS user_freeleech
				';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = torrent.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_FORUM)
			{
				$selectFields .= ',
					node.title as node_title, node.is_torrent_category, forum.torrent_category_image';
				$joinTables .= '
					LEFT JOIN xf_node as node ON (node.node_id = torrent.category_id)
					LEFT JOIN xf_forum as forum ON (forum.node_id = node.node_id)
				';
			}

			if (($fetchOptions['join'] & self::FETCH_THREAD))
			{
				$selectFields .= ',
					thread.title, thread.node_id, thread.reply_count, thread.prefix_id, thread.username, thread.user_id AS tuser_id';
				$joinTables .= '
					LEFT JOIN xf_thread AS thread ON
						(thread.thread_id = torrent.thread_id)';
			}
		}

		if (isset($fetchOptions['orderBy']))
		{
			switch ($fetchOptions['orderBy'])
			{
				case 'seeders':
					$orderBy = 'torrent.seeders';
					break;
				case 'snatched':
					$orderBy = 'torrent.completed';
					break;
				case 'size':
					$orderBy = 'torrent.size';
					break;
				case 'replies':
					$orderBy = 'thread.reply_count';
					break;
				case 'time':
				default:
					$orderBy = 'torrent.ctime';
			}

			$orderDirection = isset($fetchOptions['orderDirection']) ? $fetchOptions['orderDirection'] : 'desc';
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables,
			'orderClause'  => ($orderBy ? "ORDER BY $orderBy " : ''),
			'orderDirection' => $orderDirection
		);
	}

	public function saveTorrent(array &$torrentInfo)
	{
		$torrentDw = XenForo_DataWriter::create('XenTorrentTracker_DataWriter_Torrent');
		$torrentDw->bulkSet($torrentInfo);

		return $torrentDw->save();
	}

	public function deleteTorrents($torrentIds)
	{
		if (empty($torrentIds))
		{
			return;
		}

		if (!is_array($torrentIds))
		{
			$torrentIds = array($torrentIds);
		}

		$db = $this->_getDb();
		
		$torrents = $db->fetchPairs('
			SELECT torrent_id, thread_id
			FROM xftt_torrent
			WHERE torrent_id IN (' . $db->quote($torrentIds) . ')'
		);

		if (!empty($torrents))
		{
			$torrentIds = array_keys($torrents);
			$db->update('xftt_torrent', array(
				'flags' => 1
			), 'torrent_id IN (' . $db->quote($torrentIds) . ')');

			$db->delete('xftt_torrent_info',
				'torrent_id IN (' . $db->quote($torrentIds) . ')'
			);

			$db->update('xf_thread', array(
				'torrent_id' => 0
			), 'thread_id IN (' . $db->quote($torrents) . ')');
		}
	}

	public function deleteTorrent($torrentId)
	{		
		$torrent = $this->getTorrentById($torrentId);
		if (!empty($torrent))		
		{
			$db = $this->_getDb();

			$db->update('xftt_torrent', array(
				'flags' => 1
			), 'torrent_id = ' . $db->quote($torrentId));

			$db->delete('xftt_torrent_info',
				'torrent_id = ' . $db->quote($torrentId)
			);

			$db->update('xf_thread', array(
				'torrent_id' => 0
			), 'thread_id = ' . $db->quote($torrentId));
		}
	}

	public function prepareTorrents(array $torrents = array())
	{
		if (empty($torrents)) 
		{
			return array();
		}

		foreach($torrents AS $torrentId => &$torrent) {
			$torrent = $this->prepareTorrent($torrent);
		}

		return $torrents;
	}

	public function prepareTorrent(array $torrent)
	{
		if (isset($torrent['other_details']))
		{
			$torrent['other_details'] = unserialize($torrent['other_details']);
		}

		if (empty($torrent['title']) && isset($torrent['other_details']['name']))
		{
			$torrent['title'] = XenForo_Helper_String::censorString($torrent['other_details']['name']);
		}

		$torrent['anonymous'] = (isset($torrent['tuser_id']) && $torrent['tuser_id'] == 0) ? true : false;

		if (XenForo_Application::get('options')->xenTorrentMagnetLink)
		{
			if (empty($torrent['title'])) 
			{
				$torrent['title'] = '';
			}

			$torrent['magnetLink'] = $this->getMagnetLink($torrent['info_hash'], $torrent['title'], $torrent['size']);
		}

		return $torrent;
	}

	public function filterUnviewableTorrents(array $torrents)
	{
		if (empty($torrents)) 
		{
			return array();
		}

		foreach ($torrents AS $torrentId => $torrent)
		{
			if ($torrent['thread_id'] == 0 || $torrent['flags'] == 1)
			{
				unset($torrents[$torrentId]);
			}
		}

		return $torrents;
	}

	public function getPeerList($torrentId, array $fetchOptions)
	{
		if (empty($torrentId))
		{
			return;
		}

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$db = $this->_getDb();

		return $this->fetchAllKeyed($this->limitQueryResults('
				SELECT fu.*, user.username
				FROM xftt_peer AS fu
				INNER JOIN xf_user AS user ON (user.user_id = fu.user_id)
				WHERE fu.torrent_id = ' . $db->quote($torrentId) . '
				ORDER BY fu.user_id = ' . $db->quote(XenForo_Visitor::getUserId()) . ' DESC, fu.uploaded DESC
			', $limitOptions['limit'], $limitOptions['offset'])
		, 'user_id');
	}

	public function getSnatchers($torrentId, array $fetchOptions)
	{
		if (empty($torrentId))
		{
			return;
		}

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults('
			SELECT s.*, user.username
			FROM xftt_snatched AS s
			INNER JOIN xf_user AS user ON (user.user_id = s.user_id)
			WHERE s.torrent_id = ' . $this->_getDb()->quote($torrentId) . '
			ORDER BY s.mtime DESC
			', $limitOptions['limit'], $limitOptions['offset'])
		, 'user_id');
	}

	public function downloadTorrent(array &$attachment, &$torrentFile)
	{
		$this->_canDownloadTorrent($attachment);

		$torrent = XenTorrentTracker_Torrent::createFromTorrentFile($torrentFile);

		$torrent->setAnnounce($this->getAnnounceUrl($torrent->getHash()));
		$tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');

		if (XenForo_Application::get('options')->xenTorrentComment) 
		{
			$comment = utf8_substr(XenForo_Application::get('options')->xenTorrentComment, 0, 100);
			$torrent->setComment($comment);
		}

		if (XenForo_Application::get('options')->xenTorrentPrivateFlag)
		{
			$torrent->setPrivate();
		}
		
		$torrent->save($tempFile);

		$attachment['file_size'] = filesize($tempFile);
		$torrentFile = $tempFile;
	}

	public function getAnnounceUrl($infoHash = null)
	{
		$tracker = XenForo_Application::getOptions()->xenTorrentTracker;

		$host = $tracker['host'];
		if (empty($host))
		{
			throw new XenForo_Exception(new XenForo_Phrase('torrent_tracker_host_is_not_set'));
		}

		$port = $tracker['port'];
		if (empty($port))
		{
			throw new XenForo_Exception(new XenForo_Phrase('torrent_tracker_port_is_not_set'));
		}

		//$tracker['host'] = rtrim($tracker['host'], '/');
		$config = $this->_getTrackerModel()->getConfig();

		if (!empty($config['anonymous_announce']) || $infoHash === null)
		{
			return "http://$host:$port/announce";
		}
		else
		{
			$torrentPass = $this->_generateTorrentPass($infoHash, $config['torrent_pass_private_key']);		
			return "http://$host:$port/$torrentPass/announce";
		}
	}

	public function getMagnetLink($infoHash, $name, $size) 
	{
		try 
		{
			$infoHash = unpack('H*', $infoHash);
			$infoHash = reset($infoHash);

			$announceUrl = $this->getAnnounceUrl($infoHash);			
		}
		catch (Exception $e) 
		{
			return false;
		}

		$params = array(
			"xl=$size",
			"xt=urn:btih:$infoHash",
			"tr=$announceUrl",
			"dn=$name"
		);

		return ("magnet:?" . implode('&', $params));
	}	

	public function makeFreeleech($torrentId, $freeleech = true)
	{
		$torrentDw = XenForo_DataWriter::create('XenTorrentTracker_DataWriter_Torrent');
		$torrentDw->setExistingData($torrentId);
		$torrentDw->set('freeleech', $freeleech);

		return $torrentDw->save();
	}

	public function getSortFields()
	{
		return array(
			'time',
			'seeders',
			'size',
			'leechers',
			'snatched',
			'replies',
		);
	}

	public function getReseedRequestByUserId($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*) AS total
			FROM xftt_request_reseed
			WHERE user_id = ?
		', $userId);
	}

	public function insertReseedRequest($userId, $torrentId)
	{
		return $this->_getDb()->insert('xftt_request_reseed', array(
			'torrent_id' => $torrentId,
			'user_id' => $userId
		));
	}

	protected function _canDownloadTorrent(array $torrent)
	{
		if (!$this->canDownloadTorrent())
		{
			throw new XenForo_Exception(new XenForo_Phrase('do_not_have_permission'));
		}

		$options = XenForo_Application::getOptions();
		$user = XenForo_Visitor::getInstance();

		$torrent = $this->getTorrentById($torrent['attachment_id']);
		if (!$torrent)
		{
			throw new XenForo_Exception(new XenForo_Phrase('invalid_torrent_file'));
		}

		if ($torrent['user_id'] == $user['user_id'])
		{
			return true;
		}

		$snatchedBefore = $this->_getDb()->fetchRow('
			SELECT * FROM xftt_snatched 
			WHERE user_id = ? AND torrent_id = ?
		', array($user['user_id'], $torrent['torrent_id']));

		if ($snatchedBefore)
		{
			//return true;
		}

		if ($options->xenTorrentMinimumRatio > 0)
		{
			if (isset($user['ratio']) && is_numeric($user['ratio']) && $user['ratio'] < $options->xenTorrentMinimumRatio)
			{
				throw new XenForo_Exception(new XenForo_Phrase('minimum_ratio_required_to_download_torrent_is_x', array(
					'ratio'	=> $options->xenTorrentMinimumRatio
				)));
			}
		}

		if ($options->xenTorrentMinimumPost > 0)
		{
			if ($user['message_count'] < $options->xenTorrentMinimumPost)
			{
				throw new XenForo_Exception(new XenForo_Phrase('minimum_post_required_to_download_torrent_is_x', array(
					'post'	=> $options->xenTorrentMinimumPost
				)));
			}
		}

		if ($options->xenTorrentMustReply)
		{
			$replied = $this->_getDb()->fetchRow('
				SELECT post_id
				FROM xf_post
				WHERE user_id = ? AND thread_id = ? AND message_state = \'visible\'
			', array($user['user_id'], $torrent['thread_id']));

			if (empty($replied))
			{
				throw new XenForo_Exception(new XenForo_Phrase('you_must_reply_to_download_torrent'));
			}
		}

		if ($options->xenTorrentMustLike)
		{
			$thread = $this->_getDb()->fetchRow('
				SELECT first_post_id
				FROM xf_thread
				WHERE thread_id = ?
			', $torrent['thread_id']);

			if (empty($thread))
			{
				return true;
			}

			$liked = $this->_getDb()->fetchRow('
				SELECT like_id
				FROM xf_liked_content
				WHERE content_type = \'post\' AND like_user_id = ? AND content_id = ?
			', array($user['user_id'], $thread['first_post_id']));

			if (empty($liked))
			{
				throw new XenForo_Exception(new XenForo_Phrase('you_must_like_to_download_torrent'));
			}
		}
	}

	protected function _generateTorrentPass($hashInfo, $privateKey = null, array $user = null)
	{
		if ($user === null)
		{
			$user = XenForo_Visitor::getInstance();
		}

		if ($privateKey === null)
		{
			$privateKey = $this->_getDb()->fetchOne("
				SELECT value FROM xftt_config WHERE name = 'torrent_pass_private_key'
			");

			if (empty($privateKey))
			{
				return;
			}
		}

		$torrentPass = sprintf('%08x%s', $user['user_id'], substr(sha1(sprintf('%s %d %d %s', $privateKey, $user['torrent_pass_version'], 
			$user['user_id'], pack('H*', $hashInfo))), 0, 24));

		return $torrentPass;
	}

	public function canViewTorrents(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xenTorrentTracker', 'view');
	}

	public function canDownloadTorrent(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xenTorrentTracker', 'download');
	}

	public function canUploadTorrent(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xenTorrentTracker', 'upload');
	}

	public function canViewPeerList(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xenTorrentTracker', 'viewPeerList');
	}

	public function canViewSnatchList(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xenTorrentTracker', 'viewSnatchList');
	}

	public function canAcceptFreeleechRequest(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'xenTorrentTracker', 'canMakeFreeleech');
	}

	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}

	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	protected function _getTrackerModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Tracker');
	}
}