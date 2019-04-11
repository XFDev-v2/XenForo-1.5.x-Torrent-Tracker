<?php

class XenTorrentTracker_Torrent
{	
	//public $announce = 'http://example.com/announce';
	public $announce = null;
	// Torrent Comment
	public $comment = null;
	// Created by Program
	public $created_by = null;

	private $data;
	
	function __construct() 
	{
		// Here you can load default announce URL, comment and created_by from your configuration file.
	}

    public static function createFromTorrentFile($path)
    {
        if (!is_file($path))
        {
            throw new XenForo_Exception(new XenForo_Phrase('invalid_torrent_file'));
        }

        if (!is_readable($path)) 
        {
            throw new XenForo_Exception('Torrent does not exist or can not be read.');
        }

        // Create a new torrent
        $torrent = new static();
        $torrent->bdecode_file($path);

        return $torrent;
    }

    public function save($filename)
    {
    	if (!$filename || !is_writable($filename) || !is_writable(dirname($filename))) 
    	{
            throw new XenForo_Exception('Could not open file "' . $filename . '" for writing.');
        }

    	// Write the encoded data to the file
        file_put_contents($filename, $this->bencode($this->data));
    }

	/**
	 * Data Setter
	 * @param array $data [array of public variables]
	 * eg:
	 *  $bcoder = new \Bhutanio\BEncode;
	 * 	$bcoder->set([
	 *		'announce'=>'http://www.example.com',
	 *		'comment'=>'Downloaded from example.com',
	 *		'created_by'=>'TorrentSite v1.0'
	 *	]);
	 */
	public function set($data=array())
	{
		if ( is_array($data) ) 
		{
			if ( isset($data['announce']) ) {
				$this->announce = $data['announce'];
			}
			if ( isset($data['comment']) ) {
				$this->comment = $data['comment'];
			}
			if ( isset($data['created_by']) ) {
				$this->created_by = $data['created_by'];
			}
		}
	}
	
	/**
	 * Decode a torrent file into Bencoded data
	 * @param  string  $s 	[link to torrent file]
	 * @param  integer $pos [file position pointer]
	 * @return array/null 	[Array of Bencoded data]
	 * eg:
	 * 		$bcoder = new \Bhutanio\BEncode;
	 * 		$torrent = $bcoder->bdecode( File::get('MyAwesomeTorrent.torrent'));
	 *  	var_dump($torrent);
	 */
	public function bdecode($s, &$pos=0)
	{
		if($pos>=strlen($s)) {
			return null;
		}
		switch($s[$pos]){
		case 'd':
			$pos++;
			$retval=array();
			while ($s[$pos]!='e'){
				$key=$this->bdecode($s, $pos);
				$val=$this->bdecode($s, $pos);
				if ($key===null || $val===null)
					break;
				$retval[$key]=$val;
			}
			$retval["isDct"]=true;
			$pos++;
			return $retval;
		case 'l':
			$pos++;
			$retval=array();
			while ($s[$pos]!='e'){
				$val=$this->bdecode($s, $pos);
				if ($val===null)
					break;
				$retval[]=$val;
			}
			$pos++;
			return $retval;
		case 'i':
			$pos++;
			$digits=strpos($s, 'e', $pos)-$pos;
			$val=round((float)substr($s, $pos, $digits));
			$pos+=$digits+1;
			return $val;
		// case "0": case "1": case "2": case "3": case "4":
		// case "5": case "6": case "7": case "8": case "9":
		default:
			$digits=strpos($s, ':', $pos)-$pos;
			if ($digits<0 || $digits >20)
				return null;
			$len=(int)substr($s, $pos, $digits);
			$pos+=$digits+1;
			$str=substr($s, $pos, $len);
			$pos+=$len;
			//echo "pos: $pos str: [$str] len: $len digits: $digits\n";
			return (string)$str;
		}
		return null;
	}
	/**
	 * Created Torrent file from Bencoded data
	 * @param  array $d [array data of a decoded torrent file]
	 * @return string 	[data can be downloaded as torrent]
	 */
	public function bencode(&$d)
	{
		if(is_array($d))
		{
			$ret="l";
			$isDict = false;
			if( isset($d["isDct"]) && $d["isDct"]===true ){
				$isDict=1;
				$ret="d";
				// this is required by the specs, and BitTornado actualy chokes on unsorted dictionaries
				ksort($d, SORT_STRING);
			}
			foreach($d as $key=>$value) 
			{
				if($isDict){
					// skip the isDct element, only if it's set by us
					if($key=="isDct" and is_bool($value)) continue;
					$ret.=strlen($key).":".$key;
				}
				if (is_int($value) || is_float($value)){
					$ret.="i${value}e";
				}else if (is_string($value)) {
					$ret.=strlen($value).":".$value;
				} else {
					$ret.=$this->bencode ($value);
				}
			}
			return $ret."e";
		} 
		elseif (is_string($d)) // fallback if we're given a single bencoded string or int
			return strlen($d).":".$d;
		elseif (is_int($d) || is_float($d))
			return "i${d}e";
		else
			return null;
	}
	/**
	 * Decode a torrent file into Bencoded data
	 * @param  string $filename 	[File Path]
	 * @return array/null 			[Array of Bencoded data]
	 */
	public function bdecode_file($filename)
	{
		if ( is_file($filename) ) 
        {
			$f = file_get_contents($filename, FILE_BINARY);
			$this->data = $this->bdecode($f);
		}

		return null;
	}
	/**
	 * Generate list of files in a torrent
	 * @param  array $data 	[array data of a decoded torrent file]
	 * @return array 		[list of files in an array]
	 */
	public function getFileList($precision = false)
	{
		$info = $this->getInfoPart();

        if (isset($info['length'])) 
        {
            if ($precision) 
            {
                return array($info['name'] => round($info['length'], $precision));
            }

            return $info['name'];
        }

        if ($precision) 
        {
            $files = array();
            foreach ($info['files'] as $file) 
            {
                $files[implode(DIRECTORY_SEPARATOR, $file['path'])] = round($file['length'], $precision);
            }

            return $files;
        }

        return $info['files'];
	}

