-- Create database for Judiciary Information System
CREATE DATABASE IF NOT EXISTS judiciary_ms;
USE judiciary_ms;

-- Users table for authentication and role management (created first as it's referenced by other tables)
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('judge', 'lawyer', 'registrar', 'admin') NOT NULL,  -- Added 'admin' role
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE  -- Added for user status tracking
);

-- Cases table for storing case information
CREATE TABLE IF NOT EXISTS cases (
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
    status ENUM('pending', 'closed', 'reopened') DEFAULT 'pending',  -- Added 'reopened' status
    description TEXT,  -- Added missing description column
    severity_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
    complexity_level ENUM('simple', 'moderate', 'complex') DEFAULT 'moderate',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (judge_id) REFERENCES users(user_id),
    FOREIGN KEY (lawyer_id) REFERENCES users(user_id),
    INDEX idx_case_status (status),
    INDEX idx_case_crime_type (crime_type)
);

-- Case assignments table for linking cases with judges and lawyers
CREATE TABLE IF NOT EXISTS case_assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('judge', 'lawyer') NOT NULL,
    assigned_date DATE NOT NULL,
    status ENUM('active', 'removed', 'reassigned') DEFAULT 'active',  -- Added 'reassigned'
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX idx_case_user (case_id, user_id),
    INDEX idx_assignment_status (status)
);

-- Hearings table for scheduling and tracking court proceedings
CREATE TABLE IF NOT EXISTS hearings (
    hearing_id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    hearing_date DATETIME NOT NULL,
    hearing_type ENUM('preliminary', 'trial', 'appeal', 'other') NOT NULL,
    proceedings_summary TEXT,
    notes TEXT,  -- Added missing notes column
    adjournment_reason TEXT,
    status ENUM('scheduled', 'completed', 'postponed', 'cancelled') DEFAULT 'scheduled',
    is_conflict BOOLEAN DEFAULT FALSE,
    created_by INT,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    INDEX idx_hearing_date (hearing_date),
    INDEX idx_hearing_status (status)
);

-- Judgments table for storing final decisions
CREATE TABLE IF NOT EXISTS judgments (
    judgment_id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    judge_id INT NOT NULL,
    judgment_date DATE NOT NULL,
    judgment_text TEXT NOT NULL,
    status ENUM('draft', 'final', 'appealed', 'modified') NOT NULL DEFAULT 'draft',  -- Added 'modified'
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (judge_id) REFERENCES users(user_id),
    INDEX idx_case_date (case_id, judgment_date)
);

-- Case Progress Tracking
CREATE TABLE IF NOT EXISTS case_milestones (
    milestone_id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    milestone_name VARCHAR(100) NOT NULL,
    due_date DATE,
    completion_date DATE,
    status ENUM('pending', 'in_progress', 'completed', 'delayed') DEFAULT 'pending',
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    INDEX idx_milestone_status (status),
    INDEX idx_milestone_dates (due_date, completion_date)
);

-- E-Signature System
CREATE TABLE IF NOT EXISTS digital_signatures (
    signature_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    document_type ENUM('judgment', 'order', 'motion', 'other') NOT NULL,
    document_id INT NOT NULL,
    signature_data TEXT NOT NULL,
    signature_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verification_hash VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX idx_signature_document (document_type, document_id)
);

-- Audit Logs
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action_type VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT,
    old_values TEXT,
    new_values TEXT,
    action_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX idx_audit_timestamp (action_timestamp),
    INDEX idx_audit_table (table_name)
);

-- Statistical Data
CREATE TABLE IF NOT EXISTS case_statistics (
    stat_id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    days_to_resolution INT,
    outcome ENUM('conviction', 'acquittal', 'dismissal', 'settlement') NULL,
    severity_score INT,
    complexity_score INT,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    INDEX idx_stat_outcome (outcome)
);

-- Judge Performance Metrics
CREATE TABLE IF NOT EXISTS judge_metrics (
    metric_id INT PRIMARY KEY AUTO_INCREMENT,
    judge_id INT NOT NULL,
    year INT NOT NULL,
    quarter INT NOT NULL,
    cases_handled INT DEFAULT 0,
    avg_resolution_days DECIMAL(10,2),
    conviction_rate DECIMAL(5,2),
    FOREIGN KEY (judge_id) REFERENCES users(user_id),
    INDEX idx_judge_year (judge_id, year, quarter)
);

-- Prediction Models
CREATE TABLE IF NOT EXISTS case_predictions (
    prediction_id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    predicted_resolution_days INT,
    predicted_outcome ENUM('conviction', 'acquittal', 'dismissal', 'settlement'),
    confidence_score DECIMAL(5,2),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    INDEX idx_prediction_outcome (predicted_outcome)
);

-- User profiles for additional user information
CREATE TABLE IF NOT EXISTS user_profiles (
    profile_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    bar_number VARCHAR(50),
    specialization VARCHAR(100),
    profile_picture VARCHAR(255),
    bio TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Case evidence tracking
CREATE TABLE IF NOT EXISTS case_evidence (
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
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users(user_id),
    INDEX idx_evidence_status (status)
);

-- Billing system for lawyers
CREATE TABLE IF NOT EXISTS billing_records (
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
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    INDEX idx_billing_status (status)
);

-- Case browsing history for lawyer billing and activity tracking
CREATE TABLE IF NOT EXISTS case_browsing_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lawyer_id INT NOT NULL,
    cin VARCHAR(20) NOT NULL,
    access_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fee_amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (lawyer_id) REFERENCES users(user_id),
    FOREIGN KEY (cin) REFERENCES cases(cin),
    INDEX idx_browsing_date (access_date)
);

-- Case notifications
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    case_id INT NOT NULL,
    notification_type ENUM('hearing', 'document', 'assignment', 'billing', 'general', 'reminder') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    is_urgent BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    INDEX idx_notification_read (read_at),
    INDEX idx_notification_urgent (is_urgent)
);

-- Create fulltext indexes after all tables are created
CREATE FULLTEXT INDEX ft_case_search ON cases(defendant_name, crime_type, description);
CREATE FULLTEXT INDEX ft_hearing_search ON hearings(proceedings_summary, notes);