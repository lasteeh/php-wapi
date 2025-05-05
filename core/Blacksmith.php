<?php

namespace Core;

use Core\Components\Database;
use DateTime;
use Error;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class Blacksmith extends Base
{
  public function __construct()
  {
    if (php_sapi_name() !== 'cli') return;

    self::define_constants();
    set_exception_handler([$this, 'handle_errors']);
    self::set_home_dir();

    self::load_env();
    if (self::config('blacksmith.CONNECT_DB') === true)  self::connect_database();
    if (self::config('blacksmith.REQUIRE_DEPENDENCIES') === true) self::load_dependencies();
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
      case 'assemble':

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
      case 'smelt':
        self::set_maintenance_mode(true);

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
            $sql_file = file_get_contents($file);
            // $sqls = explode(';', $sql_file);
            $sqls = preg_split(
              "/;(?![^'\"`]*(['\"`])[^'\"`]*\\1)/",
              $sql_file
            );

            foreach ($sqls as $sql) {
              $sql = trim($sql);
              if (empty($sql)) continue;

              try {
                Database::PDO()->beginTransaction();

                $statement = Database::PDO()->prepare($sql);
                $statement->execute();
                $statement->closeCursor();

                if (Database::PDO()->inTransaction()) Database::PDO()->commit();
                echo " \n";
                echo "SQL file executed successfully: \n" . $file . " \n";
              } catch (\PDOException $error) {
                if (Database::PDO()->inTransaction()) Database::PDO()->rollback();
                echo " \n";
                echo "Error executing SQL file: \n" . $file . " \n" . $error->getMessage() . " \n";
              }
            }
          } else {
            echo "File not found: {$file} \n";
          }
        }
        break;


      case 'seed':
      case 'quench':
        self::set_maintenance_mode(true);

        $seed_directory = self::$HOME_DIR . self::DATABASE_DIR . self::SEEDS_DIR;
        if (!is_dir($seed_directory)) throw new Error("Seed directory not found. \n");

        $seed_files = glob($seed_directory . "/*.sql");
        if ($seed_files === false) throw new Error("Error fetching SQL files from {$seed_directory}. \n");

        sort($seed_files);

        $confirmation = readline("This action cannot be undone. Continue seeding? Y/n: ");
        if (strtolower($confirmation) !== 'y') {
          echo "Seeding aborted.";
          return;
        }

        foreach ($seed_files as $file) {
          if (file_exists($file)) {
            $sql_file = file_get_contents($file);
            // $sqls = explode(';', $sql_file);
            $sqls = preg_split(
              "/;(?![^'\"`]*(['\"`])[^'\"`]*\\1)/",
              $sql_file
            );

            foreach ($sqls as $sql) {
              $sql = trim($sql);
              if (empty($sql)) continue;

              try {
                Database::PDO()->beginTransaction();

                $statement = Database::PDO()->prepare($sql);
                $statement->execute();
                $statement->closeCursor();

                if (Database::PDO()->inTransaction()) Database::PDO()->commit();
                echo " \n";
                echo "SQL file executed successfully: \n" . $file . " \n";
              } catch (\PDOException $error) {
                if (Database::PDO()->inTransaction()) Database::PDO()->rollback();
                echo " \n";
                echo "Error executing SQL file: \n" . $file . " \n" . $error->getMessage() . " \n";
              }
            }
          } else {
            echo "File not found: {$file} \n";
          }
        }
        break;



      case 'migration':
      case 'scribe':
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

      case 'seeding':
      case 'temper':
        $seed_filename = $flags['name'] ?? '';
        $this->validate_filename($seed_filename);

        $current_datetime = new DateTime();
        $formatted_datetime = $current_datetime->format('YmdHisv');
        $filename = self::$HOME_DIR . self::DATABASE_DIR . self::SEEDS_DIR . $formatted_datetime . "_" . $seed_filename . ".sql";

        $seeds_directory = self::$HOME_DIR . self::DATABASE_DIR . self::SEEDS_DIR;
        if (!is_dir($seeds_directory)) {
          if (!mkdir($seeds_directory)) {
            throw new Error("Failed to create seed directory: {$seeds_directory} \n");
          }
        }

        $this->create_file($filename);
        break;


      case 'update':
      case 'upgrade':

        $download_link = $flags['url'] ?? '';
        if (empty($download_link)) {
          echo "Missing --url flag.\n";
          exit;
        }

        if (!class_exists("ZipArchive")) {
          echo "ZipArchive is not enabled. \n";
          echo "Please enable 'extension=zip' in your php.ini file. \n";
          exit;
        }

        $confirmation = readline("This action cannot be undone. Please make sure to backup all your files. Continue update? Y/n: ");
        if (strtolower($confirmation) !== 'y') {
          echo "Update aborted.";
          exit;
        }

        $save_path = "__temp-php-wapi.zip";
        $extraction_path = "__temp-php-wapi/";
        $exclusions = [
          '.gitignore',
          'storage',
        ];

        echo "Updating PHP-WAPI... \n";

        if (is_dir($extraction_path)) {
          echo "Clearing temporary update directory... \n";
          if (!$this->delete_dir($extraction_path)) {
            echo "Failed to clear temporary update directory. \n";
            exit;
          }
        }

        if (file_exists($save_path)) {
          echo "Clearing existing update zip file... \n";
          if (unlink($save_path)) {
            echo "Existing update zip file cleared. \n";
          } else {
            echo "Failed to clear update zip file. \n";
            exit;
          }
        }

        echo "Downloading update... \n";
        $file_content = file_get_contents($download_link);
        if ($file_content !== false) {
          file_put_contents($save_path, $file_content);
          echo "Update zip file downloaded successfully. \n";
        } else {
          echo "Failed to download update zip file. \n";
          if (file_exists($save_path)) {
            unlink($save_path);
          }
          exit;
        }

        $zip = new ZipArchive;
        if ($zip->open($save_path) === TRUE) {
          echo "Extracting files... \n";
          if ($zip->extractTo($extraction_path)) {
            echo "Update zip files have been extracted successfully. \n";
            $zip->close();
          } else {
            echo "Failed to extract update zip files. \n";
            $zip->close();
            if (is_dir($extraction_path)) {
              $this->delete_dir($extraction_path);
            }
            exit;
          }
        } else {
          echo "Failed to open update zip file. \n";
          if (is_dir($extraction_path)) {
            $this->delete_dir($extraction_path);
          }
          exit;
        }

        $version_filename = 'VERSION';
        $exclude_directories = ['vendors'];
        $update_source = $this->find_file_dir($extraction_path, $version_filename, $exclude_directories);
        if (empty($update_source)) {
          echo "Incorrect update files. Aborting update...\n";
          echo "Cleaning up temporary files... \n";
          if (is_dir($extraction_path)) {
            $this->delete_dir($extraction_path);
          }
          if (file_exists($save_path)) {
            unlink($save_path);
          }
          echo "Update aborted. \n";
          exit;
        }

        $version_filepath = $update_source . $version_filename;
        if (!file_exists($version_filepath)) {
          echo "Some update files are missing. Aborting update... \n";
          echo "Cleaning up temporary files... \n";
          if (is_dir($extraction_path)) {
            $this->delete_dir($extraction_path);
          }
          if (file_exists($save_path)) {
            unlink($save_path);
          }
          echo "Update aborted. \n";
          exit;
        }

        $version_content = file_get_contents($version_filepath);
        preg_match('/\b(\d+\.\d+\.\d+)\b/', $version_content, $matches);
        $downloaded_version = $matches[1] ?? null;

        if (empty($downloaded_version)) {
          echo "Some update files are missing. Aborting update... \n";
          echo "Cleaning up temporary files... \n";
          if (is_dir($extraction_path)) {
            $this->delete_dir($extraction_path);
          }
          if (file_exists($save_path)) {
            unlink($save_path);
          }
          echo "Update aborted. \n";
          exit;
        }

        echo "Checking update files... \n";

        $downloaded_version_parts = explode(".", $downloaded_version, 3);
        if ($downloaded_version_parts[0] < 1 || $downloaded_version_parts[1] < 0 || $downloaded_version_parts[2] < 10) {
          echo "Cannot update to any versions older than 1.0.10 \n";
          echo "Aborting update...\n";
          echo "Cleaning up temporary files... \n";
          if (is_dir($extraction_path)) {
            $this->delete_dir($extraction_path);
          }
          if (file_exists($save_path)) {
            unlink($save_path);
          }
          echo "Update aborted. \n";
          exit;
        }

        $current_version_content = file_get_contents(self::$HOME_DIR . $version_filename);
        preg_match('/\b(\d+\.\d+\.\d+)\b/', $current_version_content, $matches);
        $current_version = $matches[1] ?? null;

        $confirmation = readline("Updating {$current_version} to {$downloaded_version}. Continue? Y/n: ");
        if (strtolower($confirmation) !== 'y') {
          echo "Aborting update...\n";
          echo "Cleaning up temporary files... \n";
          if (is_dir($extraction_path)) {
            $this->delete_dir($extraction_path);
          }
          if (file_exists($save_path)) {
            unlink($save_path);
          }
          echo "Update aborted. \n";
          exit;
        }


        echo "Updating project files... \n";
        $this->copy_dir($update_source, self::$HOME_DIR, $exclusions);

        echo "Cleaning up temporary files... \n";
        if (is_dir($extraction_path)) {
          $this->delete_dir($extraction_path);
        }
        if (file_exists($save_path)) {
          unlink($save_path);
        }

        echo "Update completed successfully! \n";
        echo "v {$downloaded_version} \n";
        echo "Please check that all files were properly updated. \n";
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

  private function delete_dir(string $directory_path): bool
  {
    if (!is_dir($directory_path)) {
      echo "Directory does not exist. \n";
      return false;
    }

    $files = array_diff(scandir($directory_path), array('.', '..'));

    foreach ($files as $file) {
      $file_path = $directory_path . "/" . $file;
      if (is_dir($file_path)) {
        $this->delete_dir($file_path);
      } else {
        if (!unlink($file_path)) {
          echo "Failed to delete file: {$file_path} \n";
          exit;
        }
      }
    }

    if (!rmdir($directory_path)) {
      echo "Failed to delete directory: {$directory_path} \n";
      return false;
    }

    echo "Directory and its contents deleted successfully: {$directory_path} \n";
    return true;
  }

  private function copy_dir(string $source, string $dest, array $exclusions = [])
  {
    if (!file_exists($dest)) {
      mkdir($dest, 0755, true);
    }

    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
      if ($file === '.' || $file === '..') {
        continue;
      }

      $src_file = $source . '/' . $file;
      $dest_file = $dest . '/' . $file;

      $relative_path = ltrim(str_replace(self::$HOME_DIR, '', $dest_file), '/');
      $relative_path = str_replace(DIRECTORY_SEPARATOR, '/', $relative_path);

      if (in_array($relative_path, $exclusions)) {
        echo "Skipping excluded item: {$relative_path}\n";
        continue;
      }

      if (is_dir($src_file)) {
        $this->copy_dir($src_file, $dest_file, $exclusions);
      } else {
        if (!copy($src_file, $dest_file)) {
          echo "Failed to copy file: {$src_file} \n";
          exit;
        }
      }
    }
    closedir($dir);
  }

  private function find_file_dir(string $directory, string $filename, array $exclude = [])
  {
    $dir_iterator = new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS);

    $filter_iterator = new RecursiveCallbackFilterIterator($dir_iterator, function ($file, $key, $iterator) use ($exclude) {
      if ($file->isDir() && in_array($file->getFilename(), $exclude)) {
        return false;
      }
      return true;
    });

    $iterator = new RecursiveIteratorIterator($filter_iterator);

    foreach ($iterator as $file) {
      if ($file->getFilename() === $filename) {
        return dirname($file->getPathname()) . '/';
      }
    }
    return null;
  }
}
