<?php
require '/opt/vendor/autoload.php';

use Aws\Sns\SnsClient;

function init(){
	$snsConfig = array(
		'region' => 'eu-west-1',
		'scheme' => 'https',
	);

	$GLOBALS['sns'] = SnsClient::factory($snsConfig);
	
	$config = parse_ini_file('/etc/endpoint.ini');
	
	$client = new Raven_Client($config['dsn']);
	$error_handler = new Raven_ErrorHandler($client);
	$error_handler->registerExceptionHandler();
	$error_handler->registerErrorHandler();
	$error_handler->registerShutdownFunction();

	$GLOBALS['raven'] = $client;
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

function find_content(){
	$config = parse_ini_file('/etc/endpoint.ini');
	if(! $config){
		$details = array(
			'status'=>'error',
			'detail'=>array(
				'error_code'=>500,
				'error_string'=>"No config file available",
				'file_name'=>$_GET['file'],
				'query_url'=>$_SERVER['REQUEST_URI'],
			),
			);
			report_error($details);
			header('HTTP/1.0 500 Server Config Error',true,500);
			exit;
	}

	if($config['memcache_host']){
		$mc = new Memcache;
		$mcport = 11211;
		if($config['memcache_port']){
			$mcport = int($config['memcache_port']);
		}
		$mc->connect($config['memcache_host'],$mcport);
		$mcexpiry = 30;	#in seconds
		if($config['memcache_expiry']){
			$mcexpiry = int($config['memcache_expiry']);
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
			print "Cache hit!\n";
			return $data;
		} else {
			print "Cache miss\n";
		}
	}
	
	$num_servers = count($config['dbhost']);

	#$dbh=mysql_connect('gnm-mm-***REMOVED***.cuey4k0bnsmn.eu-west-1.rds.amazonaws.com','***REMOVED***','***REMOVED***');
	#print_r($config);
	$n = 0;
	$dbh=false;
	while(!$dbh){
	#	print "Trying to connect to database at ".$config['dbhost'][$n]." (attempt $n)\n";
		$dbh = mysql_connect($config['dbhost'][$n],
				$config['dbuser'],
				$config['dbpass']);
		if(! mysql_select_db($config['dbname'])){
	#		print "Connected to db ".$config['dbhost'][$n]." but could not get database '".$config['dbname']."'\n";
			$dbh = false;
		}
		++$n;
		if($n>$num_servers){
	#		print "Not able to connect to any database servers.\n";
			$details = array(
			'status'=>'error',
			'detail'=>array(
				'error_code'=>500,
				'error_string'=>"No valid database servers",
				'file_name'=>$_GET['file'],
				'query_url'=>$_SERVER['REQUEST_URI'],
			),
			);
			report_error($details);
			header('HTTP/1.0 500 Bad Request',true,500);
			exit;
		}
	}
	#print "Connected to database\n\n";

	if($_GET['file'] or $_GET['filebase']){
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
			exit;
		}
		$fn=mysql_real_escape_string($fn);
		$q="select * from idmapping where filebase='$fn'";
		$result=mysql_query($q);
		$idmappingdata=mysql_fetch_assoc($result);

		$contentid=$idmappingdata['contentid'];
	} elseif($_GET['octopusid']){
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
			exit;
		}
		$q="select * from idmapping where octopus_id=$octid";
		$result=mysql_query($q);
		$idmappingdata=mysql_fetch_assoc($result);
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
	}

	#Step 1.
	#The FCS id uniquely identifies the version (as opposed to octopus_id uniquely identifies the title which can have multiple versions.
	#Versions can have subtly different bitrates AND arrive at different times, so just searching versions with a sort order can return old results no matter what.
	#So, the first step is to find the most recent FCS ID and then search with that
	#Some entries may not have FCS IDs, and if uncaught this leads to all such entries being treated as the same title.
	#So, we iterate across them all and get the first non-empty one. If no ids are found then we must fall back to the old behaviour (step 3)
	$q="select fcs_id from encodings where contentid=$contentid order by lastupdate desc";
	$fcsresult=mysql_query($q);
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
		exit;
	}
	while($fcsdata=mysql_fetch_assoc($fcsresult)){
		if($fcsdata['fcs_id'] and strlen($fcsdata['fcs_id'])>1){
			$fcsid=$fcsdata['fcs_id'];
	#		print "got fcsid $fcsid";
			break;
		}
	}
	#die("testing, content id=$contentid fcs id =$fcsid");

	#Step 2.
	#Look for videos ordered by descending bitrate that belong to the given ID (if we got one). If not then fall through.
	#If none are found, AND we have allow_old set, then re-do the search over everything (and potentially return an old result)
	if($fcsid and $fcsid!=''){
		$q="select * from encodings left join mime_equivalents on (real_name=encodings.format) where fcs_id='$fcsid' order by vbitrate desc";
	#	print "searching by fcsid $fcsid...\n";

		$contentresult=mysql_query($q);
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
			exit;
			#print "unable to run query $q";
		}
	#	die("testing");
	}

	#Step 3.
	#fall back to the old behaviour if nothing was found. this usually means an update is in progress.
	#allow_old will enable this behaviour in the next version
	if($fcsid=='' or mysql_num_rows($contentresult)==0){
	#	if(! $_GET['allow_old']){
	#		header("HTTP/1.0 404 No content found");
	#		exit;
	#	}
	#	print "old search fallback...\n";
		$q="select * from encodings left join mime_equivalents on (real_name=encodings.format) where contentid=$contentid";
		if(! $_GET['allow_old']){
			   $q=$q." and lastupdate>='".$idmappingdata['lastupdate']."'";
		}
		#$q=$q." order by lastupdate desc";
		$q=$q." order by vbitrate desc,lastupdate desc";
	
			$contentresult=mysql_query($q);
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
					exit;
					#print "unable to run query $q";
			}
	}


	$have_match=0;

	#$override_format="";
	#$override_filename="";
	
	$data_overrides=array();
	
	if(array_key_exists('format',$_GET)){
		$data_overrides = has_dodgy_m3u8_format($_GET['format']);
	}
	
	#print "Requested format: '".$_GET['format']."'\n";
	$total_encodings=0;
	while($data=mysql_fetch_assoc($contentresult)){
	#	var_dump($data);
	#	print $_GET['format'] ." ? " .$data['format']."\n";
		++$total_encodings;

		if($data_overrides){
			if($data['format']!=$data_overrides['format'] && $data['mime_equivalent']!=$data_overrides['format']) continue;
		} else {
			if(array_key_exists('format',$_GET))
				if($data['format']!=$_GET['format'] && $data['mime_equivalent']!=$_GET['format']) continue;
		}
		
		if(array_key_exists('need_mobile',$_GET)){
			#print "checking mobile...\n";	
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
		
		#	header("Location: ".$data['url']);
		#}
		if($data_overrides and array_key_exists('filename',$data_overrides)){
			#error_log("debug: replacing filename in ".$data['url']." with ".$data_overrides['filename']."\n");
			$data['url'] = preg_replace('/\/[^\/]+$/',"/".$data_overrides['filename'],$data['url']);
		}
		if($mc){
			$mc->set($_SERVER['REQUEST_URI'],$data);
		}
		return $data;
	}
}

function report_error($errordetails)
{
$errordetails['hostname'] = $_SERVER['HOST_NAME'];
$sns = $GLOBALS['sns'];
try{
$result = $sns->publish(array(
	'TopicArn' => 'arn:aws:sns:eu-west-1:855023211239:EndpointNotifications',
	'Message' => json_encode($errordetails),
	'Subject' => "Endpoint Error",
	'MessageStructure' => 'string',
));

} catch(Exception $e) {
	error_log("Unable to notify sns: ".$e->getMessage()."\n");
}
$raven_client = $GLOBALS['raven'];
$event_id = $raven_client->getIdent($raven_client->captureMessage(json_encode($errordetails)));

if ($raven_client->getLastError() !== null) {
    printf('There was an error sending the event to Sentry: %s', $raven_client->getLastError());
}

error_log($errordetails['error_string']);
}

function output_supplementary_headers()
{
header("Access-Control-Allow-Origin: *");
}
?>
