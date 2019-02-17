<?php

require_once  'Database.php';

require_once  'WorkZones.php';

require_once  'JobTemplates.php';

require_once("Config.php");

class JobsHandler  {
	private static $instance;

	private $db;
	private $wz;
	private $jt;

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
		$this->wz=WorkZones::Instance();
		$this->jt=JobTemplates::Instance(Config::jobPath);
	}	
	public function getdb() {
		return $this->db;
	}	
}
if (!debug_backtrace()) {
	// do useful stuff
	$jh=JobsHandler::Instance();
	$action = $_POST['action'];
	if ($action) {
		if ($action==1){ //ok to create?
			$wzName = $_POST['wzName'];
			$jobName = $_POST['jobName'];
			if (!isset($wzName) || !isset($jobName)){
				die('{"errorcode":1, "error": "Variable Error"}');
			}
			
				die('{"errorcode":0, "value": true}');
		}
	}
}
?>
