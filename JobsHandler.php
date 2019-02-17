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
	
	public function getJobNameID($job) {
		$values = $this->db->select("jobnames", [
			"id",
			"name"
			], [
			"name[=]" => $job
		]);
		if (empty($values)){
			return False;
		}
		return $values[0]["id"];
	}
	

	
	public function createJobName($job){
		$id=$this->getJobNameID($job);
		if ($id===false){
			error_log("Create jobname $job");
			$this->db->insert("jobnames", [
				"name" => $job
			]);
			return $this->db->id();
		}else{
			return $id;
		}
	}

	public function createJob($wzID,$job,$content){
		$jobID=$this->createJobName($job);
		$values = $this->db->select("joblist", [
			"id",
			], [
			"workzoneid[=]" => $wzID,
			"jobnameid[=]" => $jobID
		]);
		if (empty($values)){
			$this->db->insert("joblist", [
				"workzoneid" => $wzID,
				"jobnameid" => $jobID,
				"userid" => 0,
				"content" => $content,
				"state" => 0
			]);
			return $this->db->id();
		}else{
			return $values[0]["id"];
		}
	}

	
	public function doRequest($post){
		$action = $post['action'];
		if ($action) {
			$wzName = strtolower($post['wzName']);
			$jobName = $post['jobName'];
			if (!isset($wzName) || !isset($jobName)){
				die('{"errorcode":1, "error": "Variable Error"}');
			}
			if (!(preg_match("/^(\w+\.)+\w+$/",$wzName)===1)){
				die('{"errorcode":0, "value": false, "error": "Work Zone Invalid syntax"}');
			}
			if (!$this->jt->jobExists($jobName)){
				die('{"errorcode":0, "value": false, "error": "Job not exists"}');
			}
			if ($action==1){ //ok to create?
					die('{"errorcode":0, "value": true}');
			}
			if ($action==2){ //create
					error_log("Create...");
					$wzID=$this->wz->createWorkZone($wzName);
					error_log("Created id: $wzID");
					$toDo=$this->jt->getAllDependencies($jobName);
					$jobIDs=array();
					foreach ($toDo as $index){
						$this->createJob($wzID,$index,"");
						foreach ($toDo[$index] as $deps){
							$this->createJob($wzID,$deps,"");
							// create the edges here
						}
					}
					die('{"errorcode":0, "value": true}');
			}
		}
	}
	
}

if (!debug_backtrace()) {
	// do useful stuff
	$jh=JobsHandler::Instance();
	$jh->doRequest($_POST);
}
?>
