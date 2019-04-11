<?php

class XenTorrentTracker_ControllerAdmin_Forum extends XFCP_XenTorrentTracker_ControllerAdmin_Forum
{
	public function actionEdit()
	{
		$response = parent::actionEdit();
		if ($response instanceof XenForo_ControllerResponse_View)
		{
			if (!($nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT)))
			{
				$parenId = $this->_input->filterSingle('parent_node_id', XenForo_Input::UINT);
				$response->params['parentNode'] = $this->_getNodeModel()->getNodeById($parenId);

				if (!empty($response->params['parentNode']['is_torrent_category']))
				{
					$response->params['forum']['is_torrent_category'] = true;
				}
			}
		}

		return $response;
	}
}