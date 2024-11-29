<?php

namespace Core\Components;

use Error;

class QueryBuilder
{
  public static function build_select(array $columns): string
  {
    $return_columns = $columns;
    if (empty($return_columns)) return "*";

    $valid_columns = [];
    foreach ($return_columns as $column) {
      if (!is_string($column)) continue;
      $valid_columns[] = $column;
    }

    if (empty($valid_columns)) throw new Error("No valid columns.");

    return implode($valid_columns);
  }

  public static function build_where(array $filters, array $range = [])
  {
    if (empty($filters)) return ["", []];

    $where_clause = "";
    $where_conditions = [];
    $bind_params = [];

    foreach ($filters as $column => $value) {
      if (is_array($value)) {
        $placeholders = implode(", ", array_fill(0, count($value), "?"));
        $where_conditions[] = "{$column} IN ({$placeholders})";
        $bind_params = array_merge($bind_params, $value);
      } else {
        $where_conditions[] = "{$column} = ?";
        $bind_params[] = $value;
      }
    }

    foreach ($range as $column => $values) {
      if (is_array($values) && count($values) === 2) {
        $where_conditions[] = "{$column} BETWEEN ? AND ?";
        $bind_params[] = $values[0];
        $bind_params[] = $values[1];
      }
    }

    if (empty($where_conditions)) return [$where_clause, []];
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);

    return [$where_clause, $bind_params];
  }
}
