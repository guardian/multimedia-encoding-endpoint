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
	"regexp"
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

func FixNewlines(rawContent []byte) []byte {
	fixer := regexp.MustCompile("\n")
	return fixer.ReplaceAll(rawContent, []byte("\\n"))
}

func HandleIndividualRecord(ctx context.Context, evt events.KinesisEventRecord) (*dynamodb.WriteRequest, error) {
	var content = &EndpointRequestContent{}

	log.Printf("INFO Received Kinesis event with ID %s from %s in region %s", evt.EventID, evt.EventSourceArn, evt.AwsRegion)

	unmarshalErr := json.Unmarshal(FixNewlines(evt.Kinesis.Data), content)
	if unmarshalErr != nil {
		log.Printf("ERROR Could not unmarshal event: %s. Raw content was %s", unmarshalErr, string(evt.Kinesis.Data))
		return nil, unmarshalErr
	}

	log.Printf("INFO Decoded event successfully")
	return content.asWriteRequest()
}

func doBatchWrite(ctx context.Context, ddbClient *dynamodb.DynamoDB, tableName string, incomingRequests []*dynamodb.WriteRequest, recordCount int) error {
	var putRequests []*dynamodb.WriteRequest
	if recordCount == 0 { //if there are no records to commit then just return. This case can happen if we error out on the last record of a batch.
		return nil
	}
	if recordCount < len(incomingRequests) {
		putRequests = incomingRequests[0:recordCount]
	} else {
		putRequests = incomingRequests
	}

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
		return doBatchWrite(ctx, ddbClient, tableName, items, len(items))
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

	processedRecordCount := 0
	var deferredError error

	for i, rec := range evt.Records {
		rec, err := HandleIndividualRecord(ctx, rec)
		if err != nil {
			log.Printf("ERROR Bailing out on processing due to %s. %d records have been processed", err, processedRecordCount)
			deferredError = err
			break
		}
		putRequests[i] = rec
		processedRecordCount += 1
	}

	log.Printf("INFO Writing %d records to dynamo", processedRecordCount)

	//dynamodb only supports batches of up to 25 items
	for i := 0; i < processedRecordCount; i += 25 {
		lastRecordToProcess := processedRecordCount
		if i+25 < processedRecordCount {
			lastRecordToProcess = i + 25
		}
		nextBatch := putRequests[i:lastRecordToProcess]
		err := doBatchWrite(ctx, ddbClient, tableName, nextBatch, lastRecordToProcess-i)
		if err != nil {
			log.Printf("ERROR Could not write to dynamodb: %s", err)
			return err
		}
	}

	if deferredError != nil {
		log.Printf("ERROR We exited early due to a deferred error, records processed up to this point have now been saved")
		return deferredError
	}
	log.Printf("All done.")
	return nil
}

func main() {
	lambda.Start(HandleRequest)
}
