<?php
//
// $DBNAME: the name of the MySQL database containing the tables
//          you want to synchronize.
//          NOTE: the database name needs to be the same on both hosts.
$DBNAME="neeskay";

//
// "Local" MySQL parameters
//
$LOCALHOST="waterdata.glwi.uwm.edu";
$LOCALUSER="mymaster";
$LOCALPASS='ship$log';

//
// "Remote" MySQL parameters
//
// $REMOTEHOST="neeskay.glwi.uwm.edu";
$REMOTEHOST="127.0.0.1:33306";
$REMOTEUSER="myslave";
$REMOTEPASS='ship$log';
?>
