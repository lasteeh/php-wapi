<?php

require_once(__DIR__ . "/../vendors/autoloader/Autoloader.php");
Autoloader::register();

use Core\App;

$app = new App;
$app->run();
