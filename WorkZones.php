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
	
	public get_WorkZones($query){
		$values = $this->$db->select("workzone", [
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
			sort($result);
		}
		return $result;
	}
}
if (!debug_backtrace()) {
    // do useful stuff
    
$wz=WorkZones::Instance();    


echo json_encode(array_values($wz->get_WorkZones($query)));
}
?>
