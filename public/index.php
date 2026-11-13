<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$router = new Router();
require BASE_PATH . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
