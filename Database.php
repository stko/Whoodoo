<?php
namespace Database;

require_once  'Medoo.php';

use Medoo\Medoo;


class Database extends Medoo {
	private static $instance;
	private static $databaseName;

	private $database;

	/**
	* Return an instance of the Database
	* @return Database The Database instance
	*/
	public static function instance() {
		#if (is_null(self::$instance) || self::$databaseName != $dbName){
		if (is_null(self::$instance)){
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$dbName='localDB';
		$tablePrefix='whoodoo_';
		self::$databaseName = $dbName;
		parent::__construct([
			// required
			'database_type' => 'sqlite',
			'database_file' => $dbName.'.sqlite',
			// [optional] Table prefix
			'prefix' => $tablePrefix
		/*,
			'database_name' => 'name',
			'server' => 'localhost',
			'username' => 'your_username',
			'password' => 'your_password',
		
			// [optional]
			'charset' => 'utf8mb4',
			'collation' => 'utf8mb4_general_ci',
			'port' => 3306,
		
		
			// [optional] Enable logging (Logging is disabled by default for better performance)
			'logging' => true,
		
			// [optional] MySQL socket (shouldn't be used with server and port)
			'socket' => '/tmp/mysql.sock',
		
			// [optional] driver_option for connection, read more from http://www.php.net/manual/en/pdo.setattribute.php
			'option' => [
				PDO::ATTR_CASE => PDO::CASE_NATURAL
			],
		
			// [optional] Medoo will execute those commands after connected to the database for initialization
			'command' => [
				'SET SQL_MODE=ANSI_QUOTES'
			]
		*/	]);
		// We instantiate Medoo
		$pdoStatement=$this->query("CREATE TABLE IF NOT EXISTS ".$tablePrefix."users (
			id INTEGER PRIMARY KEY, 
			username VARCHAR( 50 ) NOT NULL,
			firstname TEXT, 
			lastname TEXT,
			state INTEGER );");
		print_r($pdoStatement->errorInfo());
		$pdoStatement=$this->insert("users",[
			[
				'username' => 'foo',
				'firstname' => 'Alice',
				'lastname' => 'Smith',
				'state' => 1
			]
		]);
		$pdoStatement=$this->query("CREATE TABLE IF NOT EXISTS ".$tablePrefix."workzone (
			id INTEGER PRIMARY KEY, 
			name VARCHAR( 200 ) NOT NULL );");
		print_r($pdoStatement->errorInfo());
		$pdoStatement=$this->insert("workzone",[
			[
				'name' => 'customer.project'
			],
			[
				'name' => 'customer.project.build'
			],
			[
				'name' => 'customer.project.build.event'
			]
		]);
		print_r($pdoStatement->errorInfo());

		$account_id = $this->id();
		echo("\n$account_id\n");
	}
	
}
?>
