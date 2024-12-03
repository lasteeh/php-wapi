<?php

require_once(__DIR__ . "/../vendors/autoloader/Autoloader.php");
Autoloader::register();

use Core\Blacksmith;

$blacksmith = new Blacksmith;
$blacksmith->forge();
