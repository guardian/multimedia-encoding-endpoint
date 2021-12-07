all: bundle.zip

eventlogger: main.go
	GOOS=linux GOARCH=amd64 go build -o eventlogger

bundle.zip: eventlogger
	rm -f bundle.zip
	zip bundle.zip eventlogger

upload: bundle.zip
	if [ "${STACK}" == "" ]; then echo You must set STACK, APP, STAGE and BUCKET; fi
	aws s3 cp bundle.zip s3://${BUCKET}/${STACK}/${APP}/${STAGE}/bundle.zip

deploy: upload
	aws lambda update-function-code --function-name endpoint-eventlogger-${STAGE} --s3-bucket ${BUCKET} --s3-key ${STACK}/${APP}/${STAGE}/bundle.zip --publish

clean:
	rm -f eventlogger bundle.zip