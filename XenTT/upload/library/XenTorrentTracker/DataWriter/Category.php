<?php
class XenTorrentTracker_DataWriter_Category extends XFCP_XenTorrentTracker_DataWriter_Category
{
	protected function _getFields()
	{
		$fields = parent::_getFields();

		$fields['xf_node'] += array(
			'is_torrent_category' => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
		);

		return $fields;
	}

	protected function _preSave()
	{
		if (isset($_POST['node_type_id']))
		{
			$this->set('is_torrent_category', isset($_POST['is_torrent_category']) ? true: false);
		}

		parent::_preSave();
	}
}

