<?php

namespace toto;
require __DIR__.'/../conf/config.php';

$appFolder = $config['appFolder'];
$imgFolder = $config['imageFolder'];
$iconFolder = $imgFolder.'icons/';

abstract class Application
{
	abstract public function getInfos($ipaPath, $rootPath);
	protected static $extension; // string with ipa ou apk

	protected $id;
	protected $name;
	protected $description;
	protected $versions;

	public function Application($id='', $name='', $description='', $versions='')
	{
		$this->id = $id;
		$this->name = $name;
		$this->description = $description;
		$this->versions = $versions;
	}

	public function getVersion(){
		return $this->versions;
	}

	public function setVersion($argVersions){
		$this->versions = $argVersions;
	}

	public function getId(){
		return $this->id;
	}

	public function setId($argId){
		$this->id = $argId;

	}
}


class IosApp extends Application
{
	protected static $extension = 'ipa';

	public function getInfos($ipaPath, $rootPath=null) // old getApplicationInfo
	{
		$za = new ZipArchive();
		$za->open($ipaPath);

		for ($i=0; $i<$za->numFiles;$i++) {
			$entry = $za->statIndex($i);
			$entryName = $entry['name'];

			if (preg_match('/^Payload\/(.*?)\.app\/config.xml$/i', $entryName)) {
				$xmlPath = "zip://{$ipaPath}#{$entryName}";

				$config = new SimpleXMLElement(file_get_contents($xmlPath));

				$path = $ipaPath;

				if ($rootPath){
					$path = Path::getRelativePath($rootPath, $path);
				}

				$this->id = (string)$config->attributes()['id'];
				$this->name = (string)$config->name;
				$this->description = (string)$config->description;
				$this->versions = [(string)$config->attributes()['version'] => $path];
				return $this;
			}
		}
		return null;
	}

}

class AndroidApp extends Application
{
	protected static $extension = 'apk';

	public function getInfos($apkPath, $rootPath=null) // old getApkinfo
		{
			$apk = new \ApkParser\Parser($apkPath);
			$manifest = $apk->getManifest();

			$path = $apkPath;

			if ($rootPath)
				$path = Path::getRelativePath($rootPath, $path);

			$this->id = $manifest->getPackageName();
			$this->name = $manifest->getApplication()->getActivityNameList()[0];
			$this->description = "";
			$this->versions = [$manifest->getVersionName() => $path ];
			return $this;
		}

}


class Sort // DONE
{
	private $a;
	private $b;

	function Sort($a='', $b='')
	{
		$this->a = $a;
		$this->b = $b;
	}

	static function sortByName($array)
	{
		usort($array, function ($a, $b)
		{
			$a['name'] = strtolower($a['name']);
			$b['name'] = strtolower($b['name']);

			if ($a['name'] == $b['name'])
			{
					return 0;
			}

			return ($a['name'] < $b['name']) ? -1 : 1;
		});
	}

	static function sortByVersions($array){

		for( $i= 0 ; $i <sizeof($array)  ; $i++ ){
			$appTemp =$array[$i]['versions'];
			uksort($appTemp, function ($a, $b)
			{
				return  -1 * version_compare($a, $b); // multiply by -1 to reverse sort order
			});
			$array[$i]['versions'] = $appTemp;
		}
	}


	// private static function byName($a, $b) // old sortAppsByName
	// {
	// 	$a['name'] = strtolower($a['name']);
	// 	$b['name'] = strtolower($b['name']);

	// 	if ($a['name'] == $b['name']) {
	// 			return 0;
	// 	}

	// 	return ($a['name'] < $b['name']) ? -1 : 1;
	// }


	// private static function byVersions($a, $b) // sortVersions
	// {
	// 	return  -1 * version_compare($a, $b); // multiply by -1 to reverse sort order
	// }

}


