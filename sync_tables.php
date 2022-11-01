#!/usr/bin/env php
<?php
// script to run periodically to update the WATERbase database with
// data from the tracking system on the Neeskay.

require_once 'sync_tables.inc.php';

function console_msg($outstream, $msg) {
	return fprintf($outstream,"[%s] %s\n", date("Y-m-d H:i:s"), $msg);
}

function console_log($msg) {
	return console_msg(STDOUT,$msg);
}
function console_err($msg) {
	return console_msg(STDOUT,$msg);
}
function console_die($msg) {
	console_err($msg);
	die();
}

//
// Shortcut for checking for SQL errors.
//
function sqlerr($link_id)
{
	if (mysqli_errno($link_id) != 0) {
		console_die("MySQL error: " . mysqli_error($link_id) . "\n");
		die();
	}
}

//
// Check parameters passed in.
//
if ($argc >= 2 && $argv[1] == "--help") {
	console_die("usage: $0 [table [table ...]]\n"
		."  -- if no table names are specified, all tables on remote db\n"
		."	will be processed.");
}



// *** REMOTE CONNECT ***
console_log("Connecting to Neeskay DB...");
// connect to MySQL on neeskay-  this will fail if the ship is out of Wi-Fi
// range of the dock, and the mobile data link is disabled or being avoided
// because it is a metered network with very steep data overage charges.

if ($remote_link = mysqli_connect($REMOTEHOST, $REMOTEUSER, $REMOTEPASS)) {
	// success connecting to mysql/mariadb server aboard the R/V Neeskay
} else {
	// connection failed; output error message and exit program
	console_die("Could not connect to remote MySQL/MariaDb server $REMOTEHOST: " . mysqli_error($remote_link));
}

console_log("hoosing database...");
// choose (select) database
if (!mysqli_select_db($remote_link, $REMOTE_DBNAME)) {
	console_die("Cannot select remote MySQL/MariaDB database '$REMOTE_DBNAME' on host $REMOTEHOST:" . mysqli_error($remote_link));
}
console_log("Remote database '$REMOTE_DBNAME' is selected.");

// *** LOCAL CONNECT ***
console_log("Connecting to local DB...");
// connect to local MySQL database
console_log("if (local_link = @mysqli_connect($LOCALHOST,$LOCALUSER,$LOCALPASS)) {");

if ($local_link = @mysqli_connect($LOCALHOST, $LOCALUSER, $LOCALPASS)) {
	// success
} else {
	console_die("Cannot connect to local MySQL/MariadB host $LOCALHOST");
}
// choose local database
console_log("Choosing database...");
if (!mysqli_select_db($local_link, $LOCAL_DBNAME)) {
	console_die("MySQL cannot select database '$LOCAL_DBNAME' on $LOCALHOST: " . mysqli_error($local_link));
}
console_log("Local database $LOCAL_DBNAME is selected.");


//******** TOP OF LOOP THROUGH TABLES ************

$tables = array_slice($argv,1);

if (count($tables) === 0) {
	console_log("no tables specified; getting list of remote tables.");
	$remote_result = mysqli_query($remote_link, "show tables;");
	if (mysqli_errno($remote_link) != 0) {
		console_die("error ".mysqli_error($remote_link)." attempting to list remote tables.");
	}
	$tables = [];
	while($rmtrow = mysqli_fetch_array($remote_result)) {
		print_r($rmtrow);
		$tables[] = $rmtrow[0];
	}
}

console_log(print_r($tables,true));


//console_die("debug stop");

$table_arg = -1;
while (true) {
	// rest of loop not indented 'cause I didn't feel like hitting tab-down-home 500 times
	// CORRECTION: I either didn't know about or didn't remember the >> command.
	// these comments along with the "500" following lines. (more like 107 lines in reality...)
	$table_arg++;
	if (
		$table_arg >= count($tables) ||
		!isset($tables[$table_arg]) || ($trackingtable = $tables[$table_arg]) == ''
	) {
		echo "--end of tables--\n";
		exit(0);
	}
	// *** DETERMINE auto_increment COLUMN ***
	$trackingindex = '';
	$remote_result = mysqli_query($remote_link, "describe $trackingtable");
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
		//echo "--Getting maxid for $trackingtable: select max($trackingindex) from $trackingtable\n";
		$remote_result = mysqli_query($remote_link, "select max($trackingindex) from $trackingtable");
		sqlerr($remote_link);
		$remote_array = mysqli_fetch_array($remote_result);
		$remote_max_tracking = $remote_array[0];
		echo "{$REMOTEHOST}.{$REMOTE_DBNAME}.$trackingtable: $remote_max_tracking\n";
		// Local
		//echo "local: select max($trackingindex) from $trackingtable\n";
		$lresult = mysqli_query($local_link, "select max($trackingindex) from $trackingtable");
		sqlerr($local_link);
		$local_array = mysqli_fetch_array($lresult);
		$local_max_tracking = $local_array[0];
		echo "{$LOCALHOST}.{$LOCAL_DBNAME}.$trackingtable: $local_max_tracking\n";
		echo "\n";

		// is local running behind the Neeskay?
		if ($remote_max_tracking > $local_max_tracking) {
			// ***** YES - update the records *****
			// construct column list from LOCAL table
			$colquery = "describe $trackingtable;";
			$cresult = mysqli_query($local_link, $colquery);
			$querycols = "(";
			$cols = array();
			while ($crow = mysqli_fetch_array($cresult)) {
				$querycols .= $crow[0] . ",";
				$cols[] = $crow[0];
			}
			$querycols = substr($querycols, 0, strlen($querycols) - 1) . ")";

			// *** retrieve up to 100 rows from the NEESKAY ***
			$remote_result = mysqli_query($remote_link, "select * from $trackingtable where $trackingindex > " . $local_max_tracking . " order by $trackingindex LIMIT 1000");
			if (mysqli_errno($remote_link) != 0) {
				fprintf(STDERR, "Error reading from Neeskay: " . mysqli_error($remote_link) . "\n");
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
				$queryvals = substr($queryvals, 0, strlen($queryvals) - 1);  // chop off trailing comma
				$queryvals .= ")\n,";
				$totalrecords++;
				echo ".";
			}
			$queryvals = substr($queryvals, 0, strlen($queryvals) - 1);
			$query = $query . " " . $querycols . " values " . $queryvals;
			echo "query = \n$query\n";
			$local_result = mysqli_query($local_link, $query);
			if (mysqli_errno($local_link) != 0) {
				fprintf(STDERR, "Error inserting on local table '$trackingtable': " . mysqli_error($local_link));
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