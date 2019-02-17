<?php


require_once  'Database.php';


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
	
	public function getWorkzoneID($wz) {
		$values = $this->db->select("workzone", [
			"id",
			"name"
			], [
			"name[=]" => $wz
		]);
		if (empty($values)){
			return False;
		}
		return $values[0]["id"];
	}
	
	public function get_WorkZones($query){
		$values = $this->db->select("workzone", [
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
	
	public function createWorkZone($wz){
		$id=$this->getWorkzoneID($wz);
		if ($id===false){
			$this->db->insert("workzone", [
				"name" => $wz
			]);
			return $this->db->id();
		}else{
			return $id;
		}
	}

	public function doRequest($post){
		$query = $post['query'];
		die('{"errorcode":0, "data": '.json_encode(array_values($this->get_WorkZones($query))).'}');
	}

}
if (!debug_backtrace()) {
    // do useful stuff

	$wz=WorkZones::Instance();    
	$wz->doRequest($_POST);
}
?>
