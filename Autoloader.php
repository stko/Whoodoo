<?php
namespace app;

class Autoloader {
   private static $rootPath;

   /**
    * Register Autoloader
    */
   public static function register($rootPath='') {
      self::$rootPath = $rootPath;
      spl_autoload_register(array(__CLASS__, 'autoload'));
   }


   /**
    * Load class with this name
    *
    * @param $class : class name
    */
   public static function autoload($class) {
      if (strpos($class, __NAMESPACE__ . '\\') === 0) {
         $class = str_replace(__NAMESPACE__ . '\\', '', $class);
         $class = str_replace('\\', '/', $class);
         require_once self::$rootPath.'app/' . $class . '.php';
      }  else {
         $class = str_replace('\\', '/', $class);
         if(file_exists(self::$rootPath."app/libs/ext/" . $class . ".php"))
            require_once self::$rootPath.'app/libs/ext/' . $class . '.php';
      }
   }
}
?>
