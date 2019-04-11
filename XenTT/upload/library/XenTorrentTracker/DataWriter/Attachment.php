<?php

class XenTorrentTracker_DataWriter_Attachment extends XFCP_XenTorrentTracker_DataWriter_Attachment
{
	protected function _postDelete()
	{
		parent::_postDelete();
		$this->_getTorrentModel()->deleteTorrent($this->get('attachment_id'));
	}

	protected function _getTorrentModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Torrent');
	}
}