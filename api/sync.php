<?php
/**
 * Campus Connect - Unified State Synchronization Endpoint
 * Returns live MySQL datasets for complaints, technicians, faculties, users, and notifications
 */

require_once __DIR__ . '/common.php';

$user = getCurrentUser();

// 1. Fetch complaints
$cStmt = $pdo->query("SELECT * FROM `complaints` ORDER BY `id` DESC");
$complaintRows = $cStmt->fetchAll();

// Preload logs
$complaintIds = array_column($complaintRows, 'id');
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

$complaints = [];
foreach ($complaintRows as $c) {
    $logs = $logsMap[$c['id']] ?? [];
    $complaints[] = formatComplaintForFrontend($c, $logs);
}

// 2. Fetch technicians
$techStmt = $pdo->query("
    SELECT `id` AS `db_id`, `technician_code` AS `id`, `name`, `department` AS `dept`, `experience`, `rating`, `active`
    FROM `technicians`
    ORDER BY `id` ASC
");
$technicians = $techStmt->fetchAll();
foreach ($technicians as &$t) {
    $t['active'] = (bool)$t['active'];
    $t['experience'] = (int)$t['experience'];
    $t['rating'] = (float)$t['rating'];
    $t['password'] = 'password'; // UI form placeholder
}

// 3. Fetch faculties
$facStmt = $pdo->query("SELECT `id`, `department` AS `dept`, `name`, `active` FROM `faculties` ORDER BY `id` ASC");
$faculties = $facStmt->fetchAll();
foreach ($faculties as &$f) {
    $f['active'] = (bool)$f['active'];
    $f['password'] = 'password';
}

// 4. Fetch users (students)
$userStmt = $pdo->query("SELECT `id`, `gr_no` AS `grNo`, `name`, `department` AS `dept`, `avatar`, `warned`, `suspended` FROM `users` ORDER BY `id` ASC");
$users = $userStmt->fetchAll();
foreach ($users as &$u) {
    $u['warned'] = (bool)$u['warned'];
    $u['suspended'] = (bool)$u['suspended'];
    $u['password'] = 'password';
}

// 5. Fetch notifications
$notifSql = "SELECT `id`, `notif_id`, `for_gr`, `for_dept`, `for_tech`, `text`, `notif_time`, `is_read` FROM `notifications` ORDER BY `id` DESC LIMIT 50";
$notifStmt = $pdo->query($notifSql);
$notifRows = $notifStmt->fetchAll();

$notifs = [];
foreach ($notifRows as $n) {
    $notifs[] = [
        'id'      => $n['notif_id'] ?: ('N' . $n['id']),
        'forGr'   => $n['for_gr'],
        'forDept' => $n['for_dept'],
        'forTech' => $n['for_tech'],
        'text'    => $n['text'],
        'time'    => $n['notif_time'],
        'read'    => (bool)$n['is_read']
    ];
}

jsonResponse([
    'success'     => true,
    'session'     => $user,
    'complaints'  => $complaints,
    'technicians' => $technicians,
    'faculties'   => $faculties,
    'users'       => $users,
    'notifs'      => $notifs
]);
