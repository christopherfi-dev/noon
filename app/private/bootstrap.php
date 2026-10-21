<?php
declare(strict_types=1);

/*
 * Private bootstrap. Lives outside the web root. Loads the autoloader and
 * the private configuration; nothing here is selectable by a client.
 */

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo '{"error_code":"NOT_INSTALLED","message":"The service is not installed correctly."}';
    exit;
}
require $autoload;

$configFile = getenv('ALGEBRA_CONFIG') ?: __DIR__ . '/config.php';
$settings = is_file($configFile) ? require $configFile : [];
if (!is_array($settings)) {
    $settings = [];
}

return AlgebraEngine\Config::fromArray($settings, __DIR__);
