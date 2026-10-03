<?php
/**
 * Campus Connect - List Staff Endpoint (Technicians and Faculties)
 */

require_once __DIR__ . '/../common.php';

$user = requireAuth(['admin', 'faculty']);

// Technicians
$techStmt = $pdo->query("SELECT `id` AS `db_id`, `technician_code` AS `id`, `name`, `department` AS `dept`, `experience`, `rating`, `active` FROM `technicians` ORDER BY `id` ASC");
$technicians = $techStmt->fetchAll();

foreach ($technicians as &$t) {
    $t['active'] = (bool)$t['active'];
    $t['experience'] = (int)$t['experience'];
    $t['rating'] = (float)$t['rating'];
}

// Faculties
$facStmt = $pdo->query("SELECT `id`, `department` AS `dept`, `name`, `active` FROM `faculties` ORDER BY `id` ASC");
$faculties = $facStmt->fetchAll();

foreach ($faculties as &$f) {
    $f['active'] = (bool)$f['active'];
}

jsonResponse([
    'success'     => true,
    'technicians' => $technicians,
    'faculties'   => $faculties
]);
