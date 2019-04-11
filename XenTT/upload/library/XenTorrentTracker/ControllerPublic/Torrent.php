<?php

class XenTorrentTracker_ControllerPublic_Torrent extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		if (!$this->_getTorrentModel()->canViewTorrents())
		{
			throw $this->getNoPermissionResponseException();
		}

		if (($searchId = $this->_input->filterSingle('search_id', XenForo_Input::UINT))) 
		{
			return $this->responseReroute(__CLASS__, 'search');
		}

		return $this->responseReroute('XenTorrentTracker_ControllerPublic_Tracker', 'index');
	}

	public function actionTop()
	{
		if (!$this->_getTorrentModel()->canViewTorrents())
		{
			throw $this->getNoPermissionResponseException();
		}
		
		return $this->responseReroute('XenTorrentTracker_ControllerPublic_Tracker', 'top');
	}

	public function actionFreeleechRequests()
	{
		return $this->responseReroute('XenTorrentTracker_ControllerPublic_Tracker', 'freeleech-requests');
	}

	public function actionSearch()
	{		
		if (!XenForo_Visitor::getInstance()->canSearch() || !$this->_getTorrentModel()->canViewTorrents())
		{
			throw $this->getNoPermissionResponseException();
		}

		$defaultOrder = 'time';
		$defaultOrderDirection = 'desc';

		$input = $this->_input->filter(array(
			'category_id' => XenForo_Input::UINT,
			'query' => XenForo_Input::STRING,
			'search_id' => XenForo_Input::UINT,
			'child_nodes' => XenForo_Input::UINT,
			'date' => XenForo_Input::DATE_TIME,
			'users' => XenForo_Input::STRING,
			'nodes' => array(XenForo_Input::UINT, array('array' => true))
		));

		$searchModel = $this->_getSearchModel();
		$search = $input['search_id'] ? $searchModel->getSearchById($input['search_id']) : null;

		if (!$search)
		{
			$input = array(
				'type'	=>	'thread',
				'title_only' =>	1,
				'group_discussion' => 1,
				'reply_count' => 0,
				'nodes' => !empty($input['category_id']) ? array($input['category_id']) : $input['nodes'],
				'order' => XenForo_Search_SourceHandler_Abstract::getDefaultSourceHandler()->supportsRelevance() ? 'relevance' : '',
				'query' => $input['query'],
				'users' => $input['users'],
				'date'  => $input['date'],
				'child_nodes' => true, //$input['child_nodes'],
			);

			if (!$input['order'])
			{
				$input['order'] = 'date';
			}

			$origQuery = $input['query'];
			$input['keywords'] = XenForo_Helper_String::censorString($input['query'], null, ''); // don't allow searching of censored stuff

			$visitorUserId = XenForo_Visitor::getUserId();

			$constraints = $searchModel->getGeneralConstraintsFromInput($input, $errors);
			if ($errors)
			{
				return $this->responseError($errors);
			}

			if ($input['keywords'] === '' && $input['users'] === '')
			{
				return $this->responseError(new XenForo_Phrase('please_specify_search_query_or_name_of_member'));
			}

			$typeHandler = $searchModel->getSearchDataHandler($input['type']);
			
			// FOR TORRENTS SEARCH
			$constraints = array_merge($constraints, array(
				'orderBy' => $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder)),
				'orderDirection' => $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection)),
				'freeleech' => $this->_input->filterSingle('freeleech', XenForo_Input::UINT)
			));

			$search = $searchModel->getExistingSearch(
				$input['type'], $input['keywords'], $constraints, $input['order'], $input['group_discussion'], $visitorUserId, false
			);

			if (!$search)
			{
				$searcher = new XenForo_Search_Searcher($searchModel);

				$results = $searcher->searchType(
					$typeHandler, $input['keywords'], $constraints, $input['order'], $input['group_discussion']
				);

				$userResults = array();

				if (!$results)
				{
					return $this->getNoSearchResultsResponse($searcher);
				}

				$warnings = $searcher->getErrors() + $searcher->getWarnings();

				$search = $searchModel->insertSearch(
					$results, $input['type'], $origQuery, $constraints, $input['order'], $input['group_discussion'], $userResults,
					$warnings, $visitorUserId
				);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('torrents', '', array(
					'search_id'	=> $search['search_id'],
					'searchform' => $this->_input->filterSingle('searchform', XenForo_Input::UINT) ? true : false
				)), ''
			);
		}

		if (isset($search['search_results']))
		{
			$results = $this->_decodeSearchTableData($search['search_results']);
			if (!$results)
			{
				return $this->getNoSearchResultsResponse($search);
			}

			$threadIds = array();
			foreach($results AS $result)
			{
				if (isset($result[1])) {
					$threadIds[] = $result[1];
				}
			}

			$this->_request->setParam('thread_id', $threadIds);
			$this->_request->setParam('search_id', $search['search_id']);

			$params = array(
				'search_id' => $search['search_id'],
				'search_query' => $search['search_query']
			);

			if (!empty($search['search_constraints']))
			{
				$constraints = json_decode($search['search_constraints'], true);					
				if (!empty($constraints['date']))
				{
					$params['date'] = XenForo_Locale::date(intval($constraints['date']), 'picker');
				}

				if (!empty($constraints['orderBy']))
				{
					$params['orderBy'] = $constraints['orderBy'];
					$order = $this->_request->getParam('order');

					if (empty($order)) 
					{
						$this->_request->setParam('order', $constraints['orderBy']);
					}
				}

				if (!empty($constraints['orderDirection']))
				{
					$params['orderDirection'] = $constraints['orderDirection'];
					$direction = $this->_request->getParam('direction');

					if (empty($direction)) 
					{
						$this->_request->setParam('direction', $constraints['orderDirection']);
					}
				}

				if (!empty($constraints['freeleech']))
				{
					$params['freeleech'] = $constraints['freeleech'];
					if ($this->_request->getParam('freeleech') === null) 
					{
						$this->_request->setParam('freeleech', 1);
					}
				}

				if (!empty($constraints['user']))
				{
					$users = '';
					foreach ($this->_getUserModel()->getUsersByIds($constraints['user']) AS $user)
					{
						$users .= $user['username'] . ', ';
					}

					$params['users'] = substr($users, 0, -2);
				}

				if (!empty($constraints['node']))
				{
					$params['nodes'] = explode(' ', $constraints['node']);
				}
			}

			XenForo_Application::set('search', $params);

			return $this->responseReroute('XenTorrentTracker_ControllerPublic_Tracker', 'index');
		}
		else
		{
			return $this->getNoSearchResultsResponse($search);
		}
	}

	public function actionUpload()
	{
		$torrentModel = $this->_getTorrentModel();
		if (!$torrentModel->canUploadTorrent())
		{
			throw $this->getNoPermissionResponseException(); 
		}

		if ($this->_input->filterSingle('node_id', XenForo_Input::UINT))
		{
			return $this->responseReroute('XenForo_ControllerPublic_Forum', 'create-thread');
		}

		$viewParams = array(
			'categories' => $this->_getCategoryModel()->getTorrentCategories(),
		);

		return $this->responseView('XenTorrentTracker_ViewPublic_ChooseCategory', 'xentorrent_choose_category', $viewParams);
	}

	public function actionRequestReseed()
	{
		$torrentId = $this->_input->filterSingle('torrent_id', XenForo_Input::UINT);
		$torrent = $this->_getTorrentOrError($torrentId);

		$attachmentModel = $this->_getAttachmentModel();

		if (!$attachmentModel->canViewAttachment($torrent))
		{
			return $this->responseNoPermission();
		}

		$reseedInterval = XenForo_Application::get('options')->xenTorrentReseedInterval;
		if (!($torrent['seeders'] == 0 && XenForo_Application::$time > ($torrent['ctime'] + $reseedInterval)))
		{
			return $this->responseError(
				new XenForo_Phrase('request_failed')
			);
		}

		if (($torrent['last_reseed_request'] + $reseedInterval) > XenForo_Application::$time)
		{
			return $this->responseError(
				new XenForo_Phrase('there_was_already_reseed_request_for_this_torrent')
			);
		}

		$visitor = XenForo_Visitor::getInstance();
		$maxReseedRequest = $visitor->hasPermission('xenTorrentTracker', 'maxReseedRequest');
		if (!$maxReseedRequest)
		{
			return $this->responseNoPermission();
		}

		if ($maxReseedRequest > 0)
		{
			$requests = $this->_getTorrentModel()->getReseedRequestByUserId($visitor['user_id']);			
			if ($requests >= $maxReseedRequest)
			{
				return $this->responseError(
					new XenForo_Phrase('you_have_already_sent_max_number_of_reseed_request_for_the_day')
				);
			}
		}

		$users = $this->_getTorrentModel()->getSnatchers($torrentId, array(
			'perPage'	=> 	50,
			'page'		=> 	1
		));

		// Send alert to torrent/thread creator also
		if (isset($torrent['tuser_id']) && !empty($torrent['tuser_id']))
		{
			$users[$torrent['tuser_id']] = array('user_id' => $torrent['tuser_id'], 'uploader' => true, 'mtime' => $torrent['ctime']);
		}
		elseif (!empty($torrent['user_id']))
		{
			$users[$torrent['user_id']] = array('user_id' => $torrent['user_id'], 'uploader' => true, 'mtime' => $torrent['ctime']);
		}

		if (isset($users[$visitor['user_id']]))
		{
			unset($users[$visitor['user_id']]);
		}

		foreach ($users AS $user)
		{
			XenForo_Model_Alert::alert($user['user_id'],
				$visitor['user_id'], $visitor['username'],
				'thread', $torrent['thread_id'],
				'reseed',
				array(
					'action' => (isset($user['uploader']) ? 'uploaded' : 'completed'),
					'time' => $user['mtime']
				)
			);
		}

		$this->_getTorrentModel()->insertReseedRequest($visitor['user_id'], $torrentId);

		$torrentDw = XenForo_DataWriter::create('XenTorrentTracker_DataWriter_Torrent');
		$torrentDw->setExistingData($torrentId);
		$torrentDw->set('last_reseed_request', XenForo_Application::$time);

		$torrentDw->save();

		return $this->responseMessage(
			new XenForo_Phrase('request_sent')
		);	
	}

	public function actionRequestFreeleech()
	{
		$torrentId = $this->_input->filterSingle('torrent_id', XenForo_Input::UINT);
		$torrent = $this->_getTorrentOrError($torrentId);

		// Freeleech request can allow only be generated by torrent creator
		// Freeleech request is not allowed for torrents older than a week
		if (($torrent['user_id'] != XenForo_Visitor::getUserId()) || 
			(XenForo_Application::$time - $torrent['attach_date']) > 604800)
		{
			throw $this->getNoPermissionResponseException(); 
		}	

		$requestModel = $this->_getRequestModel();
		if (!$requestModel->getRequestByTorrentId($torrentId))
		{
			$requestModel->insertRequest($torrentId);
		}

		$message = new XenForo_Phrase('request_sent');
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('threads', $torrent),
			$message
		);	
	}

	public function actionMakeFreeleech()
	{
		$torrentId = $this->_input->filterSingle('torrent_id', XenForo_Input::UINT);
		$requestId = $this->_input->filterSingle('request_id', XenForo_Input::UINT);

		$torrent = $this->_getTorrentOrError($torrentId);

		if (!$this->_getTorrentModel()->canAcceptFreeleechRequest())
		{
			throw $this->getNoPermissionResponseException();
		}	

		if ($torrent['freeleech'] == 0)
		{
			$this->_getTorrentModel()->makeFreeleech($torrentId);
		}

		if ($requestId)
		{
			$this->_getRequestModel()->updateRequest($requestId, 'accept');
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect(XenForo_Link::buildPublicLink('torrents/freeleech-requests')),
			''
		);	
	}

	public function actionRemoveFreeleech()
	{
		$torrentId = $this->_input->filterSingle('torrent_id', XenForo_Input::UINT);
		$requestId = $this->_input->filterSingle('request_id', XenForo_Input::UINT);

		$torrent = $this->_getTorrentOrError($torrentId);

		if (!$this->_getTorrentModel()->canAcceptFreeleechRequest())
		{
			throw $this->getNoPermissionResponseException();
		}	

		if ($torrent['freeleech'] == 1)
		{
			$this->_getTorrentModel()->makeFreeleech($torrentId, false); 
		}

		if ($requestId)
		{
			$this->_getRequestModel()->updateRequest($requestId, 'reject');
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect(XenForo_Link::buildPublicLink('torrents/freeleech-requests')),
			''
		);	
	}

	public function actionFiles()
	{
		$torrentId = $this->_input->filterSingle('torrent_id', XenForo_Input::UINT);
		$torrent = $this->_getTorrentOrError($torrentId);

		$attachmentModel = $this->_getAttachmentModel();

		if (!$attachmentModel->canViewAttachment($torrent))
		{
			return $this->responseNoPermission();
		}

		$torrentModel = $this->_getTorrentModel();
		$torrent = $torrentModel->prepareTorrent($torrentModel->getTorrentById($torrentId, array(
			'join'	=>	XenTorrentTracker_Model_Torrent::FETCH_INFO | XenTorrentTracker_Model_Torrent::FETCH_THREAD
		)));

		$viewParams = array(
			'torrent' => $torrent,
		);

		return $this->responseView('XenTorrentTracker_ViewPublic_Torrent_FileList', 'xentorrent_post_torrent_file_list', $viewParams);
	}

	public function actionPeerList()
	{
		if (!$this->_getTorrentModel()->canViewPeerList())
		{
			throw $this->getNoPermissionResponseException();
		}	

		$torrentId = $this->_input->filterSingle('torrent_id', XenForo_Input::UINT);
		$torrent = $this->_getTorrentOrError($torrentId);

		$attachmentModel = $this->_getAttachmentModel();

		if (!$attachmentModel->canViewAttachment($torrent))
		{
			return $this->responseNoPermission();
		}

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$fetchOptions = array(
			'perPage'	=> 	50,
			'page'		=> 	$page
		);

		$peers = $this->_getTorrentModel()->getPeerList($torrentId, $fetchOptions);

		$viewParams = array(
			'torrent' => $torrent,
			'peers'	=> $peers,
			'page'	=> $page
		);

		return $this->responseView('XenTorrentTracker_ViewPublic_Torrent_PeerList', 'xentorrent_post_torrent_peer_list', $viewParams);
	}

	public function actionSnatchers()
	{
		if (!$this->_getTorrentModel()->canViewSnatchList())
		{
			throw $this->getNoPermissionResponseException();
		}	

		$torrentId = $this->_input->filterSingle('torrent_id', XenForo_Input::UINT);
		$torrent = $this->_getTorrentOrError($torrentId);

		$attachmentModel = $this->_getAttachmentModel();

		if (!$attachmentModel->canViewAttachment($torrent))
		{
			return $this->responseNoPermission();
		}

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$fetchOptions = array(
			'perPage'	=> 	50,
			'page'		=> 	$page
		);

		$peers = $this->_getTorrentModel()->getSnatchers($torrentId, $fetchOptions);

		$viewParams = array(
			'torrent' => $torrent,
			'peers'	=> $peers,
			'page'	=> $page,
			'viewIps' => $this->_getUserModel()->canViewIps()
		);

		return $this->responseView('XenTorrentTracker_ViewPublic_Torrent_Snatchers', 'xentorrent_post_torrent_snatchers', $viewParams);
	}

	protected function _decodeSearchTableData($data, $isSearchResults = true)
	{
		$decoded = json_decode($data, true);

		if ($decoded === null)
		{
			$decoded = unserialize($data);

			if ($isSearchResults)
			{
				foreach ($decoded AS &$result)
				{
					$result = array($result['content_type'], $result['content_id']);
				}
			}
		}

		return $decoded;
	}

	public function getNoSearchResultsResponse($search)
	{
		if ($search instanceof XenForo_Search_Searcher)
		{
			$errors = $search->getErrors();
			if ($errors)
			{
				return $this->responseError($errors);
			}
		}

		return $this->responseMessage(new XenForo_Phrase('no_results_found'));
	}

	protected function _getTorrentOrError($torrentId)
	{
		$torrent = $this->_getTorrentModel()->getTorrentById($torrentId, array(
			'join'	=> XenTorrentTracker_Model_Torrent::FETCH_ATTACHMENT | XenTorrentTracker_Model_Torrent::FETCH_THREAD
		));

		if (!$torrent)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_torrent_not_found'), 404));
		}

		return $torrent;
	}

	public static function getSessionActivityDetailsForList(array $activities)
	{
		$output = array();
		foreach ($activities AS $key => $activity)
		{
			$output[$key] = new XenForo_Phrase('viewing_torrents');
		}

		return $output;
	}

	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}

	protected function _getTorrentModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Torrent');
	}

	protected function _getRequestModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_FreeleechRequest');
	}

	protected function _getSearchModel()
	{
		return $this->getModelFromCache('XenForo_Model_Search');
	}

	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Category');
	}
}