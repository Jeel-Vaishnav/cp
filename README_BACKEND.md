# Campus Connect — PHP 8 + MySQL Backend Integration & Setup Guide

**Campus Connect** is an automated multi-role campus grievance and complaint resolution platform built with vanilla HTML/CSS/JavaScript on the frontend and pure PHP 8 + MariaDB/MySQL (PDO) on the backend.

The platform runs on **XAMPP (Apache + MySQL/MariaDB + phpMyAdmin)** on `localhost`.

---

## 1. Prerequisites & Environment

* **Operating System**: Windows (or macOS / Linux)
* **Server Stack**: [XAMPP](https://www.apachefriends.org/) (Version 8.0 or higher recommended)
  * Apache Web Server
  * MySQL / MariaDB Server
  * PHP 8.0+ with `pdo_mysql` and `session` enabled
  * phpMyAdmin

---

## 2. Installation & Quick Setup

### Step 1: Copy Project to XAMPP `htdocs`
Copy or link the `Campus - Connect` folder into your XAMPP web root directory:
```
C:\xampp\htdocs\Campus - Connect
```
*(On Windows, you can also use an NTFS Directory Junction if you develop from another folder):*
```powershell
cmd /c mklink /J "C:\xampp\htdocs\Campus - Connect" "C:\path\to\Campus - Connect"
```

### Step 2: Start Apache and MySQL
1. Open the **XAMPP Control Panel**.
2. Click **Start** for **Apache**.
3. Click **Start** for **MySQL**.
4. Ensure both services show green status indicators with ports `80` (or `8080`) and `3306`.

### Step 3: Create & Import the Database
1. Open your browser and navigate to phpMyAdmin:
   ```
   http://localhost/phpmyadmin/
   ```
2. Click **New** on the left navigation panel to create a new database.
3. Database Name: `campus_connect`
4. Collation: `utf8mb4_unicode_ci`
5. Click **Create**.
6. Select the newly created `campus_connect` database, then click the **Import** tab in the top menu.
7. Click **Choose File** and browse to:
   ```
   C:\xampp\htdocs\Campus - Connect\database\campus_connect.sql
   ```
8. Click **Import** (or **Go**) at the bottom.
9. Verify that all 8 tables are created and seeded:
   * `users`
   * `faculties`
   * `technicians`
   * `admins`
   * `complaints`
   * `complaint_logs`
   * `complaint_comments`
   * `complaint_feedback`
   * `notifications`

### Step 4: Configure Database Connection
Check the configuration file:
`api/config/database.php`

Default XAMPP configuration:
```php
<?php
$host = 'localhost';
$db   = 'campus_connect';
$user = 'root';
$pass = ''; // Default XAMPP has no password for root
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = new PDO($dsn, $user, $pass, $options);
```

### Step 5: Access the Web Application
Open your web browser and navigate to:
```
http://localhost/Campus%20-%20Connect/
```

---

## 3. Test Login Credentials

All passwords in the database are hashed with PHP `password_hash()`. For testing, default passwords are provided below:

| Role | Identifier / Field | Login Credential | Default Password | Notes |
| :--- | :--- | :--- | :--- | :--- |
| **Student** | G.R. Number | `1001` | `password` | Kabir Mehta (Computer Dept) |
| **Student** | G.R. Number | `1002` | `password` | Ananya Iyer (Electrical Dept) |
| **Student** | G.R. Number | `1003` | `password` | Rohan Verma (Mechanical Dept) |
| **Student** | G.R. Number | `1004` | `password` | Priya Sharma (Civil Dept) |
| **Faculty** | Department | `Computer Department` | `password` | Computer Faculty Advisor |
| **Faculty** | Department | `Electrical Department` | `password` | Electrical Faculty Advisor |
| **Faculty** | Department | `Mechanical Department` | `password` | Mechanical Faculty Advisor |
| **Faculty** | Department | `Civil Department` | `password` | Civil Faculty Advisor |
| **Technician** | Tech Code | `TECH-01` | `password` | Dilip Prasad (Electrical) |
| **Technician** | Tech Code | `TECH-02` | `password` | Jagdish Panchal (Mechanical) |
| **Technician** | Tech Code | `TECH-03` | `password` | Ankit Sharma (Computer) |
| **Technician** | Tech Code | `TECH-04` | `password` | Madan Lal (Civil) |
| **Admin** | Username | `admin` | `admin123` | Executive Dean / Principal Office |

---

## 4. Architecture & 7-Stage Workflow

The system enforces a strict 7-stage lifecycle with atomic database transactions and audit logging:

```
[Stage 1: Complaint Submitted] (Student reports complaint with keywords, location, photo)
               │
               ▼
[Stage 2: Assigned to Faculty] (Admin verifies and routes ticket to Department Faculty)
               │
               ▼
[Stage 3: Assigned to Technician] (Faculty reviews department queue and dispatches Technician)
               │
               ├───► [Technician Rejection / Decline] ──► (Returns to Faculty for reassignment)
               ▼
[Stage 4: Work in Progress] (Technician accepts work order and begins maintenance)
               │
               ▼
[Stage 5: Work Completed by Technician] (Technician finishes work, writes remark, uploads proof photo)
               │
               ▼
[Stage 6: Faculty Verified] (Faculty audits technician's work and QA verification)
               │
               ▼
[Stage 7: Completed] (Admin final review and closure)
               │
               ▼
[Student Feedback & Review] (Student rates 1-5 stars, gives comments, or requests further action)
```

---

## 5. API Endpoints Overview

All APIs return consistent JSON envelopes:
```json
{
  "success": true,
  "message": "Operation successful"
}
```

### Authentication (`/api/auth/`)
* `POST /api/auth/login.php`: Validates credentials against MySQL and initializes a PHP session.
* `POST /api/auth/logout.php`: Destroys the PHP session.
* `GET  /api/auth/session.php`: Returns the current authenticated session user and role.
* `POST /api/auth/register.php`: Registers a new student into the database.

### Complaints (`/api/complaints/`)
* `POST /api/complaints/create.php`: Creates a new complaint (Stage 1) with auto-generated code (e.g. `COMP-209`).
* `GET  /api/complaints/list.php`: Returns role-scoped complaints.
* `GET  /api/complaints/get.php?id={id}`: Detailed complaint view with logs, comments, and feedback.
* `POST /api/complaints/assign.php`: Admin routes to Faculty (Stage 2) or Faculty assigns Technician (Stage 3).
* `POST /api/complaints/reject.php`: Admin rejects ticket (Stage 0) or Technician declines ticket (returns to Stage 2 with reason).
* `POST /api/complaints/update.php`: Technician accepts ticket (moves to Stage 4: Work in Progress).
* `POST /api/complaints/complete.php`: Technician marks completed with proof image upload (Stage 5).
* `POST /api/complaints/verify.php`: Faculty QA audit (Stage 6) or Admin final approval (Stage 7).
* `POST /api/complaints/feedback.php`: Student rating (1-5 stars), review comment, and re-work flag.
* `GET  /api/complaints/comments.php`: List comments on a complaint.
* `POST /api/complaints/comments.php`: Add a new comment.
* `GET  /api/complaints/public.php`: Sanitized feed endpoint for `feed.html` with department filtering.

### Administrative & Staff (`/api/staff/` & `/api/users/`)
* `GET  /api/staff/list.php`: List all technicians and faculty.
* `POST /api/staff/save.php`: Create a new technician or update existing staff.
* `POST /api/staff/status.php`: Toggle active/inactive status for technicians.
* `GET  /api/users/list.php`: List student directory with warning and suspension status.
* `POST /api/users/status.php`: Admin toggle for student warning (`warned`) or suspension (`suspended`).
* `POST /api/users/update.php`: User profile and avatar configuration.

### Reports & Sync
* `GET  /api/reports/complaints.php`: Admin analytics and CSV export (`?format=csv`).
* `GET  /api/sync.php`: Unified state sync endpoint loading live MySQL database records into frontend state.
* `GET  /api/notifications/list.php`: Role-based notifications.
* `POST /api/notifications/read.php`: Mark notifications as read.

---

## 6. Directory Structure

```text
Campus - Connect/
├── index.html                   # Landing page with live statistics
├── login.html                   # Multi-role login & registration
├── portal.html                  # 4-Step complaint filing portal
├── roles.html                   # Multi-role dashboards (Student, Faculty, Tech, Admin)
├── feed.html                    # Public live transparency complaint feed
│
├── css/
│   ├── style.css                # Global design system & animations
│   ├── home.css                 # Landing page styles
│   ├── login.css                # Authentication styling
│   ├── portal.css               # Complaint filing layout
│   ├── roles.css                # Dashboard styling
│   └── feed.css                 # Public feed layout
│
├── js/
│   ├── main.js                  # App state, sync, session watchdog, notifications
│   ├── login.js                 # Frontend auth handlers (fetch -> api/auth/)
│   ├── portal.js                # Complaint submission & keyword priority detection
│   ├── roles.js                 # Role views, dispatch, QA, logs, and CSV export
│   ├── feed.js                  # Public feed consumer (fetch -> api/complaints/public.php)
│   ├── navigation.js            # Header navigation & theme toggle
│   └── animations.js            # UI micro-animations and tilt effects
│
├── api/
│   ├── config/
│   │   └── database.php         # PDO database configuration
│   ├── common.php               # Auth guard, helpers, upload validators, logger
│   ├── sync.php                 # Live state synchronization
│   ├── auth/                    # Login, logout, session, registration
│   ├── complaints/              # 7-stage workflow endpoints
│   ├── staff/                   # Technician & faculty management
│   ├── users/                   # Student management & warnings
│   ├── notifications/           # Notification dispatch & read
│   └── reports/                 # Operational reports & CSV generation
│
├── database/
│   └── campus_connect.sql       # Full schema & initial test seed data
│
├── uploads/
│   ├── complaints/              # Student photo evidence
│   ├── proofs/                  # Technician resolution proof images
│   └── avatars/                 # User avatar uploads
│
└── README_BACKEND.md            # Backend integration guide
```

---

## 7. Media Upload Guidelines

* Image and video evidence are **never stored as large base64 strings in MySQL**.
* Files are validated on upload for MIME type (JPEG, PNG, WebP, GIF, MP4, WebM) and file size (max 8MB for images, max 25MB for videos).
* Stored on the local file system with cryptographically random filenames under `uploads/complaints/`, `uploads/proofs/`, or `uploads/avatars/`.
* The database stores only the relative web URI.

---

## 8. Verification & Test Suite

Automated verification scripts are included in `scratch/`:
* `scratch/test_workflow.php`: Tests the complete 7-stage workflow end-to-end against live MySQL.
* `scratch/test_rejection.php`: Tests the technician decline and faculty reassignment workflow.

Run from CLI:
```powershell
C:\xampp\php\php.exe scratch/test_workflow.php
C:\xampp\php\php.exe scratch/test_rejection.php
```

---

## 9. Troubleshooting

1. **Database connection failed: Access denied for user 'root'@'localhost'**:
   * If your MySQL root user has a password, update `$pass` in `api/config/database.php`.
2. **Packet bigger than max_allowed_packet**:
   * Resolved: Media is now streamed directly to `uploads/` rather than embedded in SQL statements.
3. **Session expires immediately**:
   * Check that your browser allows cookies for `localhost` and that PHP session folder (`C:\xampp\tmp`) is writable.
4. **404 Not Found on API endpoints**:
   * Ensure the project is located at `C:\xampp\htdocs\Campus - Connect` so URLs resolve to `http://localhost/Campus%20-%20Connect/api/...`.
