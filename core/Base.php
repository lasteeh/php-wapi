<?php

namespace Core;

use Core\Components\Database;
use Throwable;
use Error;

class Base
{
  protected const APP_DIR = 'app/';
  protected const CONTROLLERS_DIR = 'controllers/';
  protected const MODELS_DIR = 'models/';
  protected const VIEWS_DIR = 'views/';
  protected const CONFIG_DIR = 'config/';
  protected const DATABASE_DIR = 'database/';
  protected const MIGRATIONS_DIR = 'migrations/';
  protected const CORE_DIR = 'core/';
  protected const PUBLIC_DIR = 'public/';
  protected const ASSETS_DIR = 'assets/';
  protected const STYLESHEETS_DIR = 'css/';
  protected const SCRIPTS_DIR = 'js/';
  protected const STORAGE_DIR = 'storage/';
  protected const VENDORS_DIR = 'vendors/';
  protected const INDEX_FILE = '/public/index.php';

  protected array $ERRORS = [];

  protected static $HOME_DIR;
  protected static $HOME_URL;

  final public function handle_errors($exception)
  {
    $error_count = (count($this->ERRORS) > 1) ? "Errors" : "Error";

    if (php_sapi_name() === 'cli') {
      echo "\n";
      echo "{$error_count} found!\n";

      if (!empty($this->ERRORS)) {
        foreach ($this->ERRORS as $error) {
          echo htmlspecialchars($error) . "\n";
        }
      }

      echo "{$error_count} occurred during execution. Halting further execution.\n";

      if ($exception && $exception instanceof Throwable) {
        echo $exception->getMessage() . "\n";
        echo $exception->getTraceAsString() . "\n";

        echo $exception->getFile() . ":" . $exception->getLine() . "\n";
      } elseif ($exception && is_string($exception)) {
        // Get the backtrace information
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $calling_file = $trace[0]['file'] ?? 'Fatal';
        $calling_line = $trace[0]['line'] ?? 'Error';

        echo $exception . "\n";
        echo $calling_file . ":" . $calling_line . "\n";
      }
      die;
    } else {
      $html = '';
      $html = '<!DOCTYPE html>';
      $html .= '<html lang="en">';
      $html .= '<head>';
      $html .= '<meta charset="UTF-8">';
      $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
      $html .= "<title>{$error_count} Found!</title>";
      $html .= '</head>';
      $html .= '<body style="font-family: sans-serif;">';

      $html .= '<div style="width: min(900px, 100% - 8em); padding: 2em; margin-inline: auto; margin-block-start: 2em; background-color: hsl(0,60%,96%,1); border-radius: 0.5em 0.5em 0em 0em; border-top: 4px solid maroon; box-shadow: 1px 1px 1px 1px hsl(0,0%,0%,0.1);">';
      $html .= "<h2>{$error_count} found:</h2>";


      if (!empty($this->ERRORS)) {
        $html .= '<ul>';
        foreach ($this->ERRORS as $error) {
          $html .= '<li>' . htmlspecialchars($error) . '</li>';
        }
        $html .= '</ul>';
      }

      $html .= '<hr style="margin-block: 2em; border: none; border-top: 2px dotted gray;">';
      $html .= '<p>' . "{$error_count} occurred during execution. Halting further execution." . '</p>';

      if ($exception && $exception instanceof Throwable) {
        $html .= "<p style=\"word-break: break-all;\">" . htmlspecialchars($exception->getMessage()) . "</p>";
        $html .= '<p style="font-family: monospace; font-size: 0.75rem;"><strong>Stack trace:</strong>' . htmlspecialchars($exception->getTraceAsString()) . '</p>';

        $html .= "<p style=\"font-family: monospace; font-size: 0.75rem; text-align: right; margin-block-start: 4em;\">" . htmlspecialchars($exception->getFile()) . ":" . $exception->getLine() . "</p>";
      } elseif ($exception && is_string($exception)) {
        // Get the backtrace information
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $calling_file = $trace[0]['file'] ?? 'Fatal';
        $calling_line = $trace[0]['line'] ?? 'Error';

        $html .= "<p style=\"word-break: break-all;\">" . htmlspecialchars($exception) . "</p>";

        $html .= "<p style=\"font-family: monospace; font-size: 0.75rem; text-align: right; margin-block-start: 4em;\">" . $calling_file . ":" . $calling_line . "</p>";
      }

      $html .= '</div>';

      $html .= '</body>';
      $html .= '</html>';

      echo $html;
      die;
    }
  }


