<?php
#namespace JobTemplates;

require_once("config.php");

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
	
}


if (!debug_backtrace()) {
    // do useful stuff

	$jt=JobTemplates::Instance(Config::jobPath);
	$action = $_GET['action'];
	if ($action) {
		if ($action=="1"){
			echo json_encode(array_values($jt->get_Job_Names()));
		}
	}
}

?>
