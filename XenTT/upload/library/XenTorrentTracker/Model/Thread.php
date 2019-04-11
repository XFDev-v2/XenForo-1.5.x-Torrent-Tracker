<?php

class XenTorrentTracker_Model_Thread extends XFCP_XenTorrentTracker_Model_Thread
{
	public function getThreadsInForum($forumId, array $conditions = array(), array $fetchOptions = array())
	{
		$fetchOptions['joinTorrent'] = true;
		return parent::getThreadsInForum($forumId, $conditions, $fetchOptions);
	}

	public function prepareThreadFetchOptions(array $fetchOptions)
	{
		$joinOptions = parent::prepareThreadFetchOptions($fetchOptions);

		if (isset($fetchOptions['joinTorrent']))
		{
			$joinOptions['selectFields'] .= ',
				torrent.seeders, torrent.leechers, torrent.completed, torrent.freeleech AS t_freeleech, torrent.size AS torrent_size';

			$joinOptions['joinTables'] .= '
				LEFT JOIN xftt_torrent AS torrent ON
					(torrent.torrent_id = thread.torrent_id)';
		}

		return $joinOptions;
	}
}