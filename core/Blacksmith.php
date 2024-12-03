<?php

namespace Core;

use Core\Components\Database;
use DateTime;
use Error;

class Blacksmith extends Base
{
  public function __construct()
  {
    if (php_sapi_name() !== 'cli') return;

    set_exception_handler([$this, 'handle_errors']);
    self::load_env();
    self::set_home_dir();
    self::connect_database();
    self::load_dependencies();
  }

  public function forge()
  {
    $action = $_SERVER['argv'][1] ?? '';
    $arguments = $_SERVER['argv'] ?? [];

    $flags = [];
    foreach ($arguments as $argument) {
      if (!is_string($argument) || empty(trim($argument)) || strpos($argument, '--') !== 0) continue;
      $argument = str_replace('--', '', $argument);

      if (strpos($argument, '=') === false) {
        $flags[$argument] = true;
        continue;
      }

      [$name, $value] = explode('=', $argument, 2);
      if (preg_match('/^"(.*)"$/', $value, $matches)) {
        $value = $matches[1];
      }

      $flags[$name] = $value;
    }

    switch ($action) {


      case 'refine':
        $model_name = $flags['model'] ?? '';
        $models_directory = str_replace("/", "\\", ucfirst(self::APP_DIR) . ucfirst(self::MODELS_DIR));

        $fqcn = $models_directory . ucfirst($model_name);
        if (!is_string($model_name) || empty($model_name) || !class_exists($fqcn)) throw new Error("Model not found: {$model_name} \n");

        $attributes = $flags;
        unset($attributes['model']);
        $model = $fqcn::find_by($attributes);

        if (empty($model)) die("{$model_name} not found.");

        $properties = get_object_vars($model);
        echo "\n";
        foreach ($properties as $_property => $_value) {
          if (is_array($_value)) continue;
          echo "{$_property}: {$_value} \n";
        }

        $confirmation = readline("Input new attribute values in flags: \n");
        preg_match_all('/--(\w+)=("[^"]*"|\S+)/', $confirmation, $matches);

        $keys = $matches[1];
        $values = $matches[2];
        $attribute_flags = array_combine($keys, $values);

        if (empty($attribute_flags)) die();

        $model->assign_attributes($attribute_flags);
        $model->save();
        $errors = $model->errors();

        if (!empty($errors)) {
          echo "\n";
          foreach ($errors as $error) {
            echo "{$error} \n";
          }
        }

        if ($model->record_exists()) {
          echo "\n {$model_name} updated successfully. \n";
        }
        break;


      case 'register':
        $model_name = $flags['model'] ?? '';
        $models_directory = str_replace("/", "\\", ucfirst(self::APP_DIR) . ucfirst(self::MODELS_DIR));

        $fqcn = $models_directory . ucfirst($model_name);
        if (!is_string($model_name) || empty($model_name) || !class_exists($fqcn)) throw new Error("Model not found: {$model_name} \n");

        $attributes = $flags;
        unset($attributes['model']);
        $model = new $fqcn($attributes);

        $model->save();
        $errors = $model->errors();

        if (!empty($errors)) {
          echo "\n";
          foreach ($errors as $error) {
            echo "{$error} \n";
          }
        }

        if ($model->record_exists()) {
          echo "\n {$model_name} registered successfully. \n";
        }

        break;


      case 'migrate':
        $migrations_directory = self::$HOME_DIR . self::DATABASE_DIR . self::MIGRATIONS_DIR;
        if (!is_dir($migrations_directory)) throw new Error("Migration directory not found. \n");

        $migration_files = glob($migrations_directory . "/*.sql");
        if ($migration_files === false) throw new Error("Error fetching SQL files from {$migrations_directory}. \n");

        sort($migration_files);

        $confirmation = readline("This action cannot be undone. Continue migration? Y/n: ");
        if (strtolower($confirmation) !== 'y') {
          echo "Migration aborted.";
          return;
        }

        foreach ($migration_files as $file) {
          if (file_exists($file)) {
            $sql = file_get_contents($file);

            try {
              $statement = Database::$PDO->prepare($sql);
              $statement->execute();
              echo " \n";
              echo "SQL file executed successfully: \n" . $file . " \n";
            } catch (\PDOException $error) {
              echo " \n";
              echo "Error executing SQL file: \n" . $file . " \n" . $error->getMessage() . " \n";
            }
          } else {
            echo "File not found: {$file} \n";
          }
        }
        break;



      case 'migration':
        $migration_filename = $flags['name'] ?? '';
        $this->validate_filename($migration_filename);

        $current_datetime = new DateTime();
        $formatted_datetime = $current_datetime->format('YmdHisv');
        $filename = self::$HOME_DIR . self::DATABASE_DIR . self::MIGRATIONS_DIR . $formatted_datetime . "_" . $migration_filename . ".sql";

        $migrations_directory = self::$HOME_DIR . self::DATABASE_DIR . self::MIGRATIONS_DIR;
        if (!is_dir($migrations_directory)) {
          if (!mkdir($migrations_directory)) {
            throw new Error("Failed to create migrations directory: {$migrations_directory} \n");
          }
        }

        $this->create_file($filename);
        break;
    };
  }

  private function validate_filename(string $filename)
  {
    if (empty($filename) || !preg_match('/^[a-zA-Z_]+$/', $filename)) throw new Error("Invalid filename. Only letters and underscores are allowed. \n");
  }

  private function create_file(string $name, string $content = '')
  {
    if (file_exists($name)) throw new Error("File already exists: {$name} \n");

    $file = fopen($name, 'w');
    fwrite($file, $content);
    fclose($file);

    if (!file_exists($name)) throw new Error("Failed to create file: {$name} \n");
    echo "File created successfully: {$name} \n";
  }
}
