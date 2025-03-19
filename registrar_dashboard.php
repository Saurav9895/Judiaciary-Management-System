<?php
session_start();
require_once 'db_connect.php';

// Redirect if user is not logged in or is not a registrar
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'registrar') {
    header("Location: login.php");
    exit();
}

$message = '';

// Handle status update for pending cases
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $case_id = mysqli_real_escape_string($conn, $_POST['case_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // Update case status in the database
    $update_sql = "UPDATE cases SET status = ? WHERE case_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $status, $case_id);

    if ($stmt->execute()) {
        $message = "Case status updated successfully!";
    } else {
        $message = "Error updating case status: " . $conn->error;
    }
}

// Handle rescheduling of hearings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_hearing'])) {
    $hearing_id = mysqli_real_escape_string($conn, $_POST['hearing_id']);
    $new_hearing_date = mysqli_real_escape_string($conn, $_POST['new_hearing_date']);
    $adjournment_reason = mysqli_real_escape_string($conn, $_POST['adjournment_reason']);

    // Update hearing date and adjournment reason
    $sql = "UPDATE hearings SET hearing_date = ?, adjournment_reason = ? WHERE hearing_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $new_hearing_date, $adjournment_reason, $hearing_id);

    if ($stmt->execute()) {
        $message = 'Hearing rescheduled successfully!';
    } else {
        $message = 'Error rescheduling hearing: ' . $conn->error;
    }
}

// Handle recording court proceedings summaries
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_proceedings'])) {
    $hearing_id = mysqli_real_escape_string($conn, $_POST['hearing_id']);
    $proceedings_summary = mysqli_real_escape_string($conn, $_POST['proceedings_summary']);

    // Update proceedings summary
    $sql = "UPDATE hearings SET proceedings_summary = ? WHERE hearing_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $proceedings_summary, $hearing_id);

    if ($stmt->execute()) {
        $message = 'Proceedings summary recorded successfully!';
    } else {
        $message = 'Error recording proceedings summary: ' . $conn->error;
    }
}

// Fetch today's hearings
$today = date('Y-m-d');
$today_hearings_query = "SELECT h.hearing_id, c.cin, c.defendant_name, c.crime_type, h.hearing_date, h.adjournment_reason, h.proceedings_summary 
                         FROM hearings h 
                         JOIN cases c ON h.case_id = c.case_id 
                         WHERE DATE(h.hearing_date) = ? 
                         ORDER BY h.hearing_date ASC";
