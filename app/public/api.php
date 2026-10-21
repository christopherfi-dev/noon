<?php
declare(strict_types=1);

// Public entry point. The private folder is outside the web root; adjust
// ALGEBRA_PRIVATE_DIR only if your hosting layout differs (see DEPLOYMENT.md).
const ALGEBRA_PRIVATE_DIR = __DIR__ . '/../../algebra-private';

$privateDir = getenv('ALGEBRA_PRIVATE_DIR') ?: ALGEBRA_PRIVATE_DIR;
if (!is_file($privateDir . '/bootstrap.php')) {
    $privateDir = __DIR__ . '/../private'; // repository layout for local development
}
ini_set('display_errors', '0');

try {
    $config = require $privateDir . '/bootstrap.php';
    $app = new AlgebraEngine\App($config);
    $response = (new AlgebraEngine\Http\Api($app))->handle(AlgebraEngine\Http\Request::fromGlobals());
} catch (AlgebraEngine\Storage\StorageUnavailable $e) {
    $response = AlgebraEngine\Http\Response::error(503, 'STORAGE_UNAVAILABLE', 'The service is temporarily unavailable.');
} catch (Throwable $e) {
    error_log('algebra-engine bootstrap: ' . get_class($e));
    $response = AlgebraEngine\Http\Response::error(503, 'SERVICE_UNAVAILABLE', 'The service is temporarily unavailable.');
}
$response->send();
