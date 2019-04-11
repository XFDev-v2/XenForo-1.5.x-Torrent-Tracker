<?php

class XenTorrentTracker_Helper_Torrent
{
	public static function helperGetRaioColor($ratio)
	{
		if ($ratio == '-') return XenForo_Template_Helper_Core::styleProperty('goodRatio');
		if ($ratio < XenForo_Application::getOptions()->xenTorrentMinimumRatio) return XenForo_Template_Helper_Core::styleProperty('badRatio');
		if ($ratio >= 1)   return XenForo_Template_Helper_Core::styleProperty('goodRatio');

		return XenForo_Template_Helper_Core::styleProperty('ratioColor');
	}

	public static function helperLong2Ip($long)
	{
		return long2ip($long);
	}

	public static function helperGetWaitTime($time, $waitFor)
	{
		if (XenForo_Application::$time >= ($time + $waitFor))
			return;

		$waitTime = ($time + $waitFor) - XenForo_Application::$time;

		if ($waitTime < 60)
		{
			return $waitTime . ' s';
		}
		else if($waitTime < 3600)
		{
			return intval($waitTime / 60) . ' m';
		}
		else if($waitTime < 86400)
		{
			return intval($waitTime / 3600) . ' h';
		}
		else
		{
			return intval($waitTime / 86400) . ' d';
		}
	}
}