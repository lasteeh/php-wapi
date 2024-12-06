<?php

namespace Core;

use Core\Base;
use Core\Components\Request;
use Error;

class App extends Base
{


  public function __construct(bool $env = false, bool $db = false, bool $dependencies = false)
  {
    set_exception_handler([$this, 'handle_errors']);
    if ($env === true) self::load_env();
    self::set_home_dir();
    self::set_home_url();
    if ($db === true)  self::connect_database();
    if ($dependencies === true) self::load_dependencies();
    self::config('routes'); // load routes
  }

  public function run()
  {
    $request = new Request(self::$HOME_URL, $_SERVER);
    $class = $request->controller;
    $action = $request->action;

    $class_namespace = implode("\\", array_map('ucfirst', explode("/", self::APP_DIR . self::CONTROLLERS_DIR)));
    $fqcn = $class_namespace . ucfirst($class) . "Controller";
    if (!class_exists($fqcn)) throw new Error("Controller not found: {$class}");
    if (!method_exists($fqcn, $action)) throw new Error("{$class}Controller action not found: {$action}");
    if (session_status() === PHP_SESSION_NONE) session_start();

    $contoller = new $fqcn($request);
    $contoller->execute($action);
  }
}
