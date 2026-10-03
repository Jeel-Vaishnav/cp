<?php
/**
 * Campus Connect - Create Complaint API Endpoint
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$user = requireAuth(['student', 'admin']);

// If student, check suspension status
if ($user['role'] === 'student') {
    $stmt = $pdo->prepare("SELECT `suspended` FROM `users` WHERE `id` = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $u = $stmt->fetch();
    if ($u && !empty($u['suspended'])) {
        jsonError('Your account has been suspended by administration. You cannot submit complaints.', 403);
    }
}

$data = getRequestData();

$title    = trim($data['title'] ?? $data['cTitle'] ?? '');
$category = trim($data['category'] ?? $data['cCategory'] ?? 'Computer Department');
$priority = trim($data['priority'] ?? $data['cPriority'] ?? 'Low');
$location = trim($data['location'] ?? $data['cLocation'] ?? '');
$desc     = trim($data['description'] ?? $data['desc'] ?? $data['cDesc'] ?? '');

if (empty($title) || empty($location) || empty($desc)) {
    jsonError('Please fill in all required complaint fields (Title, Location, Description).', 400);
}

if (!in_array($priority, ['Low', 'Medium', 'High'], true)) {
    $priority = 'Low';
}

// Generate unique complaint code
do {
    $codeNum = rand(210, 9999);
    $complaintCode = 'COMP-' . $codeNum;
    $stmt = $pdo->prepare("SELECT `id` FROM `complaints` WHERE `complaint_code` = ? LIMIT 1");
    $stmt->execute([$complaintCode]);
} while ($stmt->fetch());

// Handle media uploads safely
$imageUrl = handleMediaUpload('image_file', $data['image'] ?? null, 'complaints');
if (empty($imageUrl)) {
    $imageUrl = 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?q=80&w=600';
}
$videoUrl = handleMediaUpload('video_file', $data['video'] ?? null, 'complaints') ?? '';

$reporterName = $user['name'] ?? 'Student';
$reporterGr   = $user['grNo'] ?? '1001';
$reportedTime = nowStr();

try {
    $pdo->beginTransaction();

    $insertStmt = $pdo->prepare("
        INSERT INTO `complaints` (
            `complaint_code`, `title`, `category`, `description`, `location`, `priority`,
            `reported_by_user_id`, `reported_by_name`, `reported_by_gr_no`, `reported_at`,
            `status`, `current_status`, `stage`,
            `admin_status`, `faculty_status`, `technician_status`, `work_status`,
            `image`, `video`
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            'Complaint Submitted', 'Complaint Submitted', 1,
            'Pending', 'Pending', 'Pending', 'Not Started',
            ?, ?
        )
    ");

    $insertStmt->execute([
        $complaintCode, $title, $category, $desc, $location, $priority,
        $user['id'] ?? null, $reporterName, $reporterGr, $reportedTime,
        $imageUrl, $videoUrl
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Add log
    logWorkflowAction(
        $pdo,
        $newId,
        'Complaint Submitted',
        "Self Category: {$category} | Priority: {$priority}",
        $user
    );

    // Broadcast notification for admin
    pushNotification(
        $pdo,
        null,
        null,
        null,
        "Incoming verification required: {$complaintCode} [{$priority}] from {$reporterName}"
    );

    $pdo->commit();

    // Fetch the newly created record
    $fetchStmt = $pdo->prepare("SELECT * FROM `complaints` WHERE `id` = ? LIMIT 1");
    $fetchStmt->execute([$newId]);
    $createdRecord = $fetchStmt->fetch();

    $initialLog = [[
        's'    => 'Complaint Submitted',
        'note' => "Self Category: {$category} | Priority: {$priority}",
        'time' => $reportedTime,
        'by'   => $reporterName
    ]];

    jsonResponse([
        'success'        => true,
        'message'        => "Complaint {$complaintCode} registered & routed to Admin queue.",
        'complaint_code' => $complaintCode,
        'complaint_id'   => $complaintCode,
        'complaint'      => formatComplaintForFrontend($createdRecord, $initialLog)
    ], 201);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Failed to register complaint: ' . $e->getMessage(), 500);
}
