package main

import (
	"context"
	"encoding/json"
	"github.com/aws/aws-lambda-go/events"
	"github.com/aws/aws-lambda-go/lambda"
	"log"
)

type EndpointRequestContent struct {
	AccessUrl     string   `json:"access_url"`
	OutputMessage string   `json:"output_message"`
	ResponseCode  int      `json:"response_code"`
	PhpHeaders    []string `json:"php_headers"`
}

func HandleIndividualRecord(ctx context.Context, evt events.KinesisEventRecord) error {
	var content EndpointRequestContent
	log.Printf("INFO Received Kinesis event with ID %s from %s in region %s", evt.EventID, evt.EventSourceArn, evt.AwsRegion)

	unmarshalErr := json.Unmarshal(evt.Kinesis.Data, &content)
	if unmarshalErr != nil {
		log.Printf("ERROR Could not unmarshal event: %s. Raw content was %s", unmarshalErr, string(evt.Kinesis.Data))
		return unmarshalErr
	}

	log.Printf("INFO Decoded event successfully")
	return nil
}

func HandleRequest(ctx context.Context, evt events.KinesisEvent) error {
	log.Printf("INFO received %d records", len(evt.Records))
	for _, rec := range evt.Records {
		err := HandleIndividualRecord(ctx, rec)
		if err != nil {
			return err
		}
	}
	return nil
}

func main() {
	lambda.Start(HandleRequest)
}
