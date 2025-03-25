<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "judiciary_ms";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


function logAudit($conn, $user_id, $action_type, $table_name, $record_id, $old_values = null, $new_values = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $sql = "INSERT INTO audit_logs 
            (user_id, action_type, table_name, record_id, old_values, new_values, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    $old_values_json = $old_values ? json_encode($old_values) : null;
    $new_values_json = $new_values ? json_encode($new_values) : null;
    
    $stmt->bind_param("ississs", 
        $user_id, 
        $action_type, 
        $table_name, 
        $record_id, 
        $old_values_json, 
        $new_values_json, 
        $ip_address
    );
    
    $stmt->execute();
}

// Register shutdown function to ensure connection closes
register_shutdown_function(function() use ($conn) {
    $conn->close();
});


// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    $conn->select_db($dbname);

    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        user_id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('judge', 'lawyer', 'registrar') NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL
    )";
    if ($conn->query($sql) !== TRUE) {
        echo "Error creating users table: " . $conn->error;
    }

    // Create cases table
    $sql = "CREATE TABLE IF NOT EXISTS cases (
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
    )";
    if ($conn->query($sql) !== TRUE) {
        echo "Error creating cases table: " . $conn->error;
    }

    // Create hearings table
    $sql = "CREATE TABLE IF NOT EXISTS hearings (
        hearing_id INT PRIMARY KEY AUTO_INCREMENT,
        case_id INT NOT NULL,
        hearing_date DATETIME NOT NULL,
        hearing_type ENUM('preliminary', 'trial', 'appeal', 'other') NOT NULL,
        status ENUM('scheduled', 'completed', 'postponed', 'cancelled') DEFAULT 'scheduled',
        notes TEXT,
        FOREIGN KEY (case_id) REFERENCES cases(case_id)
    )";
    if ($conn->query($sql) !== TRUE) {
        echo "Error creating hearings table: " . $conn->error;
    }

    // Create judgments table
    $sql = "CREATE TABLE IF NOT EXISTS judgments (
        judgment_id INT PRIMARY KEY AUTO_INCREMENT,
        case_id INT NOT NULL,
        judge_id INT NOT NULL,
        judgment_date DATE NOT NULL,
        judgment_text TEXT NOT NULL,
        status ENUM('draft', 'final', 'appealed') DEFAULT 'draft',
        FOREIGN KEY (case_id) REFERENCES cases(case_id),
        FOREIGN KEY (judge_id) REFERENCES users(user_id)
    )";
    if ($conn->query($sql) !== TRUE) {
        echo "Error creating judgments table: " . $conn->error;
    }

    // Create case_assignments table
    $sql = "CREATE TABLE IF NOT EXISTS case_assignments (
        assignment_id INT PRIMARY KEY AUTO_INCREMENT,
        case_id INT NOT NULL,
        user_id INT NOT NULL,
        role ENUM('judge', 'lawyer') NOT NULL,
        assigned_date DATE NOT NULL,
        status ENUM('active', 'removed') DEFAULT 'active',
        FOREIGN KEY (case_id) REFERENCES cases(case_id),
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )";
    if ($conn->query($sql) !== TRUE) {
        echo "Error creating case_assignments table: " . $conn->error;
    }

    // Create case_evidence table
    $sql = "CREATE TABLE IF NOT EXISTS case_evidence (
        evidence_id INT PRIMARY KEY AUTO_INCREMENT,
        case_id INT NOT NULL,
        evidence_type ENUM('document', 'photo', 'video', 'audio', 'physical') NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        file_path VARCHAR(255),
        submitted_by INT NOT NULL,
        submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('submitted', 'verified', 'rejected') DEFAULT 'submitted',
        FOREIGN KEY (case_id) REFERENCES cases(case_id),
        FOREIGN KEY (submitted_by) REFERENCES users(user_id)
    )";
    if ($conn->query($sql) !== TRUE) {
        echo "Error creating case_evidence table: " . $conn->error;
    }

    // Create billing_records table
    $sql = "CREATE TABLE IF NOT EXISTS billing_records (
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
    )";
    if ($conn->query($sql) !== TRUE) {
        echo "Error creating billing_records table: " . $conn->error;
    }

} else {
    echo "Error creating database: " . $conn->error;
}
?>