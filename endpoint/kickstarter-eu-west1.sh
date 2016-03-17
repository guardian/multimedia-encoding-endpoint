#!/bin/bash

bin/timedatectl set-timezone Europe/London

echo -------------------------------------
echo Kickstarter: setting up initial packages
echo -------------------------------------
yum -y install httpd php php-mysql unzip php-pecl-memcache python

echo -------------------------------------
echo Kickstarter: setting up Python
echo -------------------------------------
curl https://s3-eu-west-1.amazonaws.com/gnm-multimedia-archivedtech/get-pip.py > /tmp/get-pip.py
python /tmp/get-pip.py

echo -------------------------------------
echo Kickstarter: setting up AWS CLI
echo -------------------------------------
/usr/bin/pip install awscli

echo -------------------------------------
echo Kickstarter: setting up initial configuration
echo -------------------------------------

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

echo -------------------------------------
echo Kickstarter: downloading current software version
echo -------------------------------------

#Download the endpoint data
aws s3 cp s3://gnm-multimedia-archivedtech/Endpoint/endpoint_current.zip /tmp/install.zip
mkdir /tmp/install
unzip /tmp/install.zip -d /tmp/install

echo -------------------------------------
echo Kickstarter: installing endpoint and dependencies
echo -------------------------------------
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

echo -------------------------------------
echo Kickstarter: configuring SELinux
echo -------------------------------------
#ensure that SELinux security contexts are set up correctly
/usr/sbin/restorecon -R /var/www
setsebool -P httpd_can_network_connect 1

echo -------------------------------------
echo Kickstarter: configuring Apache
echo -------------------------------------
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

echo -------------------------------------
echo Kickstarter: starting up
echo -------------------------------------
#now start up apache
service httpd start

echo -------------------------------------
echo Kickstarter: cleaning up
echo -------------------------------------
rm -rf /tmp/install
rm -f /tmp/install.zip