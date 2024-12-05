<?php

namespace Core\Components;

use Core\Base;
use Core\Traits\ManagesErrorTrait;
use Core\Components\Route;
use Error;

class ActionController extends Base
{
  use ManagesErrorTrait;

  protected Request $REQUEST;

  protected static $skip_before_action = [];
  protected static $before_action = [];
  protected static $skip_after_action = [];
  protected static $after_action = [];

  protected array $variables = [];
  protected array $meta_tags = [];

  protected $yield;
  protected $layout;

  final public function __construct(Request $request)
  {


    $this->setup_filter('before_action');
    $this->setup_filter('skip_before_action');
    $this->setup_filter('after_action');
    $this->setup_filter('skip_after_action');

    $this->REQUEST = $request;
  }

  private function setup_filter(string $filter_name)
  {
    $parent_class = get_parent_class($this);
    $application_filters = (is_subclass_of($parent_class, __CLASS__)) ? $parent_class::$$filter_name : [];

    $all_filters = array_merge(
      self::$$filter_name,
      $application_filters,
      static::$$filter_name
    );

    // normalize filter array
    $normalized_filters = [];
    foreach ($all_filters as $key => $value) {
      $normalized_filters[is_array($value) ? $key : $value] = is_array($value) ? $value : [];
    }

    static::$$filter_name = $normalized_filters;
  }

  final public function execute(string $action)
  {
    foreach (static::$before_action as $filter => $options) {
      if (
        !$this->filter_should_skip(static::$skip_before_action, $filter, $action) &&
        $this->filter_should_apply($action, $options)
      ) {
        $this->$filter();
      }
    }

    $this->$action();

    foreach (static::$after_action as $filter => $options) {
      if (
        !$this->filter_should_skip(static::$skip_after_action, $filter, $action) &&
        $this->filter_should_apply($action, $options)
      ) {
        $this->$filter();
      }
    }
  }

  final protected function filter_should_apply(string $action, array $options = []): bool
  {
    if (empty($options)) return true;

    if (isset($options['only'])) return in_array($action, $options['only'], true);

    if (isset($options['except'])) return !in_array($action, $options['except'], true);

    return false;
  }

  final protected function filter_should_skip($skip_filters, $filter, string $action): bool
  {
    if (!isset($skip_filters[$filter])) return false;

    $skip_before_filter = $skip_filters[$filter];
    if (!is_array($skip_before_filter)) return true;

    if (empty($skip_before_filter)) return true;

    if (isset($skip_before_filter['only'])) return in_array($action, $skip_before_filter['only'], true);

    if (isset($skip_before_filter['except'])) return !in_array($action, $skip_before_filter['except'], true);

    return false;
  }

  final public function redirect(string $to = '', int $status = 302, array $flash = [])
  {
    $this->clear_flash();

    foreach ($flash as $type => $messages) {
      $this->set_flash($type, $messages);
    }

    $redirect_url = self::$HOME_URL . $to;
    header("Location:" . $redirect_url, true, $status);
    exit;
  }

  final public function render(string $view = '', string $dir = '', string $layout = '', int $status = 200, array $flash = [])
  {
    // we name most of the variables in this block heavily with "__" prefix because
    // we are going to use extract() which dynamically creates variables for us
    // and we want to avoid variables being overwritten when there is a collision
    $__view = $view;
    $__dir = $dir;
    $__layout = $layout;
    $__status = $status;
    $__flash = $flash;
    $__html = '';

    http_response_code($__status);

    foreach ($__flash as $__type => $__messages) {
      $this->set_flash($__type, $__messages);
    }

    if (empty($__view)) $__view = $this->REQUEST->action;
    if (empty($__dir)) $__dir = strtolower($this->REQUEST->controller);
    if (!empty($__layout)) $this->layout = $__layout;

    $__view_file = self::$HOME_DIR . self::APP_DIR . self::VIEWS_DIR . $__dir . "/" . $__view . ".view.php";
    if (!file_exists($__view_file)) throw new Error("View file not found: {$__view_file}");

    // we extract the variables here just before the buffering of the html view files
    // but after some processes
    if (!empty($this->variables)) extract($this->variables, EXTR_PREFIX_SAME, 'view');

    ob_start();
    require_once($__view_file);
    $__content = ob_get_clean();
    $this->yield = $__content;

    $__layout_directory = self::$HOME_DIR . self::APP_DIR . self::VIEWS_DIR . "layouts/";
    if (!is_dir($__layout_directory)) mkdir($__layout_directory);

    $__layout_file = $__layout_directory . $this->layout . ".layout.php";
    if (!empty($this->layout) && !file_exists($__layout_file)) throw new Error("Layout file not found: {$__layout_file}");

    if (file_exists($__layout_file)) {
      ob_start();
      require_once($__layout_file);
      $__html = ob_get_clean();
    } else {
      $__html = $this->yield;
    }

    echo $__html;
    $this->clear_flash();
  }

