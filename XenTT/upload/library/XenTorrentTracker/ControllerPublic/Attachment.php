<?php

class XenTorrentTracker_ControllerPublic_Attachment extends XFCP_XenTorrentTracker_ControllerPublic_Attachment
{
	public function actionIndex()
	{
		$response = parent::actionIndex();

		if (!empty($response->params['attachment']) && !empty($response->params['attachmentFile']))
		{
			$extension = XenForo_Helper_File::getFileExtension($response->params['attachment']['filename']);
			if ($extension == 'torrent')
			{
				$torrentModel = $this->_getTorrentModel();
				try 
				{
					$torrentModel->downloadTorrent($response->params['attachment'], $response->params['attachmentFile']);
				} 
				catch (XenForo_Exception $e)
				{
					$this->_routeMatch->setResponseType('error');
					return $this->responseError($e->getMessage());
				}
			}
		}

		return $response;
	}

	public function actionDoUpload()
	{
		$this->_assertPostOnly();
		if ($this->_request->getParam('content_type') != 'post')
		{
			return parent::actionDoUpload();
		}

		$deleteArray = array_keys($this->_input->filterSingle('delete', XenForo_Input::ARRAY_SIMPLE));
		$delete = reset($deleteArray);
		if ($delete)
		{
			$this->_request->setParam('attachment_id', $delete);
			return $this->responseReroute(__CLASS__, 'delete');
		}

		$input = $this->_input->filter(array(
			'hash' => XenForo_Input::STRING,
			'content_type' => XenForo_Input::STRING,
			'content_data' => array(XenForo_Input::UINT, 'array' => true),
			'key' => XenForo_Input::STRING
		));

		if (empty($input['hash'])) 
		{
			$input['hash'] = $this->_input->filterSingle('temp_hash', XenForo_Input::STRING);
		}

		$file = XenForo_Upload::getUploadedFile('upload');
		if (!$file)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('attachments/upload', false, array(
					'hash' => $input['hash'],
					'content_type' => $input['content_type'],
					'content_data' => $input['content_data'],
					'key' => $input['key']
				))
			);
		}

		$extension = XenForo_Helper_File::getFileExtension($file->getFileName());
		if ($extension != 'torrent') {
			return parent::actionDoUpload();
		}

		$torrentModel = $this->_getTorrentModel();
		if (!$torrentModel->canUploadTorrent()) 
		{
			return $this->responseError(new XenForo_Phrase('do_not_have_permission'));
		}

		if (!isset($input['content_data']['post_id']))
		{
			$newAttachments = $this->_getAttachmentModel()->getAttachmentsByTempHash($input['hash']);
			foreach ($newAttachments AS $attachment)
			{
				if (XenForo_Helper_File::getFileExtension($attachment['filename']) == 'torrent')
				{
					return $this->responseError(new XenForo_Phrase('only_one_torrent_allowed_per_thread'));
				}
			}
		}

		try 
		{
			$torrent = XenTorrentTracker_Torrent::createFromTorrentFile($file->getTempFile());
			if (XenForo_Application::get('options')->xenTorrentPrivateFlag)
			{
				$torrent->setPrivate();
			}
			
			XenForo_Application::set('torrent', $torrent);
		}
		catch (Exception $e)
		{
			return $this->responseError(new XenForo_Phrase('invalid_torrent_file'));
		}

		$torrentHash = $torrent->getHash();
		if ($torrent = $torrentModel->getTorrentByInfoHash($torrentHash))
		{
			return $this->responseError(new XenForo_Phrase('this_torrent_is_already_uploaded_before'));
			
			// CHECK AND DELETE IF TORRENT IS UNASSOCIATED
			/*$cutOff = XenForo_Application::$time - 900;	// 15 MINUTES
			
			if (!$torrent['thread_id'] && (($torrent['user_id'] == XenForo_Visitor::getUserId()) || ($cutOff > $torrent['ctime'])))
			{
				$torrentDw = XenForo_DataWriter::create('XenTorrentTracker_DataWriter_Torrent');
				$torrentDw->setExistingData($torrent);
				$torrentDw->delete();
			}
			else
			{
				return $this->responseError(new XenForo_Phrase('this_torrent_is_already_uploaded_before'));
			}*/
		}

		$input['content_data']['is_torrent'] = true;
		$this->_request->setParam('content_data', $input['content_data']);

		return parent::actionDoUpload();
	}

	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}

	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	protected function _getTorrentModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Torrent');
	}
}