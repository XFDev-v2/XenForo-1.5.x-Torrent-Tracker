<?php

class XenTorrentTracker_DataWriter_Forum extends XFCP_XenTorrentTracker_DataWriter_Forum
{
	protected function _getFields()
	{
		$fields = parent::_getFields();

		$fields['xf_forum'] += array(
			'torrent_category_image' => array('type' => self::TYPE_STRING, 'maxLength' => 100, 'default' => '')
		);

		$fields['xf_node'] += array(
			'is_torrent_category' => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
		);

		return $fields;
	}

	protected function _preSave()
	{
		if (isset($_POST['node_type_id']))
		{
			if (isset($_POST['torrent_category_image']))
			{
				$this->set('torrent_category_image', trim($_POST['torrent_category_image']));
			}

			$this->set('is_torrent_category', isset($_POST['is_torrent_category']) ? true: false);
		}

		parent::_preSave();
	}
}