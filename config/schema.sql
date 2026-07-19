-- BEAS: Biometric Examination Authentication System
-- Database Schema (MySQL)
-- FUOYE - Department of Computer Engineering

CREATE DATABASE IF NOT EXISTS beas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE beas_db;

-- ============================================================
-- 1. USERS TABLE (Admin accounts)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('superadmin','admin','invigilator') DEFAULT 'admin',
    isActive BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- 2. STUDENTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    surname VARCHAR(50) NOT NULL,
    firstName VARCHAR(50) NOT NULL,
    middleName VARCHAR(50),
    age INT,
    phoneNumber VARCHAR(20),
    email VARCHAR(100) UNIQUE,
    department VARCHAR(100),
    course VARCHAR(100),
    level VARCHAR(10),
    matricNumber VARCHAR(30) NOT NULL UNIQUE,
    thumbTemplate TEXT,
    indexTemplate TEXT,
    middleTemplate TEXT,
    ringTemplate TEXT,
    pinkyTemplate TEXT,
    fingerprintsCaptured BOOLEAN DEFAULT FALSE,
    profilePictureUrl VARCHAR(255),
    faceDescriptor TEXT,
    password VARCHAR(255),
    isActive BOOLEAN DEFAULT TRUE,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- 3. COURSES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    courseCode VARCHAR(20) NOT NULL UNIQUE,
    courseTitle VARCHAR(150) NOT NULL,
    courseUnit INT NOT NULL DEFAULT 2,
    level INT NOT NULL,
    semester ENUM('first','second') NOT NULL,
    isActive BOOLEAN DEFAULT TRUE,
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    createdBy INT,
    FOREIGN KEY (createdBy) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- 4. COURSE REGISTRATION TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS course_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricNumber VARCHAR(30) NOT NULL,
    courseCode VARCHAR(20) NOT NULL,
    courseTitle VARCHAR(150),
    courseUnit INT,
    level INT,
    semester ENUM('first','second'),
    status BOOLEAN DEFAULT TRUE,
    approvedAt TIMESTAMP NULL,
    approvedBy INT NULL,
    isActive BOOLEAN DEFAULT TRUE,
    registeredAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    studentId INT,
    courseId INT,
    FOREIGN KEY (studentId) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (courseId) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_registration (matricNumber, courseCode, semester)
);

-- ============================================================
-- 5. ATTENDANCE TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    studentId INT NOT NULL,
    matricNumber VARCHAR(30) NOT NULL,
    courseId INT NOT NULL,
    courseCode VARCHAR(20),
    courseTitle VARCHAR(150),
    attendanceDate DATE NOT NULL,
    signInTime DATETIME,
    signOutTime DATETIME,
    signInFingerUsed VARCHAR(20),
    signOutFingerUsed VARCHAR(20),
    signInStatus ENUM('present','rejected','pending') DEFAULT 'pending',
    signOutStatus ENUM('signed_out','not_signed_out') DEFAULT 'not_signed_out',
    totalDuration VARCHAR(20),
    semester ENUM('first','second'),
    academicYear VARCHAR(20),
    sessionId VARCHAR(50),
    isActive BOOLEAN DEFAULT TRUE,
    verificationMethod ENUM('fingerprint','face','both') DEFAULT 'fingerprint',
    FOREIGN KEY (studentId) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (courseId) REFERENCES courses(id) ON DELETE CASCADE
);

-- ============================================================
-- 6. EXAM SESSIONS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS exam_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sessionId VARCHAR(50) NOT NULL UNIQUE,
    courseId INT NOT NULL,
    courseCode VARCHAR(20),
    courseTitle VARCHAR(150),
    examDate DATE NOT NULL,
    startTime TIME,
    endTime TIME,
    venue VARCHAR(100),
    semester ENUM('first','second'),
    academicYear VARCHAR(20),
    invigilatorId INT,
    status ENUM('scheduled','active','completed','cancelled') DEFAULT 'scheduled',
    totalExpected INT DEFAULT 0,
    totalVerified INT DEFAULT 0,
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (courseId) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (invigilatorId) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================
CREATE INDEX idx_students_matric ON students(matricNumber);
CREATE INDEX idx_attendance_date ON attendance(attendanceDate);
CREATE INDEX idx_attendance_course ON attendance(courseId);
CREATE INDEX idx_registrations_matric ON course_registrations(matricNumber);
CREATE INDEX idx_registrations_course ON course_registrations(courseCode);

-- ============================================================
-- DEFAULT ADMIN USER (password: Admin@FUOYE2025)
-- ============================================================
INSERT INTO users (username, email, password, full_name, role) VALUES
('admin', 'admin@fuoye.edu.ng', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'superadmin');

-- SAMPLE COURSES (Computer Engineering FUOYE)
INSERT INTO courses (courseCode, courseTitle, courseUnit, level, semester, createdBy) VALUES
('CPE 501', 'Digital Signal Processing', 3, 500, 'first', 1),
('CPE 503', 'Computer Networks', 3, 500, 'first', 1),
('CPE 505', 'Embedded Systems Design', 3, 500, 'first', 1),
('CPE 507', 'Artificial Intelligence', 3, 500, 'first', 1),
('CPE 511', 'Final Year Project I', 6, 500, 'first', 1),
('CPE 401', 'Microprocessor Systems', 3, 400, 'first', 1),
('CPE 403', 'Control Systems', 3, 400, 'first', 1);
