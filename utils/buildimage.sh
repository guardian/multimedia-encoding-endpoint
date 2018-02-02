#!/bin/bash -e

utils/yaml2json.rb packer/endpointserver.yaml > packer/endpointserver.json
packer build packer/endpointserver.json
