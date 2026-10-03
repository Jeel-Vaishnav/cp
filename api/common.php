<?php
/**
 * Campus Connect - Shared API Utilities & Helpers
 */

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Support Cross-Origin Resource Sharing (CORS) for decoupled cloud hosting
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session parameters (supports cross-site cookies if on HTTPS)
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => $isHttps ? 'None' : 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/config/database.php';

/**
 * Return JSON response and exit
 */
function jsonResponse($data, int $statusCode = 200): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
    }
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Return JSON error response and exit
 */
function jsonError(string $message, int $statusCode = 400, array $extra = []): void {
    $payload = array_merge([
        'success' => false,
        'message' => $message
    ], $extra);
    jsonResponse($payload, $statusCode);
}

/**
 * Parse JSON or POST input body
 */
function getRequestData(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

/**
 * Retrieve current logged-in user from session
 */
function getCurrentUser(): ?array {
    return $_SESSION['campus_user'] ?? null;
}

/**
 * Set current logged-in user in session
 */
function setCurrentUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['campus_user'] = $user;
}

/**
 * Destroy current session
 */
function clearUserSession(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Ensure user is logged in, optionally check for allowed roles
 */
function requireAuth(array $allowedRoles = []): array {
    $user = getCurrentUser();
    if (!$user) {
        jsonError('Unauthorized. Please log in.', 401);
    }
    if (!empty($allowedRoles) && !in_array($user['role'] ?? '', $allowedRoles, true)) {
        jsonError('Forbidden. Access restricted for your role.', 403);
    }
    return $user;
}

/**
 * Formats current date/time to match frontend: "DD/MM/YYYY hh:mm A"
 */
function nowStr(): string {
    return date('d/m/Y h:i A');
}

/**
 * Handles media upload either from $_FILES or base64 data string
 * Stores the file in uploads/{subfolder}/ and returns relative path
 */
function handleMediaUpload(?string $inputName, ?string $base64Data, string $subfolder): ?string {
    $uploadBaseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $subfolder;
    if (!is_dir($uploadBaseDir)) {
        mkdir($uploadBaseDir, 0755, true);
    }

    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'video/mp4'  => 'mp4',
        'video/webm' => 'webm',
    ];

    // 1. Check $_FILES
    if ($inputName && isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$inputName];
        if ($file['size'] > 25 * 1024 * 1024) { // 25 MB limit
            jsonError('Uploaded file exceeds 25MB limit', 400);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset($allowedMimes[$mime])) {
            jsonError('Invalid file type: ' . htmlspecialchars($mime), 400);
        }

        $ext = $allowedMimes[$mime];
        $filename = uniqid($subfolder . '_' . date('Ymd_His') . '_', true) . '.' . $ext;
        $destPath = $uploadBaseDir . DIRECTORY_SEPARATOR . $filename;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            return 'uploads/' . $subfolder . '/' . $filename;
        }
    }

    // 2. Check Base64 data string
    if (!empty($base64Data)) {
        // If it's already an external HTTP/HTTPS URL, preserve it
        if (preg_match('/^https?:\/\//i', $base64Data)) {
            return $base64Data;
        }

        // If it's already a relative uploads path, preserve it
        if (str_starts_with($base64Data, 'uploads/')) {
            return $base64Data;
        }

        // Handle data URI scheme e.g. "data:image/png;base64,..."
        if (preg_match('/^data:([a-zA-Z0-9]+\/[a-zA-Z0-9\.\-\+]+);base64,(.+)$/', $base64Data, $matches)) {
            $mime = strtolower($matches[1]);
            $raw = base64_decode($matches[2]);
            if ($raw === false) {
                return null;
            }

            if (!isset($allowedMimes[$mime])) {
                // Default to jpg if unknown image type
                $ext = str_contains($mime, 'video') ? 'mp4' : 'jpg';
            } else {
                $ext = $allowedMimes[$mime];
            }

            $filename = uniqid($subfolder . '_' . date('Ymd_His') . '_', true) . '.' . $ext;
            $destPath = $uploadBaseDir . DIRECTORY_SEPARATOR . $filename;
            if (file_put_contents($destPath, $raw) !== false) {
                return 'uploads/' . $subfolder . '/' . $filename;
            }
        }
    }

    return null;
}

/**
 * Record a workflow action log in complaint_logs table
 */
function logWorkflowAction(PDO $pdo, int $complaintId, string $status, string $note, ?array $user = null): void {
    if (!$user) {
        $user = getCurrentUser() ?? ['name' => 'System', 'role' => 'system', 'id' => null];
    }

    $stmt = $pdo->prepare("
        INSERT INTO `complaint_logs` 
        (`complaint_id`, `status`, `note`, `performed_by_user_id`, `performed_by_name`, `performed_by_role`, `log_time`)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $complaintId,
        $status,
        $note,
        $user['id'] ?? null,
        $user['name'] ?? 'System',
        $user['role'] ?? 'system',
        nowStr()
    ]);
}

