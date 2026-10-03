<?php
/**
 * Campus Connect - Create or Update Staff Endpoint
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$user = requireAuth(['admin']);
$data = getRequestData();

$editId = trim($data['id'] ?? $data['editId'] ?? $data['staffEditId'] ?? '');
$name   = trim($data['name'] ?? $data['staffName'] ?? '');
$dept   = trim($data['dept'] ?? $data['department'] ?? $data['staffDept'] ?? '');
$exp    = (int)($data['experience'] ?? $data['exp'] ?? $data['staffExp'] ?? 0);
$pass   = trim($data['password'] ?? $data['pass'] ?? $data['staffPass'] ?? '');

if (empty($name) || empty($dept)) {
    jsonError('Name and Department are required', 400);
}

if (!empty($editId)) {
    // Edit existing technician
    $stmt = $pdo->prepare("SELECT `id`, `technician_code` FROM `technicians` WHERE `technician_code` = ? LIMIT 1");
    $stmt->execute([$editId]);
    $tech = $stmt->fetch();

    if (!$tech) {
        jsonError('Technician not found', 404);
    }

    $sql = "UPDATE `technicians` SET `name` = ?, `department` = ?, `experience` = ?";
    $params = [$name, $dept, $exp];

    if (!empty($pass)) {
        $sql .= ", `password_hash` = ?";
        $params[] = password_hash($pass, PASSWORD_DEFAULT);
    }

    $sql .= " WHERE `id` = ?";
    $params[] = $tech['id'];

    $updateStmt = $pdo->prepare($sql);
    $updateStmt->execute($params);

    jsonResponse([
        'success' => true,
        'message' => 'Technician record modified',
        'id'      => $editId
    ]);

} else {
    // Generate new TECH code
    $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM `technicians`");
    $count = (int)$countStmt->fetch()['total'] + 1;
    $newCode = sprintf('TECH-%02d', $count);

    // Ensure uniqueness
    $checkStmt = $pdo->prepare("SELECT `id` FROM `technicians` WHERE `technician_code` = ? LIMIT 1");
    $checkStmt->execute([$newCode]);
    if ($checkStmt->fetch()) {
        $newCode = 'TECH-' . rand(10, 99);
    }

    $passwordHash = password_hash(!empty($pass) ? $pass : 'password', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO `technicians` (`technician_code`, `name`, `department`, `experience`, `rating`, `active`, `password_hash`)
        VALUES (?, ?, ?, ?, 5.00, 1, ?)
    ");
    $stmt->execute([$newCode, $name, $dept, $exp, $passwordHash]);

    jsonResponse([
        'success' => true,
        'message' => 'New technician registered',
        'id'      => $newCode
    ], 201);
}
