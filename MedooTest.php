<?php
namespace app;

require  'Medoo.php';
require  'Database.php';

# sudo apt install php-sqlite3

use Database\Database;
use Medoo\Medoo;

  
// Singleton request
$database = Database::instance();
/*
$database->insert("users",[
	[
		'username' => 'stko',
		'firstname' => 'Steffen',
		'lastname' => 'Köhler',
		'state' => 1
	],
	[
		'username' => 'woso',
		'firstname' => 'Wolfgang',
		'lastname' => 'Sauer',
		'state' => 1
	]
]);

$datas = $database->select("users", [
	"username",
	"firstname",
	"lastname",
	"state"
], [
	#"user_id[>]" => 100
]);


foreach($datas as $data)
{
	echo "username:" . $data["username"] . "\n";
	echo "firstname:" . $data["firstname"] . "\n";
	echo "lastname:" . $data["lastname"] . "\n";
	echo "state:" . $data["state"] . "\n";
}
*/ 
?>
