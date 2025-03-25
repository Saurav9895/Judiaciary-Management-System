<?php
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judge_id = (int)$_POST['judge_id'];
    $lawyer_id = (int)$_POST['lawyer_id'];
    $hearing_date = $_POST['hearing_date'];
    
    $conflict = false;
    $message = '';
    
    // Check judge availability
    $judge_sql = "SELECT c.cin, c.defendant_name 
                 FROM hearings h 
                 JOIN cases c ON h.case_id = c.case_id 
                 WHERE c.judge_id = ? AND h.hearing_date = ? AND h.is_conflict = 0";
    $stmt = $conn->prepare($judge_sql);
    $stmt->bind_param("is", $judge_id, $hearing_date);
    $stmt->execute();
    $judge_result = $stmt->get_result();
    
    if ($judge_result->num_rows > 0) {
        $conflict = true;
        $case = $judge_result->fetch_assoc();
        $message = "Judge is already scheduled for case " . $case['cin'] . " (" . $case['defendant_name'] . ") at this time";
    }
    
    // Check lawyer availability
    $lawyer_sql = "SELECT c.cin, c.defendant_name 
                  FROM hearings h 
                  JOIN cases c ON h.case_id = c.case_id 
                  WHERE c.lawyer_id = ? AND h.hearing_date = ? AND h.is_conflict = 0";
    $stmt = $conn->prepare($lawyer_sql);
    $stmt->bind_param("is", $lawyer_id, $hearing_date);
    $stmt->execute();
    $lawyer_result = $stmt->get_result();
    
    if ($lawyer_result->num_rows > 0) {
        $conflict = true;
        $case = $lawyer_result->fetch_assoc();
        $message = "Lawyer is already scheduled for case " . $case['cin'] . " (" . $case['defendant_name'] . ") at this time";
    }
    
    header('Content-Type: application/json');
    echo json_encode(['conflict' => $conflict, 'message' => $message]);
    exit();
}

header("HTTP/1.1 400 Bad Request");
echo json_encode(['error' => 'Invalid request']);
?>