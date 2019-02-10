<?php
#namespace JobTemplates;


class JobTemplates {
	private static $instance;

	private $tempates=array();

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
			echo("filename: ".$filename[0]."\n");
			$fileContent=file_get_contents($filename[0]);
			preg_match_all('/<code.*?>(.*?)<\/code>/s', $fileContent, $matches);
			#var_dump($matches);
			if (isset($matches[1])){
				$jsonString=implode($matches[1],"");
				echo ($jsonString);
				$json=json_decode($jsonString);
				var_dump($json);
				echo(json_encode($json));
			}
		}
	}
	
}


if (!debug_backtrace()) {
    // do useful stuff
}

$jt=JobTemplates::Instance($argv[1]);

?>
