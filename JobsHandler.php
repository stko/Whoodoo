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
	

	public function getJobData($jobID) {
		$values = $this->db->select("joblist", [
			"content",
			], [
			"id[=]" => $jobID
		]);
		if (empty($values)){
			return "{}";
		}else{
			$json=json_decode($values[0]["content"]);
			return $json->schema;
		}
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

	public function createJob($wzID,$job,$title,$content){
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
				"userid" => 1,
				"title" => $title,
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

	public function storeUserEntry($data,$userID){
		//error_log($strData);
		//$data=json_decode($strData);
		if (!isset($data["jobID"]) 
		or !isset($data["predecessorState"])
		or !isset($data["validated"])
		or !isset($data["content"])
		or !isset($data["state"])
		){
			die('{"errorcode":1, "error": "Variable Error"}');
		}
		$this->db->insert("changelog", [
			"jobid" => $data["jobID"],
			"timestamp" => time(),
			"changetype" => 0,
			"userid" => $userID,
			"predecessorState" => $data["predecessorState"],
			"validated" => $data["validated"],
			"content" => json_encode($data["content"]),
			"state" => $data["state"]
		]);
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
	
	public function getUserEntry($jobID){
		$data = $this->db->get("changelog", [
			"content"
		], [
			"jobid" => $jobID,
			"changetype" => 0,
			"ORDER" => ["timestamp" => "DESC"],
		]);
		return json_decode($data["content"]);
	}
	
	
	public function showWorkZoneByName($wzName){
		$jobs = $this->db->select("joblist", [
			"[>]workzone" => ["workzoneid" => "id"],
			"[>]jobnames" => ["jobnameid" => "id"],
			"[>]users" => ["userid" => "id"],
			"[>]statecodes" => "state"
		],
		[
			"joblist.id(key)",
			"jobnames.name(text)",
			"statecodes.statecolorcode(color)",
			"users.firstname",
			"users.lastname",
			"joblist.userid",
			"joblist.title",
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
			"edgelist.fromjobid(from)",
			"edgelist.tojobid(to)",
			"edgelist.state"
		],
		[
			"workzone.name[=]" => $wzName
		]);
		foreach($jobs as $key => $job){
			error_log($job["title"]);

			$jobs[$key]["text"]=$job["title"]."\n".$job["firstname"]." ".$job["lastname"]."\n[".$job["text"]."]";
		}
		$res=[ "nodes" => $jobs , "links" => $edges];
		ob_start();
		var_dump($res);
		$result = ob_get_clean();
		error_log($result);

		return $res;
	}
	
	
	public function getJobPredecessorStates($jobID){
		$edges = $this->db->select("edgelist", [
			"[>]statecodes" => "state",
			"[>]joblist" => ["fromjobid" => "id"],
			"[>]jobnames" => ["joblist.jobnameid" => "id"]
		],
		[
			"edgelist.id",
			"jobnames.name(jobname)",
			"joblist.title",
			"joblist.state(jobstate)",
			"edgelist.state",
			"statecodes.statecolorcode(color)"
		],
		[
			"tojobid[=]" => $jobID
		]);

		$res=[ "jobPredecessorStateTable" => $edges];
		ob_start();
		var_dump($res);
		$result = ob_get_clean();
		error_log($result);

		return $res;
	}
	
	/*
	INSERT INTO whoodoo_statecodes VALUES(1,'Requested',"Gainsboro","#DCDCDC",0);
INSERT INTO whoodoo_statecodes VALUES(2,'Done',"Lime","#00FF00",1);
INSERT INTO whoodoo_statecodes VALUES(3,'In Work',"Aqua","#00FFFF",2);
INSERT INTO whoodoo_statecodes VALUES(4,'Reworked',"Gold","#FFD700",3);
INSERT INTO whoodoo_statecodes VALUES(5,'Unclear',"Orange","#FFA500",4);
INSERT INTO whoodoo_statecodes VALUES(6,'Faulty',"OrangeRed","	#FF4500",5);
INSERT INTO whoodoo_statecodes VALUES(7,'Ignore',"NavajoWhite","#FFDEAD",6);

	*/
	
	public function toggleJobPredecessorIgnoreState($edgeID){
		$preJobState = $this->db->select("edgelist", [
			"[>]joblist" => ["fromjobid" => "id"],
		],
		[
			"joblist.state(jobstate)",
			"edgelist.state",
		],
		[
			"edgelist.id[=]" => $edgeID
		]);
		ob_start();
		var_dump($preJobState);
		$result = ob_get_clean();
		error_log($result);

		$jobState=$preJobState[0]["jobstate"];
		$edgeState=$preJobState[0]["state"];
		error_log("edgeID:".$edgeID);
		
		if ($edgeState==6){ //if ignore
			$newState= 3; // reworked
		}else{
			$newState= 6; // ignored
		}
		error_log("old state:".$edgeState. "new state:" .$newState);
		$data = $this->db->update("edgelist", [
			"state" => $newState
		], [
			"id" => $edgeID
		]);
		error_log("Rows affected by the update:". $data->rowCount());
		return true;
	}
	
		
	public function acceptPredecessor($edgeID){
		$preJobState = $this->db->select("edgelist", [
			"[>]joblist" => ["fromjobid" => "id"],
		],
		[
			"joblist.state(jobstate)",
			"edgelist.state",
		],
		[
			"edgelist.id[=]" => $edgeID
		]);
		ob_start();
		var_dump($preJobState);
		$result = ob_get_clean();
		error_log($result);

		$jobState=$preJobState[0]["jobstate"];
		$edgeState=$preJobState[0]["state"];


		error_log("job state:".$jobState. "edge state:" .$edgeState);

		$data = $this->db->update("edgelist", [
			"state" => $jobState
		], [
			"id" => $edgeID
		]);
		error_log("Rows affected by the update:". $data->rowCount());

		return true;
	}
	
	
	public function doRequest($post){
		ob_start();
		var_dump($post);
		$result = ob_get_clean();
		error_log($result);
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
					$toJobID=$this->createJob($wzID,$successorJobName,$this->jt->getJobTitle($successorJobName),json_encode($this->jt->getJobContent($successorJobName)));
					foreach ($childs  as $predecessorJobName =>$child){
						$fromJobID=$this->createJob($wzID,$predecessorJobName,$this->jt->getJobTitle($predecessorJobName),json_encode($this->jt->getJobContent($predecessorJobName)));
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
				die('{"errorcode":0, "data": '.json_encode($this->showWorkZoneByName($wzName)).'}');

			}
			if ($action==5){ //request Job data

				if (!isset($post['jobID']) ){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				$jobID = $post['jobID'];
				die('{"errorcode":0, "data": { "schema" : '.json_encode($this->getJobData($jobID)).', "startval" : {} }}');

			}
			if ($action==6){ //store user entry
				if (!isset($post['input'])) {
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				$this->storeUserEntry($post['input'],1);
				die('{"errorcode":0, "data": true}');

			}
			if ($action==7){ //get user entry
				if (!isset($post['jobID'])) {
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				die('{"errorcode":0, "data": '.json_encode($this->getUserEntry($post['jobID'])).'}');
			}
			if ($action==8){ //request Predecessor status list
				if (!isset($post['jobID']) ){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				$jobID = $post['jobID'];
				die('{"errorcode":0, "data": '.json_encode($this->getJobPredecessorStates($jobID)).'}');

			}
			if ($action==9){ //toggle ignore Predecessor job
				if (!isset($post['edgeID']) ){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				$edgeID = $post['edgeID'];
				die('{"errorcode":0, "data": '.json_encode($this->toggleJobPredecessorIgnoreState($edgeID)).'}');
			}
			
			if ($action==10){ //Accept Predecessor job
				if (!isset($post['edgeID']) ){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				$edgeID = $post['edgeID'];
				die('{"errorcode":0, "data": '.json_encode($this->acceptPredecessor($edgeID)).'}');

			}
			
		}else{
			die('{"errorcode":1, "error": "Variable Error"}');
		}
	}
	
}

if (!debug_backtrace()) {
	// do useful stuff
	$jh=JobsHandler::Instance();
	$jh->doRequest($_POST);
}
?>
