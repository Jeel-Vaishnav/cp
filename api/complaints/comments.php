<?php
/**
 * Campus Connect - Complaint Comments API Endpoint
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = requireAuth();
    $data = getRequestData();

    $complaintId = trim($data['complaint_id'] ?? $data['id'] ?? '');
    $comment = trim($data['comment'] ?? '');

    if (empty($complaintId) || empty($comment)) {
        jsonError('Complaint ID and comment text are required', 400);
    }

    $stmt = $pdo->prepare("SELECT `id`, `complaint_code` FROM `complaints` WHERE `complaint_code` = ? OR `id` = ? LIMIT 1");
    $stmt->execute([$complaintId, is_numeric($complaintId) ? (int)$complaintId : 0]);
    $c = $stmt->fetch();

    if (!$c) {
        jsonError('Complaint not found', 404);
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO `complaint_comments` (`complaint_id`, `user_id`, `user_name`, `user_role`, `comment`)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insertStmt->execute([
        $c['id'],
        $user['id'] ?? null,
        $user['name'] ?? 'User',
        $user['role'] ?? 'user',
        $comment
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Comment posted successfully',
        'comment' => [
            'id'         => (int)$pdo->lastInsertId(),
            'user_name'  => $user['name'] ?? 'User',
            'user_role'  => $user['role'] ?? 'user',
            'comment'    => $comment,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ], 201);

} else {
    // GET comments
    $complaintId = trim($_GET['complaint_id'] ?? $_GET['id'] ?? '');
    if (empty($complaintId)) {
        jsonError('Complaint ID is required', 400);
    }

    $stmt = $pdo->prepare("SELECT `id` FROM `complaints` WHERE `complaint_code` = ? OR `id` = ? LIMIT 1");
    $stmt->execute([$complaintId, is_numeric($complaintId) ? (int)$complaintId : 0]);
    $c = $stmt->fetch();

    if (!$c) {
        jsonError('Complaint not found', 404);
    }

    $commStmt = $pdo->prepare("
        SELECT `id`, `user_name`, `user_role`, `comment`, `created_at`
        FROM `complaint_comments`
        WHERE `complaint_id` = ?
        ORDER BY `id` ASC
    ");
    $commStmt->execute([$c['id']]);
    $comments = $commStmt->fetchAll();

    jsonResponse([
        'success'  => true,
        'comments' => $comments
    ]);
}
