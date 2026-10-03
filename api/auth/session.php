<?php
/**
 * Campus Connect - Check Current Session Endpoint
 */

require_once __DIR__ . '/../common.php';

$user = getCurrentUser();

if (!$user) {
    jsonResponse([
        'authenticated' => false,
        'user'          => null
    ]);
}

// Optionally refresh user status if student
if ($user['role'] === 'student' && !empty($user['grNo'])) {
    $stmt = $pdo->prepare("SELECT `name`, `department`, `avatar`, `warned`, `suspended` FROM `users` WHERE `gr_no` = ? LIMIT 1");
    $stmt->execute([$user['grNo']]);
    $fresh = $stmt->fetch();
    if ($fresh) {
        $user['name']      = $fresh['name'];
        $user['dept']      = $fresh['department'];
        $user['avatar']    = $fresh['avatar'];
        $user['warned']    = (bool)$fresh['warned'];
        $user['suspended'] = (bool)$fresh['suspended'];
        $_SESSION['campus_user'] = $user;
    }
}

jsonResponse([
    'authenticated' => true,
    'user'          => $user
]);
