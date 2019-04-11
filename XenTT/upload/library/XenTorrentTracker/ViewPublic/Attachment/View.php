<?php

class XenTorrentTracker_ViewPublic_Attachment_View extends XFCP_XenTorrentTracker_ViewPublic_Attachment_View
{
	public function renderRaw()
	{
		$attachment = $this->_params['attachment'];
		$extension = XenForo_Helper_File::getFileExtension($attachment['filename']);

		if ($extension == 'torrent')
		{
			$this->_response->setHeader('Content-type', 'application/x-bittorrent', true);
			$this->setDownloadFileName($attachment['filename']);

			$this->_response->setHeader('ETag', '"' . $attachment['attach_date'] . '"', true);
			$this->_response->setHeader('Content-Length', $attachment['file_size'], true);
			$this->_response->setHeader('X-Content-Type-Options', 'nosniff');
			
			return new XenTorrentTracker_TorrentOutput($this->_params['attachmentFile']);
		}

		return parent::renderRaw();
	}
}