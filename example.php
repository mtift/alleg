<?php

// Manage database credentials in this file
include('config.inc');
include('vendor/autoload.php');

date_default_timezone_set('UTC');

use Alleg\DatabaseConnection;

// Test connection to the Allegiance database
$alleg_conn = new DatabaseConnection($alleg_dsn,$alleg_username,$alleg_password,$alleg_server,$alleg_database);
$sql = 'select top 10 esalu, membr from oombrmst';
$rs = $alleg_conn->executeQuery($sql);
print "Allegiance Example\n";
while (!$rs->EOF) {
  print $rs->fields['membr'] . ' ' . $rs->fields['esalu'] . PHP_EOL;
  $rs->MoveNext();
}

// Test connection to a remote MS SQL Server
$web_conn = new DatabaseConnection($webdb_dsn,$webdb_username,$webdb_password,$webdb_server,$webdb_database);
$sql = 'select top 10 TotalDollars, LastUpdated from pledgebreaks';
$rs = $web_conn->executeQuery($sql);
print "Remote MS SQL Server example\n";
while (!$rs->EOF) {
  // Column names (fields) ARE case sensitive
  print $rs->fields['TotalDollars'] . ' ' . $rs->fields['LastUpdated'] . PHP_EOL;
  $rs->MoveNext();
}
