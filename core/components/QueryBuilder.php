<?php

namespace Core\Components;

use Error;

class QueryBuilder
{
  public static function build_columns(ActiveRecord $model, array $columns): string
  {
    if (empty($columns)) return "*";

    $valid_columns = [];
    foreach ($columns as $column) {
      if (!is_string($column) || !property_exists($model, $column)) continue;
      $valid_columns[] = $column;
    }

    if (empty($valid_columns)) throw new Error("No valid columns.");

    return implode(", ", $valid_columns);
  }

  public static function build_where(array $filters, array $range = []): array
  {
    $where_clause = "";
    $where_conditions = [];
    $bind_params = [];

    foreach ($filters as $column => $value) {
      if ($value === NOT_NULL) {
        $where_conditions[] = "{$column} IS NOT NULL";
        continue;
      } elseif (is_array($value) && !empty($value)) {
        $placeholders = [];
        $has_null = false;
        $has_not_null = false;
        $placeholder_index = 0;

        foreach ($value as $val) {
          if ($val === NOT_NULL) {
            $has_not_null = true;
            continue;
          }

          if (is_null($val)) {
            $has_null = true;
            continue;
          }

          if (!is_scalar($val)) continue;
          if (is_bool($val)) $val = $val ? 1 : 0;

          $placeholder = ":__existing_{$column}_" . $placeholder_index++;
          $placeholders[] = $placeholder;
          $bind_params[$placeholder] = $val;
        }

        $conditions = [];
        if (!empty($placeholders)) $conditions[] = "{$column} IN (" . implode(", ", $placeholders) . ")";
        if ($has_not_null) $conditions[] = "{$column} IS NOT NULL";
        if ($has_null) $conditions[] = "{$column} IS NULL";

        if (!empty($conditions)) {
          if (count($conditions) > 1) {
            $where_conditions[] = "(" . implode(" OR ", $conditions) . ")";
          } else {
            $where_conditions[] = $conditions[0];
          }
        }
      } elseif (is_null($value)) {
        $where_conditions[] = "{$column} IS NULL";
      } elseif (is_bool($value)) {
        $where_conditions[] = "{$column} = " . ($value ? 1 : 0);
      } elseif (is_scalar($value)) {
        $placeholder = ":__existing_{$column}";
        $where_conditions[] = "{$column} = {$placeholder}";
        $bind_params[$placeholder] = $value;
      }
    }

    foreach ($range as $column => $values) {
      if (is_array($values) && count($values) === 2) {
        $placeholder_start = ":__existing_{$column}_start";
        $placeholder_end = ":__existing_{$column}_end";
        $where_conditions[] = "{$column} BETWEEN {$placeholder_start} AND {$placeholder_end}";
        $bind_params[$placeholder_start] = $values[0];
        $bind_params[$placeholder_end] = $values[1];
      }
    }

    if (empty($where_conditions)) return [$where_clause, []];

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    return [$where_clause, $bind_params];
  }

  public static function build_set(array $data): array
  {
    $set_clause = '';
    $set_conditions = [];
    $bind_params = [];

    foreach ($data as $column => $value) {
      if (!is_string($column)) continue;

      $placeholder = ":__updated_{$column}";
      $set_conditions[] = "{$column} = {$placeholder}";
      $bind_params[$placeholder] = $value;
    }

    if (empty($set_conditions)) return [$set_clause, []];

    $set_clause = "SET " . implode(", ", $set_conditions);
    return [$set_clause, $bind_params];
  }

  public static function build_values(array $data): array
  {
    $values_clause = "";
    $placeholders = [];
    $bind_params = [];

    foreach ($data as $column => $value) {
      if (!is_string($column)) continue;
      $placeholder = ":__value_{$column}";
      $placeholders[] = $placeholder;
      $bind_params[$placeholder] = $value;
    }

    if (empty($placeholders)) return [$values_clause, []];

    $values_clause = "VALUES (" . implode(", ", $placeholders) . ")";
    return [$values_clause, $bind_params];
  }

  public static function build_order(ActiveRecord $model, array $sort): string
  {
    $order_by_clause = '';
    if (empty($sort)) return $order_by_clause;

    $valid_sort_columns = [];
    foreach ($sort as $column => $direction) {
      if (!is_string($column) || !property_exists($model, $column)) continue;

      $valid_sort_columns[] = "{$column} " . ($direction === 'desc' ? 'DESC' : 'ASC');
    }

    $order_by_clause = "ORDER BY " . implode(", ", $valid_sort_columns);
    return $order_by_clause;
  }

  public static function build_limit(int $limit): string
  {
    $limit = max(1, $limit);
    return "LIMIT {$limit}";
  }

  public static function build_offset(int $offset): string
  {
    $offset = max(0, $offset);
    return "OFFSET {$offset}";
  }

  public static function build_batch_set(array $data, array $columns): array
  {
    if (empty($data) || empty($columns)) return ["", [], []];

    $set_clause = "";
    $cases = [];
    $params = [];
    $set_ids = [];

    $fields = array_diff(array_keys(reset($data)), $columns);

    foreach ($fields as $field) {
      $cases[$field] = "{$field} = CASE";
      foreach ($data as $index => $row) {

        $when_conditions = [];
        $composite_id = [];
        foreach ($columns as $col) {
          if (!isset($row[$col])) throw new Error("Entry must have the column {$col}");

          $when_param = ":{$col}_when_{$field}_{$index}";
          $when_conditions[] = "{$col} = {$when_param}";
          $params[$when_param] = $row[$col];

          $composite_id[$col] = $row[$col];
        }

        $cases[$field] .= " WHEN " . implode(" AND ", $when_conditions);

        $then_param = ":{$field}_{$index}";
        $cases[$field] .= " THEN {$then_param}";
        $params[$then_param] = $row[$field];

        if (!in_array($composite_id, $set_ids)) {
          $set_ids[] = $composite_id;
        }
      }
      $cases[$field] .= " ELSE {$field} END";
    }

    if (!empty($cases)) $set_clause = "SET " . implode(", ", $cases);

    return [$set_clause, $params, $set_ids];
  }

  public static function build_batch_where(array $set_ids): array
  {
    if (empty($set_ids)) return ["", []];

    $in_clause = "";
    $params = [];
    $tuples = [];

    $columns = array_keys(reset($set_ids));

    foreach ($set_ids as $index => $set_id) {

      $placeholders = [];
      foreach ($columns as $column) {
        $placeholder = ":{$column}_in_{$index}";
        $placeholders[] = $placeholder;
        $params[$placeholder] = $set_id[$column];
      }

      $tuples[] = "(" . implode(", ", $placeholders) . ")";
    }

    $columns_str = implode(", ", $columns);
    if (!empty($tuples)) $in_clause = "WHERE ($columns_str) IN (" . implode(", ", $tuples) . ")";

    return [$in_clause, $params];
  }
}
