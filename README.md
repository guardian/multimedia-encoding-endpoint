# Multimedia endcodings referencing endpoint

**THIS IS NOW DEPRECATED**

## Source code

See `endpoint/`. You'll need php 7.x set up to run it; dependencies are managed via php Composer (see https://getcomposer.org)

There are three endpoints provided - `mediatag`, `reference` and `video`.

- `video` will serve you with a 302 redirect to the media you request, or 404 if it's not found.
- `reference` will serve you with the plain-text URL to the media you request, or 404 if it's not found. This should
not be cached for a long duration as it may change
- `mediatag` will serve you with an HTML5 `<video>` or `audio` tag to allow easy playback of the given media.

The data is read from a database which is populated by the "interactive publisher subsystem".

Please note that this is now a legacy system which is not being populated with new media but is kept in order to keep
running the interactives that are using it.

## AMI build

See `packer/`.  You'll need HashiCorp Packer installed, and an AWS account.

Note that the packer manifests are stored as yaml, because it's easier to read and allows comments, but Packer requires
that you use json or HCL.  You'll need to convert the yaml files to json before using them - I use the `yq` utility which
can simply be installed via `pip3 install yq` (assuming you have Python available).  There are plenty of alternatives
available.

To rebuild the images from scratch:

0. **Ignore packer/endpointserver.yaml**, it's deprecated.
1. Log into AWS and go to the EC2 console.  Click "Launch Image" and find the most recent "Amazon Linux" image.
2. There's no reason that arm-based shouldn't work, but we haven't tried it. Click x86 and find the AMI ID (long identifier
 that looks like `ami-xxxxxxxxxxxx`)
3. Copy that value to the clipboard and then go to the file `packer/endpointserver-amz-sel.yaml`. 
Paste it into the `builders.[0].source_ami` key.
4. Now, in a terminal window, convert that yaml to json: `yq < packer/endpointserver-amz-sel.yaml > packer/endpointserver-amz-sel.json`
5. Make sure that you have AWS credentials on the commandline. Normally this is a case of setting `AWS_ACCESS_KEY_ID` and
`AWS_SECRET_ACCESS_KEY` environment variables - in the Guardian we use Janus to do this.
6. Run packer - `packer packer/endpointserver-amz-sel.json`.  This will take a few minutes to complete.
7. When it does complete, you should see some messages like this near the end:
```
    amazon-ebs: Stopping instance
==> amazon-ebs: Waiting for the instance to stop...
==> amazon-ebs: Creating AMI Packer build for endpointserver xx from instance i-0ea825eddd2db3942
    amazon-ebs: AMI: ami-xxxxxxxxxxxxxxxxxxxxxxx
```
8. This is not yet the base AMI. This is an AMI which is primed to enable SELinux on next boot.
9. Copy the AMI ID you get in the packer output, above, and paste it into `builders.[0].source_ami` in `packer/endpoint-amz.yaml`.
10. Convert that to json: `yq < /packer/endpointserver-amz.yaml > packer/endpointserver-amz.json`
11. Now run it - `packer packer/endpointserver-amz.json`. This will take longer to complete, you might even think that
it has failed.  It will take a _long_ time before SSH connection is initiated. This is because on first boot the instance
will apply selinux labelling to the entire root filesystem, and then reboot.
12. Eventually it will complete.  Find the AMI ID in the packer output, and paste this into the endpointserver's 
Cloudformation parameters.  The final instance configuration is done by the autoscaling group at boot-up.

