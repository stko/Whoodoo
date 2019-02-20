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

	
	public function createOrModifyEdge($wzID,$fromJobID,$toJobID,$state){
		$values = $this->db->update("edgelist", [
				"state" => $state
			], [
			"workzoneid[=]" => $wzID,
			"fromjobid[=]" => $fromJobID,
			"toJobID[=]" => $toJobID
		]);
		if ($values->rowCount()==0){
			$this->db->insert("edgelist", [
				"workzoneid" => $wzID,
				"fromjobid" => $fromJobID,
				"tojobid" => $toJobID,
				"state" => $state
			]);
			return true;
		}else{
			return false;
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
				die('{"errorcode":0, "data": false, "error": "Work Zone Invalid syntax"}');
			}
			if (!$this->jt->jobExists($jobName)){
				die('{"errorcode":0, "data": false, "error": "Job not exists"}');
			}
			if ($action==1){ //ok to create?
					die('{"errorcode":0, "data": true}');
			}
			if ($action==2){ //create
					error_log("Create...");
					$wzID=$this->wz->createWorkZone($wzName);
					error_log("Created id: $wzID");
					$toDo=$this->jt->getAllDependencies($jobName);
					ob_start();
					var_dump($toDo);
					$result = ob_get_clean();
					error_log($result);
					$jobIDs=array();
					foreach ($toDo as $successorJobName => $childs){
						$toJobID=$this->createJob($wzID,$successorJobName,"");
						foreach ($childs  as $predecessorJobName =>$child){
							$fromJobID=$this->createJob($wzID,$predecessorJobName,"");
							// create the edges here
							$this->createOrModifyEdge($wzID,$fromJobID,$toJobID,0);
						}
					}
					die('{"errorcode":0, "data": { "workzoneid" :'.$wzID.', "workzonename": "'.$wzName.'" } }');
			}
			if ($action==3){ //request Work Zone overview
					die('{"errorcode":0, "data": true}');
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
