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

try {
	$data = find_content(NULL);	#based on superglobals $_GET etc.
} catch(ContentErrorException $e){
	exit;	//error message has already been output and stored
}

if(array_key_exists('file',$_GET)) $filename=$_GET['file'];

if(array_key_exists('poster',$_GET) and $data){
	if(array_key_exists('posterurl',$data)){
		header("Location: ".$data['posterurl']);
		write_to_kinesis($GLOBALS['kinesis_stream_name'], '{"access_url":"'.$GLOBALS['actual_link'].'", "output_message":null, "response_code":302, "php_headers":'.json_encode(headers_list()).'}', null);
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
		$output_message = "No poster URL found";
		write_to_kinesis($GLOBALS['kinesis_stream_name'], '{"access_url":"'.$GLOBALS['actual_link'].'", "output_message":"'.$output_message.'", "response_code":404, "php_headers":'.json_encode(headers_list()).'}', $output_message);
		exit;
	}
}

if($data){
	header("Location: ".$data['url']);
	write_to_kinesis($GLOBALS['kinesis_stream_name'], '{"access_url":"'.$GLOBALS['actual_link'].'", "output_message":null, "response_code":302, "php_headers":'.json_encode(headers_list()).'}', null);
	exit;
}

header("HTTP/1.0 404 Not Found");
$output_message = "No content found.\n";
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
write_to_kinesis($GLOBALS['kinesis_stream_name'], '{"access_url":"'.$GLOBALS['actual_link'].'", "output_message":"'.$output_message.'", "response_code":404, "php_headers":'.json_encode(headers_list()).'}', $output_message);

?>
