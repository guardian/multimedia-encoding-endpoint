<?php
include 'common.php';

#make sure that these are set before starting
$octid="(none)";
$fcsid="(none)";
$filename="(none)";
$total_encodings=0;

#This script looks up a video in the interactivepublisher database and returns a redirect if it can be found
init();	#this function is in common.php

output_supplementary_headers();

$data = find_content();	#based on superglobals $_GET etc.

if(array_key_exists('file',$_GET)) $filename=$_GET['file'];

if(array_key_exists('poster',$_GET) and $data){
	if(array_key_exists('posterurl',$data)){
		header("Location: ".$data['posterurl']);
		exit;
	} else {
		$details = array(
			'status'=>'error',
			'detail'=>array(
				   'error_code'=>404,
				   'error_string'=>"No poster image found",
				   'total_encodings_searched'=>$total_encodings,
				   'file_name'=>$filename,
				   'title_id'=>$fcsid,
				   'octopus_id'=>$octid,
				   'query_url'=>$_SERVER['REQUEST_URI'],
			),
		);
		report_error($details);
		header("HTTP/1.0 404 Poster Not Found");
		print "No poster URL found";
		exit;
	}
}

if($data){
	header("Location: ".$data['url']);
	exit;
}

print "No content found.\n";
$details = array(
'status'=>'error',
'detail'=>array(
       'error_code'=>404,
       'error_string'=>"No matching encodings found",
       'total_encodings_searched'=>$total_encodings,
       'file_name'=>$filename,
       'title_id'=>$fcsid,
       'octopus_id'=>$octid,
       'query_url'=>$_SERVER['REQUEST_URI'],
),
);
report_error($details);
header("HTTP/1.0 404 Not Found");
?>
