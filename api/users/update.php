<?php
/**
 * Campus Connect - Update User / Profile Endpoint
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$currentUser = requireAuth();
$data = getRequestData();

$targetGr = trim($data['gr_no'] ?? $data['grNo'] ?? '');
$name     = trim($data['name'] ?? $data['profName'] ?? '');
$dept     = trim($data['department'] ?? $data['dept'] ?? $data['profDept'] ?? '');
$pass     = trim($data['password'] ?? $data['profPass'] ?? '');
$avatar   = $data['avatar'] ?? $data['profImgUrl'] ?? null;

// Handle avatar upload if provided
$avatarPath = handleMediaUpload('avatar_file', $avatar, 'avatars');

if ($currentUser['role'] === 'student') {
    // Student updating their own profile
    $gr = $currentUser['grNo'];
    $sql = "UPDATE `users` SET `name` = ?, `department` = ?";
    $params = [$name, $dept];

    if (!empty($avatarPath)) {
        $sql .= ", `avatar` = ?";
        $params[] = $avatarPath;
    }
    if (!empty($pass) && $pass !== 'password') {
        $sql .= ", `password_hash` = ?";
        $params[] = password_hash($pass, PASSWORD_DEFAULT);
    }

    $sql .= " WHERE `gr_no` = ?";
    $params[] = $gr;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Update session
    $_SESSION['campus_user']['name'] = $name;
    $_SESSION['campus_user']['dept'] = $dept;
    if (!empty($avatarPath)) {
        $_SESSION['campus_user']['avatar'] = $avatarPath;
    }

    jsonResponse([
        'success' => true,
        'message' => 'Profile updated successfully!',
        'user'    => $_SESSION['campus_user']
    ]);

} elseif ($currentUser['role'] === 'technician') {
    // Technician updating own profile
    $techId = $currentUser['techId'];
    $sql = "UPDATE `technicians` SET `name` = ?, `department` = ?";
    $params = [$name, $dept];

    if (!empty($pass) && $pass !== 'password') {
        $sql .= ", `password_hash` = ?";
        $params[] = password_hash($pass, PASSWORD_DEFAULT);
    }

    $sql .= " WHERE `technician_code` = ?";
    $params[] = $techId;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $_SESSION['campus_user']['name'] = $name;
    $_SESSION['campus_user']['dept'] = $dept;

    jsonResponse([
        'success' => true,
        'message' => 'Profile updated successfully!',
        'user'    => $_SESSION['campus_user']
    ]);

} elseif ($currentUser['role'] === 'admin') {
    // Admin editing a student
    if (!empty($targetGr)) {
        $sql = "UPDATE `users` SET `name` = ?, `department` = ?";
        $params = [$name, $dept];

        if (!empty($pass)) {
            $sql .= ", `password_hash` = ?";
            $params[] = password_hash($pass, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE `gr_no` = ?";
        $params[] = $targetGr;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        jsonResponse([
            'success' => true,
            'message' => "Student profile for {$targetGr} updated successfully"
        ]);
    } else {
        // Admin updating own profile
        if (!empty($pass) && $pass !== 'admin123') {
            $stmt = $pdo->prepare("UPDATE `admins` SET `name` = ?, `password_hash` = ? WHERE `username` = 'admin'");
            $stmt->execute([$name, password_hash($pass, PASSWORD_DEFAULT)]);
        } else {
            $stmt = $pdo->prepare("UPDATE `admins` SET `name` = ? WHERE `username` = 'admin'");
            $stmt->execute([$name]);
        }
        $_SESSION['campus_user']['name'] = $name;

        jsonResponse([
            'success' => true,
            'message' => 'Admin profile updated'
        ]);
    }
}
