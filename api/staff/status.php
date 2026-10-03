<?php
/**
 * Campus Connect - Toggle Staff Active/Inactive Status Endpoint
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$user = requireAuth(['admin']);
$data = getRequestData();

$techCode = trim($data['id'] ?? $data['technician_code'] ?? '');

if (empty($techCode)) {
    jsonError('Technician ID is required', 400);
}

$stmt = $pdo->prepare("SELECT `id`, `name`, `active` FROM `technicians` WHERE `technician_code` = ? LIMIT 1");
$stmt->execute([$techCode]);
$tech = $stmt->fetch();

if (!$tech) {
    jsonError('Technician not found', 404);
}

$newActive = empty($tech['active']) ? 1 : 0;

$updateStmt = $pdo->prepare("UPDATE `technicians` SET `active` = ? WHERE `id` = ?");
$updateStmt->execute([$newActive, $tech['id']]);

jsonResponse([
    'success' => true,
    'message' => "Technician {$tech['name']} ({$techCode}) " . ($newActive ? 'activated' : 'deactivated'),
    'active'  => (bool)$newActive
]);
