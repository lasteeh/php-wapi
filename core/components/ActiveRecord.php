<?php

namespace Core\Components;

use Core\Base;
use Core\Traits\ManagesErrorTrait;
use Error;

class ActiveRecord extends Base
{
  use ManagesErrorTrait;

  protected static $TABLE;
  protected static $PRIMARY_KEY;
  protected static $DB_ON_UPDATE_COLUMN = ['updated_at'];


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
  private array $TEMPORARY_ATTRIBUTES = [];
  private array $ATTRIBUTES = [];
  private array $OLD = [];




  final public function __construct(array $attributes = [])
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

  /**
   * updates the model instance's attributes without saving to the database
   * 
   * this function assigns new values to specified attributes
   * this doesn't update the database yet
   * 
   * @param array $attributes the attributes to update
   * 
   * @throws Error if an attribute is not declared as a model property
   */
  final public function assign_attributes(array $attributes): void
  {
    foreach ($attributes as $attribute => $value) {
      $this->assign_attribute($attribute, $value);
    }
  }

  /**
   * updates the model instance's specified attribute without saving to the database
   * 
   * this function assigns a new value to the specified attribute
   * this doesn't update the database yet
   * 
   * @param string $attribute the name of the attribute to update
   * @param mixed $value the new value to assign to the attribute
   * 
   * @throws Error if the attribute is not declared as a model property
   */
  final public function assign_attribute(string $attribute, mixed $value): void
  {
    $model_name = static::class;
    if (!property_exists($this, $attribute)) throw new Error("{$model_name} property does not exist: {$attribute}");

    if (!empty($this->validations[$attribute]['confirmation'])) {
      $confirmation_attribute = "{$attribute}_confirmation";
      if (!in_array($confirmation_attribute, $this->TEMPORARY_ATTRIBUTES, true)) $this->TEMPORARY_ATTRIBUTES[] = $confirmation_attribute;
    }

    $this->$attribute = $value;
    if (!in_array($attribute, $this->ATTRIBUTES, true) && !in_array($attribute, $this->TEMPORARY_ATTRIBUTES, true)) $this->ATTRIBUTES[] = $attribute;
  }

  final public function remove_attribute(string $attribute): void
  {
    unset($this->$attribute);
    $this->ATTRIBUTES = array_diff($this->ATTRIBUTES, [$attribute]);
    $this->ATTRIBUTES = array_values($this->ATTRIBUTES);
  }

  /**
   * updates the specified column in the database for the current record 
   * 
   * this function assigns a new value to the specified column, validates it,
   * runs the necessary callbacks, and performs an SQL UPDATE query
   * 
   * @param string $column the name of the column to update
   * @param mixed $value the new value to assign to the column
   * 
   * @return bool true if the update was successful, false otherwise
   * 
   * @throws \PDOException if the database query fails
   */
  final public function update_column(string $column, mixed $value): bool
  {
    $this->assign_attribute($column, $value);
    if (isset($this->OLD[$column]) && $this->OLD[$column] === $this->$column) return true;

    $this->run_callback('before_validate');

    if (!$this->validate([$column])) return false;

    $this->run_callback('validate');

    if (!empty($this->errors())) return false;

    $this->run_callback('after_validate');
    $this->run_callback('before_update');

    [$set_clause, $set_bind_params] = QueryBuilder::build_set([$column => $value]);
    [$where_clause, $where_bind_params] = QueryBuilder::build_where($this->OLD);
    $bind_params = array_merge($set_bind_params, $where_bind_params);

    $table = static::table_name();
    $sql = "UPDATE {$table} {$set_clause} {$where_clause};";

    try {
      $statement = Database::PDO()->prepare($sql);
      $statement->execute($bind_params);
    } catch (\PDOException $error) {
      throw $error;
      return false;
    }

    $this->reload();

    $this->run_callback('after_update');
    return true;
  }

