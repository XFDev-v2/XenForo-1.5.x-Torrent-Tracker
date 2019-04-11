<?php

class XenTorrentTracker_DataWriter_Torrent extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'requested_torrent_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xftt_torrent' => array(
				'torrent_id'    => array('type' => self::TYPE_UINT, 'required' => true),
				'info_hash'     => array('type' => self::TYPE_BINARY, 'required' => true),
				'category_id'   => array('type' => self::TYPE_UINT, 'required' => true),
				'user_id'		=> array('type' => self::TYPE_UINT, 'default' => XenForo_Visitor::getUserId()),
				'thread_id'		=> array('type' => self::TYPE_UINT, 'default' => 0),
				'leechers'      => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'seeders'       => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'completed'     => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'flags'         => array('type' => self::TYPE_UINT, 'default' => 0),
				'mtime'   		=> array('type' => self::TYPE_UINT, 'default' => 0),
				'ctime'   		=> array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'size'			=> array('type' => self::TYPE_UINT, 'default' => 0, 'max' => 18446744073709551615),
				'number_files'	=> array('type' => self::TYPE_UINT, 'default' => 0),
				'freeleech'		=> array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'last_reseed_request' => array('type' => self::TYPE_UINT, 'default' => 0)
			),
			'xftt_torrent_info' => array(
				'torrent_id'    => array('type' => self::TYPE_UINT, 'default' => array('xftt_torrent', 'torrent_id'), 'required' => true),
				'file_details'	=> array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}')
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'torrent_id'))
		{
			return false;
		}

		$returnData = $this->getTablesDataFromArray($this->_getTorrentModel()->getTorrentById($id, array(
			'join' => XenTorrentTracker_Model_Torrent::FETCH_INFO
		)));
		
		return $returnData;
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'torrent_id = ' . $this->_db->quote($this->getExisting('torrent_id'));
	}

	protected function _preSave()
	{
		if ($this->isUpdate() && $this->isChanged('freeleech'))
		{
			$this->set('flags', 3);
		}
	}

	protected function _postSave()
	{
		if ($this->isInsert() && $this->get('thread_id'))
		{
			$this->_db->update('xf_thread', array(
				'torrent_id' => $this->get('torrent_id')
			), 'thread_id = ' . $this->get('thread_id'));
		}
	}

	protected function _postDelete()
	{
		$this->_db->update('xf_thread', array(
			'torrent_id' => 0
		), 'thread_id = ' . $this->get('thread_id'));
	}

	protected function _getTorrentModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Torrent');
	}
}