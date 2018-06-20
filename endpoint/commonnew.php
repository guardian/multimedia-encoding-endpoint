<?php
$autoload_name = "/opt/vendor/autoload.php";
if(! file_exists($autoload_name))
        $autoload_name = dirname(__FILE__).'/vendor/autoload.php';
if(! file_exists($autoload_name))
        throw new Exception("Not properly installed - could not find composer's autoload file in /opt or in project directory");
require $autoload_name;

use Aws\Sns\SnsClient;

/*stop AWS complaining*/
date_default_timezone_set('UTC');

function load_config()
{
	$config=NULL;
	if(file_exists('endpoint.ini'))
		$config = parse_ini_file('endpoint.ini');
		
	if(! $config && file_exists('/etc/endpoint.ini'))
		$config = parse_ini_file('/etc/endpoint.ini');
		
	if(! $config) $config=[];
	if(array_key_exists('DB_HOST',$GLOBALS)) $config['dbhost'] = [ $GLOBALS['DB_HOST'] ];	
	if(array_key_exists('DB_USER',$GLOBALS)) $config['dbuser'] = $GLOBALS['DB_USER'];
	if(array_key_exists('DB_PASSWD',$GLOBALS)) $config['dbpass'] = $GLOBALS['DB_PASSWD'];
	if(array_key_exists('DB_DBNAME',$GLOBALS)) $config['dbname'] = $GLOBALS['DB_DBNAME'];
	if(array_key_exists('MEMCACHE_HOST',$GLOBALS)) $config['memcache_host'] = $GLOBALS['MEMCACHE_HOST'];
	if(array_key_exists('MEMCACHE_PORT',$GLOBALS)) $config['memcache_port'] = $GLOBALS['MEMCACHE_PORT'];

	return $config;
}

function init(){
	$snsConfig = array(
		'region' => 'eu-west-1',
		'scheme' => 'https',
		'version' => '2010-03-31',
	);

	$GLOBALS['sns'] = SnsClient::factory($snsConfig);
	
	$config = load_config();

        if(array_key_exists('dsn',$config)){	
		$client = new Raven_Client($config['dsn']);
		$error_handler = new Raven_ErrorHandler($client);
		$error_handler->registerExceptionHandler();
		$error_handler->registerErrorHandler();
		$error_handler->registerShutdownFunction();
		$GLOBALS['raven'] = $client;
	}
}

#There is a bug in iOS clients whereby rather than using the _actual_ redirected m3u8 URL to locate submanifests
#it simply takes the orignal referer and replaces everything after the last /.  So, if the client came here through
#us the url becomes endpoint.yadayada.com/interactivevideos/video.php?format=video/{filename}.m3u8.
#We deem these "dodgy m3u8 format strings" and deal with them here by supplying override values back to the main func
function has_dodgy_m3u8_format($formatString)
{
$matches=null;

$n = preg_match('/video\/(.*\.m3u8)$/',$formatString,$matches);
#error_log(print_r($matches,true));
if($n==1){
	return array("format"=>"video/m3u8", "filename"=>$matches[1]);
}
return null;
}

class ContentErrorException extends Exception
{
    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}

function find_content($config){
	$idmappingdata = NULL;
	$fcsid = NULL;
	$octid = NULL;
	try {
		if(! $config) $config = load_config();
	} catch(Exception $e){
	//if(! $config or config==[]){
		$fn = "(none)";
		if(array_key_exists('file',$_GET)) $fn =$_GET['file'];
		
		$details = array(
			'status'=>'error',
			'detail'=>array(
				'error_code'=>500,
				'error_string'=>"No config file available",
				'file_name'=>$fn,
				'query_url'=>$_SERVER['REQUEST_URI'],
			),
			);
		report_error($details);
		header('HTTP/1.0 500 Server Config Error',true,500);
		throw new ContentErrorException("No config file available");
	}

	if(array_key_exists('memcache_host',$config)){
		$mc = new Memcache;
		$mcport = 11211;
		if(array_key_exists('memcache_port',$config)){
			$mcport = intval($config['memcache_port']);
		}
		$mc->connect($config['memcache_host'],$mcport);
		$mcexpiry = 240;	#in seconds
		if(array_key_exists('memcache_expiry',$config)){
			$mcexpiry = intval($config['memcache_expiry']);
		}
		#how long to cache not found/404 errors for
		$mcnullexpiry = 10;	#in seconds
		if(array_key_exists('memcache_notfound_expiry',$config)){
			$mcnullexpiry = intval($config['memcache_notfound_expiry']);
		}		
	} else {
		$mc = null;
		$details = array (
			'status'=>'warning',
			'detail'=>array(
				'error_string'=>"No memcache config available",
			),
		);
		report_error($details);
	}
	
	if($mc){
		print "Looking up in cache...\n";
		$data = $mc->get($_SERVER['REQUEST_URI']);
		if($data){
			if(array_key_exists('status',$data) && $data['status']=='notfound') return null;
			print "Cache hit!\n";
			if(! array_key_exists('allow_insecure',$_GET)){
				#fix for Dig dev/Natalia to always show https urls unless specifically asked not to
				$data['url'] = preg_replace('/^http:/','https:',$data['url']);
			}
			return $data;
		} else {
			print "Cache miss\n";
		}
	}
	
	$mysqli = mysqli_connect($config['dbhost'][0], $config['dbuser'], $config['dbpass'], $config['dbname']);
	
	$num_servers = count($config['dbhost']);

	$n = 0;
	$dbh=false;
	while(!$dbh){
		print "Trying to connect to database at ".$config['dbhost'][$n]." (attempt $n)\n";
		
		/*$dbh = mysql_connect($config['dbhost'][$n],
				$config['dbuser'],
				$config['dbpass']);
		if(! mysql_select_db($config['dbname'])){
			print "Connected to db ".$config['dbhost'][$n]." but could not get database '".$config['dbname']."'\n";
			$dbh = false;
		}*/
		if($dbh) break;
		++$n;
		if($n>=$num_servers){
			print "Not able to connect to any database servers.\n";
			$fn="(none)";
			if(array_key_exists('file',$_GET)) $fn=$_GET['file'];
			$details = array(
			'status'=>'error',
			'detail'=>array(
				'error_code'=>500,
				'error_string'=>"No valid database servers",
				'file_name'=>$fn,
				'query_url'=>$_SERVER['REQUEST_URI'],
			),
			);
			report_error($details);
			header('HTTP/1.0 500 Bad Request',true,500);
			throw new ContentErrorException("Not able to connect to any database servers");;
		}
	}
	print "Connected to database\n\n";
}

function output_supplementary_headers()
{
header("Access-Control-Allow-Origin: *");
}
?>
