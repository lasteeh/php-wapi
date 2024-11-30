<?php

namespace Core\Components;

use Core\Base;
use COre\Traits\ManagesErrorTrait;
use Error;

class ActiveRecord extends Base
{
  use ManagesErrorTrait;

  public static $TABLE;



  protected static $skip_before_validate = [];
  protected static $before_validate = [];
  protected static $skip_after_validate = [];
  protected static $after_validate = [];

  protected static $validate = [];
  protected static $skip_validate = [];

  protected $validations = [];

  protected static $skip_before_update = [];
  protected static $before_update = [];
  protected static $skip_after_update = [];
  protected static $after_update = [];

  protected static $skip_before_save = [];
  protected static $before_save = [];
  protected static $skip_after_save = [];
  protected static $after_save = [];

  protected static $skip_before_create = [];
  protected static $before_create = [];
  protected static $skip_after_create = [];
  protected static $after_create = [];

  protected static $skip_before_destroy = [];
  protected static $before_destroy = [];
  protected static $skip_after_destroy = [];
  protected static $after_destroy = [];



  private bool $EXISTING_RECORD = false;
  private array $ATTRIBUTES = [];
  private array $OLD = [];




  public function __construct(array $attributes = [])
  {
    // generate table name
    static::table_name();

    $this->assign_attributes($attributes);

    // store old values if record already exist
    if ($this->record_exists()) {
      foreach ($attributes as $attribute => $value) {
        $this->OLD[$attribute] = $value;
      }
    }

    // setup callbacks
    $this->setup_callback('skip_before_validate');
    $this->setup_callback('before_validate');
    $this->setup_callback('skip_validate');
    $this->setup_callback('validate');
    $this->setup_callback('skip_after_validate');
    $this->setup_callback('after_validate');

    $this->setup_callback('skip_before_update');
    $this->setup_callback('before_update');
    $this->setup_callback('skip_after_update');
    $this->setup_callback('after_update');

    $this->setup_callback('skip_before_create');
    $this->setup_callback('before_create');
    $this->setup_callback('skip_after_create');
    $this->setup_callback('after_create');

    $this->setup_callback('skip_before_save');
    $this->setup_callback('before_save');
    $this->setup_callback('skip_after_save');
    $this->setup_callback('after_save');

    $this->setup_callback('skip_before_destroy');
    $this->setup_callback('before_destroy');
    $this->setup_callback('skip_after_destroy');
    $this->setup_callback('after_destroy');

    return $this;
  }

  public function assign_attributes(array $attributes)
  {
    foreach ($attributes as $attribute => $value) {
      $this->assign_attribute($attribute, $value);
    }
  }

  public function assign_attribute(string $attribute, mixed $value)
  {
    $model_name = static::class;
    if (!property_exists($this, $attribute)) throw new Error("{$model_name} property does not exist: {$attribute}");

    $this->$attribute = $value;
    $this->ATTRIBUTES[] = $attribute;
  }

  public function update_column(string $column, mixed $value)
  {
    $this->assign_attribute($column, $value);
    $this->run_callback('before_validate');

    if (!$this->validate([$column])) return false;

    $this->run_callback('validate');

    if (!empty($this->errors())) return false;

    $this->run_callback('after_validate');
    $this->run_callback('before_update');

    $filters = [];
    foreach ($this->ATTRIBUTES as $filter) {
      if ($filter === $column) continue;
      $filters[$filter] = $this->$filter;
    }

    [$where_clause, $bind_params] = QueryBuilder::build_where($filters);
    $placeholder = ":{$column}_value";
    $bind_params[$placeholder] = $value;

    $table = static::table_name();
    $sql = "UPDATE {$table} SET {$column} = {$placeholder} {$where_clause}";

    try {
      $statement = Database::$PDO->prepare($sql);
      $statement->execute($bind_params);
    } catch (\PDOException $error) {
      throw $error;
      return false;
    }

    $this->run_callback('after_update');

    return true;
  }

  public function record_exists(): bool
  {
    if ($this->EXISTING_RECORD) return true;
    if (empty($this->ATTRIBUTES)) return false;

    $filters = [];
    foreach ($this->ATTRIBUTES as $attribute) {
      $filters[$attribute] = $this->$attribute;
    }

    [$where_clause, $bind_params] = QueryBuilder::build_where($filters);

    $table = static::table_name();
    $sql = "SELECT 1 FROM {$table} {$where_clause}";

    try {
      $statement = Database::$PDO->prepare($sql);
      $statement->execute($bind_params);
      $this->EXISTING_RECORD = $statement->fetchColumn() > 0;
      return $this->EXISTING_RECORD;
    } catch (\PDOException $error) {
      throw $error;
    }
  }

