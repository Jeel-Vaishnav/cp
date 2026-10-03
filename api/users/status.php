<?php
/**
 * Campus Connect - Toggle Student Warning / Suspension Status Endpoint
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$user = requireAuth(['admin']);
$data = getRequestData();

$gr = trim($data['gr_no'] ?? $data['grNo'] ?? '');
$field = trim($data['field'] ?? ''); // 'warned' or 'suspended'

if (empty($gr) || !in_array($field, ['warned', 'suspended'], true)) {
    jsonError('Valid G.R. number and field (warned/suspended) are required', 400);
}

$stmt = $pdo->prepare("SELECT `id`, `name`, `warned`, `suspended` FROM `users` WHERE `gr_no` = ? LIMIT 1");
$stmt->execute([$gr]);
$target = $stmt->fetch();

if (!$target) {
    jsonError('Student not found', 404);
}

$newVal = empty($target[$field]) ? 1 : 0;

$updateStmt = $pdo->prepare("UPDATE `users` SET `{$field}` = ? WHERE `id` = ?");
$updateStmt->execute([$newVal, $target['id']]);

$statusLabel = $newVal ? ($field === 'warned' ? 'Official Warning Issued' : 'Account Suspended') : ($field === 'warned' ? 'Warning Revoked' : 'Suspension Lifted');

jsonResponse([
    'success' => true,
    'message' => "Student {$target['name']} ({$gr}): {$statusLabel}",
    'field'   => $field,
    'value'   => (bool)$newVal
]);
