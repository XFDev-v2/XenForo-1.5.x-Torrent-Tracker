<?php

class XenTorrentTracker_ControllerPublic_Member extends XFCP_XenTorrentTracker_ControllerPublic_Member
{
	public function actionTorrents()
	{
		$defaultFilter = 'my';
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$filter = $this->_input->filterSingle('filter', XenForo_Input::STRING, array('default' => $defaultFilter));
		
		$userFetchOptions = array(
			'join' => XenForo_Model_User::FETCH_LAST_ACTIVITY | XenForo_Model_User::FETCH_USER_PERMISSIONS
		);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId, $userFetchOptions);

		$visitor = XenForo_Visitor::getInstance();
		if ($userId != XenForo_Visitor::getUserId() && !$visitor->hasPermission('general', 'editBasicProfile'))
		{
			return $this->responseNoPermission();
		}

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$perPage = 20;
		
		$torrentModel = $this->_getTorrentModel();

		$criteria = array(
			'filter' => $filter,
			'userId' => $userId
		);

		$total = $torrentModel->countTorrentsForUserId($criteria);

		$this->canonicalizePageNumber($page, $perPage, $total, 'members/torrents', $user);

		$fetchOptions = array(
			'page'	=> $page,
			'perPage' => $perPage,
		);
		
		$torrents = $torrentModel->getTorrentsForUserId($criteria, $fetchOptions);

		$pageNavParams = $criteria;
		$endOffset = ($page - 1) * $perPage + count($torrents);

		$viewParams = array(
			'torrents'	=> $torrents,

			'perPage' => $perPage,
			'page' => $page,
			'pageNavParams' => $pageNavParams,

			'filter' => $filter,
			'defaultFilter' => $defaultFilter,

			'startOffset' => ($page - 1) * $perPage + 1,
			'endOffset'	=> $endOffset,
			'total'	=> $total,

			'user' => $user
		);

		if ($this->getResponseType() == 'json') 
		{
			return $this->responseView('XenTorrentTracker_ViewPublic_Member_TorrentList', 'xentorrent_member_torrent_list_content', $viewParams);
		}

		return $this->responseView('XenTorrentTracker_ViewPublic_Member_TorrentList', 'xentorrent_member_torrent_list', $viewParams);
	}

	public function actionMyBonus()
	{
		if (!XenForo_Visitor::getUserId())
		{
			return $this->responseNoPermission();
		}

		$options = array(
			120 => 1,
			330 => 3,
			1000 => 10,
			5000 => 55,
			10000 => 120,
		);

		if ($this->_input->filterSingle('redeem', XenForo_Input::UINT))
		{
			$points = $this->_input->filterSingle('points', XenForo_Input::UINT);
			$visitor =XenForo_Visitor::getInstance();

			if (!in_array($points, array_keys($options)))
			{
				return $this->responseError(new XenForo_Phrase('invalid_option_specified'));
			}
			elseif($points > $visitor['seedbonus'])
			{
				return $this->responseError(new XenForo_Phrase('you_dont_have_enough_bonus_points_for_this_trade'));
			}

			$uploaded = $visitor['uploaded'] + ($options[$points] * 1073741824);
			$seedbonus = ($visitor['seedbonus'] - $points) * 10;

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
			$dw->setExistingData($visitor['user_id']);
			$dw->set('uploaded', $uploaded);
			$dw->set('seedbonus', $seedbonus);

			if ($dw->save())
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('members/my-bonus')
				);
			}
		}

		$viewParams = array(
			'options' => $options
		);

		return $this->responseView('XenTorrentTracker_ViewPublic_Member_MyBonus', 'xentorrent_member_mybonus', $viewParams);
	}

	public function actionResetPassKey()
	{
		if (!XenForo_Visitor::getUserId())
		{
			return $this->responseNoPermission();
		}

		if ($this->isConfirmedPost())
		{
			if (!$this->_getUserModel()->resetPassKey(XenForo_Visitor::getUserId()))
			{
				return $this->responseError(new XenForo_Phrase('failed_to_reset_passkey_try_contact_owner'));
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect(false, XenForo_Link::buildPublicLink('torrents'))
			);
		}
		else
		{
			$viewParams = array();
			return $this->responseView('XenTorrentTracker_ViewPublic_Member_ResetPassKey', 'xentorrent_reset_pass_key', $viewParams);
		}
	}

	protected function _getTorrentModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Torrent');
	}
}