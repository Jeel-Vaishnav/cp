<?php
/**
 * Campus Connect - Complete Work Order API Endpoint (Technician)
 * Stage 4 -> Stage 5 (Work Completed by Technician)
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$user = requireAuth(['technician', 'admin']);
$data = getRequestData();

$complaintId = trim($data['complaint_id'] ?? $data['id'] ?? '');
$remark = trim($data['remark'] ?? $data['technician_remark'] ?? '');

if (empty($complaintId)) {
    jsonError('Complaint ID is required', 400);
}

$stmt = $pdo->prepare("SELECT * FROM `complaints` WHERE `complaint_code` = ? OR `id` = ? LIMIT 1");
$stmt->execute([$complaintId, is_numeric($complaintId) ? (int)$complaintId : 0]);
$c = $stmt->fetch();

if (!$c) {
    jsonError('Complaint not found', 404);
}

// Handle proof image upload
$proofPath = handleMediaUpload('proof_file', $data['proof_image'] ?? $data['proofImg'] ?? null, 'proofs');
if (empty($proofPath)) {
    // If not provided, fallback to default or existing
    $proofPath = $c['proof_image'] ?: 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?q=80&w=600';
}

$now = nowStr();
$techName = $user['name'] ?? $c['technician_name'] ?? 'Technician';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE `complaints` SET
            `status` = 'Work Completed by Technician',
            `current_status` = 'Work Completed by Technician',
            `stage` = 5,
            `technician_status` = 'Completed',
            `work_status` = 'Completed',
            `technician_completion_date` = ?,
            `proof_image` = ?,
            `technician_remark` = ?,
            `remark` = ?
        WHERE `id` = ?
    ");
    $stmt->execute([$now, $proofPath, $remark, $remark, $c['id']]);

    $logNote = "Work finished, photo proof uploaded & submitted for Faculty Verification." . (!empty($remark) ? " Remark: {$remark}" : "");
    logWorkflowAction($pdo, $c['id'], 'Technician Completed', $logNote, $user);

    // Notify departmental faculty
    pushNotification(
        $pdo,
        null,
        $c['category'],
        null,
        "Work finished by {$techName} on {$c['complaint_code']}. Please inspect proof & verify."
    );

    $pdo->commit();

    jsonResponse([
        'success'   => true,
        'message'   => "Resolution proof submitted for {$c['complaint_code']}. Forwarded for Faculty QA.",
        'stage'     => 5,
        'proof_img' => $proofPath
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Failed to complete work order: ' . $e->getMessage(), 500);
}
