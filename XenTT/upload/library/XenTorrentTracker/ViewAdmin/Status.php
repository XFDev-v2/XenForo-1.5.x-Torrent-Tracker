<?php

class XenTorrentTracker_ViewAdmin_Status extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		if ($this->_params['status'])
		{
			return '<span style="color:green;font-weight:bold;">' . new XenForo_Phrase('online') . '</span>';
		}

		return '<span style="color:red;font-weight:bold;">' . new XenForo_Phrase('offline') . '</span>';
	}
}