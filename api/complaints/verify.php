<?php
/**
 * Campus Connect - Verification API Endpoint
 * Handles:
 * 1. Faculty QA Verification: Stage 5 -> Stage 6 (or back to Stage 4 for rework)
 * 2. Admin Final Approval: Stage 6 -> Stage 7 (Completed)
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$user = requireAuth(['faculty', 'admin']);
$data = getRequestData();

$complaintId = trim($data['complaint_id'] ?? $data['id'] ?? '');
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

    if ($user['role'] === 'faculty') {
        $approved = isset($data['approved']) ? filter_var($data['approved'], FILTER_VALIDATE_BOOLEAN) : true;
        $feedback = trim($data['feedback'] ?? $data['qa_feedback'] ?? '');

        if ($approved) {
            $stmt = $pdo->prepare("
                UPDATE `complaints` SET
                    `status` = 'Faculty Verified',
                    `current_status` = 'Faculty Verified',
                    `stage` = 6,
                    `faculty_status` = 'Verified',
                    `faculty_verification_date` = ?,
                    `qa_verified` = 1,
                    `qa_feedback` = ?
                WHERE `id` = ?
            ");
            $stmt->execute([$now, $feedback, $c['id']]);

            $logNote = "Audited and verified by Faculty - Sent to Admin for final approval." . (!empty($feedback) ? " Feedback: {$feedback}" : "");
            logWorkflowAction($pdo, $c['id'], 'Faculty Verified', $logNote, $user);

            // Notify Admin
            pushNotification(
                $pdo,
                null,
                null,
                null,
                "Audit Passed: {$c['complaint_code']} verified by {$c['category']} Faculty. Awaiting final Admin closure."
            );

            $pdo->commit();

            jsonResponse([
                'success' => true,
                'message' => "Audited and verified successfully. Sent to Admin queue.",
                'stage'   => 6
            ]);

        } else {
            // Rejected by faculty -> send back to technician
            $stmt = $pdo->prepare("
                UPDATE `complaints` SET
                    `status` = 'Work in Progress',
                    `current_status` = 'Work in Progress',
                    `stage` = 4,
                    `technician_status` = 'Accepted',
                    `work_status` = 'In Progress',
                    `qa_verified` = 0,
                    `qa_feedback` = ?
                WHERE `id` = ?
            ");
            $stmt->execute([$feedback, $c['id']]);

            $logNote = "Faculty QA check rejected resolution. Returned to Technician for rework: {$feedback}";
            logWorkflowAction($pdo, $c['id'], 'Faculty QA Rejected', $logNote, $user);

            // Notify Technician
            if (!empty($c['technician_id'])) {
                pushNotification(
                    $pdo,
                    null,
                    null,
                    $c['technician_id'],
                    "Re-work required on {$c['complaint_code']}: {$feedback}"
                );
            }

            $pdo->commit();

            jsonResponse([
                'success' => true,
                'message' => "Resolution rejected. Reassigned to technician for rework.",
                'stage'   => 4
            ]);
        }

    } elseif ($user['role'] === 'admin') {
        // Admin Final Approval -> Stage 7
        $stmt = $pdo->prepare("
            UPDATE `complaints` SET
                `status` = 'Completed',
                `current_status` = 'Completed',
                `stage` = 7,
                `admin_final_date` = ?
            WHERE `id` = ?
        ");
        $stmt->execute([$now, $c['id']]);

        logWorkflowAction($pdo, $c['id'], 'Admin Final Verified', 'Admin verified faculty audit and approved completion.', $user);
        logWorkflowAction($pdo, $c['id'], 'Completed', 'Complaint fully completed and closed.', $user);

        // Notify student that ticket is fully completed
        pushNotification(
            $pdo,
            $c['reported_by_gr_no'],
            null,
            null,
            "Your complaint {$c['complaint_code']} is officially marked COMPLETED. Please rate your experience!"
        );

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => "Final approval granted. Ticket {$c['complaint_code']} closed.",
            'stage'   => 7
        ]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Verification failed: ' . $e->getMessage(), 500);
}