  /******************************************/
  /*          sql queries below             */
  /******************************************/


  public static function find_by(array $columns, array $return = [])
  {
    $conditions = $columns;
    $returned_columns = $return;

    $select_clause = QueryBuilder::build_select($returned_columns);
    if (empty($select_clause)) throw new Error("No valid columns.");

    [$where_clause, $bind_params] = QueryBuilder::build_where($conditions);

    $table = static::table_name();
    $sql = "SELECT {$select_clause} FROM {$table} {$where_clause}";

    try {
      $statement = Database::$PDO->prepare($sql);
      $statement->execute($bind_params);
      $result = $statement->fetch(\PDO::FETCH_ASSOC);

      if (empty($result) || !is_array($result)) return null;
      return new static($result);
    } catch (\PDOException $error) {
      throw $error;
    }
  }

  /******************************************/
  /*         helper methods below           */
  /******************************************/


  private static function table_name()
  {
    if (!empty(static::$TABLE)) return static::$TABLE;

    $table_name = basename(static::class);
    $table_name = static::pluralize($table_name);

    static::$TABLE = $table_name;
    return static::$TABLE;
  }

  private static function pluralize(string $word)
  {
    // expand 
    $last_letter = strtolower($word[strlen($word) - 1]);
    if ($last_letter == 'y') return substr($word, 0, -1) . 'ies';
    return $word . 's';
  }

  private function setup_callback(string $callback_name)
  {
    $parent_class = get_parent_class($this);
    $application_callbacks = (is_subclass_of($parent_class, __CLASS__)) ? $parent_class::$$callback_name : [];

    $all_callbacks = array_merge(
      self::$$callback_name,
      $application_callbacks,
      static::$$callback_name
    );

    // normalize callbacks here
    $normalized_callbacks = [];
    foreach ($all_callbacks as $callback) {
      if (!is_string($callback) ||  !method_exists($this, $callback)) continue;
      $normalized_callbacks[] = $callback;
    }

    static::$$callback_name = $normalized_callbacks;
  }

  private function run_callback(string $callback_name)
  {
    $skip_callback_name = "skip_{$callback_name}";

    foreach (static::$$callback_name as $callback) {
      if (in_array($callback, static::$$skip_callback_name)) continue;
      $this->$callback();
    }
  }

  private function validate(array $columns): bool
  {
    $model_name = static::class;
    if (empty($this->ATTRIBUTES)) throw new Error("{$model_name} is empty.");

    $errors = [];
    foreach ($columns as $column) {
      if (!isset($this->validations[$column])) continue;
      $errors = array_merge($errors, $this->validate_field($column));
    }

    if (empty($errors)) return true;
    $this->add_errors($errors);
    return false;
  }

  private function validate_field(string $column): array
  {
    $errors = [];

    foreach ($this->validations[$column] as $rule => $value) {
      switch ($rule) {
        case 'numericality':
          if (isset($value['only_integer'])) {
            if (!is_numeric($this->$column) || !is_int($this->$column + 0)) {
              $errors[] = "{$column} must be an integer.";
            }
          }
          break;

        case 'presence':
          if ($value === true && empty($this->$column)) {
            $errors[] = "{$column} can't be blank.";
          }
          break;

        case 'uniqueness':
          if ($value === true) {
            $existing_record = static::find_by([$column => $this->$column]);
            if ($existing_record) {
              $errors[] = "{$column} '{$this->$column}' already exists.";
            }
          }
          break;

        case 'length':
          if (isset($value['minimum']) && strlen($this->$column) < $value['minimum']) {
            $minimum_value = $value['minimum'];
            $errors[] = "{$column} is too short (minimum length: {$minimum_value} characters).";
          }
          break;

        case 'confirmation':
          $confirmation_field = "{$column}_confirmation";
          if ($value === true && $this->$column !== $this->$confirmation_field) {
            $errors[] = "{$column} and {$confirmation_field} do not match.";
          }
          break;
      }
    }

    return $errors;
  }
}
