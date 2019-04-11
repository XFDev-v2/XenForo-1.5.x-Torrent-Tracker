<?php

class XenTorrentTracker_Model_FreeleechRequest extends XenForo_Model
{
	public function countRequests($open = 1)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*) 
			FROM xftt_freeleech_request
			WHERE open = ?
		', $open);
	}

	public function getRequestById($requestId)
	{
		return $this->_getDb()->fetchRow('
			SELECT * FROM xftt_freeleech_request 
			WHERE request_id = ?
		', 'request_id', $requestId);
	}

	public function getRequestByTorrentId($torrentId)
	{
		return $this->_getDb()->fetchRow('
			SELECT * FROM xftt_freeleech_request 
			WHERE torrent_id = ?
		', 'torrent_id', $torrentId);
	}

	public function insertRequest($torrentId)
	{
		return $this->_getDb()->insert('xftt_freeleech_request', array(
			'torrent_id'	=>	$torrentId,
			'date'	=> XenForo_Application::$time
		));
	}

	public function updateRequest($requestId, $action = '')
	{
		return $this->_getDb()->update('xftt_freeleech_request', array(
			'action' => $action,
			'open' => ($action == '') ? 1 : 0
		), 'request_id = ' . $requestId);
	}

	public function deleteRequest($torrentId)
	{
		return $this->_getDb()->delete('xftt_freeleech_request', 'request_id = ' . $requestId);
	}

	public function getRequests($open = 1, array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT request.*, user.*, torrent.size, torrent.freeleech, IF(user.username IS NULL, thread.username, user.username) AS username,
				thread.title, thread.thread_id, thread.node_id, node.title as nodetitle
				FROM xftt_freeleech_request AS request
				INNER JOIN xftt_torrent AS torrent ON (torrent.torrent_id = request.torrent_id)
				INNER JOIN xf_thread AS thread ON (thread.thread_id = torrent.thread_id)
				LEFT JOIN xf_user AS user ON (user.user_id = thread.user_id)
				LEFT JOIN xf_node AS node ON (node.node_id = thread.node_id)
				WHERE request.open = ' . $this->_getDb()->quote($open) . '
				ORDER BY request.date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'request_id');
	}

	protected function _getTorrentModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Torrent');
	}
}