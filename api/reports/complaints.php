<?php
/**
 * Campus Connect - Reports & Analytics Endpoint
 * Supports JSON filtered complaints & summary stats, plus CSV export
 */

require_once __DIR__ . '/../common.php';

$user = requireAuth(['admin', 'faculty']);

$dept   = $_GET['dept'] ?? 'All';
$status = $_GET['status'] ?? 'All';
$prio   = $_GET['priority'] ?? 'All';
$tech   = $_GET['tech'] ?? 'All';
$sla    = $_GET['sla'] ?? 'all'; // 'all', 'pending15', 'pending30'
$format = $_GET['format'] ?? 'json';

$sql = "SELECT * FROM `complaints` WHERE 1=1";
$params = [];

if ($dept !== 'All') {
    $sql .= " AND `category` = ?";
    $params[] = $dept;
}

if ($status !== 'All') {
    $sql .= " AND (`status` = ? OR `current_status` = ?)";
    $params[] = $status;
    $params[] = $status;
}

if ($prio !== 'All') {
    $sql .= " AND `priority` = ?";
    $params[] = $prio;
}

if ($tech !== 'All') {
    $sql .= " AND `technician_id` = ?";
    $params[] = $tech;
}

if ($sla === 'pending15') {
    $sql .= " AND `stage` < 7 AND `created_at` <= DATE_SUB(NOW(), INTERVAL 15 DAY)";
} elseif ($sla === 'pending30') {
    $sql .= " AND `stage` < 7 AND `created_at` <= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$sql .= " ORDER BY `id` DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=campus_connect_complaints_report_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, [
        'Complaint ID',
        'Title',
        'Department / Category',
        'Location',
        'Priority',
        'Reported By',
        'G.R. Number',
        'Reported Date',
        'Status',
        'Stage',
        'Technician',
        'Admin Status',
        'Faculty Status',
        'Technician Status',
        'Completion Date'
    ]);

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['complaint_code'],
            $r['title'],
            $r['category'],
            $r['location'],
            $r['priority'],
            $r['reported_by_name'],
            $r['reported_by_gr_no'],
            $r['reported_at'],
            $r['status'],
            $r['stage'],
            $r['technician_name'] ?: 'Unassigned',
            $r['admin_status'],
            $r['faculty_status'],
            $r['technician_status'],
            $r['technician_completion_date'] ?: 'N/A'
        ]);
    }
    fclose($output);
    exit;
}

// Format for JSON
$complaints = [];
foreach ($rows as $r) {
    $complaints[] = formatComplaintForFrontend($r, []);
}

// Calculate summary stats
$total = count($complaints);
$closed = count(array_filter($complaints, fn($c) => $c['stage'] === 7 || $c['status'] === 'Completed'));
$pending = $total - $closed;

jsonResponse([
    'success'    => true,
    'total'      => $total,
    'closed'     => $closed,
    'pending'    => $pending,
    'complaints' => $complaints
]);