  final public function clear_flash()
  {
    if (!isset($_SESSION['FLASH'])) return;
    unset($_SESSION['FLASH']);
  }

  final public function set_flash(string $type, array $messages)
  {
    if (!isset($_SESSION['FLASH'])) $_SESSION['FLASH'] = [];
    $_SESSION['FLASH'][$type] = $messages;
  }

  final public function flash(string $type = ''): array
  {
    if (empty($type)) return $_SESSION['FLASH'] ?? [];
    return $_SESSION['FLASH'][$type] ?? [];
  }

  final protected function yield()
  {
    return $this->yield ?? '';
  }

  final protected function layout(string $layout)
  {
    $this->layout = $layout;
  }

  final protected function partial(string $partial)
  {
    $partial_file_directory = self::$HOME_DIR . self::APP_DIR . self::VIEWS_DIR . 'partials/';
    if (!is_dir($partial_file_directory)) mkdir($partial_file_directory);

    $partial_file = $partial_file_directory . $partial . ".partial.php";
    if (!file_exists($partial_file)) throw new Error("Partial file not found: {$partial_file}");

    ob_start();
    require_once($partial_file);
    $partial = ob_get_clean();

    return $partial;
  }

  final protected function variables(array $variables = [])
  {
    $this->variables = $variables ?? [];
  }

  final protected function set_meta_tags(
    string $title = '',
    string $description = '',
    string $keywords = '',
    string $canonical = '',
    array $og = [],
    array $twitter = [],
    string $robots = ''
  ) {
    if (!empty($title)) $this->meta_tags['title'] = $title;
    if (!empty($description)) $this->meta_tags['description'] = $description;
    if (!empty($keywords)) $this->meta_tags['keywords'] = $keywords;
    if (!empty($canonical)) $this->meta_tags['canonical'] = $canonical;
    if (!empty($og)) $this->meta_tags['og'] = $og;
    if (!empty($twitter)) $this->meta_tags['twitter'] = $twitter;
    if (!empty($robots)) $this->meta_tags['robots'] = $robots;
  }

  final protected function meta_tag(string $tag)
  {
    return $this->meta_tags[$tag] ?? null;
  }

  final protected function path(string $to = '', string $name = '')
  {
    $path = '';

    if (!empty($name) && empty($to)) {
      $path = Route::fetch(name: $name, return: ['path']);
      return self::$HOME_URL . $path;
    } else {
      $path = self::$HOME_URL . $to;
    }

    return $path;
  }

  final protected function stylesheet(string $name, string $type = "css")
  {
    return self::$HOME_URL . "/" . self::PUBLIC_DIR . self::ASSETS_DIR . self::STYLESHEETS_DIR . $name . ".{$type}";
  }

  final protected function script(string $name, string $type = "js")
  {
    return self::$HOME_URL . "/" . self::PUBLIC_DIR . self::ASSETS_DIR . self::SCRIPTS_DIR . $name . ".{$type}";
  }

  final protected function asset(string $name)
  {
    return self::$HOME_URL . "/" . self::PUBLIC_DIR . self::ASSETS_DIR . $name;
  }

  final protected function params_permit(array $permit, array $input): array
  {
    $params = [];
    $permitted_fields = $permit;
    $user_input = $input;
    foreach ($permitted_fields as $field) {
      if (!isset($user_input[$field])) continue;
      $params[$field] = $user_input[$field];
    }

    return $params;
  }

  final protected function route_param(string $name): mixed
  {
    return $this->REQUEST->route_params[$name] ?? null;
  }

  final protected function route_params(): array
  {
    return $this->REQUEST->route_params ?? [];
  }

  final protected function is_path(string $name): bool
  {
    return (is_string($this->REQUEST->name) && !empty($this->REQUEST->name) && $this->REQUEST->name === $name);
  }
}
