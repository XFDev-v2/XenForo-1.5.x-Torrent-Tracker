<?php

class XenTorrentTracker_ControllerPublic_Tracker extends XenForo_ControllerPublic_Abstract
{	
	protected function _postDispatch($controllerResponse, $controllerName, $action)
	{
		if (XenForo_Visitor::getInstance()->hasPermission('xenTorrentTracker', 'canMakeFreeleech')) 
		{
			$controllerResponse->containerParams['openFreeleechRequests'] = $this->_getRequestModel()->countRequests();
		}
	}

	public function actionIndex()
	{		
		$defaultOrder = 'time';
		$defaultOrderDirection = 'desc';
		$perPage = XenForo_Application::getOptions()->xenTTPerPage;

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection));
		$selectedCategoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		$threadIds = $this->_input->filterSingle('thread_id', XenForo_Input::UINT, array('array' => true));

		$input = $this->_input->filter(array(
			//'search_id' => XenForo_Input::UINT,
			'freeleech' => XenForo_Input::UINT,
		));

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('torrents')
		);

		$search = array();
		if (XenForo_Application::getInstance()->offsetExists('search')) 
		{	
			$search = XenForo_Application::get('search');
		}

		$torrentModel = $this->_getTorrentModel();
		$categoryModel = $this->_getCategoryModel();
	
		$categories = $categoryModel->getTorrentCategories(
			(isset($search['nodes']) && is_array($search['nodes'])) ? $search['nodes'] : array()
		);

		$category = array();

		if ($selectedCategoryId) 
		{
			if (empty($categories[$selectedCategoryId])) 
			{
				return $this->responseError(new XenForo_Phrase('invalid_torrent_category_specified'));
			}

			$category = $categories[$selectedCategoryId];
		}

		$categoryList = $categoryModel->groupCategoriesByParent($categories, $selectedCategoryId);

		$criteria = array(
			'freeleech' => $input['freeleech'],
		);

		if (!empty($search))
		{
			$criteria['thread_id'] = $threadIds;
		}
		elseif ($selectedCategoryId) 
		{ 
			if (empty($categoryList[$selectedCategoryId]))
			{
				$criteria['category_id'] = $selectedCategoryId;
			}
			else
			{
				// include child categories aswell
				$criteria['category_id'] = array_keys($categoryModel->getChildCategories($categories, $selectedCategoryId));
				$criteria['category_id'][] = $selectedCategoryId;
			}
		}
		else
		{
			// multiple categories
			$criteria['category_id'] = array_keys($categories);
		}

		$total = $torrentModel->countTorrents($criteria);
		$this->canonicalizePageNumber($page, $perPage, $total, 'torrents');

		$fetchOptions = array();
		if ($order == 'replies') 
		{
			$fetchOptions['join'] =  XenTorrentTracker_Model_Torrent::FETCH_THREAD;
		}

		$torrentIds = $torrentModel->getTorrentIdsInRange($criteria,  array(
			'page'	=> $page,
			'perPage' => $perPage,
			'orderBy' => $order,
			'orderDirection' => $orderDirection
		) + $fetchOptions);

		$torrents = $torrentModel->getTorrentsByIds($torrentIds, $this->_getTorrentFetchOptions($order, $orderDirection));
		$torrents = $torrentModel->prepareTorrents(
			$torrentModel->filterUnviewableTorrents($torrents)
		);

		$orderParams = array();
		$displayConditions = array(
			'category_id'	=>	$selectedCategoryId ? $selectedCategoryId : null,
			'search_id'	=> !empty($search['search_id']) ? $search['search_id'] : null,
			'freeleech'   => ($input['freeleech']) ? 1 : null,
			'searchform' => $this->_input->filterSingle('searchform', XenForo_Input::UINT) ? true : false
		);

		foreach ($torrentModel->getSortFields() AS $field)
		{
			$orderParams[$field] = $displayConditions;
			$orderParams[$field]['order'] = ($field != $defaultOrder ? $field : false);
			if ($order == $field)
			{
				$orderParams[$field]['direction'] = ($orderDirection == 'desc' ? 'asc' : 'desc');
			}
		}

		$pageNavParams = $displayConditions;
		$pageNavParams['order'] = ($order != $defaultOrder ? $order : false);
		$pageNavParams['direction'] = ($orderDirection != $defaultOrderDirection ? $orderDirection : false);

		//$endOffset = ($page - 1) * $perPage + count($torrents);

		$viewParams = array(
			'torrents'	=> $torrents,

			//'categories' => $categories,
			'categoryList' => $categoryList,
			'selectedCategoryId' => $selectedCategoryId,
			'category'	=> $category,
			'categoryBreadcrumbs' => !empty($category) ? $categoryModel->getCategoryBreadcrumb($category) : array(),

			'perPage' => $perPage,
			'page' => $page,
			'pageNavParams' => $pageNavParams,
			
			'orderParams'	=> $orderParams,
			'order'	=> $order,
			'orderDirection' => $orderDirection,
			'displayConditions' => $displayConditions,

			'total'	=> $total,
			//'startOffset' => ($page - 1) * $perPage + 1,
			//'endOffset'	=> $endOffset,

			'search' => $search,
			'searchform' => $displayConditions['searchform'],
			'categoryOptions' => $categoryModel->categoryOptions,

			'xenTTStats' => XenForo_Application::getSimpleCacheData('xenTTStats'),
			'canUploadTorrent' => $torrentModel->canUploadTorrent()
		);

		return $this->responseView('XenTorrentTracker_ViewPublic_Index', 'xentorrent_index', $viewParams);
	}

	public function actionTop()
	{
		$input = $this->_input->filter(array(
			'type' => array(XenForo_Input::STRING, 'default' => 'user'),
		));

		if ($input['type'] == 'user')
		{
			$input['subtype'] = $this->_input->filterSingle('subtype', XenForo_Input::STRING, array('default' => 'uploaders'));
			list($title, $description, $items) = $this->_getStatsModel()->getTopUsers($input['subtype']);
		}
		else
		{
			$input['type'] = 'torrent'; // its either user or torrent
			$input['subtype'] = $this->_input->filterSingle('subtype', XenForo_Input::STRING, array('default' => 'active'));
			list($title, $description, $items) = $this->_getStatsModel()->getTopTorrents($input['subtype']);
		}

		$viewParams = array(
			'type'	=>	$input['type'],
			'subtype' => $input['subtype'],
			
			'items' => $items,

			'title' => $title,
			'description' => $description
		);

		return $this->responseView('XenTorrentTracker_ViewPublic_Top', 'xentorrent_top', $viewParams);
	}

	public function actionFreeleechRequests()
	{
		if (!$this->_getTorrentModel()->canAcceptFreeleechRequest())
		{
			throw $this->getNoPermissionResponseException();
		}

		$open = $this->_input->filterSingle('open', XenForo_Input::UINT, array('default' => 1));
		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$perPage = 20;

		$requestModel = $this->_getRequestModel();

		$total = $requestModel->countRequests($open);
		$this->canonicalizePageNumber($page, $perPage, $total, 'torrents/freeleech-requests');

		$requests = $requestModel->getRequests(
			$open, // open 
			array(
				'page' => $page,
				'perPage' => $perPage
			)
		);

		$pageNavParams = array(
			'open'	=>	$open
		);

		$viewParams = array(
			'open'	=>	$open,
			'requests'  => $requests,

			'perPage' => $perPage,
			'page' => $page,
			'total' => $total,
			'pageNavParams' => $pageNavParams
		);

		return $this->responseView('XenTorrentTracker_ViewPublic_FreeleechRequestList', 'xentorrent_freeleech_request_list', $viewParams);
	}

	protected function _getTorrentFetchOptions($order, $orderDirection)
	{
		return array(
			'join' => 
				XenTorrentTracker_Model_Torrent::FETCH_THREAD | XenTorrentTracker_Model_Torrent::FETCH_FORUM |
				XenTorrentTracker_Model_Torrent::FETCH_USER,
			'orderBy' => $order,
			'orderDirection' => $orderDirection
		);
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

	protected function _getTorrentModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Torrent');
	}

	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Category');
	}

	protected function _getRequestModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_FreeleechRequest');
	}

	protected function _getStatsModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Stats');
	}
}