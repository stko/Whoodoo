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

}


if (!debug_backtrace()) {
    // do useful stuff

	$jt=JobTemplates::Instance(Config::jobPath);
	$jt->doRequest($_POST);
}

?>
