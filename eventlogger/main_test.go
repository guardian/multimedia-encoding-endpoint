package main

import (
	"context"
	"github.com/aws/aws-lambda-go/events"
	"testing"
)

func TestInvalidJsonNewline(t *testing.T) {
	testDataString := `{"access_url":"http://multimedia.guardianapis.com/interactivevideos/mediatag.php?file=091101BangladeshVillages&format=video%2Fm3u8&maxbitrate=2000", "output_message":"<video preload='auto' id='video_8588189' poster='https://cdn.theguardian.tv/HLS/2018/06/06/091101BangladeshVillages_poster.jpg'  controls>
<source src='https://cdn.theguardian.tv/HLS/2018/06/06/091101BangladeshVillages.m3u8' type='video/m3u8'>
</video>
", "response_code":200, "php_headers":["X-Powered-By: PHP\/7.4.21","Access-Control-Allow-Origin: *","Content-type: text\/html;charset=UTF-8"]}`
	testEvent := events.KinesisEventRecord{
		AwsRegion:         "some-region",
		EventID:           "000-test-000",
		EventName:         "test",
		EventSource:       "tester",
		EventSourceArn:    "sometest",
		EventVersion:      "1",
		InvokeIdentityArn: "xxxxxxx",
		Kinesis: events.KinesisRecord{
			Data: []byte(testDataString),
		},
	}

	_, err := HandleIndividualRecord(context.Background(), testEvent)
	if err != nil {
		t.Errorf("Record processing failed with error %s", err)
	}
}
