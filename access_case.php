<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] !== 'lawyer') {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $case_id = (int)$_POST['case_id'];
    $cin = mysqli_real_escape_string($conn, $_POST['cin']);
    $lawyer_id = $_SESSION['user_id'];
    $fee_amount = 10.00; // $10 fee per case
    
    // Check if lawyer has already paid for this case
    $check_sql = "SELECT id FROM case_browsing_history WHERE lawyer_id = ? AND cin = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $lawyer_id, $cin);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Record the payment and access
        $insert_sql = "INSERT INTO case_browsing_history (lawyer_id, cin, fee_amount) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("isd", $lawyer_id, $cin, $fee_amount);
        
        if (!$insert_stmt->execute()) {
            $_SESSION['error'] = "Error processing payment. Please try again.";
            header("Location: lawyer_dashboard.php");
            exit();
        }
    }
    
    // Redirect to case details
    header("Location: case_details.php?cin=$cin");
    exit();
}

header("Location: lawyer_dashboard.php");
exit();
?>