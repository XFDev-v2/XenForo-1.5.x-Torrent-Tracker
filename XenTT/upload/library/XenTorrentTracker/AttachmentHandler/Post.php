<?php

class XenTorrentTracker_AttachmentHandler_Post extends XFCP_XenTorrentTracker_AttachmentHandler_Post
{
	/**
	 * Determines if attachments and be uploaded and managed in this context.
	 *
	 * @see XenForo_AttachmentHandler_Abstract::_canUploadAndManageAttachments()
	 */
	protected function _canUploadAndManageAttachments(array $contentData, array $viewingUser)
	{
		if (!isset($contentData['is_torrent']))
		{
			return parent::_canUploadAndManageAttachments($contentData, $viewingUser);
		}

		$postModel = $this->_getPostModel();
		$torrentModel = $this->_getTorrentModel();

		if (!empty($contentData['post_id']))
		{
			$post = $postModel->getPostById($contentData['post_id']);
			if ($post)
			{
				$contentData['thread_id'] = $post['thread_id'];
			}
		}

		if (!empty($contentData['thread_id']))
		{
			if ($torrentModel->getTorrentByThreadId($contentData['thread_id']))
			{
				throw new XenForo_ControllerResponse_Exception($this->_responseError(new XenForo_Phrase('only_one_torrent_allowed_per_thread')));
			}

			$thread = XenForo_Model::create('XenForo_Model_Thread')->getThreadById($contentData['thread_id']);
			if ($thread)
			{
				if (empty($contentData['post_id']) || $contentData['post_id'] != $thread['first_post_id'])
				{
					throw new XenForo_ControllerResponse_Exception($this->_responseError(new XenForo_Phrase('torrent_allowed_in_first_post_only')));
				}

				XenForo_Application::set('torrent_thread_id', $thread['thread_id']);
				$contentData['node_id'] = $thread['node_id'];
			}
		}

		if (!empty($contentData['node_id']))
		{
			$forumModel = XenForo_Model::create('XenForo_Model_Forum');
			$forum = $forumModel->getForumById($contentData['node_id'], array(
				'permissionCombinationId' => $viewingUser['permission_combination_id']
			));
			if ($forum)
			{
				if (!$forum['is_torrent_category'])
				{
					throw new XenForo_ControllerResponse_Exception($this->_responseError(new XenForo_Phrase('torrent_are_not_allowed_in_this_forum')));
				}

				XenForo_Application::set('torrent_node_id', $forum['node_id']);

				$permissions = XenForo_Permission::unserializePermissions($forum['node_permission_cache']);

				if (!empty($contentData['post_id']))
				{
					// editing a post, have to be able to edit this post first
					if (!$postModel->canViewPost($post, $thread, $forum, $null, $permissions, $viewingUser)
						|| !$postModel->canEditPost($post, $thread, $forum, $null, $permissions, $viewingUser))
					{
						return false;
					}
				}

				return (
					$forumModel->canViewForum($forum, $null, $permissions, $viewingUser)
					&& $forumModel->canUploadAndManageAttachment($forum, $null, $permissions, $viewingUser)
				);
			}
		}

		return false; // invalid content data
	}

	protected function _responseError($error, $responseCode = 200, array $containerParams = array())
	{
		$controllerResponse = new XenForo_ControllerResponse_Error();
		$controllerResponse->errorText = $error;
		$controllerResponse->responseCode = $responseCode;
		$controllerResponse->containerParams = $containerParams;

		return $controllerResponse;
	}

	protected function _getTorrentModel()
	{
		return XenForo_Model::create('XenTorrentTracker_Model_Torrent');
	}
}