  final protected static function load_env()
  {
    if (empty(self::$HOME_DIR)) {
      self::set_home_dir();
    }

    $env_file = self::$HOME_DIR . ".env";
    if (!file_exists($env_file)) return;

    $env_file_contents = file_get_contents($env_file);
    $lines = explode("\n", $env_file_contents);
    if (empty($lines)) return;

    foreach ($lines as $line) {
      if (!is_string($line) || empty(trim($line)) || strpos($line, '#') === 0) continue;

      $line_entry = explode('=', trim($line), 2);
      if (count($line_entry) === 2) {
        [$key, $value] = $line_entry;
        $key = trim($key);
        $value = trim($value);

        $_ENV[$key] = $value;
        putenv($key . "=" . $value);
      }
    }
  }


  final protected static function set_home_dir(string $path = '')
  {
    if (empty($path)) {
      $dir = str_replace("\\", "/", __DIR__);
      $core_dir = str_replace("/", "", self::CORE_DIR);
      self::$HOME_DIR = str_replace($core_dir, '', $dir);
    } else {
      self::$HOME_DIR = $path;
    }

    self::$HOME_DIR = str_replace("\\", "/", self::$HOME_DIR);
  }

  final protected static function set_home_url(string $path = '')
  {
    if (!empty($path)) {
      self::$HOME_URL = $path;
      return;
    }

    $env_home_url = $_ENV['HOME_URL'] ?? '';
    if (!empty($env_home_url)) {
      self::$HOME_URL = $env_home_url;
      return;
    }

    $force_https = filter_var($_ENV['FORCE_HTTPS'] ?? false, FILTER_VALIDATE_BOOL);
    if ($force_https === true) {
      $request_scheme = "https";
    } else {
      $request_scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
    }
    self::$HOME_URL = $request_scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost/') . str_replace(self::INDEX_FILE, '', $_SERVER['SCRIPT_NAME']);
  }

  final protected static function config(string $config_name)
  {
    $config_parts = explode(".", $config_name);
    $config_filename = $config_parts[0];
    $config_key = $config_parts[1] ?? null;

    $config_filepath = self::$HOME_DIR . self::CONFIG_DIR . $config_filename . ".php";
    // if (!file_exists($config_filepath)) throw new Error("Config file not found: {$config_filename}.php");
    if (!file_exists($config_filepath)) return null;

    $config = include($config_filepath);
    if (!is_string($config_key) || empty($config_key)) return $config;

    $key = $config[$config_key] ?? null;
    // if ($key === null) throw new Error("Key not defined: {$key}");

    return $key;
  }

  final protected static function connect_database(): bool
  {
    $database = Database::connect();
    return (!empty($database) && $database instanceof \PDO);
  }

  final protected static function load_dependencies()
  {
    $dependencies = self::config('dependencies.autoload') ?? [];
    if (!is_array($dependencies) || empty($dependencies)) return;

    $vendors_dir = self::$HOME_DIR . self::VENDORS_DIR;
    $vendors_dir = str_replace("\\", "/", $vendors_dir);
    foreach ($dependencies as $dependency) {
      if (is_string($dependency)) {
        if (!file_exists($vendors_dir . $dependency)) throw new Error("File does not exist: {$dependency}");
        require_once($vendors_dir . $dependency);
      } elseif (is_array($dependency)) {
        foreach ($dependency as $file) {
          if (!file_exists($vendors_dir . $file)) throw new Error("File does not exist: {$file}");
          require_once($vendors_dir . $file);
        }
      }
    }
  }
}