class Path // DONE
{
	static function join() // old joinPath, as many arguments as needed
	{
		$args = func_get_args();

		$result = false;

		if (!empty($args))
			$result = preg_replace('/\/$/', '', array_shift($args)); # Remove trailing slash

		if (!empty($args)) {
			$result .= '/';
			$result .= preg_replace('/^\//', '', array_shift($args)); # Remove trailing slash
		}

		if (!empty($args))
			$result = call_user_func_array('joinPath', array_merge([$result],$args)); # recurse with remaining args.

		return $result;
	}

	static function getRelativePath($parent, $child)
	{
		$result = substr($child, strlen($parent));
		$result = preg_replace('/^\//', '', $result);
		return $result;
	}


	static function getCurrentUrlFolder()
	{
		return preg_replace('/[^\/]*(\?.*)?$/', '', Path::getCurrentUrl());
	}


	private static function getCurrentUrl()
	{
		return Path::getCurrentServerAddress().$_SERVER['REQUEST_URI'];
	}


	private static function getCurrentServerAddress()
	{
		$protocol = "http";

		if (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'Off')
			$protocol .= 's';

		return "{$protocol}://{$_SERVER['SERVER_NAME']}:{$_SERVER['SERVER_PORT']}";
	}
}


class AppList
{
	private $extension;
	private $apps = array();

	public function AppList($ext){

		$this->extension = $ext;
	}
	public function check($appList, $appToTest) // old checkAppAlreadyInList ... check if App is Already In the List
	{
		foreach ($appList as $app){
			if (strcmp($app->getId(),$appToTest->getId()) == 0){
				return $app->getId();
			}
		}
		return -1;
	}
	public function getApps(){
		return $this->apps;
	}
	public function findPaths($dir) // old findIosAppPath et findAndroidAppPath
	{


		// $dir = joinPath($dir); # remove trailing slash, if any
		$dir = Path::join($dir); # remove trailing slash, if any
		$files = scandir($dir);
		$files = array_diff(scandir($dir), array('..', '.')); #remove  . .. directory in the linux environment
		$appPathList = [];

		foreach ($files as $file) {
			if (preg_match('/\.'.$this->extension.'$/i', $file)) // $extension represente la variable static $extension
			{
				$appPathList [] = "{$dir}/{$file}";
			}
			else if (is_dir("{$dir}/{$file}"))
			{
				$appPathList2  = $this->findPaths("{$dir}/{$file}"); // a remplacer
				// $appPathList2  = findIosAppPaths("{$dir}/{$file}"); // a remplacer
				$appPathList = array_merge($appPathList, $appPathList2);
			}
		}
		return $appPathList;
	}

	public function find($dir) // A FINIR fusion de findAndroidApps et findIosApps
	{
		// $path = new Path();
		$dirResult = Path::join($dir); # remove trailing slash, if any
		// $dir = joinPath($dir); # remove trailing slash, if any

		$files = scandir($dirResult);
		$result = array();
		global $appFolder;
		global $imgFolder;
		global $iconFolder;

		// $appPathList = findAndroidAppPaths($dir);
		$appPathList = $this->findPaths($dir);

		foreach ($appPathList as $appPath){

			// $temp = getApkinfo($appPath, $dir);
			// $indice = checkAppAlreadyInList($result, $temp);

			// var $app;

			if ($this->extension == 'ipa'){
				$app = new IosApp();

			}
			else if ($this->extension == 'apk'){
				$app = new AndroidApp();
			}


			$app->getInfos($appPath, $dir);
			$temp = $app;
		    print_r($temp);
			//get_object_vars($temp) ;

			$indice = $this->check($result, $temp);
			// $indice = checkAppAlreadyInList($result, $temp);

			if ($indice == -1){
				$results = $temp;

		    } else {
		    	$apptemp = $result[$indice];
		    	$versions = array_merge($apptemp->getVersion(), $temp->getVersion());
		    	// $result[$indice]['versions'] = $versions;
		    	$apptemp->setVersion($versions);
		    }
		}
		Sort::sortByName($result);
		// $result = Sort::sortByName($result);
		// usort($result, Sort::byName());
		// $result = Sort::sortByVersions($result);
		Sort::sortByVersions($result);

			// Trouver l icone dans le fichier

		foreach ($result as $app) {
			$appName = $app['name'];
			if ($this->extension == 'ipa'){
				$iconPath = 'Payload/'.$appName.'.app/icon-72.png';
			}
			else if ($this->extension == 'apk'){
				$iconPath = 'res/drawable/icon.png';
			}

			$za = new ZipArchive();
			$za->open($appFolder.$app['versions'][array_keys($app['versions'])[0]]);
			$za->extractTo(Path::join($imgFolder,'tmp'), $iconPath);
			$za->close();

			if (!file_exists(Path::join($iconFolder,$appName))) {
				mkdir(joinPath($iconFolder,$appName), 0755, true);
			}

			if (file_exists(jPath::join($imgFolder,'tmp/',$iconPath))) {
				copy(Path::join($imgFolder,'tmp/',$iconPath), Path::join($iconFolder,$appName,'/icon.png'));
			}

			if (file_exists($iconFolder.$appName)) {
				rrmdir(Path::join($imgFolder,'tmp/res/'));
			}
		}
		$this->apps = $result;
	}

