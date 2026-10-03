<?php
/**
 * Campus Connect - Mark Notifications as Read Endpoint
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$user = requireAuth();
$role = $user['role'] ?? 'guest';

if ($role === 'student') {
    $stmt = $pdo->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `for_gr` = ? OR `for_gr` IS NULL");
    $stmt->execute([$user['grNo'] ?? '']);
} elseif ($role === 'faculty') {
    $stmt = $pdo->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `for_dept` = ? OR `for_dept` IS NULL");
    $stmt->execute([$user['dept'] ?? '']);
} elseif ($role === 'technician') {
    $stmt = $pdo->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `for_tech` = ? OR `for_tech` IS NULL");
    $stmt->execute([$user['techId'] ?? '']);
} else {
    // Admin marks all as read
    $pdo->exec("UPDATE `notifications` SET `is_read` = 1");
}

jsonResponse([
    'success' => true,
    'message' => 'Notifications marked read.'
]);
