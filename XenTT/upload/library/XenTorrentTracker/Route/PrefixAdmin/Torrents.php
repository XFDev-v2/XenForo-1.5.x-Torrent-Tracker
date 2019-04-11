<?php

class XenTorrentTracker_Route_PrefixAdmin_Torrents implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'torrent_id');
		return $router->getRouteMatch('XenTorrentTracker_ControllerAdmin_Torrent', $action, 'xenTorrentTracker');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		if (isset($data['attachment_id']))
		{
			$data['torrent_id'] = $data['attachment_id'];
		}
		
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'torrent_id');
	}
}