	public function rrmdir($dir)
	{
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object);
	       		}
	     	}
	     	reset($objects);
	     	rmdir($dir);
		}
	}
}

class Web {

	public static function sendNotFound() {
		header('HTTP/1.0 404 Not Found');
		echo "Not Found";
		exit();
	}


	public static function setContentType($contentType) {
		header("Content-type: {$contentType}");
	}

	// NOT USED YET //
	public static function getBrowser(){ // fonction avancée

		$u_agent = $_SERVER['HTTP_USER_AGENT'];
	    $bname = 'Unknown';
	    $platform = 'Unknown';
	    $version= "";

	    //First get the platform?
	    if (preg_match('/linux/i', $u_agent)) {
	        $platform = 'linux';
	    }
	    elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
	        $platform = 'mac';
	    }
	    elseif (preg_match('/windows|win32/i', $u_agent)) {
	        $platform = 'windows';
	    }

	    // Next get the name of the useragent yes seperately and for good reason
	    if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent))
	    {
	        $bname = 'Internet Explorer';
	        $ub = "MSIE";
	    }
	    elseif(preg_match('/Firefox/i',$u_agent))
	    {
	        $bname = 'Mozilla Firefox';
	        $ub = "Firefox";
	    }
	    elseif(preg_match('/Chrome/i',$u_agent))
	    {
	        $bname = 'Google Chrome';
	        $ub = "Chrome";
	    }
	    elseif(preg_match('/Safari/i',$u_agent))
	    {
	        $bname = 'Apple Safari';
	        $ub = "Safari";
	    }
	    elseif(preg_match('/Opera/i',$u_agent))
	    {
	        $bname = 'Opera';
	        $ub = "Opera";
	    }
	    elseif(preg_match('/Netscape/i',$u_agent))
	    {
	        $bname = 'Netscape';
	        $ub = "Netscape";
	    }

	    // finally get the correct version number
	    $known = array('Version', $ub, 'other');
	    $pattern = '#(?<browser>' . join('|', $known) .
	    ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
	    if (!preg_match_all($pattern, $u_agent, $matches)) {
	        // we have no matching number just continue
	    }

	    // see how many we have
	    $i = count($matches['browser']);
	    if ($i != 1) {
	        //we will have two since we are not using 'other' argument yet
	        //see if version is before or after the name
	        if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
	            $version= $matches['version'][0];
	        }
	        else {
	            $version= $matches['version'][1];
	        }
	    }
	    else {
	        $version= $matches['version'][0];
	    }

	    // check if we have a number
	    if ($version==null || $version=="") {$version="?";}

	    return array(
	        'userAgent' => $u_agent,
	        'name'      => $bname,
	        'version'   => $version,
	        'platform'  => $platform,
	        'pattern'    => $pattern
	    );
	}
}