/**
 * Create a system notification in notifications table
 */
function pushNotification(PDO $pdo, ?string $forGr, ?string $forDept, ?string $forTech, string $text): void {
    $stmt = $pdo->prepare("
        INSERT INTO `notifications` (`notif_id`, `for_gr`, `for_dept`, `for_tech`, `text`, `notif_time`, `is_read`)
        VALUES (?, ?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([
        'N' . round(microtime(true) * 1000),
        $forGr,
        $forDept,
        $forTech,
        $text,
        nowStr()
    ]);
}

/**
 * Transform a database complaint row into frontend-compatible complaint object
 */
function formatComplaintForFrontend(array $row, ?array $logs = null): array {
    $stage = (int)($row['stage'] ?? 1);
    $status = $row['status'] ?? $row['current_status'] ?? 'Complaint Submitted';

    return [
        'db_id'                      => (int)$row['id'],
        'id'                         => $row['complaint_code'],
        'complaint_code'             => $row['complaint_code'],
        'title'                      => $row['title'] ?? '',
        'category'                   => $row['category'] ?? '',
        'dept'                       => $row['category'] ?? '',
        'description'                => $row['description'] ?? '',
        'desc'                       => $row['description'] ?? '',
        'location'                   => $row['location'] ?? '',
        'priority'                   => $row['priority'] ?? 'Low',
        'reportedBy'                 => $row['reported_by_name'] ?? '',
        'reported_by'                => $row['reported_by_name'] ?? '',
        'reportedByGr'               => $row['reported_by_gr_no'] ?? '',
        'reported_by_gr'             => $row['reported_by_gr_no'] ?? '',
        'reportedAt'                 => $row['reported_at'] ?? '',
        'reported_at'                => $row['reported_at'] ?? '',
        'status'                     => $status,
        'current_status'             => $row['current_status'] ?? $status,
        'stage'                      => $stage,
        'admin_status'               => $row['admin_status'] ?? 'Pending',
        'admin_verification_date'    => $row['admin_verification_date'] ?? null,
        'admin_final_date'           => $row['admin_final_date'] ?? null,
        'admin_remark'               => $row['admin_remark'] ?? '',
        'faculty_status'             => $row['faculty_status'] ?? 'Pending',
        'faculty_verification_date'  => $row['faculty_verification_date'] ?? null,
        'faculty_department'         => $row['faculty_department'] ?? $row['category'] ?? '',
        'faculty_remark'             => $row['faculty_remark'] ?? '',
        'technician_id'              => $row['technician_id'] ?? null,
        'techId'                     => $row['technician_id'] ?? null,
        'technician_name'            => $row['technician_name'] ?? null,
        'techName'                   => $row['technician_name'] ?? null,
        'technician_action'          => $row['technician_action'] ?? null,
        'technician_status'          => $row['technician_status'] ?? 'Pending',
        'technician_completion_date' => $row['technician_completion_date'] ?? null,
        'work_status'                => $row['work_status'] ?? 'Not Started',
        'deadline'                   => $row['deadline'] ?? '',
        'rejectionReason'            => $row['rejection_reason'] ?? '',
        'rejection_reason'           => $row['rejection_reason'] ?? '',
        'lastRejectedTech'           => $row['last_rejected_technician_id'] ?? null,
        'image'                      => $row['image'] ?? '',
        'video'                      => $row['video'] ?? '',
        'proofImg'                   => $row['proof_image'] ?? '',
        'proof_image'                => $row['proof_image'] ?? '',
        'remark'                     => $row['remark'] ?? $row['technician_remark'] ?? '',
        'qaVerified'                 => (bool)($row['qa_verified'] ?? false),
        'qa_verified'                => (bool)($row['qa_verified'] ?? false),
        'qaFeedback'                 => $row['qa_feedback'] ?? '',
        'qa_feedback'                => $row['qa_feedback'] ?? '',
        'feedback'                   => $row['feedback_rating'] ?? null,
        'feedbackComment'            => $row['feedback_comment'] ?? '',
        'feedback_comment'           => $row['feedback_comment'] ?? '',
        'feedbackStatus'             => $row['feedback_status'] ?? null,
        'feedback_status'            => $row['feedback_status'] ?? null,
        'furtherActionRequested'     => (bool)($row['further_action_requested'] ?? false),
        'further_action_requested'   => (bool)($row['further_action_requested'] ?? false),
        'feedbackTime'               => $row['feedback_submitted_at'] ?? null,
        'notified15Day'              => (bool)($row['notified_15_day'] ?? false),
        'notified30Day'              => (bool)($row['notified_30_day'] ?? false),
        'logs'                       => is_array($logs) ? $logs : []
    ];
}
