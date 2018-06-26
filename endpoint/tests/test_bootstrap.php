<?php
$dbname = $GLOBALS['DB_DBNAME'];
try {
    $pdo = new PDO($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD'] );
} catch(PDOException $e) {
    print "Got exception $e when setting up database";
    $dsn = "mysql:host=".$GLOBALS['DB_HOST'].";port=3306";
    $pdo = new PDO($dsn,$GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);
    $pdo->exec("create database `$dbname` if not exists");
    $pdo->exec("use `$dbname`");   
}


$fh = fopen('endpoint.ini','w');
fwrite($fh,"[database]\n");
fwrite($fh,"dbhost[] = \"".$GLOBALS['DB_HOST']."\"\n");
fwrite($fh,"dbuser = \"".$GLOBALS['DB_USER']."\"\n");
fwrite($fh,"dbpass = \"".$GLOBALS['DB_PASSWD']."\"\n");
fwrite($fh,"dbname = \"".$dbname."\"\n");
fwrite($fh,"\n");
fwrite($fh,"[cache]\n");
fwrite($fh,"memcache_host = \"".$GLOBALS['MEMCACHE_HOST']."\"\n");
fwrite($fh,"memcache_port = 11211\n");
fclose($fh);

$schemafile = dirname(__FILE__)."/_files/db_schema.sql";
$handle = fopen($schemafile,"r");
if(! $handle) throw Exception("Could not open schema $schemafile");
fclose($handle);

$pdo->exec(file_get_contents($schemafile));

include("tests/gen_encodings.php");
?>