$stmt = $conn->prepare($today_hearings_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$today_hearings = $stmt->get_result();

// Fetch pending cases
$pending_cases_query = "SELECT c.*, u1.full_name as judge_name, u2.full_name as lawyer_name 
                        FROM cases c 
                        LEFT JOIN users u1 ON c.judge_id = u1.user_id 
                        LEFT JOIN users u2 ON c.lawyer_id = u2.user_id 
                        WHERE c.status = 'pending' 
                        ORDER BY c.cin";
$pending_cases = $conn->query($pending_cases_query);

// Fetch past cases (closed or hearings in the past)
$past_cases_query = "SELECT c.*, u1.full_name as judge_name, u2.full_name as lawyer_name, h.hearing_date 
                     FROM cases c 
                     LEFT JOIN users u1 ON c.judge_id = u1.user_id 
                     LEFT JOIN users u2 ON c.lawyer_id = u2.user_id 
                     LEFT JOIN hearings h ON c.case_id = h.case_id 
                     WHERE c.status = 'closed'
                     ORDER BY h.hearing_date DESC";
$past_cases = $conn->query($past_cases_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Dashboard - JIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">JIS - Registrar Dashboard</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="registrar_profile.php">Profile</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        <div class="row mb-4">
            <div class="col">
                <a href="new_case.php" class="btn btn-primary">Register New Case</a>
                <a href="schedule_hearing.php" class="btn btn-success">Schedule Hearing</a>
            </div>
        </div>

        <!-- Reschedule Hearing Form -->
       

        <!-- Today's Hearings -->
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Today's Hearings</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($today_hearings->num_rows > 0): ?>
                            <div class="list-group">
                                <?php $today_hearings->data_seek(0); // Reset pointer to reuse the result ?>
                                <?php while ($hearing = $today_hearings->fetch_assoc()): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">CIN: <?php echo $hearing['cin']; ?></h6>
                                            <small>Time: <?php echo date('h:i A', strtotime($hearing['hearing_date'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo $hearing['defendant_name']; ?> - <?php echo $hearing['crime_type']; ?></p>
                                        <?php if ($hearing['adjournment_reason']): ?>
                                            <small><strong>Adjournment Reason:</strong> <?php echo $hearing['adjournment_reason']; ?></small>
                                        <?php endif; ?>
                                        <?php if ($hearing['proceedings_summary']): ?>
                                            <small><strong>Proceedings Summary:</strong> <?php echo $hearing['proceedings_summary']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No hearings scheduled for today</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Pending Cases -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Pending Cases</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($pending_cases->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while ($case = $pending_cases->fetch_assoc()): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">CIN: <?php echo $case['cin']; ?></h6>
                                            <small>Started: <?php echo $case['start_date']; ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo $case['defendant_name']; ?> - <?php echo $case['crime_type']; ?></p>
                                        <small>Judge: <?php echo $case['judge_name']; ?> | Lawyer: <?php echo $case['lawyer_name']; ?></small>
                                        <form method="POST" class="mt-2">
                                            <input type="hidden" name="case_id" value="<?php echo $case['case_id']; ?>">
                                            <div class="input-group">
                                                <select class="form-select" name="status" required>
                                                    <option value="pending" <?php echo $case['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="closed" <?php echo $case['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                                </select>
                                                <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No pending cases</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<!-- Reschedule Hearing Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Reschedule Hearing</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label for="hearing_id">Select Hearing</label>
                <select class="form-control" id="hearing_id" name="hearing_id" required>
                    <option value="">Select a hearing...</option>
                    <?php $today_hearings->data_seek(0); // Reset pointer to reuse the result ?>
                    <?php while ($hearing = $today_hearings->fetch_assoc()): ?>
                        <option value="<?php echo $hearing['hearing_id']; ?>">
                            <?php echo htmlspecialchars($hearing['cin'] . ' - ' . $hearing['defendant_name'] . ' (' . $hearing['crime_type'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="new_hearing_date">New Hearing Date</label>
                <input type="datetime-local" class="form-control" id="new_hearing_date" name="new_hearing_date" required>
            </div>
            <div class="form-group">
                <label for="adjournment_reason">Reason for Adjournment</label>
                <textarea class="form-control" id="adjournment_reason" name="adjournment_reason" rows="3" required></textarea>
            </div>
            <button type="submit" name="reschedule_hearing" class="btn btn-warning">Reschedule Hearing</button>
        </form>
    </div>
</div>

        <!-- Record Proceedings Summary Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Record Proceedings Summary</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="hearing_id">Select Hearing</label>
                        <select class="form-control" id="hearing_id" name="hearing_id" required>
                            <option value="">Select a hearing...</option>
                            <?php $today_hearings->data_seek(0); // Reset pointer to reuse the result ?>
                            <?php while ($hearing = $today_hearings->fetch_assoc()): ?>
                                <option value="<?php echo $hearing['hearing_id']; ?>">
                                    <?php echo htmlspecialchars($hearing['cin'] . ' - ' . $hearing['defendant_name'] . ' (' . $hearing['crime_type'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="proceedings_summary">Proceedings Summary</label>
                        <textarea class="form-control" id="proceedings_summary" name="proceedings_summary" rows="5" required></textarea>
                    </div>
                    <button type="submit" name="record_proceedings" class="btn btn-primary">Record Summary</button>
                </form>
            </div>
        </div>
        <!-- Past Cases -->
        <div class="row mt-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Past Cases</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($past_cases->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while ($case = $past_cases->fetch_assoc()): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">CIN: <?php echo $case['cin']; ?></h6>
                                            <small>Hearing Date: <?php echo $case['hearing_date']; ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo $case['defendant_name']; ?> - <?php echo $case['crime_type']; ?></p>
                                        <small>Judge: <?php echo $case['judge_name']; ?> | Lawyer: <?php echo $case['lawyer_name']; ?></small>
                                        <small>Status: <?php echo ucfirst($case['status']); ?></small>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No past cases found</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>