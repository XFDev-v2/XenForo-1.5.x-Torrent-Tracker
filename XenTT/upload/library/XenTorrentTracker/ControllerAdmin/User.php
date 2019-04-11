<?php

class XenTorrentTracker_ControllerAdmin_User extends XFCP_XenTorrentTracker_ControllerAdmin_User
{
	public function actionBatchUpdate()
	{
		$response = parent::actionBatchUpdate();
		if ($this->isConfirmedPost())
		{
			$userIds = $this->_input->filterSingle('user_ids', XenForo_Input::JSON_ARRAY);
			$actions = $this->_input->filterSingle('actions', XenForo_Input::ARRAY_SIMPLE);

			if ($this->_input->filterSingle('confirmUpdate', XenForo_Input::UINT) && $actions && $userIds && 
				(!empty($actions['give_freeleech']) || !empty($actions['remove_freeleech'])))
			{
				if (!empty($actions['give_freeleech']) && !empty($actions['remove_freeleech']))
				{
					return $response;
				}

				$userModel = $this->_getUserModel();

				XenForo_Db::beginTransaction();

				foreach ($userIds AS $key => $userId)
				{
					/* @var $userDw XenForo_DataWriter_User */
					$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
					if ($userDw->setExistingData($userId))
					{
						if ($userModel->isUserSuperAdmin($userDw->getMergedData()))
						{
							// no updating of super admins
							continue;
						}

						if (!empty($actions['give_freeleech']))
						{
							$userDw->set('freeleech', 1);
						}

						if(!empty($actions['remove_freeleech']))
						{
							$userDw->set('freeleech', 0);
						}

						$userDw->save();
					}
				}

				XenForo_Db::commit();
			}
		}

		return $response;
	}
}