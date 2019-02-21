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

	
	public function getWorkZoneOverview($wzName){
		$data = $this->db->query(
			"SELECT <workzone.name> , COUNT (<joblist.id>)  FROM <workzone> INNER JOIN  <whoodoo_joblist> ON  <workzone.id> = <joblist.workzoneid> WHERE (lower(<workzone.name>) LIKE lower( :workzonename ) ) AND <joblist.state> != :state GROUP BY <workzone.name>" , [
				":workzonename" => "%".$wzName."%",
				":state" => 1
			]
		);

		if ($data===false){
			die('{"errorcode":1, "error": "DB Error 1"}');
		}else{
			$res=$data->fetchAll();
			$data=array();
			foreach($res as $wzResult){
				$data[]=["name" => $wzResult["name"] , "count" => $wzResult[1]];
			}
			return $data;
		}
	}
	
	
	public function showWorkZoneByName($wzName){
		$jobs = $this->db->select("joblist", [
			"[>]workzone" => ["workzoneid" => "id"],
			"[>]jobnames" => ["jobnameid" => "id"],
		],
		[
			"joblist.id",
			"jobnames.name",
			"joblist.userid",
			"joblist.state"
		],
		[
			"workzone.name[=]" => $wzName
		]);
		$edges = $this->db->select("edgelist", [
			"[>]workzone" => ["workzoneid" => "id"]
		],
		[
			"edgelist.id",
			"edgelist.fromjobid",
			"edgelist.tojobid",
			"edgelist.state"
		],
		[
			"workzone.name[=]" => $wzName
		]);
		return [ "jobs" => $jobs , "edges" => $edges];
	}
	
	
	public function doRequest($post){
		$action = $post['action'];
		if ($action) {
			$wzName = strtolower($post['wzName']);
			$jobName = $post['jobName'];
			if ($action==1){ //ok to create?
				if (!isset($wzName) || !isset($jobName)){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				if (!(preg_match("/^(\w+\.)+\w+$/",$wzName)===1)){
					die('{"errorcode":0, "data": false, "error": "Work Zone Invalid syntax"}');
				}
				if (!$this->jt->jobExists($jobName)){
					die('{"errorcode":0, "data": false, "error": "Job not exists"}');
				}
				die('{"errorcode":0, "data": true}');
			}
			if ($action==2){ //create
				if (!isset($wzName) || !isset($jobName)){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				if (!(preg_match("/^(\w+\.)+\w+$/",$wzName)===1)){
					die('{"errorcode":0, "data": false, "error": "Work Zone Invalid syntax"}');
				}
				if (!$this->jt->jobExists($jobName)){
					die('{"errorcode":0, "data": false, "error": "Job not exists"}');
				}
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
				if (!isset($wzName) ){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				die('{"errorcode":0, "data": '.json_encode(array_values($this->getWorkZoneOverview($wzName))).'}');

			}
			if ($action==4){ //request Work Zone overview
				if (!isset($wzName) ){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				die('{"errorcode":0, "data": '.json_encode(array_values($this->showWorkZoneByName($wzName))).'}');

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
