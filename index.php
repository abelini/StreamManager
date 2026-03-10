<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__);

require APP_ROOT . '/autoload.php';

use Stream\Controller\StreamController;
use Stream\Logging\Logger;
use Stream\Repository\HitRepository;
use Stream\Security\RateLimiter;
use Stream\Storage\Database;
use Stream\Storage\GeoLocator;

$config  = require APP_ROOT . '/config.php';
$db      = Database::getInstance($config['db']['path']);
$logger  = new Logger($config['log']['path'], $config['log']['level']);
$hits    = new HitRepository($db);
$limiter = new RateLimiter($db, $config['rate_limit']['max_per_minute']);
$geo     = new GeoLocator($db);

(new StreamController($config, $hits, $limiter, $logger, $geo))->handle();