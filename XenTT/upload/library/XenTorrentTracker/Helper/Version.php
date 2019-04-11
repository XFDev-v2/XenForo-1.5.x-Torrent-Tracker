<?php

class XenTorrentTracker_Helper_Version
{
	protected static $_versionID = 15; 

    public static function isCorrectPluginVersion()
    {
        $addOns = XenForo_Application::get('addOns');   
        $installedPluginVersionId = $addOns['xenTorrentTracker'];  

        if($installedPluginVersionId >= self::$_versionID)
        {
            return true;  // 0 query
        }

        return false;
    }
}