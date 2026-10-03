-- =============================================================================
-- Campus Connect - Complete MySQL Database Schema & Seed Data
-- =============================================================================

-- Connect to the pre-created default database in TiDB Serverless
USE test;

-- Disable foreign key checks during schema setup
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `complaint_feedback`;
DROP TABLE IF EXISTS `complaint_comments`;
DROP TABLE IF EXISTS `complaint_logs`;
DROP TABLE IF EXISTS `complaints`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `technicians`;
DROP TABLE IF EXISTS `faculties`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `admins`;

-- -----------------------------------------------------------------------------
-- 1. Table: users (Students)
-- -----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `gr_no` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `avatar` TEXT NULL,
  `warned` TINYINT(1) NOT NULL DEFAULT 0,
  `suspended` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_gr_no` (`gr_no`),
  INDEX `idx_department` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Table: faculties
-- -----------------------------------------------------------------------------
CREATE TABLE `faculties` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department` VARCHAR(100) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_fac_dept` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Table: technicians
-- -----------------------------------------------------------------------------
CREATE TABLE `technicians` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `technician_code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `experience` INT NOT NULL DEFAULT 0,
  `rating` DECIMAL(3,2) NOT NULL DEFAULT 5.00,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tech_code` (`technician_code`),
  INDEX `idx_tech_dept` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Table: admins
-- -----------------------------------------------------------------------------
CREATE TABLE `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Table: complaints
-- -----------------------------------------------------------------------------
CREATE TABLE `complaints` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `complaint_code` VARCHAR(50) NOT NULL UNIQUE,
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `priority` ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Low',
  `reported_by_user_id` INT NULL,
  `reported_by_name` VARCHAR(100) NOT NULL,
  `reported_by_gr_no` VARCHAR(50) NOT NULL,
  `reported_at` VARCHAR(50) NOT NULL,
  `status` VARCHAR(100) NOT NULL DEFAULT 'Complaint Submitted',
  `current_status` VARCHAR(100) NOT NULL DEFAULT 'Complaint Submitted',
  `stage` INT NOT NULL DEFAULT 1,
  `admin_status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
  `admin_verification_date` VARCHAR(50) NULL,
  `admin_final_date` VARCHAR(50) NULL,
  `admin_remark` TEXT NULL,
  `faculty_status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
  `faculty_verification_date` VARCHAR(50) NULL,
  `faculty_id` INT NULL,
  `faculty_department` VARCHAR(100) NULL,
  `faculty_remark` TEXT NULL,
  `technician_id` VARCHAR(50) NULL,
  `technician_name` VARCHAR(100) NULL,
  `technician_action` VARCHAR(50) NULL,
  `technician_status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
  `technician_completion_date` VARCHAR(50) NULL,
  `technician_remark` TEXT NULL,
  `remark` TEXT NULL,
  `work_status` VARCHAR(50) NOT NULL DEFAULT 'Not Started',
  `deadline` VARCHAR(50) NULL,
  `rejection_reason` TEXT NULL,
  `last_rejected_technician_id` VARCHAR(50) NULL,
  `image` TEXT NULL,
  `video` TEXT NULL,
  `proof_image` TEXT NULL,
  `qa_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `qa_feedback` TEXT NULL,
  `feedback_rating` INT NULL,
  `feedback_comment` TEXT NULL,
  `feedback_status` VARCHAR(50) NULL,
  `further_action_requested` TINYINT(1) NOT NULL DEFAULT 0,
  `feedback_submitted_at` VARCHAR(50) NULL,
  `notified_15_day` TINYINT(1) NOT NULL DEFAULT 0,
  `notified_30_day` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_complaint_code` (`complaint_code`),
  INDEX `idx_reported_by_gr` (`reported_by_gr_no`),
  INDEX `idx_category` (`category`),
  INDEX `idx_status` (`status`),
  INDEX `idx_stage` (`stage`),
  INDEX `idx_technician_id` (`technician_id`),
  FOREIGN KEY (`reported_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculties`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Table: complaint_logs
-- -----------------------------------------------------------------------------
CREATE TABLE `complaint_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `complaint_id` INT NOT NULL,
  `status` VARCHAR(100) NOT NULL,
  `note` TEXT NULL,
  `performed_by_user_id` INT NULL,
  `performed_by_name` VARCHAR(100) NOT NULL,
  `performed_by_role` VARCHAR(50) NOT NULL,
  `log_time` VARCHAR(50) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_log_complaint` (`complaint_id`),
  FOREIGN KEY (`complaint_id`) REFERENCES `complaints`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Table: complaint_comments
-- -----------------------------------------------------------------------------
CREATE TABLE `complaint_comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `complaint_id` INT NOT NULL,
  `user_id` INT NULL,
  `user_name` VARCHAR(100) NOT NULL,
  `user_role` VARCHAR(50) NOT NULL,
  `comment` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_comm_complaint` (`complaint_id`),
  FOREIGN KEY (`complaint_id`) REFERENCES `complaints`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Table: complaint_feedback
-- -----------------------------------------------------------------------------
CREATE TABLE `complaint_feedback` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `complaint_id` INT NOT NULL,
  `student_id` INT NULL,
  `rating` INT NOT NULL,
  `comment` TEXT NULL,
  `further_action_requested` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_fb_complaint` (`complaint_id`),
  FOREIGN KEY (`complaint_id`) REFERENCES `complaints`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Table: notifications
