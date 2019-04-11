<?php

class XenTorrentTracker_ControllerAdmin_Torrent extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('manageXenTracker');
	}
	
	public function actionCheckStatus()
	{
		$result = XenTorrentTracker_Helper_Tracker::send(array(
			'action' => 'status'
		), false);

		$status = false;

		if (isset($result['message']) && $result['message'] == 'online')
		{
			$status = true;			
		}

		$stats = XenForo_Application::getSimpleCacheData('xenTTStats');
		if (!is_array($stats))
		{
			$stats = array();
		}

		$stats['online'] = $status;
		XenForo_Application::setSimpleCacheData('xenTTStats', $stats);

		$viewParams = array(
			'status' => $status
		);

		return $this->responseView('XenTorrentTracker_ViewAdmin_Status', '', $viewParams);	
	}

	public function actionClientBan()
	{
		$viewParams = array(
			'clients' => $this->_getTrackerModel()->getAllBanClients()
		);

		return $this->responseView('XenTorrentTracker_ViewAdmin_ClientBan_List', 'xentorrent_clientban_list', $viewParams);
	}

	public function actionClientBanAdd()
	{
		if ($this->isConfirmedPost())
		{
			$input = $this->_input->filter(array(
				'peer_id' => XenForo_Input::STRING,
				'comment' => XenForo_Input::STRING
			));

			try 
			{
				$this->_getTrackerModel()->banClient($input['peer_id'], $input['comment']);

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildAdminLink('torrents/client-ban')
				);
			}
			catch (Exception $e)
			{
				return $this->responseError($e->getMessage());
			}
		}

		$viewParams = array();
		return $this->responseView('XenTorrentTracker_ViewAdmin_ClientBan_Add', 'xentorrent_clientban_add', $viewParams);
	}

	public function actionClientBanDelete()
	{
		$input = $this->_input->filter(array(
			'peer_id' => XenForo_Input::STRING
		));

		$this->_getTrackerModel()->removeBanClient($input['peer_id']);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('torrents/client-ban')
		);
	}

	public function actionIpBan()
	{
		$viewParams = array(
			'ips' => $this->_getTrackerModel()->getAllBanIps()
		);

		return $this->responseView('XenTorrentTracker_ViewAdmin_IpBan_List', 'xentorrent_ipban_list', $viewParams);
	}

	public function actionIpBanAdd()
	{
		if ($this->isConfirmedPost())
		{
			$input = $this->_input->filter(array(
				'begin' => XenForo_Input::STRING,
				'end' => XenForo_Input::STRING,
				'comment' => XenForo_Input::STRING
			));

			try 
			{
				$this->_getTrackerModel()->banIP($input['begin'], $input['end'], $input['comment']);

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildAdminLink('torrents/ip-ban')
				);
			}
			catch (Exception $e)
			{
				return $this->responseError($e->getMessage());
			}
		}

		$viewParams = array();
		return $this->responseView('XenTorrentTracker_ViewAdmin_IpBan_Add', 'xentorrent_ipban_add', $viewParams);
	}

	public function actionIpBanDelete()
	{
		$input = $this->_input->filter(array(
			'begin' => XenForo_Input::UINT,
			'end' => XenForo_Input::UINT
		));

		$this->_getTrackerModel()->removeBanIp($input['begin'], $input['end']);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('torrents/ip-ban')
		);
	}

	public function actionLog()
	{
		$logModel = $this->_getLogModel();

		$id = $this->_input->filterSingle('log_id', XenForo_Input::UINT);
		if ($id)
		{
			$entry = $logModel->getLogById($id);
			if (!$entry)
			{
				return $this->responseError(new XenForo_Phrase('requested_log_entry_not_found'), 404);
			}

			$viewParams = array(
				'entry' => $logModel->prepareLogEntry($entry)
			);

			return $this->responseView('XenTorrentTracker_ViewAdmin_LogView', 'xentorrent_log_view', $viewParams);
		}
		else
		{
			$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
			$perPage = 20;

			$entries = $logModel->getLogEntries(array('page' => $page, 'perPage' => $perPage));

			$viewParams = array(
				'entries'	=>	$logModel->prepareLogEntries($entries),
				'page'	=> $page,
				'total' => $logModel->countLogEntries(),
				'perPage'	=> $perPage
			);

			return $this->responseView('XenTorrentTracker_ViewAdmin_Log', 'xentorrent_log', $viewParams);
		}
	}

	public function actionLogTryAgain()
	{
		$logModel = $this->_getLogModel();

		$id = $this->_input->filterSingle('log_id', XenForo_Input::UINT);
		$entry = $logModel->getLogById($id);

		if (!$entry)
		{
			return $this->responseError(new XenForo_Phrase('requested_log_entry_not_found'), 404);
		}

		$entry =$logModel->prepareLogEntry($entry);

		$logError = false;
		$message = XenTorrentTracker_Helper_Tracker::updateTracker($entry['params'], $logError);

		$db = XenForo_Application::getDb();

		$db->update('xftt_log', array(
			'log_date' => XenForo_Application::$time,
			'message' => $message,
			'is_error' => empty($logError) ? 0 : 1
		), 'log_id = ' . $db->quote($entry['log_id']));

		$entry = $logModel->getLogById($id);

		$viewParams = array(
			'entry' => $logModel->prepareLogEntry($entry)
		);

		return $this->responseView('XenTorrentTracker_ViewAdmin_LogView', 'xentorrent_log_view', $viewParams);
	}

	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	protected function _getLogModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Log');
	}

	protected function _getTorrentModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Torrent');
	}

	protected function _getTrackerModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Tracker');
	}
}