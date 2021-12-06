<?php
$autoload_name = "/opt/vendor/autoload.php";
if(! file_exists($autoload_name))
        $autoload_name = dirname(__FILE__).'/vendor/autoload.php';
if(! file_exists($autoload_name))
        throw new Exception("Not properly installed - could not find composer's autoload file in /opt or in project directory");
require $autoload_name;

use Aws\Sns\SnsClient;
use Aws\Kinesis\KinesisClient;
use Aws\Exception\AwsException;

/*stop AWS complaining*/
date_default_timezone_set('UTC');

$GLOBALS['actual_link'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

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
    $client->setRelease('1');
		$error_handler = new Raven_ErrorHandler($client);
		$error_handler->registerExceptionHandler();
		$error_handler->registerErrorHandler();
		$error_handler->registerShutdownFunction();
		$GLOBALS['raven'] = $client;
	}

  if(array_key_exists('stream_name',$config)){
    $GLOBALS['kinesis_stream_name'] = $config['stream_name'];
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
    write_to_kinesis($GLOBALS['kinesis_stream_name'], '{"access_url":"'.$GLOBALS['actual_link'].'", "output_message":null, "response_code":500, "php_headers":'.json_encode(headers_list()).'}', null);
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
		$data = $mc->get($_SERVER['REQUEST_URI']);
		if($data){
			if(array_key_exists('status',$data) && $data['status']=='notfound') return null;
			if(! array_key_exists('allow_insecure',$_GET)){
				#fix for Dig dev/Natalia to always show https urls unless specifically asked not to
				$data['url'] = preg_replace('/^http:/','https:',$data['url']);
			}
			return $data;
		}
	}

	$num_servers = count($config['dbhost']);

	$n = 0;
	$dbh=false;
	while(!$dbh){
		$dbh = mysqli_connect($config['dbhost'][$n], $config['dbuser'], $config['dbpass'], $config['dbname']);
		if(! mysqli_select_db($dbh, $config['dbname'])){
			$dbh = false;
		}

		if($dbh) break;
		++$n;
		if($n>=$num_servers){
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
      header('HTTP/1.0 500 Bad Request Bad Request',true,500);
      write_to_kinesis($GLOBALS['kinesis_stream_name'], '{"access_url":"'.$GLOBALS['actual_link'].'", "output_message":null, "response_code":500, "php_headers":'.json_encode(headers_list()).'}', null);
			throw new ContentErrorException("Not able to connect to any database servers");;
		}
	}

	$contentid=-1;

	if(array_key_exists('file',$_GET)){
		$fn=$_GET['file'];
		if(preg_match("/[;']/",$fn)){
			$details = array(
			'status'=>'error',
			'detail'=>array(
				'error_code'=>400,
				'error_string'=>"Invalid filespec",
				'file_name'=>$_GET['file'],
				'query_url'=>$_SERVER['REQUEST_URI'],
			),
			);
			report_error($details);
      header('HTTP/1.0 400 Bad Request',true,400);
      write_to_kinesis($GLOBALS['kinesis_stream_name'], '{"access_url":"'.$GLOBALS['actual_link'].'", "output_message":null, "response_code":400, "php_headers":'.json_encode(headers_list()).'}', null);
			throw new ContentErrorException("Invalid filespec");
		}
		$fn=mysqli_real_escape_string($dbh, $fn);
		$q="select * from idmapping where filebase='$fn' order by lastupdate desc limit 1";
		$result = mysqli_query($dbh, $q);
		$idmappingdata=mysqli_fetch_assoc($result);

		$contentid=$idmappingdata['contentid'];

	} elseif(array_key_exists('octopusid',$_GET)){
		$octid=$_GET['octopusid'];
		if(! preg_match("/^\d+$/",$octid)){
					$details = array(
					'status'=>'error',
					'detail'=>array(
							'error_code'=>400,
							'error_string'=>"Invalid octid",
							'octopus_id'=>$octid,
							'query_url'=>$_SERVER['REQUEST_URI'],
					),
					);
					report_error($details);
      header('HTTP/1.0 400 Bad Request',true,400);
      write_to_kinesis($GLOBALS['kinesis_stream_name'], '{"access_url":"'.$GLOBALS['actual_link'].'", "output_message":null, "response_code":400, "php_headers":'.json_encode(headers_list()).'}', null);
			throw new ContentErrorException("Invalid octid");
		}
		$q="select * from idmapping where octopus_id=$octid order by lastupdate desc limit 1";

		$result = mysqli_query($dbh, $q);

		$idmappingdata=mysqli_fetch_assoc($result);

		$contentid=$idmappingdata['contentid'];
	} else {
		$details = array(
		   'status'=>'error',
			'detail'=>array(
					'error_code'=>404,
					'error_string'=>"No search request",
					'query_url'=>$_SERVER['REQUEST_URI'],
			),
			);
			report_error($details);
    header('HTTP/1.0 404 Not Found',true,404);
    write_to_kinesis($GLOBALS['kinesis_stream_name'], '{"access_url":"'.$GLOBALS['actual_link'].'", "output_message":null, "response_code":404, "php_headers":'.json_encode(headers_list()).'}', null);
	}

	if(! $contentid or $contentid==""){
		$fn = "(none)";
		if(array_key_exists('file',$_GET)) $fn =$_GET['file'];

		$details=array(
			'status'=>'error',
			'detail'=>array(
				'error_code'=>404,
				'error_string'=>'Octopus ID or filename not found',
				'octopus_id'=>$octid,
				'query_url'=>$_SERVER['REQUEST_URI'],
				'file_name'=>$fn,
			),
		);
		report_error($details);
    header('HTTP/1.0 404 Not Found',true,404);
    write_to_kinesis($GLOBALS['kinesis_stream_name'], '{"access_url":"'.$GLOBALS['actual_link'].'", "output_message":null, "response_code":404, "php_headers":'.json_encode(headers_list()).'}', null);
		throw new ContentErrorException("Octopus ID or filename not found");
	}
	#Step 1.
	#The FCS id uniquely identifies the version (as opposed to octopus_id uniquely identifies the title which can have multiple versions.
	#Versions can have subtly different bitrates AND arrive at different times, so just searching versions with a sort order can return old results no matter what.
	#So, the first step is to find the most recent FCS ID and then search with that
	#Some entries may not have FCS IDs, and if uncaught this leads to all such entries being treated as the same title.
	#So, we iterate across them all and get the first non-empty one. If no ids are found then we must fall back to the old behaviour (step 3)
	$q="select fcs_id from encodings left join mime_equivalents on (real_name=encodings.format)where contentid=$contentid order by lastupdate desc";
	//print "second query is $q\n";
	$fcsresult=mysqli_query($dbh, $q);


	if(!$fcsresult){
		$details = array(
		'status'=>'error',
		'detail'=>array(
				'error_code'=>500,
				'error_string'=>"No content returned",
				'query_url'=>$_SERVER['REQUEST_URI'],
				'db_query'=>$q,
			),
		);
		report_error($details);
    header("HTTP/1.0 500 Database query error");
    write_to_kinesis($GLOBALS['kinesis_stream_name'], '{"access_url":"'.$GLOBALS['actual_link'].'", "output_message":null, "response_code":500, "php_headers":'.json_encode(headers_list()).'}', null);
		throw new ContentErrorException("No content from database");
	}
	$fcsid=NULL;
	while($fcsdata=mysqli_fetch_assoc($fcsresult)){
		if($fcsdata['fcs_id'] and strlen($fcsdata['fcs_id'])>1){
			$fcsid=$fcsdata['fcs_id'];

			break;
		}
	}
	#die("testing, content id=$contentid fcs id =$fcsid");

	#Step 2.
	#Look for videos ordered by descending bitrate that belong to the given ID (if we got one). If not then fall through.
	#If none are found, AND we have allow_old set, then re-do the search over everything (and potentially return an old result)
	if($fcsid and $fcsid!=''){
		$q="select * from encodings left join mime_equivalents on (real_name=encodings.format) where fcs_id='$fcsid' order by vbitrate desc";

		$contentresult=mysqli_query($dbh, $q);
		if(!$contentresult){
			$details = array(
			'status'=>'error',
			'detail'=>array(
					'error_code'=>500,
					'error_string'=>"No encodings found for given title id",
					'title_id'=>$fcsid,
					'query_url'=>$_SERVER['REQUEST_URI'],
					'database_query'=>$q,
				),
			);
			report_error($details);
      header("HTTP/1.0 500 Database query error");
      $output_message = "unable to run query $q";
      write_to_kinesis($GLOBALS['kinesis_stream_name'], '{"access_url":"'.$GLOBALS['actual_link'].'", "output_message":null, "response_code":500, "php_headers":'.json_encode(headers_list()).'}', $output_message);
			throw new ContentErrorException("No encodings found for given title id");
		}
	#	die("testing");
	}

	#Step 3.
	#fall back to the old behaviour if nothing was found. this usually means an update is in progress.
	#allow_old will enable this behaviour in the next version
	if($fcsid==NULL or $fcsid=='' or mysqli_num_rows($contentresult)==0 ){

		$q="select * from encodings left join mime_equivalents on (real_name=encodings.format) where contentid=$contentid";
		if(! array_key_exists('allow_old',$_GET) and $idmappingdata){
			   $q=$q." and lastupdate>='".$idmappingdata['lastupdate']."'";
		}
		#$q=$q." order by lastupdate desc";
		$q=$q." order by vbitrate desc,lastupdate desc";

			$contentresult=mysqli_query($dbh, $q);
			if(!$contentresult){
					$details = array(
					'status'=>'error',
					'detail'=>array(
							'error_code'=>500,
							'error_string'=>"No encodings found in fallback mode",
							'query_url'=>$_SERVER['REQUEST_URI'],
				'database_query'=>$q,
					),
					);
					report_error($details);
          header("HTTP/1.0 500 Database query error");
          write_to_kinesis($GLOBALS['kinesis_stream_name'], '{"access_url":"'.$GLOBALS['actual_link'].'", "output_message":null, "response_code":500, "php_headers":'.json_encode(headers_list()).'}', null);
					throw new ContentErrorException("No encodings found in fallback mode");

			}
	}

	$have_match=0;

	#$override_format="";
	#$override_filename="";

	$data_overrides=array();

	if(array_key_exists('format',$_GET)){
		$data_overrides = has_dodgy_m3u8_format($_GET['format']);
	}


	$total_encodings=0;
	while($data=mysqli_fetch_assoc($contentresult)){

		++$total_encodings;

		if($data_overrides){
			if($data['format']!=$data_overrides['format'] && $data['mime_equivalent']!=$data_overrides['format']) continue;
		} else {
			if(array_key_exists('format',$_GET))
				if($data['format']!=$_GET['format'] && $data['mime_equivalent']!=$_GET['format']) continue;
		}

		if(array_key_exists('need_mobile',$_GET)){
			//print "checking mobile...\n";
			if($data['mobile']!=1) continue;
		}
		if(array_key_exists('minbitrate',$_GET))
			if($data['vbitrate']<$_GET['minbitrate']) continue;
		if(array_key_exists('maxbitrate',$_GET))
			if($data['vbitrate']>$_GET['maxbitrate']) continue;
		   if(array_key_exists('minheight',$_GET))
				 if($data['frame_height']<$_GET['minheight']) continue;
		if(array_key_exists('maxheight',$_GET))
					if($data['frame_height']>$_GET['maxheight']) continue;
		if(array_key_exists('minwidth',$_GET))
					if($data['frame_width']<$_GET['minwidth']) continue;
		if(array_key_exists('maxwidth',$_GET))
					if($data['frame_width']>$_GET['maxwidth']) continue;
		$data['url']=preg_replace("/\|/","",$data['url']);

		#output_supplementary_headers();

		#if($_GET['poster']){	#set the poster=arg to anything to get poster image instead
		if(preg_match("/^(.*)\.[^\.]+$/",$data['url'],$matches)){
			$posterurl=$matches[1]."_poster.jpg";
			$data['posterurl']=$posterurl;
		}

		if($data_overrides and array_key_exists('filename',$data_overrides)){
			$data['url'] = preg_replace('/\/[^\/]+$/',"/".$data_overrides['filename'],$data['url']);
		}
		if($mc){
			/*third parameter is flags, fourth is expiry http://stackoverflow.com/questions/3740317/php-memcached-error/3740625*/
			$mc->set($_SERVER['REQUEST_URI'],$data, false, $mcexpiry);
		}
		if(! array_key_exists('allow_insecure',$_GET)){
			#fix for Dig dev/Natalia to always show https urls unless specifically asked not to
			$data['url'] = preg_replace('/^http:/','https:',$data['url']);
		}
		return $data;
	}
  if($mc){
	   /* nothing was found */
	   $mc->set($_SERVER['REQUEST_URI'],array('status'=>'notfound'),false,$mcnullexpiry);
  }
}

