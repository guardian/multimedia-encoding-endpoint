#!/usr/bin/env bash

#from http://stackoverflow.com/questions/3915040/bash-fish-command-to-print-absolute-path-to-a-file
function abspath() {
    # generate absolute path from relative path
    # $1     : relative filename
    # return : absolute path
    if [ -d "$1" ]; then
        # dir
        (cd "$1"; pwd)
    elif [ -f "$1" ]; then
        # file
        if [[ $1 == */* ]]; then
            echo "$(cd "${1%/*}"; pwd)/${1##*/}"
        else
            echo "$(pwd)/$1"
        fi
    fi
}

DEPLOYPATH="s3://gnm-multimedia-archivedtech/Endpoint"
BASEPATH=$(abspath "${BASH_SOURCE%/*}")/endpoint

#echo ${BASH_SOURCE}
echo Running in ${BASEPATH}


echo -----------------------------------
echo Building new zip bundle...
echo -----------------------------------
zip -j /tmp/endpoint_current.zip ${BASEPATH}/common.php ${BASEPATH}/composer.* ${BASEPATH}/endpoint.ini ${BASEPATH}/mediatag.php ${BASEPATH}/reference.php ${BASEPATH}/video.php 
if [ "$?" != "0" ]; then
    echo zip bundle failed to build :\(
    exit 1
fi

echo -----------------------------------
echo Moving old tar bundle on S3...
echo -----------------------------------
aws s3 mv "${DEPLOYPATH}/endpoint_current.zip"  "${DEPLOYPATH}/endpoint_$(date +%Y%m%d_%H%M%S).zip"
if [ "$?" != "0" ]; then
    echo aws command failed :\(
    exit 1
fi

echo -----------------------------------
echo Deploying new bundle to S3...
echo -----------------------------------
aws s3 cp  /tmp/endpoint_current.zip "${DEPLOYPATH}/endpoint_current.zip"
if [ "$?" != "0" ]; then
    echo aws command failed :\(
    exit 1
fi

rm -f /tmp/endpoint_current.zip

echo All done!