# eventlogger

We recently updated the enocodings endpoint to log every request out to a Kinesis stream.

The app contained here is a simple Lambda function that is intended to be triggered by the data coming onto kinesis
and then write it down into a Dynamodb table - attaching a timestamp and uuid on the way.

## Building

You will need Go installed, preferably v1.16+ but definitely 1.11 or higher (go modules are used for dependency management).
You'll also need the "make"  utility, any basic "developer tools" package for your platform should have this (if you are
on Windows try cygwin)

With that there, you can simply run `make` and you'll get a file called `bundle.zip` which is the lambda code deployment.

To do a full deployment though, you need to:
- choose names for "app", "stack" and "stage" labels. These are basically arbitary, but "stage" must be "CODE" (development/testing),
"DEV" (active development) or "PROD" (production usage)
- you'll also need to have a bucket from where Lambda will retrieve the code bundle
- finally, to run the commands you'll need the `aws` commandline utility installed and configured with enough permissions

- Set up your environment:
```bash
export STACK=mystack
export APP=myapp
export STAGE=CODE
export BUCKET=mydeploymentbucket
```

Now you can simply:
```bash
make upload
```

and the code will be built, zipped and pushed to your chosen location.

- Next, in a Web browser, go to the Cloudformation management page in the AWS web console.
- Create a new stack and use the included `eventlogger_cloudformation.yaml` file
- Input the App, Stack and Stage identifiers you chose before, as well as the deployment bucket
- Let it run.  If you get an error creating the Lambda function, "key not found", then make sure you uploaded the `bundle.zip`
file either manually or with `make upload` and make sure that your app, stack, stage and bucket are all correct.

- If you need to update the code, it's not enough just to upload the new bundle.zip. Lambda must be told to invalidate its cache.
You can do that with:
```bash
make deploy
```

This will do the entire process that `make upload` does but also tells lambda to invalidate.  If you run this before
the cloudformation then you'll see an error because the lambda function does not exist yet.

## Where are the logs?
- Go to the Lambda management section of the AWS Web Console and click the deployed lambda function
- Click the "monitor" tab
- Click the "View logs in CloudWatch" button just below the tabs

## Testing
- If you want to just send some test data onto the kinesis stream, see the `test_push_kinesis` directory. There is a simple
app there which can be built with a quick `make` and which will send a json blob from a file onto a kinesis stream of your
choosing..