<?php
/**
 * Campus Connect - Get Single Complaint API Endpoint
 */

require_once __DIR__ . '/../common.php';

$code = trim($_GET['id'] ?? $_GET['code'] ?? '');

if (empty($code)) {
    jsonError('Complaint ID or Code is required', 400);
}

$stmt = $pdo->prepare("SELECT * FROM `complaints` WHERE `complaint_code` = ? OR `id` = ? LIMIT 1");
$stmt->execute([$code, is_numeric($code) ? (int)$code : 0]);
$complaint = $stmt->fetch();

if (!$complaint) {
    jsonError('Complaint not found', 404);
}

// Fetch logs
$logStmt = $pdo->prepare("
    SELECT `status` AS `s`, `note`, `performed_by_name` AS `by`, `log_time` AS `time`
    FROM `complaint_logs`
    WHERE `complaint_id` = ?
    ORDER BY `id` ASC
");
$logStmt->execute([$complaint['id']]);
$logs = $logStmt->fetchAll();

// Fetch comments
$commStmt = $pdo->prepare("
    SELECT `id`, `user_name`, `user_role`, `comment`, `created_at`
    FROM `complaint_comments`
    WHERE `complaint_id` = ?
    ORDER BY `id` ASC
");
$commStmt->execute([$complaint['id']]);
$comments = $commStmt->fetchAll();

// Fetch feedback
$fbStmt = $pdo->prepare("
    SELECT `rating`, `comment`, `further_action_requested`, `created_at`
    FROM `complaint_feedback`
    WHERE `complaint_id` = ?
    ORDER BY `id` DESC LIMIT 1
");
$fbStmt->execute([$complaint['id']]);
$feedback = $fbStmt->fetch();

$result = formatComplaintForFrontend($complaint, $logs);
$result['comments'] = $comments;
$result['feedback_details'] = $feedback ?: null;

jsonResponse([
    'success'   => true,
    'complaint' => $result
]);
