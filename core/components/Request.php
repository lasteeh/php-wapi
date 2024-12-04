<?php

namespace Core\Components;

use Error;

class Request
{
  public ?string $controller = null;
  public ?string $action = null;
  public ?string $request_uri = null;
  public ?string $name = null;
  public array $request_uri_params = [];
  public array $route_params = [];

  public function __construct(string $home_url, array $server)
  {
    if (!filter_var($home_url, FILTER_VALIDATE_URL)) throw new Error("Invalid home url: " . $home_url);

    // create app's custom request uri
    $url_components = parse_url($home_url);
    $request_path = $url_components['path'] ?? '';
    $request_uri = $server['REQUEST_URI'] ?? '';

    if (strpos($request_uri, $request_path) === 0) {
      $this->request_uri = substr($request_uri, strlen($request_path));
    } else {
      $this->request_uri = $request_path;
    }

    // configure request
    $request_method = $server['REQUEST_METHOD'] ?? '';
    $request_method = strtolower($request_method);

    $all_routes = Route::all();
    $routes = $all_routes[$request_method] ?? [];
    if (empty($routes)) throw new Error("No route matches: " . strtoupper($request_method) . " \"" . $this->request_uri . "\"");

    $match_found = false;
    foreach ($routes as $path => $config) {
      if ($match_found) break;

      $route_pattern = str_replace('/', '\/', $path);
      $route_pattern = '#^' . preg_replace('/:([^\s\/]+)/', '([^\/]+)', $route_pattern) . '$#';
      $url_parts = explode("?", $this->request_uri);
      $base_url = $url_parts[0];

      if (!preg_match($route_pattern, $base_url, $matches)) continue;
      preg_match_all('/:([^\s\/]+)/', $path, $parameter_keys);

      foreach ($parameter_keys[1] as $index => $key) {
        if (!isset($matches[$index + 1])) continue;
        $this->route_params[$key] = $matches[$index + 1];
      }

      $this->controller = $config['controller'] ?? null;
      $this->action = $config['action'] ?? null;
      $this->name = $config['name'] ?? null;

      $match_found = true;
      break;
    }

    $trimmed_request = ltrim($this->request_uri, "/");
    $this->request_uri_params = explode("/", $trimmed_request);

    if ($match_found) return $this;

    $catchall_routes = Route::fetch_catchall();
    $catchall_route = $catchall_routes[$request_method] ?? [];

    if (!is_array($catchall_route) || empty($catchall_route)) throw new Error("No route matches: " . strtoupper($request_method) . " \"" . $this->request_uri . "\"");

    $this->controller = $catchall_route['controller'] ?? null;
    $this->action = $catchall_route['action'] ?? null;

    return $this;
  }
}