-- -----------------------------------------------------------------------------
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `notif_id` VARCHAR(100) NULL,
  `for_gr` VARCHAR(50) NULL,
  `for_dept` VARCHAR(100) NULL,
  `for_tech` VARCHAR(50) NULL,
  `text` TEXT NOT NULL,
  `notif_time` VARCHAR(50) NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_for_gr` (`for_gr`),
  INDEX `idx_for_dept` (`for_dept`),
  INDEX `idx_for_tech` (`for_tech`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;


-- =============================================================================
-- SEED DATA
-- Default password for all students, faculties, technicians: 'password'
-- Hash: '$2y$10$kdme3G.enayqXC1YPLPzGeq8fFamK9uYMh7JJbUli1eNFlNgGgBF.'
--
-- Default password for admin: 'admin123'
-- Hash: '$2y$10$q5LUmrTf4EDqgmWoBww0aO42SzxzCq7MKFSwPthB3Qss6X2oouSf2'
-- =============================================================================

-- Seed Users (Students)
INSERT INTO `users` (`id`, `gr_no`, `name`, `password_hash`, `department`, `avatar`, `warned`, `suspended`) VALUES
(1, '1001', 'Kabir Mehta', '$2y$10$kdme3G.enayqXC1YPLPzGeq8fFamK9uYMh7JJbUli1eNFlNgGgBF.', 'Computer Department', NULL, 0, 0),
(2, '1002', 'Ananya Iyer', '$2y$10$kdme3G.enayqXC1YPLPzGeq8fFamK9uYMh7JJbUli1eNFlNgGgBF.', 'Electrical Department', NULL, 0, 0),
(3, '1003', 'Rohan Verma', '$2y$10$kdme3G.enayqXC1YPLPzGeq8fFamK9uYMh7JJbUli1eNFlNgGgBF.', 'Mechanical Department', NULL, 0, 0),
(4, '1004', 'Priya Sharma', '$2y$10$kdme3G.enayqXC1YPLPzGeq8fFamK9uYMh7JJbUli1eNFlNgGgBF.', 'Civil Department', NULL, 0, 0);

-- Seed Faculties
INSERT INTO `faculties` (`id`, `department`, `name`, `password_hash`, `active`) VALUES
(1, 'Computer Department', 'Computer Faculty Advisor', '$2y$10$kdme3G.enayqXC1YPLPzGeq8fFamK9uYMh7JJbUli1eNFlNgGgBF.', 1),
(2, 'Electrical Department', 'Electrical Faculty Advisor', '$2y$10$kdme3G.enayqXC1YPLPzGeq8fFamK9uYMh7JJbUli1eNFlNgGgBF.', 1),
(3, 'Mechanical Department', 'Mechanical Faculty Advisor', '$2y$10$kdme3G.enayqXC1YPLPzGeq8fFamK9uYMh7JJbUli1eNFlNgGgBF.', 1),
(4, 'Civil Department', 'Civil Faculty Advisor', '$2y$10$kdme3G.enayqXC1YPLPzGeq8fFamK9uYMh7JJbUli1eNFlNgGgBF.', 1);

-- Seed Technicians
INSERT INTO `technicians` (`id`, `technician_code`, `name`, `department`, `experience`, `rating`, `active`, `password_hash`) VALUES
(1, 'TECH-01', 'Dilip Prasad', 'Electrical Department', 5, 4.80, 1, '$2y$10$kdme3G.enayqXC1YPLPzGeq8fFamK9uYMh7JJbUli1eNFlNgGgBF.'),
(2, 'TECH-02', 'Jagdish Panchal', 'Mechanical Department', 8, 4.70, 1, '$2y$10$kdme3G.enayqXC1YPLPzGeq8fFamK9uYMh7JJbUli1eNFlNgGgBF.'),
(3, 'TECH-03', 'Ankit Sharma', 'Computer Department', 3, 4.90, 1, '$2y$10$kdme3G.enayqXC1YPLPzGeq8fFamK9uYMh7JJbUli1eNFlNgGgBF.'),
(4, 'TECH-04', 'Madan Lal', 'Civil Department', 12, 4.50, 1, '$2y$10$kdme3G.enayqXC1YPLPzGeq8fFamK9uYMh7JJbUli1eNFlNgGgBF.');

-- Seed Admin
INSERT INTO `admins` (`id`, `username`, `name`, `password_hash`) VALUES
(1, 'admin', 'Principal Office Workspace', '$2y$10$q5LUmrTf4EDqgmWoBww0aO42SzxzCq7MKFSwPthB3Qss6X2oouSf2');

-- Seed Sample Complaints
INSERT INTO `complaints` (
  `id`, `complaint_code`, `title`, `category`, `description`, `location`, `priority`,
  `reported_by_user_id`, `reported_by_name`, `reported_by_gr_no`, `reported_at`,
  `status`, `current_status`, `stage`,
  `admin_status`, `admin_verification_date`,
  `faculty_status`, `faculty_verification_date`, `faculty_department`,
  `technician_id`, `technician_name`, `technician_action`, `technician_status`, `technician_completion_date`,
  `work_status`, `deadline`, `rejection_reason`,
  `image`, `video`, `proof_image`, `remark`, `qa_verified`, `qa_feedback`,
  `admin_final_date`, `created_at`
) VALUES
(
  1, 'COMP-201', 'Danger: Open wire sparking in Corridor', 'Electrical Department',
  'Naked copper wires are hanging loose from class 201 circuit board. Sparks visible when turning fan on.',
  'Engineering Block A', 'High',
  1, 'Kabir Mehta', '1001', '16/07/2026 09:30 AM',
  'Assigned to Faculty', 'Assigned to Faculty', 2,
  'Approved', '16/07/2026 10:15 AM',
  'Pending', NULL, 'Electrical Department',
  NULL, NULL, NULL, 'Pending', NULL,
  'Not Started', '', '',
  'https://images.unsplash.com/photo-1558346490-a72e53ae2d4f?q=80&w=600', '', '', '', 0, '',
  NULL, '2026-07-16 09:30:00'
),
(
  2, 'COMP-202', 'Tubelight not working & Classroom Fan off', 'Electrical Department',
  'Back row tubelight completely dark, fan makes buzzing noise.',
  'Science Library Room 2', 'Medium',
  2, 'Ananya Iyer', '1002', '15/07/2026 02:15 PM',
  'Completed', 'Completed', 7,
  'Approved', '15/07/2026 02:30 PM',
  'Verified', '16/07/2026 11:30 AM', 'Electrical Department',
  'TECH-01', 'Dilip Prasad', 'Accepted', 'Completed', '16/07/2026 10:00 AM',
  'Completed', '17/07/2026', '',
  'https://images.unsplash.com/photo-1565814329452-e1efa11c5b89?q=80&w=600', '', 'https://images.unsplash.com/photo-1517254485319-68a189ddc2f1?q=80&w=600',
  'Replaced bulb and starter elements.', 1, 'Inspected classrooms, verified perfectly operational.',
  '16/07/2026 11:45 AM', '2026-07-15 14:15:00'
),
(
  3, 'COMP-203', 'Lab 4 Server Rack Switch Network Failure', 'Computer Department',
  'Main rack switch in CS Lab 4 stopped responding. Network dropped for 40 student PCs during practical exam.',
  'CS Block Lab 4', 'High',
  1, 'Kabir Mehta', '1001', '16/07/2026 11:00 AM',
  'Work in Progress', 'Work in Progress', 4,
  'Approved', '16/07/2026 11:10 AM',
  'Pending', NULL, 'Computer Department',
  'TECH-03', 'Ankit Sharma', 'Accepted', 'Accepted', NULL,
  'In Progress', '17/07/2026', '',
  'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?q=80&w=600', '', '', '', 0, '',
  NULL, '2026-07-16 11:00:00'
),
(
  4, 'COMP-204', 'Smartboard & Projector Signal Flickering', 'Computer Department',
  'HDMI output on smartboard flickering and cutting video signal every 2 minutes during lectures.',
  'CS Seminar Hall B', 'Medium',
  1, 'Kabir Mehta', '1001', '16/07/2026 01:20 PM',
  'Work Completed by Technician', 'Work Completed by Technician', 5,
  'Approved', '16/07/2026 01:30 PM',
  'Pending', NULL, 'Computer Department',
  'TECH-03', 'Ankit Sharma', 'Accepted', 'Completed', '16/07/2026 03:00 PM',
  'Completed', '18/07/2026', '',
  'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?q=80&w=600', '', 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?q=80&w=600',
  'Replaced faulty HDMI cable & re-calibrated projector output.', 0, '',
  NULL, '2026-07-16 13:20:00'
),
(
  5, 'COMP-205', 'Workshop Lathe Machine Emergency Stop Stuck', 'Mechanical Department',
  'Emergency cut-off switch on Lathe Unit 3 is jammed depressed. Machine unable to power on safely.',
  'Central Mechanical Workshop', 'High',
  3, 'Rohan Verma', '1003', '16/07/2026 10:00 AM',
  'Faculty Verified', 'Faculty Verified', 6,
  'Approved', '16/07/2026 10:20 AM',
  'Verified', '16/07/2026 02:30 PM', 'Mechanical Department',
  'TECH-02', 'Jagdish Panchal', 'Accepted', 'Completed', '16/07/2026 01:45 PM',
  'Completed', '18/07/2026', '',
  'https://images.unsplash.com/photo-1581092160607-ee22621dd758?q=80&w=600', '', 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?q=80&w=600',
  'Repaired emergency spring return mechanism and cleaned internal contacts.', 1, 'Audited lathe machine operation. Safety stop triggers instantly. Forwarded to Admin for final closure.',
  NULL, '2026-07-16 10:00:00'
),
(
  6, 'COMP-208', 'Damaged Paver Blocks near Dept Quadrangle', 'Civil Department',
  'Sunken and loose paver blocks causing tripping hazard at the main department entrance pathway.',
  'Civil Department Entrance', 'Medium',
  4, 'Priya Sharma', '1004', '16/07/2026 02:00 PM',
  'Complaint Submitted', 'Complaint Submitted', 1,
  'Pending', NULL,
  'Pending', NULL, 'Civil Department',
  NULL, NULL, NULL, 'Pending', NULL,
  'Not Started', '', '',
  'https://images.unsplash.com/photo-1590069261209-f8e9b8642343?q=80&w=600', '', '', '', 0, '',
  NULL, '2026-07-16 14:00:00'
);

-- Seed Sample Complaint Logs
INSERT INTO `complaint_logs` (`complaint_id`, `status`, `note`, `performed_by_name`, `performed_by_role`, `log_time`) VALUES
-- Logs for COMP-201
(1, 'Complaint Submitted', 'Submitted with photo evidence', 'Kabir Mehta', 'student', '16/07/2026 09:30 AM'),
(1, 'Admin Verified', 'Verified by Admin & assigned to Electrical Faculty Advisor', 'Admin Office', 'admin', '16/07/2026 10:15 AM'),

-- Logs for COMP-202
(2, 'Complaint Submitted', 'Reported by Student', 'Ananya Iyer', 'student', '15/07/2026 02:15 PM'),
(2, 'Admin Verified', 'Verified by Admin & assigned to Electrical Faculty', 'Admin Office', 'admin', '15/07/2026 02:30 PM'),
(2, 'Faculty Assigned Tech', 'Dispatched to Technician Dilip Prasad', 'Electrical Faculty Advisor', 'faculty', '15/07/2026 03:00 PM'),
(2, 'Technician Accepted', 'Accepted by Technician Dilip Prasad', 'Dilip Prasad', 'technician', '15/07/2026 03:30 PM'),
(2, 'Work in Progress', 'Repair and electrical replacement in progress', 'Dilip Prasad', 'technician', '15/07/2026 04:00 PM'),
(2, 'Technician Completed', 'Work finished, photo proof uploaded & sent to Faculty', 'Dilip Prasad', 'technician', '16/07/2026 10:00 AM'),
(2, 'Faculty Verified', 'Audited and verified by Faculty - Sent to Admin', 'Electrical Faculty Advisor', 'faculty', '16/07/2026 11:30 AM'),
(2, 'Admin Final Verified', 'Admin verified faculty audit and approved completion.', 'Admin Office', 'admin', '16/07/2026 11:45 AM'),
(2, 'Completed', 'Complaint fully completed and closed.', 'System', 'system', '16/07/2026 11:45 AM'),

-- Logs for COMP-203
(3, 'Complaint Submitted', 'Auto High priority', 'Kabir Mehta', 'student', '16/07/2026 11:00 AM'),
(3, 'Admin Verified', 'Verified by Admin & assigned to Computer Faculty', 'Admin Office', 'admin', '16/07/2026 11:10 AM'),
(3, 'Faculty Assigned Tech', 'Assigned to Technician Ankit Sharma', 'Computer Faculty Advisor', 'faculty', '16/07/2026 11:15 AM'),
(3, 'Technician Accepted', 'Accepted work order by Ankit Sharma', 'Ankit Sharma', 'technician', '16/07/2026 11:20 AM'),
(3, 'Work in Progress', 'Switch diagnostics and patch cable replacement underway', 'Ankit Sharma', 'technician', '16/07/2026 11:30 AM'),

-- Logs for COMP-204
(4, 'Complaint Submitted', 'Reported by Student', 'Kabir Mehta', 'student', '16/07/2026 01:20 PM'),
(4, 'Admin Verified', 'Verified by Admin & assigned to Computer Faculty', 'Admin Office', 'admin', '16/07/2026 01:30 PM'),
(4, 'Faculty Assigned Tech', 'Dispatched to Ankit Sharma', 'Computer Faculty Advisor', 'faculty', '16/07/2026 01:45 PM'),
(4, 'Technician Accepted', 'Accepted work order by Ankit Sharma', 'Ankit Sharma', 'technician', '16/07/2026 02:00 PM'),
(4, 'Work in Progress', 'Display port recabling in progress', 'Ankit Sharma', 'technician', '16/07/2026 02:15 PM'),
(4, 'Technician Completed', 'Completed and submitted for Faculty Verification', 'Ankit Sharma', 'technician', '16/07/2026 03:00 PM'),

-- Logs for COMP-205
(5, 'Complaint Submitted', 'Safety risk identified', 'Rohan Verma', 'student', '16/07/2026 10:00 AM'),
(5, 'Admin Verified', 'Verified by Admin & assigned to Mechanical Faculty', 'Admin Office', 'admin', '16/07/2026 10:20 AM'),
(5, 'Faculty Assigned Tech', 'Assigned to Technician Jagdish Panchal', 'Mechanical Faculty Advisor', 'faculty', '16/07/2026 10:45 AM'),
(5, 'Technician Accepted', 'Accepted by Technician Jagdish Panchal', 'Jagdish Panchal', 'technician', '16/07/2026 11:00 AM'),
(5, 'Work in Progress', 'Safety mechanism overhaul underway', 'Jagdish Panchal', 'technician', '16/07/2026 11:30 AM'),
(5, 'Technician Completed', 'Completed with photo proof & submitted to Faculty', 'Jagdish Panchal', 'technician', '16/07/2026 01:45 PM'),
(5, 'Faculty Verified', 'Audited and verified by Mechanical Faculty - Sent to Admin', 'Mechanical Faculty Advisor', 'faculty', '16/07/2026 02:30 PM'),

-- Logs for COMP-208
(6, 'Complaint Submitted', 'Submitted for Admin Verification', 'Priya Sharma', 'student', '16/07/2026 02:00 PM');

-- Seed Sample Notifications
INSERT INTO `notifications` (`notif_id`, `for_gr`, `for_dept`, `for_tech`, `text`, `notif_time`, `is_read`) VALUES
('N1', '1001', NULL, NULL, 'Admin verified and assigned COMP-201 to Electrical Faculty', '16/07/2026 10:15 AM', 0);
