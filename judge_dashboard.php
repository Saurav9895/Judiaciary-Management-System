<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'judge') {
    header("Location: login.php");
    exit();
}

$judge_id = $_SESSION['user_id'];

// Fetch cases assigned to this judge
$assigned_cases_query = "SELECT c.*, COALESCE(h.hearing_date, 'No hearing scheduled') as next_hearing, u.full_name as lawyer_name 
                         FROM cases c 
                         LEFT JOIN hearings h ON c.case_id = h.case_id 
                         LEFT JOIN users u ON c.lawyer_id = u.user_id 
                         WHERE c.judge_id = ? AND c.status = 'pending' 
                         ORDER BY h.hearing_date ASC";
$stmt = $conn->prepare($assigned_cases_query);
$stmt->bind_param("i", $judge_id);
$stmt->execute();
$assigned_cases = $stmt->get_result();

// Fetch today's hearings for this judge
$today = date('Y-m-d');
$today_hearings_query = "SELECT c.*, h.hearing_date, h.hearing_id 
                         FROM cases c 
                         JOIN hearings h ON c.case_id = h.case_id 
                         WHERE c.judge_id = ? AND DATE(h.hearing_date) = ? 
                         ORDER BY h.hearing_date ASC";
$stmt = $conn->prepare($today_hearings_query);
$stmt->bind_param("is", $judge_id, $today);
$stmt->execute();
$today_hearings = $stmt->get_result();

// Fetch past cases for this judge
$past_cases_query = "SELECT c.*, h.hearing_date, u.full_name as lawyer_name 
                     FROM cases c 
                     LEFT JOIN hearings h ON c.case_id = h.case_id 
                     LEFT JOIN users u ON c.lawyer_id = u.user_id 
                     WHERE c.judge_id = ? AND (c.status = 'closed') 
                     ORDER BY h.hearing_date DESC";
