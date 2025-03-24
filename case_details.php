<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$cin = isset($_GET['cin']) ? trim($_GET['cin']) : '';
if (empty($cin)) {
    header('Location: dashboard.php');
    exit();
}

// Fetch case details
$sql = "SELECT c.*, u1.full_name as judge_name, u2.full_name as lawyer_name 
        FROM cases c 
        LEFT JOIN users u1 ON c.judge_id = u1.user_id 
        LEFT JOIN users u2 ON c.lawyer_id = u2.user_id 
        WHERE c.cin = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $cin);
$stmt->execute();
$result = $stmt->get_result();
$case = $result->fetch_assoc();

if (!$case) {
    header('Location: dashboard.php');
    exit();
}

// Check access for lawyers
if ($_SESSION['role'] === 'lawyer') {
    $lawyer_id = $_SESSION['user_id'];
    
    // Check if case is assigned to this lawyer
    $assigned_sql = "SELECT 1 FROM case_assignments WHERE case_id = ? AND user_id = ? AND role = 'lawyer'";
    $assigned_stmt = $conn->prepare($assigned_sql);
    $assigned_stmt->bind_param("ii", $case['case_id'], $lawyer_id);
    $assigned_stmt->execute();
    $assigned_result = $assigned_stmt->get_result();
    
    // Check if lawyer has paid for this case (if not assigned)
    if ($assigned_result->num_rows === 0) {
        $paid_sql = "SELECT 1 FROM case_browsing_history WHERE lawyer_id = ? AND cin = ?";
        $paid_stmt = $conn->prepare($paid_sql);
        $paid_stmt->bind_param("is", $lawyer_id, $cin);
        $paid_stmt->execute();
        $paid_result = $paid_stmt->get_result();
        
        if ($paid_result->num_rows === 0) {
            $_SESSION['error'] = "You must pay the access fee to view this case record";
            header("Location: lawyer_dashboard.php");
            exit();
        }
    }
}

// Fetch hearings
$hearings_sql = "SELECT * FROM hearings WHERE case_id = ? ORDER BY hearing_date DESC";
$hearings_stmt = $conn->prepare($hearings_sql);
$hearings_stmt->bind_param("i", $case['case_id']);
$hearings_stmt->execute();
$hearings_result = $hearings_stmt->get_result();
$hearings = $hearings_result->fetch_all(MYSQLI_ASSOC);

// Fetch judgments
$judgments_sql = "SELECT * FROM judgments WHERE case_id = ? ORDER BY judgment_date DESC";
$judgments_stmt = $conn->prepare($judgments_sql);
$judgments_stmt->bind_param("i", $case['case_id']);
$judgments_stmt->execute();
$judgments_result = $judgments_stmt->get_result();
$judgments = $judgments_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Details - JIS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <h2>Case Details</h2>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?php echo htmlspecialchars($case['title'] ?? 'No title available'); ?></h5>
                <p class="card-text"><?php echo htmlspecialchars($case['description'] ?? 'No description available'); ?></p>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">CIN: <?php echo htmlspecialchars($case['cin']); ?></li>
                    <li class="list-group-item">Defendant: <?php echo htmlspecialchars($case['defendant_name']); ?></li>
                    <li class="list-group-item">Crime Type: <?php echo htmlspecialchars($case['crime_type']); ?></li>
                    <li class="list-group-item">Judge: <?php echo htmlspecialchars($case['judge_name']); ?></li>
                    <li class="list-group-item">Lawyer: <?php echo htmlspecialchars($case['lawyer_name']); ?></li>
                    <li class="list-group-item">Status: <?php echo htmlspecialchars($case['status']); ?></li>
                </ul>
            </div>
        </div>

        <h3>Hearings</h3>
        <?php if (!empty($hearings)): ?>
            <div class="list-group">
            <?php foreach ($hearings as $hearing): ?>
                <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">Hearing Date: <?php echo htmlspecialchars($hearing['hearing_date']); ?></h6>
                        <small>Type: <?php echo htmlspecialchars($hearing['hearing_type']); ?></small>
                    </div>
                    <p class="mb-1"><?php echo htmlspecialchars($hearing['notes'] ?? 'No notes available'); ?></p>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No hearings scheduled</p>
        <?php endif; ?>

        <h3>Judgments</h3>
        <?php if (!empty($judgments)): ?>
            <div class="list-group">
            <?php foreach ($judgments as $judgment): ?>
                <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">Judgment Date: <?php echo htmlspecialchars($judgment['judgment_date']); ?></h6>
                        <small>Status: <?php echo htmlspecialchars($judgment['status']); ?></small>
                    </div>
                    <p class="mb-1"><?php echo htmlspecialchars($judgment['judgment_text']); ?></p>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No judgments recorded</p>
        <?php endif; ?>

        <a href="lawyer_dashboard.php" class="btn btn-secondary mt-4">Back to Dashboard</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>