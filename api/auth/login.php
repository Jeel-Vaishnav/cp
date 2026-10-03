<?php
/**
 * Campus Connect - Login API Endpoint
 * Supports Student, Faculty, Technician, and Admin authentication
 */

require_once __DIR__ . '/../common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$data = getRequestData();
$role = strtolower(trim($data['role'] ?? 'student'));

if ($role === 'student') {
    $gr = trim($data['gr_no'] ?? $data['gr'] ?? $data['stuGr'] ?? '');
    $pass = $data['password'] ?? $data['pass'] ?? $data['stuPass'] ?? '';

    if (empty($gr) || empty($pass)) {
        jsonError('G.R. Number and password are required', 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `gr_no` = ? LIMIT 1");
    $stmt->execute([$gr]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        jsonError('Invalid G.R. Number or Password', 401);
    }

    if (!empty($user['suspended'])) {
        jsonError('Your account is suspended. Contact Principal Office.', 403);
    }

    $sessionUser = [
        'id'        => (int)$user['id'],
        'role'      => 'student',
        'grNo'      => $user['gr_no'],
        'name'      => $user['name'],
        'dept'      => $user['department'],
        'avatar'    => $user['avatar'],
        'warned'    => (bool)$user['warned'],
        'suspended' => (bool)$user['suspended']
    ];

    setCurrentUser($sessionUser);

    jsonResponse([
        'success' => true,
        'message' => "Welcome " . $user['name'],
        'user'    => $sessionUser
    ]);

} elseif ($role === 'faculty') {
    $dept = trim($data['department'] ?? $data['dept'] ?? $data['facDept'] ?? '');
    $pass = $data['password'] ?? $data['pass'] ?? $data['facPass'] ?? '';

    if (empty($dept) || empty($pass)) {
        jsonError('Department and password are required', 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM `faculties` WHERE `department` = ? LIMIT 1");
    $stmt->execute([$dept]);
    $faculty = $stmt->fetch();

    if (!$faculty || !password_verify($pass, $faculty['password_hash'])) {
        jsonError('Invalid Faculty Credentials', 401);
    }

    if (empty($faculty['active'])) {
        jsonError('This faculty account has been deactivated.', 403);
    }

    $sessionUser = [
        'id'   => (int)$faculty['id'],
        'role' => 'faculty',
        'name' => $faculty['name'],
        'dept' => $faculty['department']
    ];

    setCurrentUser($sessionUser);

    jsonResponse([
        'success' => true,
        'message' => "Faculty authorized: " . $faculty['department'],
        'user'    => $sessionUser
    ]);

} elseif ($role === 'technician') {
    $techId = strtoupper(trim($data['technician_code'] ?? $data['techId'] ?? $data['id'] ?? ''));
    $pass = $data['password'] ?? $data['pass'] ?? $data['techPass'] ?? '';

    if (empty($techId) || empty($pass)) {
        jsonError('Technician ID and password are required', 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM `technicians` WHERE `technician_code` = ? LIMIT 1");
    $stmt->execute([$techId]);
    $tech = $stmt->fetch();

    if (!$tech || !password_verify($pass, $tech['password_hash'])) {
        jsonError('Invalid Technician credentials', 401);
    }

    if (empty($tech['active'])) {
        jsonError('This technician account has been deactivated.', 403);
    }

    $sessionUser = [
        'id'         => (int)$tech['id'],
        'role'       => 'technician',
        'techId'     => $tech['technician_code'],
        'name'       => $tech['name'],
        'dept'       => $tech['department'],
        'experience' => (int)$tech['experience'],
        'rating'     => (float)$tech['rating']
    ];

    setCurrentUser($sessionUser);

    jsonResponse([
        'success' => true,
        'message' => "Technician session open: " . $tech['name'],
        'user'    => $sessionUser
    ]);

} elseif ($role === 'admin') {
    $user = trim($data['username'] ?? $data['user'] ?? $data['adminUser'] ?? '');
    $pass = $data['password'] ?? $data['pass'] ?? $data['adminPass'] ?? '';

    if (empty($user) || empty($pass)) {
        jsonError('Username and password are required', 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE `username` = ? LIMIT 1");
    $stmt->execute([$user]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($pass, $admin['password_hash'])) {
        jsonError('Admin Credentials Invalid', 401);
    }

    $sessionUser = [
        'id'       => (int)$admin['id'],
        'role'     => 'admin',
        'username' => $admin['username'],
        'name'     => $admin['name']
    ];

    setCurrentUser($sessionUser);

    jsonResponse([
        'success' => true,
        'message' => 'Admin terminal unlocked',
        'user'    => $sessionUser
    ]);

} else {
    jsonError('Unsupported role: ' . htmlspecialchars($role), 400);
}
