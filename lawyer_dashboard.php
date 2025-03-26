<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'lawyer') {
    header("Location: login.php");
    exit();
}

$lawyer_id = $_SESSION['user_id'];

// Fetch today's hearings for this lawyer
$today = date('Y-m-d');
$today_hearings_query = "SELECT c.*, h.hearing_date, h.hearing_id 
                         FROM cases c 
                         JOIN hearings h ON c.case_id = h.case_id 
                         WHERE c.lawyer_id = ? AND DATE(h.hearing_date) = ? 
                         ORDER BY h.hearing_date ASC";
$stmt = $conn->prepare($today_hearings_query);
$stmt->bind_param("is", $lawyer_id, $today);
$stmt->execute();
$today_hearings = $stmt->get_result();

// Fetch pending cases for this lawyer
$assigned_cases_query = "SELECT c.*, COALESCE(h.hearing_date, 'No hearing scheduled') as next_hearing, u.full_name as judge_name 
                         FROM cases c 
                         LEFT JOIN hearings h ON c.case_id = h.case_id 
                         LEFT JOIN users u ON c.judge_id = u.user_id 
                         WHERE c.lawyer_id = ? AND c.status = 'pending' 
                         ORDER BY h.hearing_date ASC";
$stmt = $conn->prepare($assigned_cases_query);
$stmt->bind_param("i", $lawyer_id);
$stmt->execute();
$assigned_cases = $stmt->get_result();

// Fetch past cases for this lawyer
$past_cases_query = "SELECT c.*, h.hearing_date, u.full_name as judge_name 
                     FROM cases c 
                     LEFT JOIN hearings h ON c.case_id = h.case_id 
                     LEFT JOIN users u ON c.judge_id = u.user_id 
                     WHERE c.lawyer_id = ? AND (c.status = 'closed') 
                     ORDER BY h.hearing_date DESC";
$stmt = $conn->prepare($past_cases_query);
$stmt->bind_param("i", $lawyer_id);
$stmt->execute();
$past_cases = $stmt->get_result();

// Fetch all past cases (not assigned to this lawyer)
$all_past_cases_query = "SELECT c.*, u.full_name as judge_name 
                         FROM cases c 
                         LEFT JOIN users u ON c.judge_id = u.user_id 
                         WHERE c.status = 'closed' AND c.case_id NOT IN (
                             SELECT case_id FROM case_assignments WHERE user_id = ?
                         )
                         ORDER BY c.start_date DESC";
$stmt = $conn->prepare($all_past_cases_query);
$stmt->bind_param("i", $lawyer_id);
$stmt->execute();
$all_past_cases = $stmt->get_result();

// Fetch case access history with fees
$access_history_sql = "SELECT c.cin, c.defendant_name, c.crime_type, cb.access_date, cb.fee_amount 
                      FROM case_browsing_history cb
                      JOIN cases c ON cb.cin = c.cin
                      WHERE cb.lawyer_id = ?
                      ORDER BY cb.access_date DESC";
$access_stmt = $conn->prepare($access_history_sql);
$access_stmt->bind_param("i", $lawyer_id);
$access_stmt->execute();
$access_history = $access_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lawyer Dashboard - JIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">JIS - Lawyer Dashboard</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="search_cases.php">Search Cases</a>
                <a class="nav-link" href="lawyer_profile.php">Profile</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Today's Hearings</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($today_hearings->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while($hearing = $today_hearings->fetch_assoc()): ?>
                                    <a href="case_details.php?cin=<?php echo $hearing['cin']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">CIN: <?php echo $hearing['cin']; ?></h6>
                                            <small>Time: <?php echo $hearing['hearing_date']; ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo $hearing['defendant_name']; ?> - <?php echo $hearing['crime_type']; ?></p>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No hearings scheduled for today</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">My Cases</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($assigned_cases->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while($case = $assigned_cases->fetch_assoc()): ?>
                                    <a href="case_details.php?cin=<?php echo $case['cin']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">CIN: <?php echo $case['cin']; ?></h6>
                                            <small>Next Hearing: <?php echo $case['next_hearing']; ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo $case['defendant_name']; ?> - <?php echo $case['crime_type']; ?></p>
                                        <small>Judge: <?php echo $case['judge_name']; ?></small>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No cases assigned</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Past Cases</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($past_cases->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while($case = $past_cases->fetch_assoc()): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">CIN: <?php echo $case['cin']; ?></h6>
                                            <small>Hearing Date: <?php echo $case['hearing_date']; ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo $case['defendant_name']; ?> - <?php echo $case['crime_type']; ?></p>
                                        <small>Judge: <?php echo $case['judge_name']; ?></small>
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
        <div class="row mt-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Public Case Records (Fee: $10 per case)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($all_past_cases->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>CIN</th>
                                            <th>Defendant</th>
                                            <th>Crime Type</th>
                                            <th>Judge</th>
                                            <th>Start Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($case = $all_past_cases->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $case['cin']; ?></td>
                                                <td><?php echo $case['defendant_name']; ?></td>
                                                <td><?php echo $case['crime_type']; ?></td>
                                                <td><?php echo $case['judge_name']; ?></td>
                                                <td><?php echo $case['start_date']; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary pay-button" 
                                                            data-case-id="<?php echo $case['case_id']; ?>"
                                                            data-cin="<?php echo $case['cin']; ?>">
                                                        Pay $10
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No public case records available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Case Access History</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($access_history->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>CIN</th>
                                            <th>Defendant</th>
                                            <th>Crime Type</th>
                                            <th>Fee</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_fees = 0;
                                        while($access = $access_history->fetch_assoc()): 
                                            $total_fees += $access['fee_amount'];
                                        ?>
                                            <tr>
                                                <td><?php echo $access['access_date']; ?></td>
                                                <td><?php echo $access['cin']; ?></td>
                                                <td><?php echo $access['defendant_name']; ?></td>
                                                <td><?php echo $access['crime_type']; ?></td>
                                                <td>$<?php echo number_format($access['fee_amount'], 2); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                        <tr class="table-info">
                                            <td colspan="4" class="text-right"><strong>Total Fees:</strong></td>
                                            <td><strong>$<?php echo number_format($total_fees, 2); ?></strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No case access history</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Confirmation Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to pay $10 to access this case?</p>
                    <p><strong>Case CIN:</strong> <span id="caseCin"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="paymentForm" method="POST" action="access_case.php">
                        <input type="hidden" name="case_id" id="modalCaseId">
                        <input type="hidden" name="cin" id="modalCaseCin">
                        <button type="submit" class="btn btn-primary">Confirm Payment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Payment confirmation modal
        document.querySelectorAll('.pay-button').forEach(button => {
            button.addEventListener('click', function() {
                const caseId = this.getAttribute('data-case-id');
                const caseCin = this.getAttribute('data-cin');
                
                document.getElementById('caseCin').textContent = caseCin;
                document.getElementById('modalCaseId').value = caseId;
                document.getElementById('modalCaseCin').value = caseCin;
                
                const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
                modal.show();
            });
        });

        // Handle form submission
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            // You could add additional validation or payment processing here
            // For now, it will submit to access_case.php as before
        });
    </script>
</body>
</html>