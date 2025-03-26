<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "judiciary_ms";

// Create connection with error handling
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Set charset to utf8mb4 for full Unicode support
    $conn->set_charset("utf8mb4");
    
    // Enable error reporting
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
} catch (mysqli_sql_exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("System temporarily unavailable. Please try again later.");
}

/**
 * Logs an audit trail entry
 */
function logAudit($conn, $user_id, $action_type, $table_name, $record_id, $old_values = null, $new_values = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $old_values_json = ($old_values && (is_array($old_values) || is_object($old_values))) 
            ? json_encode($old_values, JSON_PRETTY_PRINT) 
            : $old_values;
            
        $new_values_json = ($new_values && (is_array($new_values) || is_object($new_values))) 
            ? json_encode($new_values, JSON_PRETTY_PRINT) 
            : $new_values;
        
        $sql = "INSERT INTO audit_logs 
                (user_id, action_type, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ississss", 
            $user_id, 
            $action_type, 
            $table_name, 
            $record_id, 
            $old_values_json, 
            $new_values_json, 
            $ip_address,
            $user_agent
        );
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
        return false;
    }
}

// Create database tables
$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        user_id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('judge', 'lawyer', 'registrar', 'admin') NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        is_active BOOLEAN DEFAULT TRUE,
        INDEX idx_user_role (role),
        INDEX idx_user_active (is_active)
    )",
    
    "cases" => "CREATE TABLE IF NOT EXISTS cases (
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
        status ENUM('pending', 'closed', 'reopened') DEFAULT 'pending',
        description TEXT,
        severity_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
        complexity_level ENUM('simple', 'moderate', 'complex') DEFAULT 'moderate',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (judge_id) REFERENCES users(user_id) ON DELETE SET NULL,
        FOREIGN KEY (lawyer_id) REFERENCES users(user_id) ON DELETE SET NULL,
        INDEX idx_case_status (status),
        INDEX idx_case_crime_type (crime_type)
    )",
    
    "audit_logs" => "CREATE TABLE IF NOT EXISTS audit_logs (
        log_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        action_type VARCHAR(50) NOT NULL,
        table_name VARCHAR(50) NOT NULL,
        record_id INT,
        old_values TEXT,
        new_values TEXT,
        action_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        user_agent TEXT,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
        INDEX idx_audit_timestamp (action_timestamp),
        INDEX idx_audit_table (table_name)
    )"
];

foreach ($tables as $table => $sql) {
    try {
        $conn->query($sql);
    } catch (Exception $e) {
        error_log("Error creating table $table: " . $e->getMessage());
    }
}

// Register shutdown function
register_shutdown_function(function() use ($conn) {
    try {
        if ($conn && $conn->ping()) {
            $conn->close();
        }
    } catch (Exception $e) {
        error_log("Connection closure error: " . $e->getMessage());
    }
});

// Set current user ID function
function setCurrentUserId($conn, $user_id) {
    $conn->query("SET @current_user_id = " . (int)$user_id);
}
?>