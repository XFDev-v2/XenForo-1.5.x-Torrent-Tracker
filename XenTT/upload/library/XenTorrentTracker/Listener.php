<?php

class XenTorrentTracker_Listener 
{
	public static function initDependencies(XenForo_Dependencies_Abstract $dependencies, array $data) 
	{
		XenForo_Template_Helper_Core::$helperCallbacks['ratiocolor'] = array('XenTorrentTracker_Helper_Torrent', 'helperGetRaioColor');
		XenForo_Template_Helper_Core::$helperCallbacks['long2ip'] = array('XenTorrentTracker_Helper_Torrent', 'helperLong2Ip');
		XenForo_Template_Helper_Core::$helperCallbacks['waittime'] = array('XenTorrentTracker_Helper_Torrent', 'helperGetWaitTime');
	}

	public static function visitorSetup(XenForo_Visitor &$visitor)
	{
		if ($visitor->hasPermission('xenTorrentTracker', 'canMakeFreeleech'))
		{
			$visitor->offsetSet('canAcceptFreeleechRequest', true);
		}
	}

	public static function navigationTabs(array &$extraTabs, $selectedTabId)
	{
		$extraTabs['torrents'] = array(
			'title'		=>	new XenForo_Phrase('torrents'),
			'href'		=>  XenForo_Link::buildPublicLink('full:torrents'),
			'selected'	=> ($selectedTabId == 'torrents') ? true : false,
			'position'	=> 'end',
			'linksTemplate'	=> 'xentorrent_navlinks'
		);
	}

	public static function loadClass($class, array &$extend)
	{
		if ($class == 'XenForo_AttachmentHandler_Post')
			$extend[] = 'XenTorrentTracker_AttachmentHandler_Post';
	}

	public static function loadModel($class, array &$extend)
	{
		if ($class == 'XenForo_Model_Attachment')
			$extend[] = 'XenTorrentTracker_Model_Attachment';

		if ($class == 'XenForo_Model_Thread')
			$extend[] = 'XenTorrentTracker_Model_Thread'; 

		if ($class == 'XenForo_Model_User')
			$extend[] = 'XenTorrentTracker_Model_User'; 

		if ($class == 'XenForo_Model_UserUpgrade')
			$extend[] = 'XenTorrentTracker_Model_UserUpgrade'; 
	}

	public static function loadDataWriter($class, array &$extend)
	{
		if ($class == 'XenForo_DataWriter_Attachment')
			$extend[] = 'XenTorrentTracker_DataWriter_Attachment';

		if ($class == 'XenForo_DataWriter_Category')
			$extend[] = 'XenTorrentTracker_DataWriter_Category';

		if ($class == 'XenForo_DataWriter_Forum')
			$extend[] = 'XenTorrentTracker_DataWriter_Forum';

		if ($class == 'XenForo_DataWriter_User')
			$extend[] = 'XenTorrentTracker_DataWriter_User';

		if ($class == 'XenForo_DataWriter_Discussion_Thread')
			$extend[] = 'XenTorrentTracker_DataWriter_Thread';

		if ($class == 'XenForo_DataWriter_UserUpgrade')
			$extend[] = 'XenTorrentTracker_DataWriter_UserUpgrade';
	}

	public static function loadController($class, array &$extend)
	{
		if ($class == 'XenForo_ControllerPublic_Attachment')
			$extend[] = 'XenTorrentTracker_ControllerPublic_Attachment';

		if ($class == 'XenForo_ControllerPublic_Member')
			$extend[] = 'XenTorrentTracker_ControllerPublic_Member';

		if ($class == 'XenForo_ControllerPublic_Thread')
			$extend[] = 'XenTorrentTracker_ControllerPublic_Thread';

		if ($class == 'XenForo_ControllerAdmin_Option')
			$extend[] = 'XenTorrentTracker_ControllerAdmin_Option';

		if ($class == 'XenForo_ControllerAdmin_Forum')
			$extend[] = 'XenTorrentTracker_ControllerAdmin_Forum';

		if ($class == 'XenForo_ControllerAdmin_UserUpgrade')
			$extend[] = 'XenTorrentTracker_ControllerAdmin_UserUpgrade';

		if ($class == 'XenForo_ControllerAdmin_User')
			$extend[] = 'XenTorrentTracker_ControllerAdmin_User';
	}

	public static function loadView($class, array &$extend)
	{
		if ($class == 'XenForo_ViewPublic_Attachment_View')
			$extend[] = 'XenTorrentTracker_ViewPublic_Attachment_View';
	}

	public static function addTemplateHooks($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		switch ($hookName)
		{
			case 'forum_list_sidebar':
				if (XenForo_Visitor::getUserId())
				{
					$contents = $template->create('xentorrent_sidebar_my_tracker_stats', $template->getParams()) . $contents;
				}
				break;
			case 'message_user_info_extra':
				$contents .= $template->create('xentorrent_message_user_info_extra', $hookParams);
				break;
			case 'member_view_tabs_heading':
				$contents = $template->create('xentorrent_member_view_tabs_heading', $template->getParams()) . $contents;
				break;
			case 'member_view_tabs_content':
				$contents = $template->create('xentorrent_member_view_tabs_content', $template->getParams()) . $contents;
				break;
			case 'admin_forum_edit_tabs':
				$contents .= $template->create('xentorrent_admin_forum_edit_tabs', $template->getParams());
				break;
			case 'admin_forum_edit_panes':
				$contents .= $template->create('xentorrent_admin_forum_edit_panes', $template->getParams());
				break;
			case 'admin_sidebar_applications':
				$contents .= $template->create('xentorrent_admin_sidebar_tracker_stats', $template->getParams());
				break;
		}
	}

	public static function templateCreate(&$templateName, array &$params, XenForo_Template_Abstract $template)
	{
		switch ($templateName)
		{
			case 'forum_list':
				$template->preloadTemplate('xentorrent_sidebar_my_tracker_stats');
				$template->setParam('xenTTStats', XenForo_Application::getSimpleCacheData('xenTTStats'));
				break;
			case 'member_view':
				$template->preloadTemplate('xentorrent_member_view_tabs_heading');
				$template->preloadTemplate('xentorrent_member_view_tabs_content');
				break;
			case 'forum_edit':
				$template->preloadTemplate('xentorrent_admin_forum_edit_tabs');
				$template->preloadTemplate('xentorrent_admin_forum_edit_panes');
				break;
			case 'thread_view':
				$template->preloadTemplate('xentorrent_message_user_info_extra');
				break;
			case 'application_splash':
				$template->preloadTemplate('xentorrent_admin_sidebar_tracker_stats');
				$template->setParam('xenTTStats', XenForo_Application::getSimpleCacheData('xenTTStats'));
				break;
		}
	}

	public static function userMatchesCriteria($rule, array $data, array $user, &$returnValue)
	{
		switch ($rule)
		{
			case 'xentt_uploaded':
				if (isset($user['uploaded']) && ($user['uploaded'] > ($data['uploaded'] * 1048576)))
				{
					$returnValue = true;
				}
				break;
			case 'xentt_downloaded':
				if (isset($user['downloaded']) && ($user['downloaded'] > ($data['downloaded'] * 1048576)))
				{
					$returnValue = true;
				}
				break;
		}
	}
}