  final public function save(): bool
  {
    $exists = $this->record_exists();
    $updated_attributes = $exists ? $this->updated_attributes() : [];
    $columns = $exists ? array_keys($updated_attributes) : array_keys($this->validations);

    if ($exists && empty($updated_attributes)) return true;

    $this->run_callback('before_validate');
    if (!$this->validate($columns)) return false;

    $this->run_callback('validate');
    if (!empty($this->errors())) return false;

    $this->run_callback('after_validate');
    $this->run_callback('before_save');

    $table = static::table_name();
    $sql = "";
    $bind_params = [];
    if ($exists) {
      $this->run_callback('before_update');
      if (!empty($this->errors())) return false;

      [$set_clause, $set_bind_params] = QueryBuilder::build_set($this->updated_attributes());
      [$where_clause, $where_bind_params] = QueryBuilder::build_where($this->OLD);
      $bind_params = array_merge($set_bind_params, $where_bind_params);

      $sql = "UPDATE {$table} {$set_clause} {$where_clause};";
    } else {
      $this->run_callback('before_create');
      if (!empty($this->errors())) return false;

      $new_record = [];
      foreach ($this->ATTRIBUTES as $attribute) {
        $new_record[$attribute] = $this->$attribute;
      }

      $columns_clause = QueryBuilder::build_columns($this, $this->ATTRIBUTES);
      [$values_clause, $values_bind_params] = QueryBuilder::build_values($new_record);
      $bind_params = $values_bind_params;

      $sql = "INSERT INTO {$table} ({$columns_clause}) {$values_clause};";
    }

    try {
      $statement = Database::PDO()->prepare($sql);
      $statement->execute($bind_params);
    } catch (\PDOException $error) {
      throw $error;
    }

    $this->reload();

    if ($exists) {
      $this->run_callback('after_update');
    } else {
      $this->run_callback('after_create');
    }

    $this->run_callback('after_save');
    return true;
  }

  final public function destroy(): bool
  {
    if (!$this->record_exists()) {
      $this->add_error("Record not found.");
      return false;
    }

    $this->run_callback('before_destroy');

    $filters = [];
    foreach ($this->ATTRIBUTES as $attribute) {
      $filters[$attribute] = $this->$attribute;
    }

    [$where_clause, $where_bind_params] = QueryBuilder::build_where($filters);

    $table = static::table_name();
    $sql = "DELETE FROM {$table} {$where_clause}";

    try {
      $statement = Database::PDO()->prepare($sql);
      $statement->execute($where_bind_params);
    } catch (\PDOException $error) {
      throw $error;
    }

    $this->run_callback('after_destroy');
    return true;
  }

  final public static function insert_all(array $data, array $options = []): bool
  {
    $unique_columns = $options['unique_by'] ?? [];
    $batch_size = max(50, (int)($options['batch_size'] ?? 50));
    $duplicate_handling = $options['on_duplicate'] ?? ''; // do nothing by default, or 'ignore' or 'update'

    $fields = array_keys(reset($data));
    $batches = array_chunk($data, $batch_size);

    $unique_column_map = [];
    foreach ($unique_columns as $unique_column) {
      if (!is_string($unique_column) || isset($unique_column_map[$unique_column])) continue;
      $unique_column_map[$unique_column] = true;
    }

    foreach ($batches as $batch) {
      $placeholders = [];
      $bind_params = [];
      $update_clause = "";
      $ignore_clause = "";

      if (!empty($unique_column_map) && $duplicate_handling === 'update') {
        $update_clause .= "ON DUPLICATE KEY UPDATE ";

        foreach ($fields as $field) {
          if (isset($unique_column_map[$field])) continue;

          $update_clause .= "{$field} = VALUES({$field}), ";
        }

        $update_clause = rtrim($update_clause, ", ");
      } elseif ($duplicate_handling === 'ignore') {
        $ignore_clause = "IGNORE";
      }

      foreach ($batch as $row_index => $entry) {
        $row = [];
        foreach ($fields as $field) {
          $placeholder = ":{$field}_{$row_index}";
          $row[] = $placeholder;
          $bind_params[$placeholder] = $entry[$field];
        }
        $placeholders[] = "(" . implode(",", $row) . ")";
      }

      $table = static::table_name();
      $fields_clause = "(" . implode(",", $fields) . ")";
      $values_clause = implode(",", $placeholders);
      $sql = "INSERT {$ignore_clause} INTO {$table} {$fields_clause} VALUES {$values_clause} {$update_clause};";

      try {
        $statement = Database::PDO()->prepare($sql);
        $statement->execute($bind_params);
      } catch (\PDOException $error) {
        throw $error;
        return false;
      }
    }

    return true;
  }

