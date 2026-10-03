<?php
/**
 * Campus Connect - Student Feedback & Further Action API Endpoint
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$user = requireAuth(['student', 'admin']);
$data = getRequestData();

$complaintId = trim($data['complaint_id'] ?? $data['id'] ?? '');
$rating = (int)($data['rating'] ?? 5);
$comment = trim($data['comment'] ?? $data['feedback_comment'] ?? '');
$furtherAction = isset($data['furtherAction']) ? filter_var($data['furtherAction'], FILTER_VALIDATE_BOOLEAN) : false;

if (empty($complaintId)) {
    jsonError('Complaint ID is required', 400);
}

if ($rating < 1 || $rating > 5) {
    $rating = 5;
}

$stmt = $pdo->prepare("SELECT * FROM `complaints` WHERE `complaint_code` = ? OR `id` = ? LIMIT 1");
$stmt->execute([$complaintId, is_numeric($complaintId) ? (int)$complaintId : 0]);
$c = $stmt->fetch();

if (!$c) {
    jsonError('Complaint not found', 404);
}

// Student can only submit feedback for their own complaints
if ($user['role'] === 'student' && $c['reported_by_gr_no'] !== $user['grNo']) {
    jsonError('You can only submit feedback for your own complaints.', 403);
}

$now = nowStr();
$feedbackStatus = $furtherAction ? 'not_satisfied' : 'satisfied';

try {
    $pdo->beginTransaction();

    // 1. Update complaint record
    $updateSql = "
        UPDATE `complaints` SET
            `feedback_rating` = ?,
            `feedback_comment` = ?,
            `feedback_status` = ?,
            `further_action_requested` = ?,
            `feedback_submitted_at` = ?
    ";
    $params = [$rating, $comment, $feedbackStatus, $furtherAction ? 1 : 0, $now];

    if ($furtherAction) {
        $updateSql .= ", `status` = 'Student Not Satisfied', `current_status` = 'Student Not Satisfied' ";
    }

    $updateSql .= " WHERE `id` = ?";
    $params[] = $c['id'];

    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute($params);

    // 2. Insert into complaint_feedback table
    $fbStmt = $pdo->prepare("
        INSERT INTO `complaint_feedback` 
        (`complaint_id`, `student_id`, `rating`, `comment`, `further_action_requested`, `created_at`)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $fbStmt->execute([
        $c['id'],
        $user['id'] ?? null,
        $rating,
        $comment,
        $furtherAction ? 1 : 0
    ]);

    // 3. Workflow log
    $actionTitle = $furtherAction ? 'Student Feedback - Further Action Requested' : 'Student Feedback Submitted';
    $logNote = "Rated {$rating} Stars. " . (!empty($comment) ? "Comment: {$comment}" : "(No comment provided)");
    if ($furtherAction) {
        $logNote .= " [FLAGGED: Further escalation/re-work requested by student]";
    }

    logWorkflowAction($pdo, $c['id'], $actionTitle, $logNote, $user);

    // 4. Notifications
    if ($furtherAction) {
        // Alert admin and faculty
        pushNotification(
            $pdo,
            null,
            null,
            null,
            "Re-open / Action Flag: Student filed dissatisfaction for {$c['complaint_code']}: \"{$comment}\""
        );
        pushNotification(
            $pdo,
            null,
            $c['category'],
            null,
            "Dissatisfaction Alert: Student requested further action on {$c['complaint_code']}: \"{$comment}\""
        );
    }

    $pdo->commit();

    jsonResponse([
        'success'        => true,
        'message'        => $furtherAction ? 'Complaint flagged for further administrative review.' : 'Thank you for your feedback rating!',
        'further_action' => $furtherAction,
        'rating'         => $rating
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Failed to record feedback: ' . $e->getMessage(), 500);
}
