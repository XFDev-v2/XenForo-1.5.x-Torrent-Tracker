<?php

class XenTorrentTracker_DataWriter_User extends XFCP_XenTorrentTracker_DataWriter_User
{
	protected function _getFields()
	{
		$fields = parent::_getFields();
		$fields['xf_user'] += array(
			'downloaded'	=>	array('type' => self::TYPE_UINT, 'default' => 0, 'max' => 18446744073709551615),
			'uploaded'	    =>  array('type' => self::TYPE_UINT, 'default' => 0, 'max' => 18446744073709551615),
			'wait_time'	    =>  array('type' => self::TYPE_UINT, 'default' => 0),
			'can_leech'	    =>  array('type' => self::TYPE_BOOLEAN, 'default' => 1),
			'peers_limit'	=>  array('type' => self::TYPE_UINT, 'default' => 0),
			'seedbonus'		=>  array('type' => self::TYPE_UINT, 'default' => 0, 'max' => 18446744073709551615),
			'freeleech'	    =>  array('type' => self::TYPE_BOOLEAN, 'default' => 0),
			'torrent_pass_version' =>  array('type' => self::TYPE_UINT, 'default' => 0),
			'torrent_pass'  => array('type' => self::TYPE_STRING, 'default' => ''),
		);

		return $fields;
	}

	protected function _preSave()
	{	
		if($this->isInsert())
		{
			$option = XenForo_Application::getOptions()->xenTTNewUser;

			$userInput = array(
				'downloaded' => 0,
				'uploaded'   => intval($option['upload']) * 1048576, // option * 1MB
				'wait_time'   => !empty($option['wait_time']) ? $option['wait_time'] : 0,
				'peers_limit'   => !empty($option['peers_limit']) ? $option['peers_limit'] : 0,
				'seedbonus'   => 0,
				'freeleech'   => 0,
				'can_leech'   => !empty($option['can_leech']) ? 1 : 0,
				'torrent_pass' => '',
				'torrent_pass_version' => 0
			);

			$this->bulkSet($userInput);
		}
		elseif (isset($_POST['user_edit_form']))
		{
			$input = new XenForo_Input(XenForo_Application::getFc()->getRequest());

			$userInput = $input->filter(array(
				'downloaded' => XenForo_Input::UINT,
				'uploaded'   => XenForo_Input::UINT,
				'wait_time'   => XenForo_Input::UINT,
				'peers_limit'   => XenForo_Input::UINT,
				'seedbonus'   => XenForo_Input::UINT,
				'freeleech'   => XenForo_Input::UINT,
				'can_leech'   => XenForo_Input::UINT,
			));

			$userInput['seedbonus'] = $userInput['seedbonus'] * 10;
			$this->bulkSet($userInput);

			if ($input->filterSingle('reset_passkey', XenForo_Input::UINT))
			{
				$this->set('torrent_pass_version', mt_rand());
			}
		}

		parent::_preSave();
	}
}