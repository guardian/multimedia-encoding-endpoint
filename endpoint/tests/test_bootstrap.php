<?php
$dbname = $GLOBALS['DB_DBNAME'];
try {
    $pdo = new PDO($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD'] );
} catch(PDOException $e){
    $dsn = "mysql:host=127.0.0.1;port=3306";
    $pdo = new PDO($dsn,$GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);
    $pdo->exec("create database `$dbname` if not exists");
    $pdo->exec("use `$dbname`");
    #$pdo = new PDO($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD'] );
    $dbname = $GLOBALS['DB_DBNAME'];    
}



$schemafile = dirname(__FILE__)."/_files/db_schema.sql";
$handle = fopen($schemafile,"r");
if(! $handle) throw Exception("Could not open schema $schemafile");
fclose($handle);

$pdo->exec(file_get_contents($schemafile));

include("tests/gen_encodings.php");
?>
