<?php
/**
 * Campus Connect - List Notifications Endpoint
 */

require_once __DIR__ . '/../common.php';

$user = getCurrentUser();
if (!$user) {
    jsonResponse(['notifications' => []]);
}

$role = $user['role'] ?? 'guest';

$sql = "SELECT `id`, `notif_id`, `for_gr`, `for_dept`, `for_tech`, `text`, `notif_time`, `is_read` FROM `notifications` WHERE 1=1";
$params = [];

if ($role === 'student') {
    $sql .= " AND (`for_gr` = ? OR `for_gr` IS NULL)";
    $params[] = $user['grNo'] ?? '';
} elseif ($role === 'faculty') {
    $sql .= " AND (`for_dept` = ? OR `for_dept` IS NULL)";
    $params[] = $user['dept'] ?? '';
} elseif ($role === 'technician') {
    $sql .= " AND (`for_tech` = ? OR `for_tech` IS NULL)";
    $params[] = $user['techId'] ?? '';
}

$sql .= " ORDER BY `id` DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$notifs = [];
foreach ($rows as $r) {
    $notifs[] = [
        'id'      => $r['notif_id'] ?: ('N' . $r['id']),
        'forGr'   => $r['for_gr'],
        'forDept' => $r['for_dept'],
        'forTech' => $r['for_tech'],
        'text'    => $r['text'],
        'time'    => $r['notif_time'],
        'read'    => (bool)$r['is_read']
    ];
}

jsonResponse([
    'success'       => true,
    'notifications' => $notifs
]);