function report_error($errordetails)
{
$errordetails['hostname'] = $_SERVER['SERVER_NAME'];

if(array_key_exists('sns',$GLOBALS)){
	$sns = $GLOBALS['sns'];

    $config = load_config();

	try{
	$result = $sns->publish(array(
        	'TopicArn' => $config['topic'],
		'Message' => json_encode($errordetails),
		'Subject' => "Endpoint Error",
		'MessageStructure' => 'string',
	));

	} catch(Exception $e) {
		error_log("Unable to notify sns: ".$e->getMessage()."\n");
	}
}

if(array_key_exists('raven',$GLOBALS)){
	/* do not bother reporting 404 errors to Sentry */
	if(array_key_exists('error_code',$errordetails['detail']) && ! $errordetails['detail']['error_code'] != 404){
		$raven_client = $GLOBALS['raven'];
		try{
			$event_id = $raven_client->getIdent(
				$raven_client->captureMessage("Endpoint reported " + $errordetails['detail']['error_code'] + " " + $errordetails['detail']['error_string'],$errordetails)
			);
		} catch(Exception $e){
			$raven_client->captureMessage(json_encode($errordetails));
		}
		if ($raven_client->getLastError() !== null) {
		error_log('There was an error sending the event to Sentry: ' . $raven_client->getLastError());
		}
	}
}

error_log($errordetails['detail']['error_string']);
}
function output_supplementary_headers()
{
header("Access-Control-Allow-Origin: *");
}

function write_to_kinesis($name, $content, $output_message){

  if ($output_message) {
    print $output_message;
  }

  $kinesisClient = new Aws\Kinesis\KinesisClient([
      'version' => '2013-12-02',
      'region' => 'eu-west-1',
      'scheme' => 'https'
  ]);

  try {
      $result = $kinesisClient->PutRecord([
          'Data' => $content,
          'StreamName' => $name,
          'PartitionKey' => md5($content)
      ]);
  } catch (AwsException $e) {
      echo $e->getMessage();
      echo "\n";
  }
}

?>
