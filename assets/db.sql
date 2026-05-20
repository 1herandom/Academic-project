CREATE DATABASE IF NOT EXISTS smart_edu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_edu;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS group_chat_members;
DROP TABLE IF EXISTS group_chats;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS chat_conversations;
DROP TABLE IF EXISTS quiz_answers;
DROP TABLE IF EXISTS quiz_attempts;
DROP TABLE IF EXISTS question_options;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS quizzes;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS materials;
DROP TABLE IF EXISTS notices;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS attendance_records;
DROP TABLE IF EXISTS attendance_sessions;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS support_requests;
DROP TABLE IF EXISTS password_requests;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institutional_id VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    personal_email VARCHAR(180) NULL,
    password_hash VARCHAR(255) NOT NULL,
    temp_password TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    profile_photo VARCHAR(255) NULL,
    remember_selector VARCHAR(32) NULL,
    remember_token_hash VARCHAR(255) NULL,
    remember_expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institutional_id VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    personal_email VARCHAR(180) NULL,
    teacher_code CHAR(4) NULL,
    password_hash VARCHAR(255) NOT NULL,
    temp_password TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    profile_photo VARCHAR(255) NULL,
    remember_selector VARCHAR(32) NULL,
    remember_token_hash VARCHAR(255) NULL,
    remember_expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institutional_id VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    cluster_group VARCHAR(100) NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    personal_email VARCHAR(180) NULL,
    student_code CHAR(8) NULL,
    password_hash VARCHAR(255) NOT NULL,
    temp_password TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    profile_photo VARCHAR(255) NULL,
    remember_selector VARCHAR(32) NULL,
    remember_token_hash VARCHAR(255) NULL,
    remember_expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_title VARCHAR(120) NOT NULL,
    teacher_user_id INT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    CONSTRAINT fk_courses_teacher FOREIGN KEY (teacher_user_id) REFERENCES teachers(id) ON DELETE SET NULL,
    CONSTRAINT fk_courses_created_by FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_user_id INT NOT NULL,
    enrolled_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    UNIQUE KEY unique_enrollment (course_id, student_user_id),
    CONSTRAINT fk_enroll_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_enroll_student FOREIGN KEY (student_user_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE attendance_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    teacher_user_id INT NULL,
    session_date DATETIME NOT NULL,
    session_type ENUM('L','T','W') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    UNIQUE KEY unique_attendance (course_id, session_date, session_type),
    CONSTRAINT fk_session_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_session_teacher FOREIGN KEY (teacher_user_id) REFERENCES teachers(id) ON DELETE CASCADE
);

CREATE TABLE attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendance_session_id INT NOT NULL,
    student_user_id INT NOT NULL,
    status ENUM('Present','Absent') NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    UNIQUE KEY unique_record (attendance_session_id, student_user_id),
    CONSTRAINT fk_record_session FOREIGN KEY (attendance_session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_record_student FOREIGN KEY (student_user_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    deadline_at DATETIME NOT NULL,
    subject_link VARCHAR(255) NULL,
    brief_file VARCHAR(255) NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    CONSTRAINT fk_assignment_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignment_teacher FOREIGN KEY (created_by) REFERENCES teachers(id) ON DELETE CASCADE
);

CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_user_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size INT NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_submission (assignment_id, student_user_id),
    CONSTRAINT fk_submission_assignment 
        FOREIGN KEY (assignment_id) 
        REFERENCES assignments(id) 
        ON DELETE CASCADE,
    CONSTRAINT fk_submission_student 
        FOREIGN KEY (student_user_id) 
        REFERENCES students(id) 
        ON DELETE CASCADE
);

CREATE TABLE materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    category ENUM('Lecture Notes','Lab Sheets','Reading Material') NOT NULL,
    file_path VARCHAR(255) NULL,
    video_link VARCHAR(255) NULL,
    file_type ENUM('PDF','PPTX','MP4') NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    CONSTRAINT fk_material_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_material_teacher FOREIGN KEY (created_by) REFERENCES teachers(id) ON DELETE CASCADE
);

CREATE TABLE notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    sender_role ENUM('Admin', 'Teacher') NOT NULL,
    sender_id INT NOT NULL,
    target_type ENUM('all', 'all_teachers', 'all_students', 'course', 'teacher', 'student') NOT NULL,
    target_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP()
);

CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    teacher_user_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 30,
    created_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    CONSTRAINT fk_quiz_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_quiz_teacher FOREIGN KEY (teacher_user_id) REFERENCES teachers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    CONSTRAINT fk_question_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_option_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_user_id INT NOT NULL,
    score INT DEFAULT NULL,
    started_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    completed_at DATETIME NULL,
    UNIQUE KEY unique_attempt (quiz_id, student_user_id),
    CONSTRAINT fk_attempt_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    CONSTRAINT fk_attempt_student FOREIGN KEY (student_user_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option_id INT NULL,
    UNIQUE KEY unique_answer (attempt_id, question_id),
    CONSTRAINT fk_answer_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_answer_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_answer_option FOREIGN KEY (selected_option_id) REFERENCES question_options(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS support_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'resolved') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP()
);

-- ── Password Reset Requests Table (Feature: Bipin Guragain) ─────────────────
-- Stores user submitted password reset requests from the Forgot Password form.
-- Admins can approve & generate temp passwords, then send them via personal email.
CREATE TABLE IF NOT EXISTS password_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(150) NOT NULL,
    email        VARCHAR(180) NOT NULL COMMENT 'Institutional @herald.edu.np email',
    student_id   VARCHAR(80)  NOT NULL COMMENT 'Student or Staff ID for identity verification',
    request_type ENUM('password_reset','general') NOT NULL DEFAULT 'password_reset',
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    temp_password VARCHAR(50) NULL COMMENT 'Temporary password generated by admin on approval',
    created_at   DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pr_status (status),
    INDEX idx_pr_email  (email)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ── Annotation feature migration ──────────────────────────────────────────────
-- Adds an optional teacher annotation to each attendance record.
ALTER TABLE attendance_records
    ADD COLUMN annotation TEXT NULL DEFAULT NULL AFTER status,
    ADD COLUMN annotated_by INT NULL DEFAULT NULL AFTER annotation,
    ADD COLUMN annotated_at DATETIME NULL DEFAULT NULL AFTER annotated_by,
    ADD CONSTRAINT fk_record_annotated_by
        FOREIGN KEY (annotated_by) REFERENCES teachers(id) ON DELETE SET NULL;
CREATE TABLE group_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    creator_role ENUM('Academic Admin','Teacher','Student') NOT NULL,
    creator_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE group_chat_members (
    group_id INT NOT NULL,
    user_role ENUM('Academic Admin','Teacher','Student') NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, user_role, user_id),
    FOREIGN KEY (group_id) REFERENCES group_chats(id) ON DELETE CASCADE
);

-- Conversations table: each row is a unique chat thread between two users
CREATE TABLE chat_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- initiator fields
    initiator_role ENUM('Academic Admin','Teacher') NOT NULL,
    initiator_id   INT NOT NULL,
    -- participant fields
    participant_role ENUM('Academic Admin','Teacher','Student') NOT NULL,
    participant_id   INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- prevent duplicate conversations between same two people
    UNIQUE KEY unique_convo (initiator_role, initiator_id, participant_role, participant_id)
);

-- Messages table
CREATE TABLE chat_messages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NULL,
    group_id        INT NULL,
    sender_role     ENUM('Academic Admin','Teacher','Student') NOT NULL,
    sender_id       INT NOT NULL,
    body            TEXT NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    sent_at         DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    CONSTRAINT fk_msg_conversation FOREIGN KEY (conversation_id)
        REFERENCES chat_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_group FOREIGN KEY (group_id)
        REFERENCES group_chats(id) ON DELETE CASCADE
);

CREATE INDEX idx_chat_msg_convo ON chat_messages(conversation_id, sent_at);
CREATE INDEX idx_chat_msg_group ON chat_messages(group_id, sent_at);
CREATE INDEX idx_chat_msg_unread ON chat_messages(conversation_id, is_read);

-- ─── Herald v2 Migration ─────────────────────────────────────────────────────
-- Run this file once against your smart_edu database.
-- It is safe to run multiple times (uses IF NOT EXISTS / column checks).

-- Add personal_email column to admins (used for password reset emails)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'smart_edu' AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'personal_email'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE admins ADD COLUMN personal_email VARCHAR(180) NULL DEFAULT NULL AFTER email',
    'SELECT "admins.personal_email already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add personal_email column to teachers
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'smart_edu' AND TABLE_NAME = 'teachers' AND COLUMN_NAME = 'personal_email'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE teachers ADD COLUMN personal_email VARCHAR(180) NULL DEFAULT NULL AFTER email',
    'SELECT "teachers.personal_email already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add personal_email column to students
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'smart_edu' AND TABLE_NAME = 'students' AND COLUMN_NAME = 'personal_email'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE students ADD COLUMN personal_email VARCHAR(180) NULL DEFAULT NULL AFTER email',
    'SELECT "students.personal_email already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Migration complete. personal_email column added to admins, teachers, students.' AS result;

