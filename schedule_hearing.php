<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'registrar') {
    header('Location: login.php');
    exit();
}

$message = '';

// Check for scheduling conflicts
function checkSchedulingConflict($conn, $judge_id, $lawyer_id, $hearing_date) {
    $conflict = false;
    
    // Check judge availability
    $judge_sql = "SELECT 1 FROM hearings h 
                 JOIN cases c ON h.case_id = c.case_id 
                 WHERE c.judge_id = ? AND h.hearing_date = ? AND h.is_conflict = 0";
    $stmt = $conn->prepare($judge_sql);
    $stmt->bind_param("is", $judge_id, $hearing_date);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $conflict = true;
        $message = "Judge is already scheduled for another hearing at this time";
    }
    
    // Check lawyer availability
    $lawyer_sql = "SELECT 1 FROM hearings h 
                  JOIN cases c ON h.case_id = c.case_id 
                  WHERE c.lawyer_id = ? AND h.hearing_date = ? AND h.is_conflict = 0";
    $stmt = $conn->prepare($lawyer_sql);
    $stmt->bind_param("is", $lawyer_id, $hearing_date);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $conflict = true;
        $message = "Lawyer is already scheduled for another hearing at this time";
    }
    
    return ['conflict' => $conflict, 'message' => $message ?? ''];
}

// Handle hearing scheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $case_id = mysqli_real_escape_string($conn, $_POST['case_id']);
    $hearing_date = mysqli_real_escape_string($conn, $_POST['hearing_date']);
    $hearing_type = mysqli_real_escape_string($conn, $_POST['hearing_type']);
    $proceedings_summary = mysqli_real_escape_string($conn, $_POST['proceedings_summary']);
    $lawyer_id = mysqli_real_escape_string($conn, $_POST['lawyer_id']);
    $judge_id = mysqli_real_escape_string($conn, $_POST['judge_id']);
    
    // Check for conflicts
    $conflict_check = checkSchedulingConflict($conn, $judge_id, $lawyer_id, $hearing_date);
    
    if ($conflict_check['conflict'] && !isset($_POST['force_schedule'])) {
        $message = $conflict_check['message'];
    } else {
        // Assign lawyer and judge to the case
        $assign_sql = "UPDATE cases SET lawyer_id = ?, judge_id = ? WHERE case_id = ?";
        $assign_stmt = $conn->prepare($assign_sql);
        $assign_stmt->bind_param("iii", $lawyer_id, $judge_id, $case_id);
        $assign_stmt->execute();
        
        // Schedule hearing
        $is_conflict = $conflict_check['conflict'] ? 1 : 0;
        $hearing_sql = "INSERT INTO hearings 
                        (case_id, hearing_date, hearing_type, proceedings_summary, is_conflict) 
                        VALUES (?, ?, ?, ?, ?)";
        $hearing_stmt = $conn->prepare($hearing_sql);
        $hearing_stmt->bind_param("isssi", $case_id, $hearing_date, $hearing_type, $proceedings_summary, $is_conflict);
        
        if ($hearing_stmt->execute()) {
            $message = 'Hearing scheduled successfully!';
            if ($is_conflict) {
                $message .= ' (Note: Scheduling conflict exists)';
            }
        } else {
            $message = 'Error scheduling hearing: ' . $conn->error;
        }
    }
}

// Fetch pending cases
$cases_sql = "SELECT case_id, cin, defendant_name, crime_type FROM cases WHERE status = 'pending'";
$cases_result = $conn->query($cases_sql);

// Fetch lawyers and judges
$lawyers_sql = "SELECT user_id, full_name FROM users WHERE role = 'lawyer'";
$lawyers_result = $conn->query($lawyers_sql);

$judges_sql = "SELECT user_id, full_name FROM users WHERE role = 'judge'";
$judges_result = $conn->query($judges_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Hearing - JIS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">JIS - Registrar Dashboard</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="search_cases.php">Search Cases</a>
                <!-- <a class="nav-link" href="statistics.php">Statistics</a> -->
                <!-- <a class="nav-link" href="audit_logs.php">Log Audit</a> -->

                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container mt-5">
        <h2>Schedule Hearing</h2>
        <?php if ($message): ?>
            <div class="alert alert-<?= strpos($message, 'Error') !== false ? 'danger' : 'info' ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <form method="POST" class="mt-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="case_id">Select Case</label>
                        <select class="form-control" id="case_id" name="case_id" required>
                            <option value="">Select a case...</option>
                            <?php while ($case = $cases_result->fetch_assoc()): ?>
                                <option value="<?= $case['case_id'] ?>">
                                    <?= htmlspecialchars($case['cin'] . ' - ' . $case['defendant_name'] . ' (' . $case['crime_type'] . ')') ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="hearing_type">Hearing Type</label>
                        <select class="form-control" id="hearing_type" name="hearing_type" required>
                            <option value="preliminary">Preliminary</option>
                            <option value="trial">Trial</option>
                            <option value="appeal">Appeal</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="lawyer_id">Assign Lawyer</label>
                        <select class="form-control" id="lawyer_id" name="lawyer_id" required>
                            <option value="">Select a lawyer...</option>
                            <?php while ($lawyer = $lawyers_result->fetch_assoc()): ?>
                                <option value="<?= $lawyer['user_id'] ?>">
                                    <?= htmlspecialchars($lawyer['full_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="judge_id">Assign Judge</label>
                        <select class="form-control" id="judge_id" name="judge_id" required>
                            <option value="">Select a judge...</option>
                            <?php while ($judge = $judges_result->fetch_assoc()): ?>
                                <option value="<?= $judge['user_id'] ?>">
                                    <?= htmlspecialchars($judge['full_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="hearing_date">Hearing Date & Time</label>
                        <input type="datetime-local" class="form-control" id="hearing_date" name="hearing_date" required>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="proceedings_summary">Proceedings Summary</label>
                        <textarea class="form-control" id="proceedings_summary" name="proceedings_summary" rows="1"></textarea>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($conflict_check['conflict'])): ?>
                <div class="alert alert-warning mt-3">
                    <h5>Scheduling Conflict Detected!</h5>
                    <p><?= $conflict_check['message'] ?></p>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="force_schedule" name="force_schedule">
                        <label class="form-check-label" for="force_schedule">
                            Schedule anyway (will be marked as conflict)
                        </label>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Schedule Hearing</button>
                <a href="registrar_dashboard.php" class="btn btn-secondary">Cancel</a>
                <a href="registrar_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </form>
        

    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize datetime picker
        flatpickr("#hearing_date", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "today",
            minTime: "08:00",
            maxTime: "17:00",
            disable: [
                function(date) {
                    // Disable weekends
                    return (date.getDay() === 0 || date.getDay() === 6);
                }
            ]
        });
        
        // Check for conflicts when date, judge or lawyer changes
        $('#hearing_date, #judge_id, #lawyer_id').change(function() {
            const hearing_date = $('#hearing_date').val();
            const judge_id = $('#judge_id').val();
            const lawyer_id = $('#lawyer_id').val();
            
            if (hearing_date && judge_id && lawyer_id) {
                $.ajax({
                    url: 'check_conflict.php',
                    method: 'POST',
                    data: {
                        hearing_date: hearing_date,
                        judge_id: judge_id,
                        lawyer_id: lawyer_id
                    },
                    success: function(response) {
                        if (response.conflict) {
                            alert('Conflict detected: ' + response.message);
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>