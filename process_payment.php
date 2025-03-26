<?php
session_start();
require_once 'db_connect.php';

// Set header for JSON response
header('Content-Type: application/json');

// Verify user is logged in as lawyer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lawyer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$lawyer_id = $_SESSION['user_id'];
$case_id = $_POST['case_id'] ?? null;
$cin = $_POST['cin'] ?? null;

// Validate input
if (!$case_id || !$cin) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid case data']);
    exit();
}

try {
    // Check if payment already exists
    $check_stmt = $conn->prepare("SELECT * FROM case_browsing_history WHERE lawyer_id = ? AND cin = ?");
    $check_stmt->bind_param("is", $lawyer_id, $cin);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Already paid for this case']);
        exit();
    }

    // Record payment in database
    $stmt = $conn->prepare("INSERT INTO case_browsing_history (lawyer_id, case_id, cin, fee_amount, access_date) VALUES (?, ?, ?, 10.00, NOW())");
    $stmt->bind_param("iis", $lawyer_id, $case_id, $cin);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Payment processing failed: ' . $e->getMessage()]);
}