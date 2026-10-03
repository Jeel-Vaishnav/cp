<?php
/**
 * Campus Connect - Assign / Dispatch Complaint API Endpoint
 * Handles:
 * 1. Admin dispatch to Faculty/Department (Stage 1 -> Stage 2)
 * 2. Faculty dispatch to Technician (Stage 2 -> Stage 3)
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$user = requireAuth(['admin', 'faculty']);
$data = getRequestData();

$complaintId = trim($data['complaint_id'] ?? $data['id'] ?? '');
if (empty($complaintId)) {
    jsonError('Complaint ID is required', 400);
}

// Find complaint
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
        // Admin dispatch to department
        $dept = trim($data['dept'] ?? $data['category'] ?? $c['category']);
        $prio = trim($data['priority'] ?? $data['prio'] ?? $c['priority']);
        $note = trim($data['note'] ?? $data['admin_remark'] ?? '');

        if (!in_array($prio, ['Low', 'Medium', 'High'], true)) {
            $prio = $c['priority'];
        }

        // Find faculty for this department
        $facStmt = $pdo->prepare("SELECT `id`, `name` FROM `faculties` WHERE `department` = ? LIMIT 1");
        $facStmt->execute([$dept]);
        $fac = $facStmt->fetch();

        $updateStmt = $pdo->prepare("
            UPDATE `complaints` SET
                `category` = ?,
                `priority` = ?,
                `status` = 'Assigned to Faculty',
                `current_status` = 'Assigned to Faculty',
                `stage` = 2,
                `admin_status` = 'Approved',
                `admin_verification_date` = ?,
                `admin_remark` = ?,
                `faculty_id` = ?,
                `faculty_department` = ?,
                `faculty_status` = 'Pending',
                `last_rejected_technician_id` = NULL,
                `rejection_reason` = ''
            WHERE `id` = ?
        ");
        $updateStmt->execute([
            $dept, $prio, $now, $note,
            $fac['id'] ?? null, $dept,
            $c['id']
        ]);

        $logNote = "Verified by Admin & assigned to {$dept} Faculty Advisor" . (!empty($note) ? " ({$note})" : "");
        logWorkflowAction($pdo, $c['id'], 'Admin Verified', $logNote, $user);

        // Notify student and faculty
        pushNotification(
            $pdo,
            $c['reported_by_gr_no'],
            null,
            null,
            "Admin verified and assigned {$c['complaint_code']} to {$dept} Faculty"
        );
        pushNotification(
            $pdo,
            null,
            $dept,
            null,
            "Incoming complaint {$c['complaint_code']} routed to your department for technician assignment."
        );

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => "Complaint {$c['complaint_code']} verified & assigned to {$dept} Faculty",
            'stage'   => 2
        ]);

    } elseif ($user['role'] === 'faculty') {
        // Faculty dispatch to technician
        $techId = strtoupper(trim($data['technician_id'] ?? $data['techId'] ?? ''));
        $deadline = trim($data['deadline'] ?? '');
        $remark = trim($data['remark'] ?? $data['faculty_remark'] ?? '');

        if (empty($techId)) {
            jsonError('Please select a technician to assign', 400);
        }

        // Get technician details
        $tStmt = $pdo->prepare("SELECT * FROM `technicians` WHERE `technician_code` = ? OR `id` = ? LIMIT 1");
        $tStmt->execute([$techId, is_numeric($techId) ? (int)$techId : 0]);
        $tech = $tStmt->fetch();

        if (!$tech) {
            jsonError('Selected technician not found', 404);
        }

        $techCode = $tech['technician_code'];
        $techName = $tech['name'];

        $updateStmt = $pdo->prepare("
            UPDATE `complaints` SET
                `technician_id` = ?,
                `technician_name` = ?,
                `deadline` = ?,
                `faculty_remark` = ?,
                `status` = 'Assigned to Technician',
                `current_status` = 'Assigned to Technician',
                `stage` = 3,
                `faculty_status` = 'Dispatched',
                `technician_status` = 'Pending',
                `technician_action` = NULL,
                `work_status` = 'Not Started',
                `last_rejected_technician_id` = NULL,
                `rejection_reason` = ''
            WHERE `id` = ?
        ");
        $updateStmt->execute([
            $techCode, $techName, $deadline, $remark,
            $c['id']
        ]);

        $logNote = "Dispatched to Technician {$techName}" . (!empty($deadline) ? " (Deadline: {$deadline})" : "");
        logWorkflowAction($pdo, $c['id'], 'Faculty Assigned Tech', $logNote, $user);

        // Notify technician
        pushNotification(
            $pdo,
            null,
            null,
            $techCode,
            "Work order dispatched: {$c['complaint_code']} [{$c['priority']} Priority]. Location: {$c['location']}"
        );

        // Notify student
        pushNotification(
            $pdo,
            $c['reported_by_gr_no'],
            null,
            null,
            "Faculty assigned Technician {$techName} to resolve {$c['complaint_code']}."
        );

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => "Dispatched to {$techName} successfully!",
            'stage'   => 3
        ]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Assignment failed: ' . $e->getMessage(), 500);
}
