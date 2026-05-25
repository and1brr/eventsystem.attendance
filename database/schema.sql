CREATE DATABASE IF NOT EXISTS event_attendance;
USE event_attendance;

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL UNIQUE,
    role_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_roles FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    fname VARCHAR(100) NOT NULL,
    lname VARCHAR(100) NOT NULL,
    mname VARCHAR(100) NULL,
    course VARCHAR(150) NULL,
    year_level VARCHAR(50) NULL,
    section VARCHAR(50) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(150) NOT NULL,
    event_description TEXT NULL,
    event_date DATE NOT NULL,
    event_time TIME NULL,
    location_lat DECIMAL(10,7) NULL,
    location_lng DECIMAL(10,7) NULL,
    geofence_radius_m INT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_users FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_events_event_date (event_date)
) ENGINE=InnoDB;

CREATE TABLE scan_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    scanned_by INT NOT NULL,
    scan_code VARCHAR(100) NOT NULL,
    scan_type ENUM('attendance', 'entry', 'exit') NOT NULL DEFAULT 'attendance',
    status ENUM('success', 'duplicate', 'invalid') NOT NULL DEFAULT 'success',
    event_id INT NULL,
    attendance_name VARCHAR(150) NULL,
    scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_scan_logs_students FOREIGN KEY (student_id) REFERENCES students(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_scan_logs_users FOREIGN KEY (scanned_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_scan_logs_events FOREIGN KEY (event_id) REFERENCES events(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_scan_logs_scanned_at (scanned_at),
    INDEX idx_scan_logs_student_id (student_id),
    INDEX idx_scan_logs_scanned_by (scanned_by),
    INDEX idx_scan_logs_event_id (event_id)
) ENGINE=InnoDB;

INSERT INTO roles (name, description) VALUES
('Admin', 'Full access to manage users, students, and records'),
('Staff', 'Scans student QR codes and views attendance records');

INSERT INTO users (username, password_hash, full_name, email, role_id) VALUES
('admin', 'sha256:JAvlGPq9JyTdtvBO6x2llnRI1+gxwIyPqCKAn3THIKk=', 'System Admin', 'admin@example.com', 1),
('staff', 'sha256:EBdue3sk0xes/PjSBkz9LyThVPe1qWYDB31e+BPWprY=', 'Front Desk Staff', 'staff@example.com', 2);
