<?php

class XenTorrentTracker_DataWriter_Thread extends XFCP_XenTorrentTracker_DataWriter_Thread
{
	protected function _getFields()
	{
		$fields = parent::_getFields();
		$fields['xf_thread']['torrent_id'] = array('type' => self::TYPE_UINT, 'default' => 0);

		return $fields;
	}

	protected function _discussionPreSave()
	{
		parent::_discussionPreSave();

		$attachmentHash = isset($_POST['attachment_hash']) ? $_POST['attachment_hash'] : '';

		if ($this->isInsert() && !empty($attachmentHash))
		{
			$torrentId = $this->_db->fetchOne('
				SELECT attachment_id FROM xf_attachment AS a
				INNER JOIN xftt_torrent as t ON (t.torrent_id = a.attachment_id)
				WHERE temp_hash = ?
			', $attachmentHash);

			if ($torrentId) {
				$this->set('torrent_id', $torrentId);
			}
		}
	}

	protected function _discussionPostSave()
	{
		parent::_discussionPostSave();

		if ($this->isInsert() && $this->get('torrent_id'))
		{
			$this->_db->update('xftt_torrent', array(
				'thread_id' => $this->get('thread_id')
			), 'torrent_id = ' . $this->get('torrent_id'));
		}

		if ($this->isUpdate() && $this->isChanged('node_id') && $this->get('torrent_id'))
		{
			$this->_db->update('xftt_torrent', array(
				'category_id' => $this->get('node_id')
			), 'torrent_id = ' . $this->get('torrent_id'));
		}
	}
}