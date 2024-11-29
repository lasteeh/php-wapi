<?php

namespace Core\Components;

use Core\Base;
use Error;

class ActiveRecord extends Base
{
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
  }

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

  private function assign_attributes(array $attributes)
  {
    $model_name = static::class;

    foreach ($attributes as $attribute => $value) {
      if (!property_exists($this, $attribute)) throw new Error("{$model_name} property does not exist: {$attribute}");
      $this->assign_attribute($attribute, $value);
    }

    $record_exists = $this->record_exists();
    if (!$record_exists) return;

    foreach ($attributes as $attribute => $value) {
      $this->OLD[$attribute] = $value;
    }
  }

  private function assign_attribute(string $attribute, mixed $value)
  {
    $this->$attribute = $value;
    $this->ATTRIBUTES[] = $attribute;
  }

  public function record_exists(bool $bool = null): bool
  {
    if ($bool !== null) return $this->EXISTING_RECORD = $bool;

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

  private static function existing_record(array $attributes): static
  {
    $record = new static($attributes);
    $record->record_exists(true);
    return $record;
  }

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
      return !empty($result) ? static::existing_record($result) : null;
    } catch (\PDOException $error) {
      throw $error;
    }
  }
}
