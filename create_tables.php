<?php
require_once 'db_connect.php';

// Create court_cases table
$sql = "CREATE TABLE IF NOT EXISTS court_cases (
    cin VARCHAR(20) PRIMARY KEY,
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
)";

if ($conn->query($sql) !== TRUE) {
    echo "Error creating court_cases table: " . $conn->error;
}

// Create case_hearings table
$sql = "CREATE TABLE IF NOT EXISTS case_hearings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cin VARCHAR(20),
    hearing_date DATE NOT NULL,
    proceedings_summary TEXT,
    adjournment_reason TEXT,
    FOREIGN KEY (cin) REFERENCES court_cases(cin)
)";

if ($conn->query($sql) !== TRUE) {
    echo "Error creating case_hearings table: " . $conn->error;
}

// Create case_judgments table
$sql = "CREATE TABLE IF NOT EXISTS case_judgments (
    cin VARCHAR(20) PRIMARY KEY,
    judgment_date DATE NOT NULL,
    judgment_summary TEXT NOT NULL,
    FOREIGN KEY (cin) REFERENCES court_cases(cin)
)";

if ($conn->query($sql) !== TRUE) {
    echo "Error creating case_judgments table: " . $conn->error;
}

// Create case_browsing_history table for lawyers
$sql = "CREATE TABLE IF NOT EXISTS case_browsing_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lawyer_id INT,
    cin VARCHAR(20),
    access_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fee_amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (lawyer_id) REFERENCES users(user_id),
    FOREIGN KEY (cin) REFERENCES court_cases(cin)
)";

if ($conn->query($sql) !== TRUE) {
    echo "Error creating case_browsing_history table: " . $conn->error;
}

$conn->close();
echo "Tables created successfully";
?>