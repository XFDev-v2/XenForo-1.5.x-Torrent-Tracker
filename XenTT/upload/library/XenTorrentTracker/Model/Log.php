<?php

class XenTorrentTracker_Model_Log extends XenForo_Model
{
	public function getLogById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT * FROM xftt_log
			WHERE log_id = ?
		', $id);
	}

	public function deleteLog($id)
	{
		$db = $this->_getDb();
		$db->delete('xftt_log', 'log_id = ' . $db->quote($id));
	}

	public function clearLog()
	{
		$this->_getDb()->query('TRUNCATE TABLE xftt_log');
	}

	public function prepareLogEntries($entries)
	{
		if (empty($entries))
		{
			return;
		}
		
		foreach ($entries AS &$entry)
		{
			$entry = $this->prepareLogEntry($entry);
		}

		return $entries;
	}

	public function prepareLogEntry($entry)
	{
		$entry['params'] = unserialize($entry['params']);
		$entry['action'] = new XenForo_Phrase('xtt_' . $entry['action']);

		if ($entry['is_error'])
		{
			$entry['errorClass'] = 'alert';
		}

		return $entry;
	}

	public function countLogEntries()
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xftt_log
		');
	}

	public function getLogEntries(array $fetchOptions = array())
	{
		$db = $this->_getDb();

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT * FROM xftt_log
				ORDER BY log_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'log_id');
	}
}