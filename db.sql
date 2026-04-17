CREATE DATABASE IF NOT EXISTS smart_edu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_edu;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS materials;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS attendance_records;
DROP TABLE IF EXISTS attendance_sessions;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institutional_id VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('Academic Admin','Teacher','Student') NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    teacher_code CHAR(4) NULL,
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
    CONSTRAINT fk_courses_teacher FOREIGN KEY (teacher_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_courses_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_user_id INT NOT NULL,
    enrolled_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    UNIQUE KEY unique_enrollment (course_id, student_user_id),
    CONSTRAINT fk_enroll_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_enroll_student FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE attendance_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    teacher_user_id INT NOT NULL,
    session_date DATE NOT NULL,
    session_type ENUM('L','T','W') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    UNIQUE KEY unique_attendance (course_id, session_date, session_type),
    CONSTRAINT fk_session_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_session_teacher FOREIGN KEY (teacher_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendance_session_id INT NOT NULL,
    student_user_id INT NOT NULL,
    status ENUM('Present','Absent') NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    UNIQUE KEY unique_record (attendance_session_id, student_user_id),
    CONSTRAINT fk_record_session FOREIGN KEY (attendance_session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_record_student FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE
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
    CONSTRAINT fk_assignment_teacher FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
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
        REFERENCES users(id) 
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
    CONSTRAINT fk_material_teacher FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);
