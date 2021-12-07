package main

import (
	"flag"
	"github.com/aws/aws-sdk-go/aws"
	"github.com/aws/aws-sdk-go/aws/session"
	"github.com/aws/aws-sdk-go/service/kinesis"
	"io/ioutil"
	"log"
	"os"
)

func main() {
	streamName := flag.String("stream", "", "Name of the kinesis stream to send to")
	file := flag.String("file", "content.json", "Name of the file content to send onto the given kinesis stream")
	flag.Parse()

	sess := session.Must(session.NewSession())

	client := kinesis.New(sess)

	f, err := os.Open(*file)
	if err != nil {
		log.Fatalf("Could not open %s: %s", *file, err)
	}
	content, readErr := ioutil.ReadAll(f)
	f.Close()
	if readErr != nil {
		log.Fatalf("Could not read from %s: %s", *file, readErr)
	}

	response, err := client.PutRecord(&kinesis.PutRecordInput{
		Data:                      content,
		PartitionKey:              aws.String("a"),
		SequenceNumberForOrdering: nil,
		StreamName:                streamName,
	})
	if err != nil {
		log.Fatalf("Could not send record: %s", err)
	}
	log.Printf("Sent message. Shard ID is %s and sequence number is %s", *response.ShardId, *response.SequenceNumber)
}
