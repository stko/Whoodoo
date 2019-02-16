<?php
namespace WorkZones;

require_once  'Database.php';

use Database\Database;

class WorkZones  {
	private static $instance;

	private $db;

	/**
	* Return an instance of the Class
	* @return Database The Database instance
	*/
	public static function instance() {
		if (is_null(self::$instance)){
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->db=Database::Instance();
	}	
	public function getdb() {
		return $this->db;
	}	
}
if (!debug_backtrace()) {
    // do useful stuff
    
$wz=WorkZones::Instance();    
$db=$wz->getdb();
$query = $_GET['query'];

// These values may have been gotten from a database.
// We'll use a simple array just to show this example.
$values2 = ['Neo',
            'Ibiyemi',
            'Olayinka',
            'Jonathan',
            'Stephen', 
            'Fisayo', 
            'Gideon',
            'Mezie',
            'Oreoluwa', 
            'Jordan', 
            'Enkay', 
            'Michelle', 
            'Jessica'];

$values = $db->select("workzone", [
        "name"
], [
        #"user_id[>]" => 100
]);
$result=array();
if ($query) {
    foreach ($values as $key => $value) {
        if (stripos($value["name"], $query) !== false) {
            if (! in_array($value["name"],$result)){
				$result[] = $value["name"];
            }
        }
    }
}

echo json_encode(array_values($result));
}
?>
