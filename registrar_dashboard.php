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

// Fetch pending hearings for dropdowns
$pending_hearings_query = "SELECT h.hearing_id, c.cin, c.defendant_name, c.crime_type, h.hearing_date 
                          FROM hearings h 
                          JOIN cases c ON h.case_id = c.case_id 
                          WHERE c.status = 'pending'
                          ORDER BY h.hearing_date DESC";
$pending_hearings = $conn->query($pending_hearings_query);

// Fetch today's hearings for display
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

// Fetch pending cases with hearing information
$pending_cases_query = "SELECT c.*, u1.full_name as judge_name, u2.full_name as lawyer_name,
                       h.hearing_date, h.adjournment_reason, h.proceedings_summary
                       FROM cases c 
                       LEFT JOIN users u1 ON c.judge_id = u1.user_id 
                       LEFT JOIN users u2 ON c.lawyer_id = u2.user_id
                       LEFT JOIN hearings h ON c.case_id = h.case_id
                       WHERE c.status = 'pending' 
                       ORDER BY c.cin";
$pending_cases = $conn->query($pending_cases_query);

// Fetch past cases (closed or hearings in the past)
$past_cases_query = "SELECT c.*, u1.full_name as judge_name, u2.full_name as lawyer_name, 
                     h.hearing_date, h.adjournment_reason, h.proceedings_summary
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #27ae60;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            background-color: var(--primary-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .list-group-item {
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .list-group-item:hover {
            background-color: #f8f9fa;
            border-left: 4px solid var(--secondary-color);
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .btn-warning {
            background-color: #f39c12;
            border-color: #f39c12;
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
            border-color: #e67e22;
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .case-item {
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .case-item:hover {
            background-color: #f8f9fa;
            transform: translateX(3px);
        }
        
        .nav-tabs .nav-link {
            color: var(--primary-color);
        }
        
        .nav-tabs .nav-link.active {
            font-weight: 600;
            border-bottom: 3px solid var(--secondary-color);
        }
        
        .modal-body table th {
            width: 40%;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-balance-scale me-2"></i>JIS - Registrar Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="registrar_profile.php"><i class="fas fa-user me-1"></i>Profile</a>
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col">
                <a href="new_case.php" class="btn btn-primary me-2"><i class="fas fa-plus me-1"></i>Register New Case</a>
                <a href="schedule_hearing.php" class="btn btn-success"><i class="fas fa-calendar-plus me-1"></i>Schedule Hearing</a>
            </div>
        </div>

        <!-- Today's Hearings -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-gavel me-2"></i>Today's Hearings</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($today_hearings->num_rows > 0): ?>
                            <div class="list-group">
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
                        <h5 class="card-title mb-0"><i class="fas fa-clock me-2"></i>Pending Cases</h5>
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
                                        <?php if ($case['hearing_date']): ?>
                                            <small class="d-block mt-1">Next Hearing: <?php echo date('M d, Y h:i A', strtotime($case['hearing_date'])); ?></small>
                                        <?php endif; ?>
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

        <!-- Cases Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-folder-open me-2"></i>Cases</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="casesTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-cases" type="button" role="tab">Pending Cases</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="closed-tab" data-bs-toggle="tab" data-bs-target="#closed-cases" type="button" role="tab">Closed Cases</button>
                    </li>
                </ul>
                <div class="tab-content" id="casesTabContent">
                    <!-- Pending Cases Tab -->
                    <div class="tab-pane fade show active" id="pending-cases" role="tabpanel">
                        <?php 
                        // Re-fetch pending cases for this section
                        $pending_cases_tab = $conn->query($pending_cases_query);
                        if ($pending_cases_tab->num_rows > 0): ?>
                            <div class="list-group mt-3">
                                <?php while ($case = $pending_cases_tab->fetch_assoc()): ?>
                                    <div class="list-group-item case-item" 
                                         data-bs-toggle="modal" 
                                         data-bs-target="#caseDetailsModal"
                                         data-case-id="<?php echo $case['case_id']; ?>"
                                         data-cin="<?php echo $case['cin']; ?>"
                                         data-defendant="<?php echo $case['defendant_name']; ?>"
                                         data-crime="<?php echo $case['crime_type']; ?>"
                                         data-judge="<?php echo $case['judge_name']; ?>"
                                         data-lawyer="<?php echo $case['lawyer_name']; ?>"
                                         data-start-date="<?php echo $case['start_date']; ?>"
                                         data-status="<?php echo $case['status']; ?>"
                                         data-description="<?php echo htmlspecialchars($case['description'] ?? ''); ?>"
                                         data-hearing-date="<?php echo $case['hearing_date']; ?>"
                                         data-adjournment-reason="<?php echo htmlspecialchars($case['adjournment_reason'] ?? ''); ?>"
                                         data-proceedings-summary="<?php echo htmlspecialchars($case['proceedings_summary'] ?? ''); ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo $case['cin']; ?></h6>
                                            <small class="text-muted"><?php echo $case['start_date']; ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo $case['defendant_name']; ?></p>
                                        <small><?php echo $case['crime_type']; ?></small>
                                        <?php if ($case['hearing_date']): ?>
                                            <small class="d-block mt-1">Next Hearing: <?php echo date('M d, Y h:i A', strtotime($case['hearing_date'])); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mt-3">No pending cases found</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Closed Cases Tab -->
                    <div class="tab-pane fade" id="closed-cases" role="tabpanel">
                        <?php 
                        // Re-fetch closed cases for this section
                        $closed_cases_tab = $conn->query($past_cases_query);
                        if ($closed_cases_tab->num_rows > 0): ?>
                            <div class="list-group mt-3">
                                <?php while ($case = $closed_cases_tab->fetch_assoc()): ?>
                                    <div class="list-group-item case-item" 
                                         data-bs-toggle="modal" 
                                         data-bs-target="#caseDetailsModal"
                                         data-case-id="<?php echo $case['case_id']; ?>"
                                         data-cin="<?php echo $case['cin']; ?>"
                                         data-defendant="<?php echo $case['defendant_name']; ?>"
                                         data-crime="<?php echo $case['crime_type']; ?>"
                                         data-judge="<?php echo $case['judge_name']; ?>"
                                         data-lawyer="<?php echo $case['lawyer_name']; ?>"
                                         data-start-date="<?php echo $case['start_date']; ?>"
                                         data-status="<?php echo $case['status']; ?>"
                                         data-description="<?php echo htmlspecialchars($case['description'] ?? ''); ?>"
                                         data-hearing-date="<?php echo $case['hearing_date']; ?>"
                                         data-adjournment-reason="<?php echo htmlspecialchars($case['adjournment_reason'] ?? ''); ?>"
                                         data-proceedings-summary="<?php echo htmlspecialchars($case['proceedings_summary'] ?? ''); ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo $case['cin']; ?></h6>
                                            <small class="text-muted">Closed on <?php echo $case['hearing_date']; ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo $case['defendant_name']; ?></p>
                                        <small><?php echo $case['crime_type']; ?></small>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mt-3">No closed cases found</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reschedule Hearing Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-calendar-alt me-2"></i>Reschedule Hearing (Pending Cases Only)</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="hearing_id_reschedule" class="form-label">Select Hearing</label>
                        <select class="form-select" id="hearing_id_reschedule" name="hearing_id" required>
                            <option value="">Select a hearing...</option>
                            <?php 
                            // Reset pointer and reuse the pending hearings query
                            $pending_hearings->data_seek(0);
                            while ($hearing = $pending_hearings->fetch_assoc()): ?>
                                <option value="<?php echo $hearing['hearing_id']; ?>">
                                    <?php echo htmlspecialchars($hearing['cin'] . ' - ' . $hearing['defendant_name'] . ' (' . $hearing['crime_type'] . ') - ' . date('M d, Y h:i A', strtotime($hearing['hearing_date']))); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="new_hearing_date" class="form-label">New Hearing Date</label>
                        <input type="datetime-local" class="form-control" id="new_hearing_date" name="new_hearing_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="adjournment_reason" class="form-label">Reason for Adjournment</label>
                        <textarea class="form-control" id="adjournment_reason" name="adjournment_reason" rows="3" required></textarea>
                    </div>
                    <button type="submit" name="reschedule_hearing" class="btn btn-warning"><i class="fas fa-calendar-check me-1"></i>Reschedule Hearing</button>
                </form>
            </div>
        </div>

        <!-- Record Proceedings Summary Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-clipboard me-2"></i>Record Proceedings Summary (Pending Cases Only)</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="hearing_id_proceedings" class="form-label">Select Hearing</label>
                        <select class="form-select" id="hearing_id_proceedings" name="hearing_id" required>
                            <option value="">Select a hearing...</option>
                            <?php 
                            // Reset pointer and reuse the pending hearings query
                            $pending_hearings->data_seek(0);
                            while ($hearing = $pending_hearings->fetch_assoc()): ?>
                                <option value="<?php echo $hearing['hearing_id']; ?>">
                                    <?php echo htmlspecialchars($hearing['cin'] . ' - ' . $hearing['defendant_name'] . ' (' . $hearing['crime_type'] . ') - ' . date('M d, Y h:i A', strtotime($hearing['hearing_date']))); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="proceedings_summary" class="form-label">Proceedings Summary</label>
                        <textarea class="form-control" id="proceedings_summary" name="proceedings_summary" rows="5" required></textarea>
                    </div>
                    <button type="submit" name="record_proceedings" class="btn btn-primary"><i class="fas fa-save me-1"></i>Record Summary</button>
                </form>
            </div>
        </div>

        <!-- Past Cases -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-archive me-2"></i>Past Cases</h5>
            </div>
            <div class="card-body">
                <?php 
                // Re-fetch past cases for this section
                $past_cases_display = $conn->query($past_cases_query);
                if ($past_cases_display->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($case = $past_cases_display->fetch_assoc()): ?>
                            <div class="list-group-item case-item" 
                                 data-bs-toggle="modal" 
                                 data-bs-target="#caseDetailsModal"
                                 data-case-id="<?php echo $case['case_id']; ?>"
                                 data-cin="<?php echo $case['cin']; ?>"
                                 data-defendant="<?php echo $case['defendant_name']; ?>"
                                 data-crime="<?php echo $case['crime_type']; ?>"
                                 data-judge="<?php echo $case['judge_name']; ?>"
                                 data-lawyer="<?php echo $case['lawyer_name']; ?>"
                                 data-start-date="<?php echo $case['start_date']; ?>"
                                 data-status="<?php echo $case['status']; ?>"
                                 data-description="<?php echo htmlspecialchars($case['description'] ?? ''); ?>"
                                 data-hearing-date="<?php echo $case['hearing_date']; ?>"
                                 data-adjournment-reason="<?php echo htmlspecialchars($case['adjournment_reason'] ?? ''); ?>"
                                 data-proceedings-summary="<?php echo htmlspecialchars($case['proceedings_summary'] ?? ''); ?>">
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

    <!-- Case Details Modal -->
    <div class="modal fade" id="caseDetailsModal" tabindex="-1" aria-labelledby="caseDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="caseDetailsModalLabel">Case Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Case Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th>CIN:</th>
                                    <td id="modal-cin"></td>
                                </tr>
                                <tr>
                                    <th>Defendant:</th>
                                    <td id="modal-defendant"></td>
                                </tr>
                                <tr>
                                    <th>Crime Type:</th>
                                    <td id="modal-crime"></td>
                                </tr>
                                <tr>
                                    <th>Start Date:</th>
                                    <td id="modal-start-date"></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td id="modal-status"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Assigned Personnel</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th>Judge:</th>
                                    <td id="modal-judge"></td>
                                </tr>
                                <tr>
                                    <th>Lawyer:</th>
                                    <td id="modal-lawyer"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Hearing Information Section -->
                    <div class="mb-3">
                        <h6>Hearing Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Hearing Date:</th>
                                <td id="modal-hearing-date"></td>
                            </tr>
                            <tr>
                                <th>Adjournment Reason:</th>
                                <td id="modal-adjournment-reason"></td>
                            </tr>
                            <tr>
                                <th>Proceedings Summary:</th>
                                <td id="modal-proceedings-summary"></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Case Description</h6>
                        <div class="card">
                            <div class="card-body" id="modal-description">
                                No description available
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Case Details Modal Handler
        document.addEventListener('DOMContentLoaded', function() {
            var caseDetailsModal = document.getElementById('caseDetailsModal');
            if (caseDetailsModal) {
                caseDetailsModal.addEventListener('show.bs.modal', function(event) {
                    var button = event.relatedTarget;
                    
                    // Update modal title
                    document.getElementById('caseDetailsModalLabel').textContent = 
                        'Case Details: ' + button.getAttribute('data-cin');
                    
                    // Populate case details
                    document.getElementById('modal-cin').textContent = button.getAttribute('data-cin');
                    document.getElementById('modal-defendant').textContent = button.getAttribute('data-defendant');
                    document.getElementById('modal-crime').textContent = button.getAttribute('data-crime');
                    document.getElementById('modal-judge').textContent = button.getAttribute('data-judge') || 'Not assigned';
                    document.getElementById('modal-lawyer').textContent = button.getAttribute('data-lawyer') || 'Not assigned';
                    document.getElementById('modal-start-date').textContent = button.getAttribute('data-start-date');
                    
                    // Format status
                    const status = button.getAttribute('data-status');
                    document.getElementById('modal-status').textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    
                    // Description
                    const description = button.getAttribute('data-description');
                    document.getElementById('modal-description').textContent = description || 'No description available';
                    
                    // Hearing information
                    const hearingDate = button.getAttribute('data-hearing-date');
                    document.getElementById('modal-hearing-date').textContent = 
                        hearingDate ? new Date(hearingDate).toLocaleString() : 'No hearing scheduled';
                    
                    const adjournmentReason = button.getAttribute('data-adjournment-reason');
                    document.getElementById('modal-adjournment-reason').textContent = 
                        adjournmentReason || 'No adjournment reason provided';
                    
                    const proceedingsSummary = button.getAttribute('data-proceedings-summary');
                    document.getElementById('modal-proceedings-summary').textContent = 
                        proceedingsSummary || 'No proceedings summary available';
                });
            }
        });
    </script>
</body>
</html>