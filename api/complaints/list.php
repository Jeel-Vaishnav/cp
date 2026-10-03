<?php
/**
 * Campus Connect - List Complaints API Endpoint
 * Role-aware and filter-enabled
 */

require_once __DIR__ . '/../common.php';

$user = getCurrentUser();
$role = $user['role'] ?? 'guest';

$view = $_GET['view'] ?? '';
$filterDept = $_GET['dept'] ?? $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');
$stageFilter = isset($_GET['stage']) ? (int)$_GET['stage'] : null;

// Base query
$sql = "SELECT * FROM `complaints` WHERE 1=1";
$params = [];

// Role-based visibility
if ($role === 'student') {
    // If student is requesting own list
    if (empty($_GET['all'])) {
        $sql .= " AND `reported_by_gr_no` = ?";
        $params[] = $user['grNo'];
    }
} elseif ($role === 'faculty') {
    if (empty($_GET['all'])) {
        $sql .= " AND `category` = ?";
        $params[] = $user['dept'];
    }
} elseif ($role === 'technician') {
    if (empty($_GET['all'])) {
        $sql .= " AND `technician_id` = ?";
        $params[] = $user['techId'];
    }
} elseif ($role === 'admin') {
    // Admin can see all complaints
}

// Department filter
if (!empty($filterDept) && $filterDept !== 'all') {
    $sql .= " AND `category` = ?";
    $params[] = $filterDept;
}

// Stage filter
if ($stageFilter !== null) {
    $sql .= " AND `stage` = ?";
    $params[] = $stageFilter;
}

// Search keyword
if (!empty($search)) {
    $sql .= " AND (`complaint_code` LIKE ? OR `title` LIKE ? OR `description` LIKE ? OR `location` LIKE ? OR `reported_by_name` LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

$sql .= " ORDER BY `id` DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Preload logs for all retrieved complaints
$complaintIds = array_column($complaints, 'id');
$logsMap = [];

if (!empty($complaintIds)) {
    $inClause = implode(',', array_fill(0, count($complaintIds), '?'));
    $logStmt = $pdo->prepare("
        SELECT `complaint_id`, `status` AS `s`, `note`, `performed_by_name` AS `by`, `log_time` AS `time`
        FROM `complaint_logs`
        WHERE `complaint_id` IN ($inClause)
        ORDER BY `id` ASC
    ");
    $logStmt->execute($complaintIds);
    $allLogs = $logStmt->fetchAll();

    foreach ($allLogs as $log) {
        $logsMap[$log['complaint_id']][] = [
            's'    => $log['s'],
            'note' => $log['note'],
            'time' => $log['time'],
            'by'   => $log['by']
        ];
    }
}

// Format for frontend
$result = [];
foreach ($complaints as $c) {
    $logs = $logsMap[$c['id']] ?? [];
    $result[] = formatComplaintForFrontend($c, $logs);
}

jsonResponse([
    'success'    => true,
    'count'      => count($result),
    'complaints' => $result
]);
