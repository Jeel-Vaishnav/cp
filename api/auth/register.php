<?php
/**
 * Campus Connect - Student Registration Endpoint
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$data = getRequestData();
$name = trim($data['name'] ?? $data['regName'] ?? '');
$gr   = trim($data['gr_no'] ?? $data['gr'] ?? $data['regGr'] ?? '');
$dept = trim($data['department'] ?? $data['dept'] ?? $data['regDept'] ?? 'Computer Department');
$pass = $data['password'] ?? $data['pass'] ?? $data['regPass'] ?? '';

if (empty($name) || empty($gr) || empty($pass)) {
    jsonError('Name, Enrollment/GR number, and password are required', 400);
}

// Check if GR number already exists
$stmt = $pdo->prepare("SELECT `id` FROM `users` WHERE `gr_no` = ? LIMIT 1");
$stmt->execute([$gr]);
if ($stmt->fetch()) {
    jsonError('Enrollment Number registered already', 409);
}

$passwordHash = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO `users` (`gr_no`, `name`, `password_hash`, `department`, `avatar`, `warned`, `suspended`)
    VALUES (?, ?, ?, ?, NULL, 0, 0)
");
$stmt->execute([$gr, $name, $passwordHash, $dept]);

jsonResponse([
    'success' => true,
    'message' => 'Student account generated! Log in below.',
    'gr_no'   => $gr
], 201);
