<?php

namespace Core\Components;

use Core\Base;

class Database extends Base
{
  public static $PDO;

  public static function connect()
  {
    if (!empty(self::$PDO)) return self::$PDO;

    $db_config = self::config('database');

    $db_host = $db_config['DB_HOST'] ?? '';
    $db_name = $db_config['DB_NAME'] ?? '';
    $db_username = $db_config['DB_USERNAME'] ?? '';
    $db_password = $db_config['DB_PASSWORD'] ?? '';

    try {
      $pdo = new \PDO("mysql:host=" . $db_host . ";dbname=" . $db_name, $db_username, $db_password);
      $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

      self::$PDO = $pdo;
      return self::$PDO;
    } catch (\PDOException $error) {
      throw $error;
    }
  }
}
