<?php

class XenTorrentTracker_Helper_Tracker
{
	const NUMBER_OF_TRIES = 2;
	
	public static function updateTracker(array $params, &$logError)
	{
		$config = XenForo_Model::create('XenTorrentTracker_Model_Tracker')->getConfig();
		$tracker = XenForo_Application::getOptions()->xenTorrentTracker;

		$host = $tracker['host'];
		if (empty($host))
		{
			return new XenForo_Phrase('torrent_tracker_host_is_not_set');
		}

		if (!isset($config['torrent_pass_private_key']))
		{
			return new XenForo_Phrase('private_key_not_set');
		}

		$host = rtrim($host, '/');
		$port = $config['listen_port'];

		$url = "http://$host:$port/update";
		$params['key'] = $config['torrent_pass_private_key'];

		$numberOfTries = self::NUMBER_OF_TRIES;
		$message = '';

		while ($numberOfTries)
		{
			try
			{
				$client = XenForo_Helper_Http::getClient($url, array(
					'keepalive' => true,
					'timeout' => 15
				));

				$client->setParameterGet($params);
				$request = $client->request('GET');

				if ($request->isSuccessful())
				{
					$logError = false;
					return $request->getBody();
				}				
			}
			catch (Zend_Http_Client_Exception $e)
			{
				//$message = $e->getMessage();
				$logError = $e;
			}

			$numberOfTries--;
		}

		return $message;
	}

	public static function send(array $params = array(), $log = true)
	{
		if (!isset($params['action']))
		{
			return;
		}

		$error = false;
		$start = microtime(true); 

		$message = self::updateTracker($params, $error);
		
		if (!$log)
		{
			return self::_returnResponse($message);
		}

		if ($error) 
		{
			$message = $error->getMessage();
			$response = false;
		}
		else
		{
			$response = self::_returnResponse($message);
		}

		$logMessage = $message . self::_getTime($start, microtime(true));
		$db = XenForo_Application::getDb();

		try 
		{
			$db->insert('xftt_log', array(
				'log_date' => XenForo_Application::$time,
				'message' => $logMessage,
				'params'  => serialize($params),
				'action'  => $params['action'],
				'is_error' => empty($error) ? 0 : 1
			));

			if (!empty($error))
			{
				$file = $error->getFile();
				$messagePrefix = 'XenTT Error: ';

				$db->insert('xf_error_log', array(
					'exception_date' => XenForo_Application::$time,
					'user_id' => null,
					'ip_address' => '',
					'exception_type' => get_class($error),
					'message' => $messagePrefix . $error->getMessage(),
					'filename' => $file,
					'line' => $error->getLine(),
					'trace_string' => $error->getTraceAsString(),
					'request_state' => serialize($params)
				));
			}
		} 
		catch (Exception $e) {}

		return $response;
	}

	protected static function _returnResponse($response)
	{
		$response = str_replace(array("\n\r", "\n", "\r"), '', trim($response));

		try
		{
			$response = json_decode($response, true);
		}
		catch(Exception $e)
		{
			$response = array();
		}

		return $response;
	}

	protected static function _getTime($start, $end)
	{
		$time = ($end - $start) * 1000;
		if ($time >= 1000)
		{
			return ' (' . number_format(($time / 1000), 2) .' seconds)';
		}
		else
		{
			return ' (' . number_format($time, 2) .' ms)';
		}
	}
}