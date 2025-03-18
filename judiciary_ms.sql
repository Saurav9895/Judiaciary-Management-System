-- Create database for Judiciary Information System
CREATE DATABASE IF NOT EXISTS judiciary_ms;
USE judiciary_ms;

-- Users table for authentication and role management
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('judge', 'lawyer', 'registrar') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);



-- Cases table for storing case information
CREATE TABLE cases (
    case_id INT PRIMARY KEY AUTO_INCREMENT,
    cin VARCHAR(20) UNIQUE NOT NULL,
    defendant_name VARCHAR(100) NOT NULL,
    defendant_address TEXT NOT NULL,
    crime_type VARCHAR(50) NOT NULL,
    crime_date DATE NOT NULL,
    crime_location TEXT NOT NULL,
    arresting_officer VARCHAR(100) NOT NULL,
    arrest_date DATE NOT NULL,
    judge_id INT,
    prosecutor_name VARCHAR(100) NOT NULL,
    lawyer_id INT,
    start_date DATE NOT NULL,
    expected_completion_date DATE,
    status ENUM('pending', 'closed') DEFAULT 'pending',
    FOREIGN KEY (judge_id) REFERENCES users(user_id),
    FOREIGN KEY (lawyer_id) REFERENCES users(user_id)
);
-- Case assignments table for linking cases with judges and lawyers
CREATE TABLE case_assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('judge', 'lawyer') NOT NULL,
    assigned_date DATE NOT NULL,
    status ENUM('active', 'removed') DEFAULT 'active',
    FOREIGN KEY (case_id) REFERENCES cases(case_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX idx_case_user (case_id, user_id)
);

-- Hearings table for scheduling and tracking court proceedings
CREATE TABLE hearings (
    hearing_id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    hearing_date DATETIME NOT NULL,
    hearing_type ENUM('preliminary', 'trial', 'appeal', 'other') NOT NULL,
    proceedings_summary TEXT,  -- Add this column if missing
    adjournment_reason TEXT,
    status ENUM('scheduled', 'completed', 'postponed', 'cancelled') DEFAULT 'scheduled',
    FOREIGN KEY (case_id) REFERENCES cases(case_id)
);

-- Judgments table for storing final decisions
CREATE TABLE judgments (
    judgment_id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    judge_id INT NOT NULL,
    judgment_date DATE NOT NULL,
    judgment_text TEXT NOT NULL,
    status ENUM('draft', 'final', 'appealed') NOT NULL DEFAULT 'draft',
    FOREIGN KEY (case_id) REFERENCES cases(case_id),
    FOREIGN KEY (judge_id) REFERENCES users(user_id),
    INDEX idx_case_date (case_id, judgment_date)
);

-- Case browsing history for lawyer billing and activity tracking
CREATE TABLE case_history (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    user_id INT NOT NULL,
    action_type ENUM('view', 'edit', 'comment') NOT NULL,
    action_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    details TEXT,
    FOREIGN KEY (case_id) REFERENCES cases(case_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX idx_user_timestamp (user_id, action_timestamp)
);

-- User profiles for additional user information
CREATE TABLE user_profiles (
    profile_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    bar_number VARCHAR(50),
    specialization VARCHAR(100),
    profile_picture VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Case evidence tracking
CREATE TABLE case_evidence (
    evidence_id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    evidence_type ENUM('document', 'photo', 'video', 'audio', 'physical') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    submitted_by INT NOT NULL,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    chain_of_custody TEXT,
    status ENUM('submitted', 'verified', 'rejected') DEFAULT 'submitted',
    FOREIGN KEY (case_id) REFERENCES cases(case_id),
    FOREIGN KEY (submitted_by) REFERENCES users(user_id)
);

-- Billing system for lawyers
CREATE TABLE billing_records (
    billing_id INT PRIMARY KEY AUTO_INCREMENT,
    lawyer_id INT NOT NULL,
    case_id INT NOT NULL,
    billing_date DATE NOT NULL,
    hours_spent DECIMAL(5,2) NOT NULL,
    rate_per_hour DECIMAL(10,2) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('pending', 'approved', 'paid', 'disputed') DEFAULT 'pending',
    payment_date DATE,
    FOREIGN KEY (lawyer_id) REFERENCES users(user_id),
    FOREIGN KEY (case_id) REFERENCES cases(case_id)
);


CREATE TABLE case_browsing_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lawyer_id INT NOT NULL,
    cin VARCHAR(20) NOT NULL,
    access_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fee_amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (lawyer_id) REFERENCES users(user_id),
    FOREIGN KEY (cin) REFERENCES cases(cin)
);
-- Case notifications
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    case_id INT NOT NULL,
    notification_type ENUM('hearing', 'document', 'assignment', 'billing', 'general') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (case_id) REFERENCES cases(case_id)
);