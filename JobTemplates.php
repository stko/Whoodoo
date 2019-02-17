<?php

require_once("Config.php");

class JobTemplates {
	private static $instance;

	private $templates=array();

	/**
	* Return an instance of the JobTemplates
	* @param String $path full path to the job template files
	* @return JobTemplates The JobTemplates instance
	*/
	public static function instance(String $path) {
		#if (is_null(self::$instance) || self::$databaseName != $dbName){
		if (is_null(self::$instance)){
			self::$instance = new self($path);
		}
		return self::$instance;
	}

	private function __construct(String $path) {
		$directory = new RecursiveDirectoryIterator( $path);
		$iterator = new RecursiveIteratorIterator($directory);
		$filenameArray = new RegexIterator($iterator, '/^.+\.txt$/i', RecursiveRegexIterator::GET_MATCH);
		foreach( $filenameArray as $filename){
			$filename=$filename[0];
			$fileContent=file_get_contents($filename);
			preg_match_all('/<code.*?>(.*?)<\/code>/s', $fileContent, $matches);
			#var_dump($matches);
			$filename=preg_replace(array('/\//','/.txt/'),array('.',''),$filename);
			error_log("filename: ".$filename);
			if (isset($matches[1])){
				$jsonString=implode($matches[1],"");
				$json=json_decode($jsonString);
				#echo($json->title."\n");
				$this->templates[$filename]=$json;
			}
		}
	}
	
	private function getFullPath($jobName,$dependencyName){
		$jobElements=explode(".",$jobName);
		$dependencyElements=explode(".",$dependencyName);
		// not finished, just for basic functionality
		if (count($dependencyElements)==1){ // name without any path
			return implode(".",array_slice($jobElements,0,count($jobElements)-1)).".".$dependencyName;
		}else{ //just do nothing and return the original
			return $dependencyName;
		}
		
	}
	
	public function get_Job_Names(){
		$arr=array_keys($this->templates);
		sort($arr);
		return $arr;
	}
	
	public function jobExists($job){
		return isset($this->templates[$job]);
	}

	public function doRequest($post){
		$action = $post['action'];
		if ($action) {
			if ($action=="1"){
				die('{"errorcode":0, "data": '.json_encode(array_values($this->get_Job_Names())).'}');
			}
		}
	}
	
	public function getAllDependencies($origin){ //must be called with a fully qualified job name, obiously
		$jobList=array();
		$this->fillDependencies($jobList,$origin);
		return $jobList;
	}
	
	private function fillDependencies(&$arr, $job){
		if (!array_key_exists($job,$this->templates)){
			error_log("$job not found");
			return false;
		}
		error_log("Working on $job");
		if (!array_key_exists($job,$arr)){
			$arr[$job]=array();
			foreach ($this->templates[$job]->required as $required){
				error_log("requires $required");
				$required=$this->getFullPath($job,$required);
				$arr[$job][$required]=1; // this avoids doublets
				$this->fillDependencies($arr,$required);
				
			}
		}
	}

}


if (!debug_backtrace()) {
    // do useful stuff

	$jt=JobTemplates::Instance(Config::jobPath);
	$jt->doRequest($_POST);
}

?>
