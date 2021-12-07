package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"github.com/aws/aws-lambda-go/events"
	"github.com/aws/aws-lambda-go/lambda"
	"github.com/aws/aws-sdk-go/aws"
	"github.com/aws/aws-sdk-go/aws/session"
	"github.com/aws/aws-sdk-go/service/dynamodb"
	"github.com/google/uuid"
	"log"
	"os"
	"time"
)

type EndpointRequestContent struct {
	AccessUrl     string   `json:"access_url"`
	OutputMessage *string  `json:"output_message"`
	ResponseCode  int      `json:"response_code"`
	PhpHeaders    []string `json:"php_headers"`
}

func (c *EndpointRequestContent) asWriteRequest() (*dynamodb.WriteRequest, error) {
	var outputMessageAttrib *dynamodb.AttributeValue
	if c.OutputMessage == nil {
		outputMessageAttrib = &dynamodb.AttributeValue{NULL: aws.Bool(true)}
	} else {
		copiedOutputMessage := *c.OutputMessage
		outputMessageAttrib = &dynamodb.AttributeValue{S: aws.String(copiedOutputMessage)}
	}

	phpHeaderAttributes := make([]*dynamodb.AttributeValue, len(c.PhpHeaders))
	for i, headerString := range c.PhpHeaders {
		phpHeaderAttributes[i] = &dynamodb.AttributeValue{S: aws.String(headerString)}
	}

	recordId, err := uuid.NewRandom()
	if err != nil {
		return nil, err
	}

	timestamp := time.Now().Format(time.RFC3339)

	return &dynamodb.WriteRequest{
		PutRequest: &dynamodb.PutRequest{Item: map[string]*dynamodb.AttributeValue{
			"uid":            {S: aws.String(recordId.String())},
			"timestamp":      {S: aws.String(timestamp)},
			"access_url":     {S: aws.String(c.AccessUrl)},
			"output_message": outputMessageAttrib,
			"response_code":  {N: aws.String(fmt.Sprintf("%d", c.ResponseCode))},
			"php_headers":    {L: phpHeaderAttributes},
		}},
	}, nil
}

func HandleIndividualRecord(ctx context.Context, evt events.KinesisEventRecord) (*dynamodb.WriteRequest, error) {
	var content = &EndpointRequestContent{}

	log.Printf("INFO Received Kinesis event with ID %s from %s in region %s", evt.EventID, evt.EventSourceArn, evt.AwsRegion)

	unmarshalErr := json.Unmarshal(evt.Kinesis.Data, content)
	if unmarshalErr != nil {
		log.Printf("ERROR Could not unmarshal event: %s. Raw content was %s", unmarshalErr, string(evt.Kinesis.Data))
		return nil, unmarshalErr
	}

	log.Printf("INFO Decoded event successfully")
	return content.asWriteRequest()
}

func doBatchWrite(ctx context.Context, ddbClient *dynamodb.DynamoDB, tableName string, putRequests []*dynamodb.WriteRequest) error {
	req := &dynamodb.BatchWriteItemInput{
		RequestItems: map[string][]*dynamodb.WriteRequest{
			tableName: putRequests,
		},
		ReturnConsumedCapacity:      nil,
		ReturnItemCollectionMetrics: nil,
	}
	response, err := ddbClient.BatchWriteItemWithContext(ctx, req)
	if err != nil {
		log.Printf("ERROR Could not write batch to dynamodb: %s", err)
		return err
	}
	if items, haveItems := response.UnprocessedItems[tableName]; haveItems {
		return doBatchWrite(ctx, ddbClient, tableName, items)
	}
	return nil
}

func HandleRequest(ctx context.Context, evt events.KinesisEvent) error {
	sess := session.Must(session.NewSession())
	ddbClient := dynamodb.New(sess)

	tableName := os.Getenv("OUTPUT_DDB_TABLE_NAME")
	if tableName == "" {
		log.Printf("ERROR You must specify OUTPUT_DDB_TABLE_NAME in the environment")
		return errors.New("no OUTPUT_DDB_TABLE_NAME")
	}

	log.Printf("INFO received %d records", len(evt.Records))
	putRequests := make([]*dynamodb.WriteRequest, len(evt.Records))

	for i, rec := range evt.Records {
		rec, err := HandleIndividualRecord(ctx, rec)
		if err != nil {
			return err
		}
		putRequests[i] = rec
	}

	log.Printf("INFO Writing %d records to dynamo", len(putRequests))

	err := doBatchWrite(ctx, ddbClient, tableName, putRequests)
	if err != nil {
		log.Printf("ERROR Could not write to dynamodb: %s", err)
	}

	log.Printf("All done.")
	return nil
}

func main() {
	lambda.Start(HandleRequest)
}
