<?php
include 'commonnew.php';

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


?>
