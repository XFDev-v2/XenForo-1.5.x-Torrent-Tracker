<?php

class XenTorrentTracker_TorrentOutput extends XenForo_FileOutput
{
	public function output()
	{
		parent::output();

		if ($this->_fileName)
		{
			@unlink($this->_fileName);
		}
	}
}