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

    return implode(", ", $valid_columns);
  }

  public static function build_where(array $filters, array $range = [])
  {
    if (empty($filters)) return ["", []];

    $where_clause = "";
    $where_conditions = [];
    $bind_params = [];

    foreach ($filters as $column => $value) {
      if (is_array($value)) {
        $placeholders = [];
        foreach ($value as $index => $val) {
          $placeholder = ":{$column}_{$index}";
          $placeholders[] = $placeholder;
          $bind_params[$placeholder] = $val;
        }
        $where_conditions[] = "{$column} IN (" . implode(", ", $placeholders) . ")";
      } else {
        $placeholder = ":{$column}";
        $where_conditions[] = "{$column} = {$placeholder}";
        $bind_params[$placeholder] = $value;
      }
    }

    foreach ($range as $column => $values) {
      if (is_array($values) && count($values) === 2) {
        $placeholder_start = "{$column}_start";
        $placeholder_end = "{$column}_end";
        $where_conditions[] = "{$column} BETWEEN {$placeholder_start} AND {$placeholder_end}";
        $bind_params[$placeholder_start] = $values[0];
        $bind_params[$placeholder_end] = $values[1];
      }
    }

    if (empty($where_conditions)) return [$where_clause, []];

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    return [$where_clause, $bind_params];
  }
}
