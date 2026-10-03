<?php
/**
 * Campus Connect - Public Transparency Feed API Endpoint
 */

require_once __DIR__ . '/../common.php';

$filterDept = $_GET['dept'] ?? $_GET['category'] ?? 'all';

$sql = "SELECT `id`, `complaint_code`, `title`, `category`, `description`, `location`, `priority`, `status`, `current_status`, `stage`, `image`, `proof_image`, `reported_at` FROM `complaints` WHERE 1=1";
$params = [];

if (!empty($filterDept) && $filterDept !== 'all') {
    $sql .= " AND `category` = ?";
    $params[] = $filterDept;
}

$sql .= " ORDER BY `id` DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$feed = [];
foreach ($rows as $r) {
    $feed[] = [
        'id'          => $r['complaint_code'],
        'title'       => $r['title'],
        'category'    => $r['category'],
        'dept'        => $r['category'],
        'description' => $r['description'],
        'desc'        => $r['description'],
        'location'    => $r['location'],
        'priority'    => $r['priority'],
        'status'      => $r['status'],
        'stage'       => (int)$r['stage'],
        'image'       => $r['image'] ?? '',
        'proofImg'    => $r['proof_image'] ?? '',
        'reportedAt'  => $r['reported_at']
    ];
}

jsonResponse([
    'success' => true,
    'feed'    => $feed
]);
