<?php

namespace Core\Components;

use Error;

class QueryBuilder
{
  public static function build_columns(array $columns): string
  {
    if (empty($columns)) return "*";

    $valid_columns = [];
    foreach ($columns as $column) {
      if (!is_string($column)) continue;
      $valid_columns[] = $column;
    }

    if (empty($valid_columns)) throw new Error("No valid columns.");

    return implode(", ", $valid_columns);
  }

  public static function build_where(array $filters, array $range = []): array
  {
    if (empty($filters)) return ["", []];

    $where_clause = "";
    $where_conditions = [];
    $bind_params = [];

    foreach ($filters as $column => $value) {
      if (is_array($value) && !empty($value)) {
        $placeholders = [];
        foreach ($value as $index => $val) {
          $placeholder = ":__existing_{$column}_{$index}";
          $placeholders[] = $placeholder;
          $bind_params[$placeholder] = $val;
        }
        $where_conditions[] = "{$column} IN (" . implode(", ", $placeholders) . ")";
      } elseif (is_null($value)) {
        $where_conditions[] = "{$column} IS NULL";
      } elseif (is_bool($value)) {
        $where_conditions[] = "{$column} = " . ($value ? 1 : 0);
      } else {
        $placeholder = ":__existing_{$column}";
        $where_conditions[] = "{$column} = {$placeholder}";
        $bind_params[$placeholder] = $value;
      }
    }

    foreach ($range as $column => $values) {
      if (is_array($values) && count($values) === 2) {
        $placeholder_start = "__existing_{$column}_start";
        $placeholder_end = "__existing_{$column}_end";
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
}
