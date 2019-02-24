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

if (true){
		$data = $database->select("joblist", 
			[
				"joblist.id",
				"joblist.state"
			],
			[
				"workzoneid" => 3
			]
		);
}else{


$data = $database->select("post", [
	// Here is the table relativity argument that tells the relativity between the table you want to join.
 
	// The row author_id from table post is equal the row user_id from table account
	"[>]account" => ["author_id" => "user_id"],
 
	// The row user_id from table post is equal the row user_id from table album.
	// This is a shortcut to declare the relativity if the row name are the same in both table.
	"[>]album" => "user_id",
 
	// [post.user_id is equal photo.user_id and post.avatar_id is equal photo.avatar_id]
	// Like above, there are two row or more are the same in both table.
	"[>]photo" => ["user_id", "avatar_id"],
 
	// If you want to join the same table with different value,
	// you have to assign the table with alias.
	"[>]account (replyer)" => ["replyer_id" => "user_id"],
 
	// You can refer the previous joined table by adding the table name before the column.
	"[>]account" => ["author_id" => "user_id"],
	"[>]album" => ["account.user_id" => "user_id"],
 
	// Multiple condition
	"[>]account" => [
		"author_id" => "user_id",
		"album.user_id" => "user_id"
	]
], [
	"post.post_id",
	"post.title",
	"account.user_id",
	"account.city",
	"replyer.user_id",
	"replyer.city"
], [
	"post.user_id" => 100,
	"ORDER" => ["post.post_id" => "DESC"],
	"LIMIT" => 50
]);
}


if ($data===false){
	var_dump( $database->error() );
}else{
	print_r($data);
}
var_dump($database->log());

//	"SELECT <workzone.name> , COUNT (<joblist.id>)  FROM <workzone> INNER JOIN  <whoodoo_joblist> ON  <workzone.id> = <joblist.workzoneid> WHERE (lower(<workzone.name>) LIKE lower( :workzonename ) ) AND <joblist.state> != :state "


/*"SELECT <workzone.name> ,COUNT (<joblist.id>) FROM <workzone> , <whoodoo_joblist> WHERE (lower(<workzone.name>) LIKE :workzonename ) AND <joblist.state> != :state AND <workzone.id> = <joblist.workzoneid>" , [
		":workzonename" => "%customer%",
		":state" => 1
*/
?>
