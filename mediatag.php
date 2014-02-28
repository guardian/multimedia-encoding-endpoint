<?php
#This script looks up a video in the interactivepublisher database and returns a redirect if it can be found

$dbh=mysql_connect('gnm-mm-***REMOVED***.cuey4k0bnsmn.eu-west-1.rds.amazonaws.com','***REMOVED***','***REMOVED***');
mysql_select_db('***REMOVED***');

if($_GET['file'] or $_GET['filebase']){
	$fn=$_GET['file'];
	if(preg_match("/[;']/",$fn)){
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
		header('HTTP/1.0 400 Bad Request',true,400);
		exit;
	}
	$q="select * from idmapping where octopus_id=$octid";
	$result=mysql_query($q);
	$idmappingdata=mysql_fetch_assoc($result);
	$contentid=$idmappingdata['contentid'];
} else {
	header('HTTP/1.0 404 Not Found',true,404);
}

$q="select * from encodings left join mime_equivalents on (real_name=encodings.format) where contentid=$contentid";
if(! $_GET['allow_old']){
	$q=$q." and lastupdate>='".$idmappingdata['lastupdate']."'";
}
#$q=$q." order by lastupdate desc";
$q=$q." order by vbitrate desc,lastupdate desc";

$contentresult=mysql_query($q);
if(!$contentresult){
	header("HTTP/1.0 500 Database query error");
	exit;
	#print "unable to run query $q";
}

$have_match=0;

#print "Requested format: '".$_GET['format']."'\n";

while($data=mysql_fetch_assoc($contentresult)){
#	var_dump($data);
#	print $_GET['format'] ." ? " .$data['format']."\n";

	if(array_key_exists('format',$_GET))
		if($data['format']!=$_GET['format'] && $data['mime_equivalent']!=$_GET['format']) continue;
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

#	if($_GET['poster']){	#set the poster=arg to anything to get poster image instead
		preg_match("/^(.*)\.[^\.]+$/",$data['url'],$matches);
		$posterurl=$matches[1]."_poster.jpg";
#		header("Location: ".$posterurl);
#	} else {
#		header("Location: ".$data['url']);
		if(! $_GET['nocontrols']){
			$extra_args=$extra_args." controls";
		}
		if($_GET['autoplay']){
			$extra_args=$extra_args." autoplay";
		}
		if($_GET['loop']){
			$extra_args=$extra_args." loop";
		}

		print "<video preload='auto' id='video_".$data['octopus_id']."' poster='$posterurl' $extra_args>\n";
		print "\t<source src='".$data['url']."' type='".$data['format']."'>\n";
		print "</video>\n";
#	}
	exit;
}
print "No content found.\n";
header("HTTP/1.0 404 Not Found");
?>
