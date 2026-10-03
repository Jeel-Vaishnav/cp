<?php
/**
 * Campus Connect - Cloud Database Connection Diagnostic Test
 * Run this in browser (http://localhost/.../api/test_db.php) or on cloud host
 */

header('Content-Type: application/json; charset=utf-8');

$diag = [
    'time' => date('Y-m-d H:i:s T'),
    'php_version' => PHP_VERSION,
    'pdo_drivers' => PDO::getAvailableDrivers(),
    'env' => [
        'DB_HOST' => getenv('DB_HOST') ? getenv('DB_HOST') : '(default: localhost)',
        'DB_PORT' => getenv('DB_PORT') ? getenv('DB_PORT') : '(default: 3306)',
        'DB_NAME' => getenv('DB_NAME') ? getenv('DB_NAME') : '(default: campus_connect)',
        'DB_USER' => getenv('DB_USER') ? getenv('DB_USER') : '(default: root)',
        'DB_PASS_SET' => getenv('DB_PASS') !== false && strlen(getenv('DB_PASS')) > 0 ? 'yes' : 'no/empty',
        'DB_SSL' => getenv('DB_SSL') ?: 'not set',
    ],
    'connection' => 'pending'
];

try {
    require_once __DIR__ . '/config/database.php';
    if (isset($pdo)) {
        $stmt = $pdo->query('SELECT DATABASE() AS current_db, VERSION() AS db_version');
        $row = $stmt->fetch();
        $diag['connection'] = 'SUCCESS';
        $diag['current_database'] = $row['current_db'] ?? 'unknown';
        $diag['db_version'] = $row['db_version'] ?? 'unknown';

        // Count tables
        $tablesStmt = $pdo->query("SHOW TABLES");
        $diag['tables_found'] = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Throwable $e) {
    $diag['connection'] = 'FAILED';
    $diag['error'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
