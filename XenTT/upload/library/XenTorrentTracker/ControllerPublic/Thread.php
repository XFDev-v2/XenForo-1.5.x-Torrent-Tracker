<?php

class XenTorrentTracker_ControllerPublic_Thread extends XFCP_XenTorrentTracker_ControllerPublic_Thread
{
	protected function _getDefaultViewParams(array $forum, array $thread, array $posts, $page = 1, array $viewParams = array())
	{
		if (empty($forum['is_torrent_category']) || empty($posts) || empty($thread['first_post_id']))
		{
			return parent::_getDefaultViewParams($forum, $thread, $posts, $page, $viewParams);
		}

		$firstPostId = $thread['first_post_id'];

		if (isset($posts[$firstPostId]) && !empty($posts[$firstPostId]['attachments']))
		{
			$post =& $posts[$firstPostId];
			foreach ($post['attachments'] AS $attachmentId => $attachment)
			{
				if (XenForo_Helper_File::getFileExtension($attachment['filename']) == 'torrent')
				{
					$torrentModel = $this->_getTorrentModel();

					$fetchOptions = array(
						'join' => XenTorrentTracker_Model_Torrent::FETCH_INFO
					);

					// Don't allow freeleech requests for threads older than a week.
					if (XenForo_Visitor::getUserId() == $thread['user_id'] && (XenForo_Application::$time - $post['post_date']) < 604800)
					{
						$fetchOptions['join'] |= XenTorrentTracker_Model_Torrent::FETCH_REQUEST;
						$post['canRequestForFreeleech'] = true;
					}

					$torrent = $torrentModel->prepareTorrent($torrentModel->getTorrentById($attachmentId, $fetchOptions));

					if (!empty($torrent))
					{
						if (isset($torrent['other_details']['files']))
						{
							unset($torrent['other_details']['files']);	
						}
						
						if (!empty($torrent['request_id']))
						{
							$post['canRequestForFreeleech'] = false;
						}

						if ($torrent['seeders'] == 0 && XenForo_Application::$time > ($torrent['ctime'] + 86400))
						{
							$torrent['canAskForReseed'] = true;
						}

						$torrent['viewPeerList'] = $torrentModel->canViewPeerList();
						$torrent['viewSnatchList'] = $torrentModel->canViewSnatchList();

						$post['torrent'] = array_merge($attachment, $torrent);
						unset($post['attachments'][$attachmentId]);

						break;
					}
				}
			}
		}

		return parent::_getDefaultViewParams($forum, $thread, $posts, $page, $viewParams);	
	}

	protected function _getTorrentModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Torrent');
	}

	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}