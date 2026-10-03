<?php
/**
 * Campus Connect - Health Check & Cloud Status Endpoint
 * Used by Render, Railway, or monitoring tools to verify app and DB health
 */

header('Content-Type: application/json; charset=utf-8');

$status = [
    'status'    => 'ok',
    'timestamp' => date('c'),
    'php'       => PHP_VERSION,
    'database'  => 'unknown'
];

try {
    require_once __DIR__ . '/config/database.php';
    if (isset($pdo)) {
        $pdo->query('SELECT 1');
        $status['database'] = 'connected';
    }
} catch (Throwable $e) {
    $status['status'] = 'degraded';
    $status['database'] = 'error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
