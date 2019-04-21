<?php

require_once  'Database.php';

require_once  'WorkZones.php';

require_once  'JobTemplates.php';

require_once  'login.php';

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
	

	public function getJobSchema($jobID) {
		$values = $this->db->select("joblist", [
			"content"
			], [
			"id[=]" => $jobID
		]);
		if (empty($values)){
			return "{}";
		}else{
			return json_decode($values[0]["content"]);
		}
	}
	

	
	public function createJobName($job){
		$id=$this->getJobNameID($job);
		if ($id===false){
			$this->db->insert("jobnames", [
				"name" => $job
			]);
			return $this->db->id();
		}else{
			return $id;
		}
	}

	public function createJob($wzID,$job,$title,$content){
		global $actualUser;
		$jobID=$this->createJobName($job);
		$values = $this->db->select("joblist", [
			"id",
			], [
			"workzoneid[=]" => $wzID,
			"jobnameid[=]" => $jobID
		]);
		if (empty($values)){
			$json=json_decode($content);
			$data= [
				"workzoneid" => $wzID,
				"jobnameid" => $jobID,
				"ownerid" => $actualUser["id"],
				"title" => $title,
				"content" => $content,
				"validated" => 0,
				"startdate" => time(),
				"enddate" => time()+3600*24*$json->duration,
				"plannedenddate" => time()+3600*24*$json->duration,
				"fulfillrate" => 0,
				"duration" => $json->duration,
				"ismilestone" => $json->isMileStone ? 1 : 0 ,
				"state" => 0
			];
			ob_start();
			var_dump($data);
			$result = ob_get_clean();
			$this->db->insert("joblist", $data);
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

	public function setJobValues($data,$userID){
		if (!isset($data["jobID"]) 
		or !isset($data["predecessorState"])
		or !isset($data["validated"])
		or !isset($data["comment"])
		or !isset($data["content"])
		or !isset($data["state"])
		){
			die('{"errorcode":1, "error": "Variable Error"}');
		}
		$values = $this->db->select("joblist", [
			"workzoneid",
			"state"
			], [
			"id[=]" => $data["jobID"]
		]);
		$wzID=$values[0]["workzoneid"];
		$oldState=$values[0]["state"];
		$newState=$data["state"];
		$newEdgeState=$data["state"];
		if ($data["validated"]){
			$newState=1;
		}
		if (($oldState==$newState and $oldState==1) or $oldState!=$newState){
			if ($oldState==1){// was finished, but isn't anymore
				if ($oldState==$newState){ //it's an update, which triggers a rework
				$newEdgeState=3; //reworked
				}else{ // even more worse: Job is not valid anymore
				$newEdgeState=5; //faulty
				$newState=5; // faulty
				}
			}
			$values = $this->db->update("edgelist", [
					"state" => $newEdgeState
				], [
				"workzoneid[=]" => $wzID,
				"fromjobid[=]" => $data["jobID"]
			]);

			
			$values = $this->db->update("joblist", [
					"state" => $newState
				], [
				"id[=]" => $data["jobID"]
			]);

		}

		$values = $this->db->update("joblist", [
			"startdate" => $data["content"]["endDate"]-$data["content"]["duration"]*24*3600,
			//"enddate" => $data["content"]["endDate"],
			"plannedenddate" => $data["content"]["endDate"],
			"duration" => $data["content"]["duration"],
			"fulfillrate" => $data["content"]["fulfillrate"],
			"ismilestone" => $data["content"]["isMileStone"] ? 1 : 0 
			], [
			"id[=]" => $data["jobID"]
		]);

	
		$userInfo=$this->getJobOwnerInfo($data["jobID"]);
		if ($userInfo!=NULL) {
			$owner=$userInfo["id"];
			ob_start();
			var_dump($userInfo);
			$result = ob_get_clean();
		} else {
			$owner=$userID; 
		}
		

		$pdoStatement=$this->db->insert("changelog", [
			"jobid" => $data["jobID"],
			"timestamp" => time(),
			"changetype" => 0,
			"userid" => $userID,
			"jobowner" => $owner,
			"predecessorState" => $data["predecessorState"],
			"validated" => $data["validated"],
			"comment" => json_encode($data["comment"]),
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
	
	public function getJobOwnerInfo($jobID){
		$data = $this->db->get("changelog", [
			"[>]users" => ["jobowner" => "id"]
		],
		[
			"users.firstname",
			"users.lastname",
			"users.id"
		], [
			"jobid" => $jobID,
			"ORDER" => ["timestamp" => "DESC"],
		]);
		return $data;
	}
	
	public function writeChangeLog($jobID,$text,$jobOwner){
		global $actualUser;
		$pdoStatement=$this->db->insert("changelog", [
			"jobid" => $jobID,
			"timestamp" => time(),
			"changetype" => 1,
			"userid" => $actualUser["id"],
			"jobowner" => $jobOwner,
			"predecessorState" => 0,
			"validated" => 0,
			"comment" => $text,
			"content" => "{}",
			"state" => 0
		]);
		error_log("Wrote Changelog on $jobID with $text");

	}
	
	public function getJobValues($jobID){
		global $actualUser;

		$jobValues = $this->db->get("changelog", [
			"[>]joblist" => ["jobid" => "id"]
		],
		[
			"joblist.title",
			"changelog.content"
		], [
			"jobid" => $jobID,
			"changetype" => 0,
			"ORDER" => ["timestamp" => "DESC"],
		]);
		$jobTitle= $this->db->get("joblist", [
			"title",
			"startdate",
			"enddate",
			"plannedenddate",
			"ismilestone"
		], [
			"id" => $jobID
		]);
		if ($jobValues!=NULL){
			$userInfo=$this->getJobOwnerInfo($jobID);
			$res=json_decode($jobValues["content"]);
			if ($userInfo!==FALSE){
				$res->jobName=$jobTitle["title"];
				$res->startDate=$jobTitle["startdate"];
				//$res->endDate=$jobTitle["enddate"];
				$res->endDate=$jobTitle["plannedenddate"];
				$res->isMileStone=$jobTitle["ismilestone"]== 1 ? true : false ;
				$res->owner=$userInfo["firstname"]." ".$userInfo["lastname"];
				$res->notmine=$userInfo["id"]!=$actualUser["id"];
				return $res;
			}
		}
		$res=[
			"owner"=>"Nobody",
			"jobName"=>$jobTitle["title"],
			"notmine"=>true
		];
		return $res;
	}
	
	
	public function showWorkZoneByName($wzName){
		$jobs = $this->db->select("joblist", [
			"[>]workzone" => ["workzoneid" => "id"],
			"[>]jobnames" => ["jobnameid" => "id"],
			"[>]users" => ["ownerid" => "id"],
			"[>]statecodes" => "state"
		],
		[
			"joblist.id(key)",
			"jobnames.name(text)",
			"statecodes.statecolorcode(color)",
			"users.firstname",
			"users.lastname",
			"joblist.ownerid",
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
		if ($jobs!==FALSE){
			foreach($jobs as $key => $job){
				$jobs[$key]["text"]=$job["title"]."\n".$job["firstname"]." ".$job["lastname"]."\n[".$job["text"]."]";
			}
		}else{
			$jobs=[];
		}
		$res=[ "nodes" => $jobs , "links" => $edges];
		return $res;
	}
	
	public function getJobHistory($jobID){
		$changelog = $this->db->select("changelog", [
			"[>]users" => ["jobowner" => "id"]
		],[
			"users.firstname",
			"users.lastname",
			"changelog.jobid",
			"changelog.timestamp",
			"changelog.changetype",
			"changelog.userid",
			"changelog.jobowner",
			"changelog.predecessorState",
			"changelog.validated",
			"changelog.comment",
			"changelog.content",
			"changelog.state"
		],
		[
			"jobid[=]" => $jobID,
			"ORDER" => ["timestamp" => "ASC"],
		]);
		$history=["comments"=>[], "values"=>[]];
		foreach ($changelog as $change){
			array_push($history["comments"], [
				"user"=>$change["firstname"]." ".$change["lastname"],
				"userid"=>$change["userid"],
				"comment"=>$change["comment"],
				"timestamp"=>date("r",$change["timestamp"])
			]);
			if ($change["changetype"]==0) {
				$json=json_decode($change["content"]);
				foreach ($json as $name => $value) {
					if(strpos(strtolower($name),"date")!==false){
						$value=date("m/d/Y",$value);
					}
					if(!isset($history["values"][$name]) || $history["values"][$name]["value"]!=$value ){
						$history["values"][$name]=[
							"user"=>$change["firstname"]." ".$change["lastname"],
							"userid"=>$change["userid"],
							"comment"=>$change["comment"],
							"name"=>$name,
							"timestamp"=>date("m/d/Y H:i:s",$change["timestamp"]),
							"value"=>$value
						];
					}
				}
			}
		}
		return $history;
	}

	public function getStateNames($thisState){
		global $stateNameTable;
		if (!isset($stateNameTable)){
			$stateNameTable=[];
			$states = $this->db->select("statecodes", [
				"state",
				"statename"
			],
			[
			]);
			foreach ($states as $key =>$state) {
				$stateNameTable[$state["state"]]=$state["statename"];
			}
		}
		return $stateNameTable[$thisState]."(".$thisState.")";
	}

	public function getJobPredecessorStates($jobID){
		$edges = $this->db->select("edgelist", [
			"[>]statecodes" => "state",
			"[>]joblist" => ["fromjobid" => "id"],
			"[>]jobnames" => ["joblist.jobnameid" => "id"]
		],
		[
			"edgelist.id",
			"edgelist.fromjobid(jobid)",
			"jobnames.name(jobname)",
			"joblist.title",
			"joblist.state(jobstate)",
			"edgelist.state",
			"statecodes.statecolorcode(color)"
		],
		[
			"tojobid[=]" => $jobID
		]);
		foreach ($edges as $key =>$edge) {
			$edges[$key]["history"]=$this->getJobHistory($edge["jobid"]);
		}
		$res=[ "jobPredecessorStateTable" => $edges];
		return $res;
	}
	
	public function takeoverOwnership($jobID){
		global $actualUser;
		$this->writeChangeLog($jobID,"Took Ownership",$actualUser["id"]);
		$edges = $this->db->update("joblist", [
			"ownerid" => $actualUser=["id"]
		],
		[
			"id[=]" => $jobID
		]);
		return true;
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
	
	public function  calculateNewJobState($old,$new){
		$lookup=[
			// Requested
			0 => [
				0 => 0,
				1 => 0,
				2 => 0,
				3 => 0,
				4 => 0,
				5 => 0,
				6 => 0
			],
			// Done
			1 => [
				0 => 1,
				1 => 1,
				2 => 1,
				3 => 4,
				4 => 4,
				5 => 4,
				6 => 1
			],
			// in Work
			2 => [
				0 => 2,
				1 => 2,
				2 => 2,
				3 => 2,
				4 => 2,
				5 => 2,
				6 => 2
			],
			// Reworked
			3 => [
				0 => 3,
				1 => 3,
				2 => 3,
				3 => 3,
				4 => 4,
				5 => 4,
				6 => 4
			],
			// Unclear
			4 => [
				0 => 0,
				1 => 4,
				2 => 2,
				3 => 4,
				4 => 4,
				5 => 4,
				6 => 4
			],
			//Faulty
			5 => [
				0 => 5,
				1 => 5,
				2 => 5,
				3 => 5,
				4 => 5,
				5 => 5,
				6 => 5
			],
			// Ignore
			6 => [
				0 => 0,
				1 => 1,
				2 => 2,
				3 => 3,
				4 => 4,
				5 => 5,
				6 => 6
			],
		];
		return $lookup[$old][$new];
	}
	
	public function updateJobTree(&$model,$jobArray){
		foreach ($jobArray as $jobID){
			$oldJobState=$model["jobs"][$jobID]["state"];
			$newJobState=$model["jobs"][$jobID]["state"];
			foreach($model["edges"] as $edge){
				if ($edge["tojobid"]==$jobID){
					$newJobState=$this->calculateNewJobState($newJobState,$edge["tojobid"]);
				}
			}
			if ($oldJobState!=$newJobState){
				error_log("job ".$jobID." changes from state ".$oldJobState." to ".$newJobState);
				$model["jobs"][$jobID]["state"]=$newJobState;
				$model["jobs"][$jobID]["new"]=true;
				$affectedJobs=[];
				foreach($model["edges"] as $id => $edge){
					if ($edge["fromjobid"]==$jobID){
						$oldEgdeState=$edge["state"];
						$newEgdeState=$this->calculateNewJobState($oldEgdeState,$newJobState);
						if ($oldEgdeState!=$newEgdeState){
							error_log("edge  ".$id." changes from state ".$oldEgdeState." to ".$newEgdeState);
							if (! in_array($edge["fromjobid"],$affectedJobs)){
								$affectedJobs[]=$edge["fromjobid"];
							}
							$model["edges"][$id]["state"]=$newEgdeState;
							$model["edges"][$id]["new"]=true;
						}
					}
				}
				$this->updateJobTree($model,$affectedJobs);
			}else{
				error_log("job ".$jobID." does not change from state ".$oldJobState." to ".$newJobState);

			}
		}
	}

	public function dumpModel($model){
		foreach ($model["jobs"] as $jobID => $job){
			error_log("--------");
			error_log("id             :".$job["id"]);
			error_log("title          :".$job["title"]);
			error_log("state          :".$this->getStateNames($job["state"]));
			error_log("startdate      :".date("m.d.Y H:i:s",$job["startdate"]));
			error_log("enddate        :".date("m.d.Y H:i:s",$job["enddate"]));
			error_log("plannedenddate :".date("m.d.Y H:i:s",$job["plannedenddate"]));
			error_log("duration       :".$job["duration"]);
			error_log("fulfillrate    :".$job["fulfillrate"]);
			error_log("ismilestone    :".$job["ismilestone"]);
		}
	}

	public function printJobsOnly(&$model,$jobID,$jobDescends){
		foreach ($jobDescends as $subLevelJobID => $subLevelJob){
			error_log("job:".$model["jobs"][$jobID]["title"]. " has decent ".$model["jobs"][$subLevelJobID]["title"]);
		}
	}

	public function calculateJobEndDates(&$model,$jobID,$jobDescends){
		$thisJob=$model["jobs"][$jobID];
		if ($thisJob["state"]==1){//job finished, no calculation needed
			return;
		}
		// find the latest end date of the descants
		$date=time();
		foreach ($jobDescends as $subLevelJobID => $subLevelJob){
			$desValue=$model["jobs"][$subLevelJobID]["enddate"];
			if ($desValue>$date){
				$date=$desValue;
			}
		}
		$newEndTime=$date+3600*24*$thisJob["duration"]*(100-$thisJob["fulfillrate"]);
		//does this change the job?
		if ($thisJob["enddate"]!=$date){
			$model["jobs"][$jobID]["enddate"]=$date;
			$model["jobs"][$jobID]["new"]=true;
		}

	}

	public function calculateJobIgnores(&$model,$jobID,$jobDescends){
		$thisJob=$model["jobs"][$jobID];
		if ($thisJob["state"]==1){//job finished, no calculation needed
			return;
		}
		// go through the edges
		$isIgnored=true;
		foreach ($jobDescends as $subLevelJobID => $subLevelJob){
			$thisEdgeID=$subLevelJob["edge"];
			$thisEdgeState=$model["edges"][$thisEdgeID]["state"];
			$prevJobState=$model["jobs"][$subLevelJobID]["state"];
			if (!($thisEdgeState== 6 || $prevJobState==6 )){ // if state = ignored
				$isIgnored=false;
			}
		}
		$newState=$thisJob["state"];
		if ($isIgnored){
			$newState=6;
		}
		error_log("New Ignore state:".$newState);
		//does this change the job?
		if ($thisJob["state"]!=$newState){
			$model["state"][$jobID]["state"]=$newState;
			$model["jobs"][$jobID]["new"]=true;
		}

	}

	public function iterateThroughDependency(&$model , $dependency, $functionToCall){
		foreach ($dependency as $levelID => $level){
			error_log("iterateThroughDependency level:".$levelID);
			foreach ($level as $jobID => $jobDescends){
				$functionToCall($model,$jobID,$jobDescends);
			}
		}
	}

	public function calculateModelData(&$model){
		// building a array containing the different dependency levels
		$stepUpArray=[];
		$actLevel=0;
		$stepUpArray[$actLevel]=[];
		// at first we fill level 0 with all jobs which do not have a predecessors, so the starting jobs
		foreach($model["jobs"] as $jobID => $job){
			if (count($job["preJobs"])==0){
				$stepUpArray[$actLevel][$jobID]=$job["sucJobs"]; # by this $jobID => $job trick we make sure that each jobID is stored only once
			}
		}
		# now we repeat this with the jobs found, until there's no more precessor found
		$moreJobsFound=true;
		while($moreJobsFound){
			$moreJobsFound=false;
			$actLevel++;
			$stepUpArray[$actLevel]=[];
			foreach($stepUpArray[$actLevel-1] as $jobID => $jobSuccessors){
				if (count($jobSuccessors)>0){
					$moreJobsFound=true;
					foreach($jobSuccessors as $nextJobID =>$nextJob){
						$stepUpArray[$actLevel][$nextJobID]=$model["jobs"][$nextJobID]["sucJobs"]; # by this $jobID => $job trick we make sure that each jobID is stored only once
					}
				}
			}
			if (!$moreJobsFound){
				unset($stepUpArray[$actLevel]);
			}
		}


		// building a array containing the different dependency levels
		$stepDownArray=[];
		$actLevel=0;
		$stepDownArray[$actLevel]=[];
		// at first we fill level 0 with all jobs which do not have a sucessor, so the ending jobs
		foreach($model["jobs"] as $jobID => $job){
			if (count($job["sucJobs"])==0){
				$stepDownArray[$actLevel][$jobID]=$job["preJobs"]; # by this $jobID => $job trick we make sure that each jobID is stored only once
			}
		}
		# now we repeat this with the jobs found, until there's no more successor found
		$moreJobsFound=true;
		while($moreJobsFound){
			$moreJobsFound=false;
			$actLevel++;
			$stepDownArray[$actLevel]=[];
			foreach($stepDownArray[$actLevel-1] as $jobID => $jobSuccessors){
				if (count($jobSuccessors)>0){
					$moreJobsFound=true;
					foreach($jobSuccessors as $nextJobID =>$nextJob){
						$stepDownArray[$actLevel][$nextJobID]=$model["jobs"][$nextJobID]["preJobs"]; # by this $jobID => $job trick we make sure that each jobID is stored only once
					}
				}
			}
			if (!$moreJobsFound){
				unset($stepDownArray[$actLevel]);
			}
		}

		error_log("Aufsteigende Jobs");
		$this->iterateThroughDependency($model , $stepUpArray, array($this, 'printJobsOnly'));

		error_log("absteigende Jobs");
		$this->iterateThroughDependency($model , $stepDownArray, array($this, 'printJobsOnly'));

		error_log("calculateJobEndDates");
		$this->iterateThroughDependency($model , $stepDownArray, array($this, 'calculateJobEndDates'));

		error_log("calculateJobIgnores");
		$this->iterateThroughDependency($model , $stepDownArray, array($this, 'calculateJobIgnores'));


	}

	
	public function updateModelState($workzoneid,$newStateJob){
		$jobs = $this->db->select("joblist", 
			[
				"joblist.id",
				"joblist.title",
				"joblist.state",
				"joblist.startdate",
				"joblist.enddate",
				"joblist.plannedenddate",
				"joblist.duration",
				"joblist.fulfillrate",
				"joblist.ismilestone"
				],
			[
				"workzoneid" => $workzoneid
			]
		);
		$edges = $this->db->select("edgelist", 
			[
				"edgelist.id",
				"edgelist.fromjobid",
				"edgelist.tojobid",
				"edgelist.state"
			],
			[
				"workzoneid" => $workzoneid
			]
		);
		$sortedJobs=[];
		foreach($jobs as $job){//sort by index for better processing
			$jobID=$job["id"];
			$sortedJobs[$jobID]=$job;
			/*
			$sortedJobs[$jobID]=[];
			$sortedJobs[$jobID]["id"]=$job["id"];
			$sortedJobs[$jobID]["state"]=$job["state"];
			$sortedJobs[$jobID]["title"]=$job["title"];
			$sortedJobs[$jobID]["startdate"]=$job["startdate"];
			$sortedJobs[$jobID]["enddate"]=$job["enddate"];
			$sortedJobs[$jobID]["plannedenddate"]=$job["plannedenddate"];
			$sortedJobs[$jobID]["duration"]=$job["duration"];
			$sortedJobs[$jobID]["fulfillrate"]=$job["fulfillrate"];
			$sortedJobs[$jobID]["ismilestone"]=$job["ismilestone"];
			*/
			$sortedJobs[$jobID]["preJobs"]=[];
			$sortedJobs[$jobID]["sucJobs"]=[];
			foreach ($edges as $edgeID => $edge){
				if ($edge["fromjobid"]==$jobID){
					$sortedJobs[$jobID]["sucJobs"][$edge["tojobid"]]=["jobid" => $edge["tojobid"], "edge"=> $edgeID];
				}
				if ($edge["tojobid"]==$jobID){
					$sortedJobs[$jobID]["preJobs"][$edge["fromjobid"]]=["jobid" => $edge["fromjobid"], "edge"=> $edgeID];
				}
			}
		}
		$model=[ "jobs" => $sortedJobs , "edges" => $edges];
		$this->updateJobTree($model,[$newStateJob]);
		$this->calculateModelData($model);


		$this->dumpModel($model);

		foreach($model["jobs"] as $key=>$jobID){
			if (isset($model["jobs"][$key]["new"])){
				error_log("job ".$key." state changed to ".$model["jobs"][$key]["state"]);
			}
		}
		foreach($model["edges"] as $edgeID => $edge){
			if (isset($model["edges"][$edgeID]["new"])){
				error_log("edge ".$edgeID." state changed to ".$model["edges"][$edgeID]["state"]);
			}
		}
		return true;
	}

	
	public function toggleJobPredecessorIgnoreState($edgeID){
		$preJobState = $this->db->select("edgelist", [
			"[>]joblist" => ["fromjobid" => "id"],
		],
		[
			"joblist.state(jobstate)",
			"edgelist.tojobid",
			"edgelist.workzoneid",
			"edgelist.state",
		],
		[
			"edgelist.id[=]" => $edgeID
		]);
		$jobState=$preJobState[0]["jobstate"];
		$edgeState=$preJobState[0]["state"];
		$newStateJob=$preJobState[0]["tojobid"];
		$workzoneid=$preJobState[0]["workzoneid"];
		
		if ($edgeState==6){ //if ignore
			$newState= 3; // reworked
		}else{
			$newState= 6; // ignored
		}
		$data = $this->db->update("edgelist", [
			"state" => $newState
		], [
			"id" => $edgeID
		]);
		$this->updateModelState($workzoneid,$newStateJob);
		return true;
	}
	
		
	public function acceptPredecessor($edgeID){
		$preJobState = $this->db->select("edgelist", [
			"[>]joblist" => ["fromjobid" => "id"],
		],
		[
			"joblist.state(jobstate)",
			"edgelist.tojobid",
			"edgelist.state",
			"edgelist.workzoneid",
		],
		[
			"edgelist.id[=]" => $edgeID
		]);
		$jobState=$preJobState[0]["jobstate"];
		$edgeState=$preJobState[0]["state"];
		$newStateJob=$preJobState[0]["tojobid"];
		$workzoneid=$preJobState[0]["workzoneid"];
		$data = $this->db->update("edgelist", [
			"state" => $jobState
		], [
			"id" => $edgeID
		]);
		$this->updateModelState($workzoneid,$newStateJob);
		return true;
	}
	
	
	public function doRequest($post){
		ob_start();
		var_dump($post);
		$result = ob_get_clean();
		error_log($result);
		$action = $post['action'];
		if ($action) {
			if (isset($post['wzName'])){
				$wzName = strtolower($post['wzName']);
			}
			if (isset($post['jobName'])){
				$jobName =$post['jobName'];
			}
			if ($action==1){ //ok to create?
				error_log("Ok to create Workzone?");
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
				error_log("Create Workzone");
				if (!isset($wzName) || !isset($jobName)){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				if (!(preg_match("/^(\w+\.)+\w+$/",$wzName)===1)){
					die('{"errorcode":0, "data": false, "error": "Work Zone Invalid syntax"}');
				}
				if (!$this->jt->jobExists($jobName)){
					die('{"errorcode":0, "data": false, "error": "Job not exists"}');
				}
				$wzID=$this->wz->createWorkZone($wzName);
				$toDo=$this->jt->getAllDependencies($jobName);
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
				error_log("request Work Zone overview");
				if (!isset($wzName) ){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				die('{"errorcode":0, "data": '.json_encode(array_values($this->getWorkZoneOverview($wzName))).'}');

			}
			if ($action==4){ //show Work Zone by Name
				error_log("show Work Zone by Name");
				if (!isset($wzName) ){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				die('{"errorcode":0, "data": '.json_encode($this->showWorkZoneByName($wzName)).'}');

			}
			if ($action==5){ //request Job schema
				error_log("request Job schema");
				if (!isset($post['jobID']) ){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				$jobID = $post['jobID'];
				die('{"errorcode":0, "data": { "content" : '.json_encode($this->getJobSchema($jobID)).', "startval" : {} }}');

			}
			if ($action==6){ //store Job Values
				error_log("store Job Values");
				if (!isset($post['input'])) {
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				$this->setJobValues($post['input'],1);
				die('{"errorcode":0, "data": true}');

			}
			if ($action==7){ //get Job Values
				error_log("get Job Values");
				if (!isset($post['jobID'])) {
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				die('{"errorcode":0, "data": '.json_encode($this->getJobValues($post['jobID'])).'}');
			}
			if ($action==8){ //request Predecessor status list
				error_log("request Predecessor status list");
				if (!isset($post['jobID']) ){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				$jobID = $post['jobID'];
				die('{"errorcode":0, "data": '.json_encode($this->getJobPredecessorStates($jobID)).'}');

			}
			if ($action==9){ //toggle ignore Predecessor job
				error_log("toggle ignore Predecessor job");
				if (!isset($post['edgeID']) ){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				$edgeID = $post['edgeID'];
				die('{"errorcode":0, "data": '.json_encode($this->toggleJobPredecessorIgnoreState($edgeID)).'}');
			}
			
			if ($action==10){ //Accept Predecessor job
				error_log("Accept Predecessor job");
				if (!isset($post['edgeID']) ){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				$edgeID = $post['edgeID'];
				die('{"errorcode":0, "data": '.json_encode($this->acceptPredecessor($edgeID)).'}');

			}
			if ($action==11){ //take over ownership
				error_log("take over ownership");
				if (!isset($post['jobID']) ){
					die('{"errorcode":1, "error": "Variable Error"}');
				}
				$jobID = $post['jobID'];
				die('{"errorcode":0, "data": '.json_encode($this->takeoverOwnership($jobID)).'}');

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
