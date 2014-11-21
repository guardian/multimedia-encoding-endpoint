<?php
require 'vendor/autoload.php';

use Aws\Sns\SnsClient;
$config = array(
	'region' => 'eu-west-1',
	'scheme' => 'https',
);

$sns = SnsClient::factory($config);

$dataObject = array(
	'status'=>'error',
	'Type'=>'error',
        'detail'=>array(
                'error_code'=>403,
                'error_string'=>"This is not an error, just a test",
                'octopus_id'=>'12345678',
                'item_id'=>'KP-4567',
                'query_url'=>'http://query-url',
        ),
#'default'=>json_encode(array(
#	'status'=>'error',
#	'detail'=>array(
#		'error_code'=>403,
#		'error_string'=>"This is not an error, just a test",
#		'octopus_id'=>'12345678',
#		'item_id'=>'KP-4567',
#		'query_url'=>'http://query-url',
#	),
#))
);

$jsonData = json_encode($dataObject);

$result = $sns->publish(array(
	'TopicArn' => 'arn:aws:sns:eu-west-1:855023211239:EndpointNotifications',
	'Message' => $jsonData,
	'Subject' => 'Test message',
	'MessageStructure' => 'string',
));

print "Sent message ID is ".$result['MessageId']."\n";
