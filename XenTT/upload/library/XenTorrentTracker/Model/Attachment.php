<?php

class XenTorrentTracker_Model_Attachment extends XFCP_XenTorrentTracker_Model_Attachment
{
	protected function _deleteAttachmentsFromPairs(array $attachments)
	{
		if (!$attachments)
		{
			return;
		}

		parent::_deleteAttachmentsFromPairs($attachments);
		$this->_getTorrentModel()->deleteTorrents(array_keys($attachments));
	}

	public function insertTemporaryAttachment($dataId, $tempHash)
	{
		$attachmentId = parent::insertTemporaryAttachment($dataId, $tempHash);
		$app = XenForo_Application::getInstance();

		if ($app->offsetExists('torrent'))
		{
			$torrent = $app->offsetGet('torrent');
			$threadId = $app->offsetExists('torrent_thread_id') ? $app->offsetGet('torrent_thread_id') : 0;
			$nodeId = $app->offsetExists('torrent_node_id') ? $app->offsetGet('torrent_node_id') : 0;

			// Don't upload the torrent if we don't have a valid nodeid
			if (!$nodeId)
			{
				return $attachmentId;
			}

			$otherDetails = array(
				'files'		=> $torrent->getFileList(2),
				'comment'	=> $torrent->getComment(),
				'created'	=> $torrent->getCreatedBy(),
				'name'		=> $torrent->getName()
			);

			$torrentInfo = array(
				'torrent_id'	=> $attachmentId,
				'info_hash'		=> $torrent->getHash(true), 
				'size'			=> $torrent->getSize(),
				'file_details'	=> serialize($otherDetails),
				'number_files'	=> count($otherDetails['files']),
				'thread_id' 	=> $threadId,
				'category_id' 	=> $nodeId
			);

			$torrentId = $this->_getTorrentModel()->saveTorrent($torrentInfo);
		}

		return $attachmentId;	
	}

	protected function _getTorrentModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Torrent');
	}
}