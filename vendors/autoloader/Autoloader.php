<?php

class Autoloader
{
  public static function register()
  {
    spl_autoload_register([__CLASS__, 'autoload']);
  }

  public static function autoload($classname)
  {
    // we need to extract the filename here
    // so that we can normalize the directories we got from the namespaces to be all lowercase
    // and replace backslashes with forwardslashes here to be compatible with linux environments
    // to avoid errors caused by environments that are case sensitive
    // this is why directory names should be all lowercase
    $classname = str_replace("\\", "/", $classname);
    $last_slash_position = strrpos($classname, "/");
    if ($last_slash_position !== false) {
      $class_namespace = substr($classname, 0, $last_slash_position + 1);
      $class_filename = str_replace($class_namespace, "", $classname);
      $classname = strtolower($class_namespace) . $class_filename;
    }

    // we get the root directory of the app here to easily require the files
    // using namespaces as sub directories
    // and classnames as class file names
    // it is important to match these names:
    // namespaces with directories (directories should be all lowercase)
    // classnames with class file (class files should be case sensitive)
    $app_directory = str_replace("vendors", "", __DIR__);
    $app_directory = str_replace("autoloader", "", $app_directory);
    $class_file = $app_directory . $classname . ".php";

    // we replace backslashes with forwardslashes again
    // windows environments uses backlsashes for directory separators as default
    // but can also use forwardslashes
    $class_file = str_replace("\\", "/", $class_file);


    // we normalize the string again to eliminate multiple consecutive slashes to avoid environment error
    // where they cant normalize paths having multiple consecutive slashes
    $class_file = preg_replace('/\/+/', '/', $class_file);

    if (file_exists($class_file)) {
      require_once($class_file);
    } else {
      throw new Error("File does not exist: {$class_file}");
    }
  }
}
