<?php

class XenTorrentTracker_DataWriter_Log extends XenForo_DataWriter
{
	protected $_existingDataErrorPhrase = 'requested_log_entry_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xftt_log' => array(
				'log_id'        => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'log_date'      => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'params'		=> array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}'),
				'message'		=> array('type' => self::TYPE_STRING, 'default' => '')
			)
		);
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!($id = $this->_getExistingPrimaryKey($data)))
		{
			return false;
		}

		return array('xftt_log' => $this->_getLogModel()->getLogById($id));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'log_id = ' . $this->_db->quote($this->getExisting('log_id'));
	}

	protected function _getLogModel()
	{
		return $this->getModelFromCache('XenTorrentTracker_Model_Log');
	}
}