    public function getHash($raw = false) 
    {
        $info = $this->getInfoPart();
        return sha1($this->bencode($info), $raw);   
    }

    public function getEncodedHash() 
    {
        return urlencode($this->getHash(true));
    }

    public function getInfo() 
    {
        if (isset($this->data['info']))
        {
        	return $this->data['info'];
        }

        return null;
    }

    public function setAnnounce($announceUrl) 
    {
        $this->announce = $announceUrl;
        $this->data['announce'] = $announceUrl;

        if (isset($this->data['announce-list']) && is_array($this->data['announce-list']))
        {
        	$this->data['announce-list'][] = array($announceUrl);
        }
    }

    public function getAnnounce()
    {
    	if (isset($this->data['announce']))
    	{
    		return $this->data['announce'];
    	}

    	return null;
    }

    public function setComment($comment) 
    {
        $this->comment = $comment;
        $this->data['comment'] = $comment;
    }

    public function getComment()
    {
    	if (isset($this->data['comment']))
    	{
    		return $this->data['comment'];
    	}

    	return null;
    }

    public function getCreatedBy()
    {
    	if (isset($this->data['created by']))
    	{
    		return $this->data['created by'];
    	}

    	return null;
    }	

    public function getSize()
    {
    	$info = $this->getInfoPart();

        // If the length element is set, return that one. If not, loop through the files and generate the total
        if (isset($info['length'])) 
        {
            return $info['length'];
        }

        $files = $this->getFileList();
        $size  = 0;

        foreach ($files as $file) 
        {
            $size = $this->add($size, $file['length']);
        }

        return $size;
    }

    public function getName() 
    {
        $info = $this->getInfoPart();

        return isset($info['name']) ? $info['name'] : '';
    }

	/**
	 * Replace array data on Decoded torrent data so that it can be bencoded into a private torrent file.
	 * Provide the custom data using $this->set();
	 * @param  array $data 	[array data of a decoded torrent file]
	 * @return array 		[array data for torrent file]
	 */
	public function setPrivate()
	{
		// Remove announce
		// announce-list is an unofficial extension to the protocol that allows for multiple trackers per torrent
		unset($this->data['announce']);
		unset($this->data['announce-list']);
		// Bitcomet & Azureus cache peers in here
		unset($this->data['nodes']);
		// Azureus stores the dht_backup_enable flag here
		unset($this->data['azureus_properties']);
		// Remove web-seeds
		unset($this->data['url-list']);
		// Remove libtorrent resume info
		unset($this->data['libtorrent_resume']);
		// Remove profiles / Media Infos
		unset($this->data['info']['profiles']);
		unset($this->data['info']['file-duration']);
		unset($this->data['info']['file-media']);

		// Add Announce URL
		if ( is_array($this->announce) ) 
		{
			$this->data['announce'] = reset($this->announce);
			$this->data["announce-list"] = array();
			$announce_list = array();
			foreach ($this->announce as $announceUri) 
			{
				$announce_list[] = $announceUri;
			}

			$this->data["announce-list"] = $announce_list;
		} 
		else if(!empty($this->announce)) 
		{
			$this->data['announce'] = $this->announce;
		}

		// Add Comment
		if (!empty($this->comment)) 
		{
			$this->data['comment'] = $this->comment;
		}

		// Created by and Created on
		if (!empty($this->created_by)) 
		{
			$this->data['created by'] = $this->created_by;
		}

		// Make Private
		$this->data['info']['private'] = 1;

		// Sort by key to respect spec
		ksort($this->data['info']);
		ksort($this->data);

		//return $this->data;
	}

    private function getInfoPart() 
    {
        $info = $this->getInfo();

        if ($info === null) 
        {
            throw new XenForo_Exception('The info part of the torrent is not set.');
        }

        return $info;
    }

    /**
     * Add method that should work on both 32 and 64-bit platforms
     *
     * @param int $a
     * @param int $b
     * @return int|string
     */
    private function add($a, $b) 
    {
        if (PHP_INT_SIZE === 4) 
        {
            return bcadd($a, $b);
        }

        return $a + $b;
    }
}