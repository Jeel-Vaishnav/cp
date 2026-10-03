<?php
/**
 * Campus Connect - List Users (Students) Endpoint
 */

require_once __DIR__ . '/../common.php';

$user = requireAuth(['admin']);

$stmt = $pdo->query("SELECT `id`, `gr_no` AS `grNo`, `name`, `department` AS `dept`, `avatar`, `warned`, `suspended`, `created_at` FROM `users` ORDER BY `id` ASC");
$users = $stmt->fetchAll();

foreach ($users as &$u) {
    $u['warned'] = (bool)$u['warned'];
    $u['suspended'] = (bool)$u['suspended'];
}

jsonResponse([
    'success' => true,
    'users'   => $users
]);
