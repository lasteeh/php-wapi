<?php

namespace Core\Components;

use Error;

class Route
{
  protected static $GET_ROUTES = [];
  protected static $POST_ROUTES = [];

  private static $NAMED_ROUTES = [];
  private static $catchall_route = "catchall";


  public static function get(string $request_uri, string $controller_action, array $options = [])
  {
    $pair = explode("@", $controller_action, 2);
    if (count($pair) !== 2) throw new Error("Invalid Controller@action: {$controller_action}");

    self::$GET_ROUTES[$request_uri] = [
      'controller' => $pair[0],
      'action' => $pair[1],
    ];

    if (!is_array($options) || empty($options)) return;

    foreach ($options as $option => $value) {
      if ($value === null || $value === '') continue;
      self::$GET_ROUTES[$request_uri][$option] = $value;

      if ($option !== 'name') continue;
      if (isset(self::$NAMED_ROUTES[$value])) throw new Error("Route name already in use: {$value}");
      self::$NAMED_ROUTES[$value] = [
        'method' => 'get',
        'path' => $request_uri,
      ];
    }
  }

  public static function post(string $request_uri, string $controller_action, array $options = [])
  {
    $pair = explode("@", $controller_action, 2);
    if (count($pair) !== 2) throw new Error("Invalid Controller@action: {$controller_action}");

    self::$POST_ROUTES[$request_uri] = [
      'controller' => $pair[0],
      'action' => $pair[1],
    ];

    if (!is_array($options) || empty($options)) return;

    foreach ($options as $option => $value) {
      if ($value === null || $value === '') continue;
      self::$POST_ROUTES[$request_uri][$option] = $value;

      if ($option !== 'name') continue;
      if (isset(self::$NAMED_ROUTES[$value])) throw new Error("Route name already in use: {$value}");
      self::$NAMED_ROUTES[$value] = [
        'method' => 'post',
        'path' => $request_uri,
      ];
    }
  }

  public static function home(string $controller_action, array $options = [])
  {
    self::get("/", $controller_action, $options);
  }

  public static function fetch(string $name = '', array $options = [])
  {
    if (empty($options)) return self::$NAMED_ROUTES[$name] ?? null;
  }

  public static function all(array $methods = []): array
  {
    if (empty($methods)) return ['get' => self::$GET_ROUTES, 'post' => self::$POST_ROUTES];

    $routes = [];
    foreach ($methods as $method) {
      if (!is_string($method) || empty($method)) continue;
      $method = strtolower($method);
      switch ($method) {
        case 'get':
          $routes[$method] =  self::$GET_ROUTES;
          break;
        case 'post':
          $routes[$method] = self::$POST_ROUTES;
          break;
      }
    }

    return $routes;
  }

  public static function catchall(string $controller_action, array $options = [])
  {
    $methods = $options['via'] ?? [];

    if (!is_array($methods) || empty($methods)) {
      self::get(self::$catchall_route, $controller_action, $options);
      self::post(self::$catchall_route, $controller_action, $options);
      return;
    }

    foreach ($methods as $method) {
      if (!is_string($method) || empty($method)) continue;
      $method = strtolower($method);
      switch ($method) {
        case 'get':
          self::get(self::$catchall_route, $controller_action, $options);
          break;
        case 'post':
          self::post(self::$catchall_route, $controller_action, $options);
          break;
      }
    }
  }

  public static function fetch_catchall(array $methods = []): array
  {
    $all_routes = self::all();
    $catchall_routes = [];

    if (empty($methods)) $methods = ['get', 'post'];

    foreach ($methods as $method) {
      if (!is_string($method) || empty($method)) continue;
      if (!isset($all_routes[$method])) continue;
      $method = strtolower($method);

      foreach ($all_routes[$method] as $route => $config) {
        if ($route !== self::$catchall_route) continue;
        $catchall_routes[$method] = ['name' => $route, ...$config];
      }
    }

    return $catchall_routes;
  }
}