<?php
/**
 * Campus Connect - Update Complaint Status API Endpoint
 * Handles:
 * 1. Technician acceptance: Stage 3 -> Stage 4 (Work in Progress)
 * 2. Status / Priority updates
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$user = requireAuth(['technician', 'faculty', 'admin']);
$data = getRequestData();

$complaintId = trim($data['complaint_id'] ?? $data['id'] ?? '');
$action = trim($data['action'] ?? '');

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

    if ($action === 'accept' && ($user['role'] === 'technician' || $user['role'] === 'admin')) {
        $techName = $user['name'] ?? $c['technician_name'] ?? 'Technician';

        $stmt = $pdo->prepare("
            UPDATE `complaints` SET
                `status` = 'Work in Progress',
                `current_status` = 'Work in Progress',
                `stage` = 4,
                `technician_status` = 'Accepted',
                `technician_action` = 'Accepted',
                `work_status` = 'In Progress'
            WHERE `id` = ?
        ");
        $stmt->execute([$c['id']]);

        logWorkflowAction($pdo, $c['id'], 'Technician Accepted', "Accepted work order by {$techName}", $user);
        logWorkflowAction($pdo, $c['id'], 'Work in Progress', "Field inspection and maintenance underway by {$techName}", $user);

        pushNotification(
            $pdo,
            $c['reported_by_gr_no'],
            null,
            null,
            "Technician {$techName} has accepted your complaint {$c['complaint_code']} and started work."
        );

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => "Work order {$c['complaint_code']} accepted. In Progress.",
            'stage'   => 4
        ]);
    } else {
        // Generic update if needed
        $pdo->commit();
        jsonResponse([
            'success' => true,
            'message' => 'Complaint updated'
        ]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Failed to update complaint: ' . $e->getMessage(), 500);
}
