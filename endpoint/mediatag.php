<?php
include 'common.php';

init();	#this function is in common.php

output_supplementary_headers();
try{
	$data = find_content();	#based on superglobals $_GET etc.
} catch(ContentErrorException $e){
	exit;	//error message has already been output and stored
}

if($data){
	if(! array_key_exists('nocontrols',$_GET)){
		$extra_args=$extra_args." controls";
	}
	if($_GET['autoplay']){
		$extra_args=$extra_args." autoplay";
	}
	if($_GET['loop']){
		$extra_args=$extra_args." loop";
	}

	header('Content-Type: text/html');
	print "<video preload='auto' id='video_".$data['octopus_id']."' poster='".$data['posterurl']."' $extra_args>\n";
	print "\t<source src='".$data['url']."' type='".$data['format']."'>\n";
	print "</video>\n";
	exit;
}

header("HTTP/1.0 404 Not Found");
print "No content found.\n";
$details = array(
'status'=>'error',
'detail'=>array(
       'error_code'=>404,
       'error_string'=>"No matching encodings found",
       'total_encodings_searched'=>$total_encodings,
       'file_name'=>$_GET['file'],
       'title_id'=>$fcsid,
       'octopus_id'=>$octid,
       'query_url'=>$_SERVER['REQUEST_URI'],
),
);
report_error($details);

?>
