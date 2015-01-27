#!/bin/bash

yum -y install httpd php php-mysql unzip

cat > /etc/endpoint.ini << EOF
[database]
dbhost[] = "gnm-mm-***REMOVED***-eu.cuey4k0bnsmn.eu-west-1.rds.amazonaws.com"
dbhost[] = "gnm-mm-***REMOVED***.cuey4k0bnsmn.eu-west-1.rds.amazonaws.com"

dbname = "***REMOVED***"
dbuser = "***REMOVED***"
dbpass = "***REMOVED***"
EOF

#Download the endpoint data
curl https://s3-eu-west-1.amazonaws.com/gnm-multimedia-archivedtech/endpoint_26jan15.zip > /tmp/install.zip
mkdir /tmp/install
unzip /tmp/install.zip -d /tmp/install

#install the scripts
mkdir /var/www/html/interactivevideos
mv /tmp/install/common.php /var/www/html/interactivevideos
mv /tmp/install/video.php /var/www/html/interactivevideos
mv /tmp/install/mediatag.php /var/www/html/interactivevideos
mv /tmp/install/reference.php /var/www/html/interactivevideos

#install php composer to get AWS SDK, etc.
mkdir -p /opt
cd /opt
mv /tmp/install/composer.json /opt
mv /tmp/install/composer.phar /opt
HOME=/usr/share/php php /opt/composer.phar install

#ensure that SELinux security contexts are set up correctly
/usr/sbin/restorecon -R /var/www
setsebool -P httpd_can_network_connect 1

#now start up apache
service httpd start

rm -rf /tmp/install
rm -f /tmp/install.zip