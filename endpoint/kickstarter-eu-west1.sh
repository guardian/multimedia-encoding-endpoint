#!/bin/bash

bin/timedatectl set-timezone Europe/London

yum -y install httpd php php-mysql unzip php-pecl-memcache

cat > /etc/endpoint.ini << EOF
[database]
dbhost[] = "gnm-mm-***REMOVED***-backup.cuey4k0bnsmn.eu-west-1.rds.amazonaws.com:3306"
dbhost[] = "gnm-mm-***REMOVED***.cuey4k0bnsmn.eu-west-1.rds.amazonaws.com"

dbname = "***REMOVED***"
dbuser = "***REMOVED***"
dbpass = "***REMOVED***"

[sentry]
dsn = "http://***REMOVED***:***REMOVED***@***REMOVED***"

[cache]
memcache_host = ***REMOVED***
memcache_port = 11211
memcache_expiry = 240
EOF

#blank out default welcome page to just get a blank screen
cat > /usr/share/httpd/noindex/index.html << EOF

EOF

#Download the endpoint data
curl https://s3-eu-west-1.amazonaws.com/gnm-multimedia-archivedtech/endpoint_01mar16.zip > /tmp/install.zip
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

#tweak apache config
#up server limits
echo ServerLimit 512 >> /etc/httpd/conf/httpd.conf
echo MaxRequestWorkers 512 >> /etc/httpd/conf/httpd.conf

#disable access to directories and remove HTML error pages
cat << EOF > /etc/httpd/conf.d/endpointserver.conf
<Directory "/var/www/html">
        Options -Indexes
        ErrorDocument 403 "Access Denied"
        ErrorDocument 404 "Access Denied"
</Directory>
EOF

#now start up apache
service httpd start

rm -rf /tmp/install
rm -f /tmp/install.zip