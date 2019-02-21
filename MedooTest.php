<?php

require  'Medoo.php';
require  'Database.php';

# sudo apt install php-sqlite3

use Medoo\Medoo;

  
// Singleton request
$database = Database::instance();

/*
$data = $database->query(
	"SELECT <jobname.name> FROM <jobname>,<joblist> WHERE <joblist.workzoneid> = :workzoneid AND <joblist.state> != :state AND <jobname.id> = <joblist.jobnameid>" , [
		":workzoneid" => 2,
		":state" => 1
	]
)->fetchAll();
*/

$data = $database->query(
	"SELECT <workzone.name> , COUNT (<joblist.id>)  FROM <workzone> INNER JOIN  <whoodoo_joblist> ON  <workzone.id> = <joblist.workzoneid> WHERE (lower(<workzone.name>) LIKE lower( :workzonename ) ) AND <joblist.state> != :state GROUP BY <workzone.name>" , [
		":workzonename" => "%cust%",
		":state" => 1
	]
);

if ($data===false){
	var_dump( $database->error() );
}else{
	print_r($data->fetchAll());
}
var_dump($database->log());

//	"SELECT <workzone.name> , COUNT (<joblist.id>)  FROM <workzone> INNER JOIN  <whoodoo_joblist> ON  <workzone.id> = <joblist.workzoneid> WHERE (lower(<workzone.name>) LIKE lower( :workzonename ) ) AND <joblist.state> != :state "


/*"SELECT <workzone.name> ,COUNT (<joblist.id>) FROM <workzone> , <whoodoo_joblist> WHERE (lower(<workzone.name>) LIKE :workzonename ) AND <joblist.state> != :state AND <workzone.id> = <joblist.workzoneid>" , [
		":workzonename" => "%customer%",
		":state" => 1
*/
?>
