<?php

class XenTorrentTracker_Model_Category extends XenForo_Model
{
	public $categoryOptions = '';

	public function getTorrentCategories($selectedCategories = array())
	{		
		$categories = $this->fetchAllKeyed('
			SELECT node.title, node.description, node.breadcrumb_data, node.node_id, node.lft, node.rgt, node.parent_node_id, node.display_order, node.depth, 
				forum.discussion_count, forum.allow_posting
			FROM xf_node as node
			LEFT JOIN xf_forum forum ON (forum.node_id = node.node_id)
			WHERE node.is_torrent_category = 1
			ORDER BY lft ASC
		', 'node_id');

		$categoryPermissions = $this->_getNodeModel()->getNodePermissionsForPermissionCombination();
		foreach ($categories AS $category)
		{
			if (!XenForo_Permission::hasPermission($categoryPermissions, $category['node_id'], 'view'))
			{
				unset($categories[$category['node_id']]);
			}
			else
			{
				$selected = '';
				if (!empty($selectedCategories) && in_array($category['node_id'], $selectedCategories)) 
				{
					$selected = 'selected="selected"';
				}

				// dirty
				$this->categoryOptions .= '<option value="' . $category['node_id'] . '" ' . $selected . '>' . str_repeat('&nbsp; &nbsp; ', $category['depth']) . htmlspecialchars($category['title']) . '</option>';
			}
		}

		return $categories;
	}

	public function getChildCategories($categories, $selectedCategoryId)
	{
		if (empty($categories[$selectedCategoryId]))
		{
			return array();
		}

		$range = $categories[$selectedCategoryId];
		$childrens = array();

		foreach($categories AS $categoryId => $category)
		{
			if ($category['lft'] > $range['lft'] && $category['rgt'] < $range['rgt'])
			{
				$childrens[$categoryId] = $category;
			}
		}

		return $childrens;
	}

	public function groupCategoriesByParent(array $categories, $selectedCategoryId = 0)
	{
		$categoryList = array();
		$ungroupCategoryList = array();

		foreach ($categories AS $category)
		{
			$categoryList[$category['parent_node_id']][$category['node_id']] = $category;
			if (!$selectedCategoryId && $category['parent_node_id'] != 0 && empty($categories[$category['parent_node_id']]))
			{
				$ungroupCategoryList[$category['parent_node_id']] = 1;
			}
		}

		$categoryList = $this->applyRecursiveCountsToGrouped($categoryList);
		$categoryList['top'] = isset($categoryList[$selectedCategoryId]) ? $categoryList[$selectedCategoryId] : array();

		if (!empty($ungroupCategoryList))
		{
			$categoryList['ungrouped'] = array();
			foreach ($ungroupCategoryList AS $parentId => $val)
			{
				$categoryList = $this->applyRecursiveCountsToGrouped($categoryList, $parentId);
				if (!empty($categoryList[$parentId]))
				{
					$categoryList['ungrouped'] = array_merge($categoryList['ungrouped'], $categoryList[$parentId]);
				}
			}
		}

		return $categoryList;
	}

	public function applyRecursiveCountsToGrouped(array $grouped, $parentCategoryId = 0)
	{
		if (!isset($grouped[$parentCategoryId]))
		{
			return array();
		}

		$this->_applyRecursiveCountsToGrouped($grouped, $parentCategoryId);
		return $grouped;
	}

	public function getCategoryBreadcrumb($category, $includeSelf = true)
	{
		$breadcrumbs = array();

		if (!isset($category['categoryBreadcrumb']))
		{
			$category['categoryBreadcrumb'] = unserialize($category['breadcrumb_data']);
		}

		foreach ($category['categoryBreadcrumb'] AS $catId => $breadcrumb)
		{
			$breadcrumbs[$catId] = array(
				'href' => XenForo_Link::buildPublicLink('full:torrents', '', array('category_id' => $breadcrumb['node_id'])),
				'value' => $breadcrumb['title']
			);
		}

		if ($includeSelf)
		{
			$breadcrumbs[$category['node_id']] = array(
				'href' => XenForo_Link::buildPublicLink('full:torrents', '', array('category_id' => $category['node_id'])),
				'value' => $category['title']
			);
		}

		return $breadcrumbs;
	}

	protected function _applyRecursiveCountsToGrouped(array &$grouped, $parentCategoryId)
	{
		$output = array(
			'discussion_count' => 0,
		);

		foreach ($grouped[$parentCategoryId] AS $categoryId => &$category)
		{
			if (isset($grouped[$categoryId]))
			{
				$childCounts = $this->_applyRecursiveCountsToGrouped($grouped, $categoryId);
				$category['discussion_count'] += $childCounts['discussion_count'];
			}

			$output['discussion_count'] += $category['discussion_count'];
		}

		return $output;
	}

	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}
}