#!/usr/bin/env php
<?php
// script to run periodically to update the WATERbase database with
// data from the tracking system on the Neeskay.

require_once 'sync_tables.inc.php';
//
// Shortcut for checking for SQL errors.
//
function sqlerr($link_id) {
	if (mysqli_errno($link_id) != 0) {
		fprintf(STDERR,"MySQL error: ".mysqli_error($link_id)."\n");
		die();
	}
}

//
// Check parameters passed in.
//
if ($argc < 2) {
	fprintf(STDERR, "usage: $0 table [table ...]\n");
	die();
}



// *** REMOTE CONNECT ***
echo "Connecting to Neeskay DB...";
// connect to MySQL on neeskay-  this will fail a lot.
if ($remote_link = mysqli_connect($REMOTEHOST,$REMOTEUSER,$REMOTEPASS)) {
	// success
} else {
	fprintf(STDERR, "MySQL could not connect to host $REMOTEHOST: ".mysqli_error($remote_link)."\n");
	die();
}
echo "Choosing database...";
// choose database - if we got connected this will probably work! 
if (!mysqli_select_db($remote_link, $DBNAME)) {
	fprintf(STDERR, "MySQL cannot select database '$DBNAME' on host $REMOTEHOST:" . mysqli_error($remote_link)."\n");
	die();
}
echo "OK.\n";

// *** LOCAL CONNECT ***
echo "Connecting to local DB...";
// connect to local MySQL database
print ("if ($local_link = @mysqli_connect($LOCALHOST,$LOCALUSER,$LOCALPASS)) {\n");

if ($local_link = @mysqli_connect($LOCALHOST,$LOCALUSER,$LOCALPASS)) {
	// success
} else {
	fprintf(STDERR, "MySQL cannot connect to host $LOCALHOST.\n");
	die();
}
// choose local database
echo "Choosing database...";
if (!mysqli_select_db($local_link, $DBNAME)) {
	fputs(STDERR, "MySQL cannot select database '$DBNAME' on $LOCALHOST: ".mysqli_error($local_link)."\n");
	die();
}
echo "OK.\n";


//******** TOP OF LOOP THROUGH TABLES ************
$table_arg = 0;
while (true) { 
// rest of loop not indented 'cause I didn't feel like hitting tab-down-home 500 times
$table_arg ++;
if ($table_arg > count($argv) ||
	!isset($argv[$table_arg]) || ($trackingtable = $argv[$table_arg]) == '') {
	echo "--end of tables--\n";
	exit(0);
}
// *** DETERMINE auto_increment COLUMN ***
$trackingindex = '';
$remote_result = mysqli_query($remote_link,"describe $trackingtable");
if (mysqli_errno($remote_link) != 0) {
	fprintf(STDERR, "Table $trackingtable does not exist on host $REMOTEHOST in database $DBNAME.\n");
	continue;
}
while ($drow = mysqli_fetch_array($remote_result)) {
	if ($drow['Extra'] == "auto_increment") {
		$trackingindex = $drow['Field'];
		break;
	}
}
if ($trackingindex == '') {
	fprintf(STDERR, "Unable to find an auto_increment column in table $trackingtable on host $REMOTEHOST.\n");
	die();
}
echo "** table $trackingtable auto_increment column: $trackingindex\n";



// *** TOP OF UPDATING LOOP 
$totalrecordcount = 0;
$totalrecordsinserted = 0;
while (true) {

	// *** CHECK TRACKING TABLE ***
	// Neeskay
	echo "--Getting maxid for $trackingtable: select max($trackingindex) from $trackingtable\n";
	$remote_result = mysqli_query($remote_link,"select max($trackingindex) from $trackingtable");
	sqlerr($remote_link);
	$remote_array = mysqli_fetch_array($remote_result);
	$remote_max_tracking = $remote_array[0];
	echo "$REMOTEHOST: $remote_max_tracking, ";
	// Local
	echo "local: select max($trackingindex) from $trackingtable\n";
	$lresult = mysqli_query($local_link, "select max($trackingindex) from $trackingtable");
	sqlerr($local_link);
	$local_array = mysqli_fetch_array($lresult);
	$local_max_tracking = $local_array[0];
	echo "$LOCALHOST: $local_max_tracking";
	echo "\n";

	// is local running behind the Neeskay?
	if ($remote_max_tracking > $local_max_tracking) {
		// ***** YES - update the records *****
		// construct column list from LOCAL table
		$colquery = "describe $trackingtable;";
		$cresult = mysqli_query($local_link,$colquery);
		$querycols = "(";
		$cols = array();
		while ($crow = mysqli_fetch_array($cresult)) {
			$querycols .= $crow[0].",";
			$cols[] = $crow[0];
		}
		$querycols = substr($querycols,0,strlen($querycols)-1).")";
			
		// *** retrieve up to 100 rows from the NEESKAY ***
		$remote_result = mysqli_query($remote_link,"select * from $trackingtable where $trackingindex > " . $local_max_tracking . " order by $trackingindex LIMIT 100");
		if (mysqli_errno($remote_link) != 0) {
			fprintf(STDERR, "Error reading from Neeskay: ".mysqli_error($remote_link)."\n");
			die();
		}
		// *** insert into the local database ***
		// construct SQL
		$query = "insert into $trackingtable ";
		echo "  ";
		$queryvals = "";
		while ($row = mysqli_fetch_array($remote_result)) {
			$queryvals .= "(";
			foreach ($cols as $colname) {
				$colvalue = $row[$colname];
				echo "row[$colname] = $colvalue\n";
				$queryvals .= "'" . $colvalue . "',";
			}
			$queryvals = substr($queryvals,0,strlen($queryvals)-1);  // chop off trailing comma
			$queryvals .= ")\n,";
			$totalrecords ++;
			echo ".";
		}
		$queryvals = substr($queryvals,0,strlen($queryvals)-1);
		$query = $query . " " . $querycols . " values ".$queryvals;
		echo "query = \n$query\n";
		$local_result = mysqli_query($local_link,$query);
		if (mysqli_errno($local_link) != 0) {
			fprintf(STDERR, "Error inserting on local table '$trackingtable': ".mysqli_error($local_link));
			die();
		}
		$totalrecordsinserted += mysqli_affected_rows($local_link);
		echo "/\n";
	} else {
		echo "  Table $trackingtable is up to date.\n";
		break;
	}
	echo "  Total records read from remote:  $totalrecords\n";
	echo "  Total records inserted at local: $totalrecordsinserted\n";
}

} // end of table loop
?>