$stmt = $conn->prepare($past_cases_query);
$stmt->bind_param("i", $judge_id);
$stmt->execute();
$past_cases = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Judge Dashboard - JIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background-color: var(--primary-color) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            color: white !important;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        .list-group-item {
            border-left: none;
            border-right: none;
            padding: 15px 20px;
            transition: all 0.3s;
        }
        
        .list-group-item:hover {
            background-color: var(--light-color);
        }
        
        .badge-primary {
            background-color: var(--secondary-color);
        }
        
        .badge-warning {
            background-color: #f39c12;
        }
        
        .badge-success {
            background-color: #27ae60;
        }
        
        .case-card {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .case-card:hover {
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            text-align: center;
            padding: 20px;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stats-label {
            color: #7f8c8d;
            font-weight: 600;
        }
        
        .modal-header {
            background-color: var(--primary-color);
            color: white;
        }
        
        .hearing-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-gavel me-2"></i>JIS - Judge Dashboard</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="search_cases.php"><i class="fas fa-search me-1"></i> Search Cases</a>
                <a class="nav-link" href="judge_profile.php"><i class="fas fa-user me-1"></i> Profile</a>
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card">
                    <h3 class="stats-number"><?php echo $assigned_cases->num_rows; ?></h3>
                    <p class="stats-label">Active Cases</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <h3 class="stats-number"><?php echo $today_hearings->num_rows; ?></h3>
                    <p class="stats-label">Today's Hearings</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <h3 class="stats-number"><?php echo $past_cases->num_rows; ?></h3>
                    <p class="stats-label">Closed Cases</p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="fas fa-calendar-day me-2"></i>Today's Hearings</h5>
                        <span class="badge bg-warning rounded-pill"><?php echo $today_hearings->num_rows; ?></span>
                    </div>
                    <div class="card-body">
                        <?php if ($today_hearings->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while($hearing = $today_hearings->fetch_assoc()): ?>
                                    <a href="case_details.php?cin=<?php echo $hearing['cin']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo $hearing['defendant_name']; ?></h6>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($hearing['hearing_date'])); ?></small>
                                        </div>
                                        <p class="mb-1">CIN: <?php echo $hearing['cin']; ?></p>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted"><?php echo $hearing['crime_type']; ?></small>
                                            <span class="badge bg-primary hearing-badge">Hearing</span>
                                        </div>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No hearings scheduled for today</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="fas fa-clipboard-list me-2"></i>My Cases</h5>
                        <span class="badge bg-primary rounded-pill"><?php echo $assigned_cases->num_rows; ?></span>
                    </div>
                    <div class="card-body">
                        <?php if ($assigned_cases->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while($case = $assigned_cases->fetch_assoc()): ?>
                                    <a href="#" class="list-group-item list-group-item-action case-card" data-bs-toggle="modal" data-bs-target="#caseModal" 
                                       data-cin="<?php echo $case['cin']; ?>"
                                       data-defendant="<?php echo $case['defendant_name']; ?>"
                                       data-crime="<?php echo $case['crime_type']; ?>"
                                       data-lawyer="<?php echo $case['lawyer_name']; ?>"
                                       data-hearing="<?php echo $case['next_hearing']; ?>"
                                       data-status="<?php echo $case['status']; ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo $case['defendant_name']; ?></h6>
                                            <small class="text-muted"><?php echo $case['next_hearing']; ?></small>
                                        </div>
                                        <p class="mb-1">CIN: <?php echo $case['cin']; ?></p>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted"><?php echo $case['crime_type']; ?></small>
                                            <span class="badge bg-warning hearing-badge">Pending</span>
                                        </div>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No cases assigned</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Past Cases</h5>
                        <span class="badge bg-success rounded-pill"><?php echo $past_cases->num_rows; ?></span>
                    </div>
                    <div class="card-body">
                        <?php if ($past_cases->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>CIN</th>
                                            <th>Defendant</th>
                                            <th>Crime Type</th>
                                            <th>Lawyer</th>
                                            <th>Last Hearing</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($case = $past_cases->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $case['cin']; ?></td>
                                            <td><?php echo $case['defendant_name']; ?></td>
                                            <td><?php echo $case['crime_type']; ?></td>
                                            <td><?php echo $case['lawyer_name']; ?></td>
                                            <td><?php echo $case['hearing_date']; ?></td>
                                            <td><span class="badge bg-success">Closed</span></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No past cases found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Case Details Modal -->
    <div class="modal fade" id="caseModal" tabindex="-1" aria-labelledby="caseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="caseModalLabel">Case Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-id-card me-2"></i>Case Information</h6>
                            <ul class="list-group list-group-flush mb-3">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>CIN:</span>
                                    <span id="modal-cin" class="fw-bold"></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Defendant:</span>
                                    <span id="modal-defendant" class="fw-bold"></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Crime Type:</span>
                                    <span id="modal-crime" class="fw-bold"></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Status:</span>
                                    <span id="modal-status" class="badge bg-warning"></span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-users me-2"></i>Assigned Personnel</h6>
                            <ul class="list-group list-group-flush mb-3">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Lawyer:</span>
                                    <span id="modal-lawyer" class="fw-bold"></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Next Hearing:</span>
                                    <span id="modal-hearing" class="fw-bold"></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="#" id="full-details-btn" class="btn btn-primary me-md-2">
                                    <i class="fas fa-file-alt me-1"></i> View Full Details
                                </a>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i> Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Case Modal Script
        document.addEventListener('DOMContentLoaded', function() {
            var caseModal = document.getElementById('caseModal');
            
            caseModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                
                // Extract info from data-* attributes
                var cin = button.getAttribute('data-cin');
                var defendant = button.getAttribute('data-defendant');
                var crime = button.getAttribute('data-crime');
                var lawyer = button.getAttribute('data-lawyer');
                var hearing = button.getAttribute('data-hearing');
                var status = button.getAttribute('data-status');
                
                // Update the modal's content
                document.getElementById('modal-cin').textContent = cin;
                document.getElementById('modal-defendant').textContent = defendant;
                document.getElementById('modal-crime').textContent = crime;
                document.getElementById('modal-lawyer').textContent = lawyer;
                document.getElementById('modal-hearing').textContent = hearing;
                document.getElementById('modal-status').textContent = status.charAt(0).toUpperCase() + status.slice(1);
                
                // Set the full details link
                document.getElementById('full-details-btn').href = 'case_details.php?cin=' + cin;
            });
        });
    </script>
</body>
</html>