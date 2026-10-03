<?php
/**
 * Campus Connect - Reject / Decline Complaint API Endpoint
 * Handles:
 * 1. Admin rejection (Stage 1 -> Stage 0)
 * 2. Technician decline (Stage 3 -> Stage 2 Reassigning)
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$user = requireAuth(['admin', 'technician']);
$data = getRequestData();

$complaintId = trim($data['complaint_id'] ?? $data['id'] ?? '');
$reason = trim($data['reason'] ?? $data['rejectionReason'] ?? $data['admin_remark'] ?? '');

if (empty($complaintId)) {
    jsonError('Complaint ID is required', 400);
}

$stmt = $pdo->prepare("SELECT * FROM `complaints` WHERE `complaint_code` = ? OR `id` = ? LIMIT 1");
$stmt->execute([$complaintId, is_numeric($complaintId) ? (int)$complaintId : 0]);
$c = $stmt->fetch();

if (!$c) {
    jsonError('Complaint not found', 404);
}

$now = nowStr();

try {
    $pdo->beginTransaction();

    if ($user['role'] === 'admin') {
        if (empty($reason)) {
            $reason = 'Admin rejected the submitted complaint due to policy/verification failure.';
        }

        $stmt = $pdo->prepare("
            UPDATE `complaints` SET
                `status` = 'Rejected by Admin',
                `current_status` = 'Rejected by Admin',
                `stage` = 0,
                `admin_status` = 'Rejected',
                `admin_remark` = ?
            WHERE `id` = ?
        ");
        $stmt->execute([$reason, $c['id']]);

        logWorkflowAction($pdo, $c['id'], 'Rejected by Admin', "Admin rejected complaint: {$reason}", $user);

        pushNotification(
            $pdo,
            $c['reported_by_gr_no'],
            null,
            null,
            "Your complaint {$c['complaint_code']} was rejected by Admin. Reason: {$reason}"
        );

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => "Complaint {$c['complaint_code']} marked rejected.",
            'stage'   => 0
        ]);

    } elseif ($user['role'] === 'technician') {
        if (empty($reason)) {
            jsonError('Please specify a reason for declining this work order', 400);
        }

        $techCode = $user['techId'] ?? $c['technician_id'];
        $techName = $user['name'] ?? $c['technician_name'] ?? 'Technician';

        // Revert complaint to faculty queue for reassignment
        $stmt = $pdo->prepare("
            UPDATE `complaints` SET
                `status` = 'Assigned to Faculty (Reassigning Tech)',
                `current_status` = 'Assigned to Faculty (Reassigning Tech)',
                `stage` = 2,
                `technician_status` = 'Rejected',
                `technician_action` = 'Rejected',
                `work_status` = 'Cancelled',
                `rejection_reason` = ?,
                `last_rejected_technician_id` = ?,
                `technician_id` = NULL,
                `technician_name` = NULL
            WHERE `id` = ?
        ");
        $stmt->execute([$reason, $techCode, $c['id']]);

        logWorkflowAction(
            $pdo,
            $c['id'],
            'Technician Rejected',
            "Declined by {$techName}: {$reason}",
            $user
        );

        // Notify faculty to reassign
        pushNotification(
            $pdo,
            null,
            $c['category'],
            null,
            "Technician {$techName} declined {$c['complaint_code']}: {$reason}. Please reassign to another technician."
        );

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => "Work order declined. Returned to {$c['category']} Faculty for reassignment.",
            'stage'   => 2
        ]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Failed to process rejection: ' . $e->getMessage(), 500);
}