  final public static function update_all(array $data, array $options = []): bool
  {
    $unique_columns = $options['unique_by'] ?? [];
    $batch_size = max(50, (int)($options['batch_size'] ?? 50));

    $batches = array_chunk($data, $batch_size);

    $table = static::table_name();
    foreach ($batches as $batch) {
      [$set_clause, $set_bind_params, $ids] = QueryBuilder::build_batch_set($batch, $unique_columns);
      [$where_clause, $where_bind_params] = QueryBuilder::build_batch_where($ids);
      $bind_params = array_merge($set_bind_params, $where_bind_params);

      if (empty($set_clause) || empty($where_clause)) continue;
      if (empty($bind_params)) continue;

      $sql = "UPDATE {$table} {$set_clause} {$where_clause};";

      try {
        $statement = Database::PDO()->prepare($sql);
        $statement->execute($bind_params);
      } catch (\PDOException $error) {
        throw $error;
        return false;
      }
    }

    return true;
  }

  /**
   * checks the current model instance if it already exist in the database 
   * 
   * this function checks the existence of the current model instance
   * by performing a database query based on the current set attributes
   * 
   * @return bool true if record exists, false otherwise
   * 
   * @throws \PDOException if the database query fails
   */
  final public function record_exists(): bool
  {
    if ($this->EXISTING_RECORD) return true;
    if (empty($this->ATTRIBUTES)) return false;

    $filters = [];
    foreach ($this->ATTRIBUTES as $attribute) {
      $filters[$attribute] = $this->$attribute;
    }

    $primary_key = static::primary_key();
    if (!empty($primary_key)) {
      if (is_string($primary_key)) {
        if (!isset($filters[$primary_key])) {
          $filters[$primary_key] = $this->$primary_key ?? null;
        }
      } elseif (is_array($primary_key)) {
        foreach ($primary_key as $key) {
          if (isset($filters[$key])) continue;
          $filters[$key] = $this->$key ?? null;
        }
      }
    }

    [$where_clause, $bind_params] = QueryBuilder::build_where($filters);

    $table = static::table_name();
    $sql = "SELECT 1 FROM {$table} {$where_clause};";

    try {
      $statement = Database::PDO()->prepare($sql);
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

  final public static function all(array $return = []): array
  {
    $returned_columns = $return;
    $model = new static();

    $columns_clause = QueryBuilder::build_columns($model, $returned_columns);
    if (empty($columns_clause)) throw new Error("No valid columns.");

    $table = $model::table_name();
    $sql = "SELECT {$columns_clause} FROM {$table};";

    try {
      $statement = Database::PDO()->prepare($sql);
      $statement->execute();
      $records = $statement->fetchAll(\PDO::FETCH_ASSOC);
      return $records;
    } catch (\PDOException $error) {
      throw $error;
    }
  }

  /**
   * finds a record in the database that matches the specified conditions
   * 
   * @param array $columns an associative array of conditions where the keys are column names and the values are the values to match.
   *                       example: ['email' => 'example@example.com', 'status' => 'active']
   * 
   * @param array $return an optional array of column names to be returned in the query. If empty, all columns will be returned
   *                      example: ['id', 'email']
   * 
   * @throws \Error if the query cannot determine valid columns for the SELECT clause
   * @throws \PDOException if there is an issue with the database query execution
   * 
   * @return static|null returns an instance of the calling class with the matching record's data, or null if no record is found
   */
  final public static function find_by(array $columns, array $return = []): ?static
  {
    $conditions = $columns;
    $returned_columns = $return;
    $model = new static();

    $columns_clause = QueryBuilder::build_columns($model, $returned_columns);
    if (empty($columns_clause)) throw new Error("No valid columns.");

    [$where_clause, $bind_params] = QueryBuilder::build_where($conditions);

    $table = $model::table_name();
    $sql = "SELECT {$columns_clause} FROM {$table} {$where_clause};";

    try {
      $statement = Database::PDO()->prepare($sql);
      $statement->execute($bind_params);
      $result = $statement->fetch(\PDO::FETCH_ASSOC);

      if (empty($result) || !is_array($result)) return null;
      return new static($result);
    } catch (\PDOException $error) {
      throw $error;
    }
  }

  final public static function fetch_by(array $filter, array $return = [], array $range = [], array $sort = []): array
  {
    $model = new static();

    $columns_clause = QueryBuilder::build_columns($model, $return);
    if (empty($columns_clause)) throw new Error("No valid columns.");

    [$where_clause, $where_bind_params] = QueryBuilder::build_where($filter, $range);
    $order_clause = QueryBuilder::build_order($model, $sort);

    $table = $model::table_name();
    $sql = "SELECT {$columns_clause} FROM {$table} {$where_clause} {$order_clause}";

    try {
      $statement = Database::PDO()->prepare($sql);
      $statement->execute($where_bind_params);
      $result = $statement->fetchAll(\PDO::FETCH_ASSOC);

      return $result;
    } catch (\PDOException $error) {
      throw $error;
    }
  }

  final public static function count(array $filters = [], array $range = []): ?int
  {
    [$where_clause, $bind_params] = QueryBuilder::build_where($filters, $range);

    $table = static::table_name();
    $sql = "SELECT count(*) as count FROM {$table} {$where_clause}";

    try {
      $statement = Database::PDO()->prepare($sql);
      $statement->execute($bind_params);

      return (int) $statement->fetch(\PDO::FETCH_ASSOC)['count'];
    } catch (\PDOException $error) {
      throw $error;
    }
  }

  final public static function paginate(array $filters = [], array $range = [], array $sort = [], int $page = 1, int $limit = 10): array
  {
    $offset = ($page - 1) * $limit;
    $model = new static();

    [$where_clause, $where_bind_params] = QueryBuilder::build_where($filters, $range);
    $order_clause = QueryBuilder::build_order($model, $sort);
    $limit_clause = QueryBuilder::build_limit($limit);
    $offset_clause = QueryBuilder::build_offset($offset);

    $table = $model::table_name();
    $sql = "SELECT * FROM {$table} {$where_clause} {$order_clause} {$limit_clause} {$offset_clause}";

    try {
      $statement = Database::PDO()->prepare($sql);
      $statement->execute($where_bind_params);

      return $statement->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\PDOException $error) {
      throw $error;
    }
  }

  /******************************************/
  /*         helper methods below           */
  /******************************************/

  final public static function sort(array $items, array $by): array
  {
    usort($items, function ($a, $b) use ($by) {
      foreach ($by as $param) {
        $field = $param[0] ?? '';
        $order = strtolower($param[1] ?? 'asc');

        if (empty(trim($field)) || empty(trim($order))) continue;

        $value_a = $a[$field] ?? null;
        $value_b = $b[$field] ?? null;

        if (is_numeric($value_a) && is_numeric($value_b)) {
          $compare_result = $value_a <=> $value_b;
        } else {
          $compare_result = strcmp((string) $value_a, (string) $value_b);
        }

        if ($order === 'desc') {
          $compare_result = -$compare_result;
        }

        if ($compare_result !== 0) return $compare_result;
      }

      return 0;
    });

    return $items;
  }

  public static function table_name(): string
  {
    $called_class = get_called_class();
    $stored_table_name = $called_class::$TABLE ?? null;

    if (property_exists($called_class, 'TABLE') && !empty($stored_table_name)) return $stored_table_name;
    $table_name = substr($called_class, strrpos($called_class, '\\') + 1);
    $table_name = static::pluralize($table_name);

    return $table_name;
  }

  private static function pluralize(string $word): string
  {
    // expand 
    $last_letter = strtolower($word[strlen($word) - 1]);
    if ($last_letter == 'y') return substr($word, 0, -1) . 'ies';
    return $word . 's';
  }

  private function setup_callback(string $callback_name): void
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

  private function run_callback(string $callback_name): void
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

  private function updated_attributes(): array
  {
    $updated_attributes = [];
    foreach ($this->ATTRIBUTES as $attribute) {
      if ($this->$attribute === $this->OLD[$attribute]) continue;
      $updated_attributes[$attribute] = $this->$attribute;
    }

    return $updated_attributes;
  }

  public function primary_key(): false|array|string
  {
    $called_class = get_called_class();
    $pk = $called_class::$PRIMARY_KEY ?? 'id';

    // if (!property_exists($called_class, $pk)) throw new Error("{$called_class}: Primary key not defined.");
    if (!(is_bool($pk) && !$pk) && !is_array($pk) && !is_string($pk)) throw new Error("{$called_class}: Invalid primary key(s).");
    return $pk;
  }

  protected function is_attribute_changed(string $attribute): bool
  {
    if (!array_key_exists($attribute, $this->OLD)) return true;
    return $this->$attribute !== $this->OLD[$attribute];
  }

  private function reload_conditions(): array
  {
    $conditions = [];
    foreach ($this->ATTRIBUTES as $attribute) {
      if (!isset($this->$attribute) || in_array($attribute, static::$DB_ON_UPDATE_COLUMN)) continue;
      $conditions[$attribute] = $this->$attribute;
    }
    return $conditions;
  }

  private function reload()
  {
    $primary_key = static::primary_key();
    $primary_key_value = null;
    if (is_string($primary_key)) $primary_key_value = $this->$primary_key ?? null;
    $updated_row = null;

    if (is_bool($primary_key) && !$primary_key) {
      // primary key declared as false
      if (static::count($this->reload_conditions()) > 1) {
        $updated_row = null;
      } else {
        $updated_row = static::find_by($this->reload_conditions());
      }
    } elseif (is_string($primary_key)) {
      // single primary key
      if (!empty($primary_key_value)) {
        $updated_row = static::find_by([$primary_key => $primary_key_value]);
      } else {
        // try fetching last inserted ID if the primary key value is empty
        $primary_key_value = Database::PDO()->lastInsertId();
        if (!empty($primary_key_value)) {
          $updated_row = static::find_by([$primary_key => $primary_key_value]);
        } else {
          if (static::count($this->reload_conditions()) > 1) {
            $updated_row = null;
          } else {
            $updated_row = static::find_by($this->reload_conditions());
          }
        }
      }
    } elseif (is_array($primary_key)) {
      // composite primary keys
      $conditions = [];
      foreach ($primary_key as $key) {
        if (isset($this->$key) && !empty($this->$key)) {
          $conditions[$key] = $this->$key;
        }
      }
      if (!empty($conditions)) {
        $updated_row = static::find_by($conditions);
      } else {
        if (static::count($this->reload_conditions()) > 1) {
          $updated_row = null;
        } else {
          $updated_row = static::find_by($this->reload_conditions());
        }
      }
    }

    if (empty($updated_row)) throw new Error("Oops. Unexpected error occured when retrieving entry.");

    foreach ($updated_row as $key => $value) {
      if (!property_exists($this, $key) || !in_array($key, $updated_row->ATTRIBUTES)) continue;
      $this->assign_attribute($key, $value);

      if (isset($this->OLD[$key]) && $value === $this->OLD[$key]) continue;
      $this->OLD[$key] = $value;
    }

    if (!empty($updated_row->EXISTING_RECORD)) $this->EXISTING_RECORD = $updated_row->EXISTING_RECORD ?? false;
  }
}
