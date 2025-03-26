<?php
session_start();
require_once 'db_connect.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'lawyer') {
    header("Location: login.php");
    exit();
}

$lawyer_id = $_SESSION['user_id'];

// Fetch today's hearings
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

// Fetch pending cases
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

// Fetch past cases with payment status
$past_cases_query = "SELECT c.*, h.hearing_date, u.full_name as judge_name,
                     CASE WHEN cb.lawyer_id IS NULL THEN 0 ELSE 1 END as is_paid
                     FROM cases c 
                     LEFT JOIN hearings h ON c.case_id = h.case_id 
                     LEFT JOIN users u ON c.judge_id = u.user_id
                     LEFT JOIN case_browsing_history cb ON c.cin = cb.cin AND cb.lawyer_id = ?
                     WHERE c.lawyer_id = ? AND c.status = 'closed' 
                     ORDER BY h.hearing_date DESC";
$stmt = $conn->prepare($past_cases_query);
$stmt->bind_param("ii", $lawyer_id, $lawyer_id);
$stmt->execute();
$past_cases = $stmt->get_result();

// Fetch case access history
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .paid-case { background-color: #f8f9fa; }
        .unpaid-case { opacity: 0.7; }
        .card { margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .card-header { background-color: #343a40; color: white; font-weight: bold; }
        .list-group-item { transition: all 0.3s ease; }
        .list-group-item:hover { background-color: #f8f9fa; }
        .payment-btn { width: 180px; }
        .spinner-border { width: 3rem; height: 3rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="lawyer_dashboard.php">JIS - Lawyer Dashboard</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="search_cases.php"><i class="bi bi-search"></i> Search</a>
                <a class="nav-link" href="lawyer_profile.php"><i class="bi bi-person"></i> Profile</a>
                <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-calendar-event"></i> Today's Hearings</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($today_hearings->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while($hearing = $today_hearings->fetch_assoc()): ?>
                                    <a href="case_details.php?cin=<?= htmlspecialchars($hearing['cin']) ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">CIN: <?= htmlspecialchars($hearing['cin']) ?></h6>
                                            <small><?= htmlspecialchars($hearing['hearing_date']) ?></small>
                                        </div>
                                        <p class="mb-1"><?= htmlspecialchars($hearing['defendant_name']) ?> - <?= htmlspecialchars($hearing['crime_type']) ?></p>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No hearings today</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-folder"></i> My Active Cases</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($assigned_cases->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while($case = $assigned_cases->fetch_assoc()): ?>
                                    <a href="case_details.php?cin=<?= htmlspecialchars($case['cin']) ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">CIN: <?= htmlspecialchars($case['cin']) ?></h6>
                                            <small><?= htmlspecialchars($case['next_hearing']) ?></small>
                                        </div>
                                        <p class="mb-1"><?= htmlspecialchars($case['defendant_name']) ?> - <?= htmlspecialchars($case['crime_type']) ?></p>
                                        <small>Judge: <?= htmlspecialchars($case['judge_name']) ?></small>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No active cases</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-archive"></i> My Past Cases</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($past_cases->num_rows > 0): ?>
                            <div class="list-group" id="pastCasesList">
                                <?php while($case = $past_cases->fetch_assoc()): ?>
                                    <div class="list-group-item list-group-item-action <?= $case['is_paid'] ? 'paid-case' : 'unpaid-case' ?>"
                                         id="case-<?= htmlspecialchars($case['cin']) ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">CIN: <?= htmlspecialchars($case['cin']) ?></h6>
                                            <small><?= htmlspecialchars($case['hearing_date']) ?></small>
                                        </div>
                                        <p class="mb-1"><?= htmlspecialchars($case['defendant_name']) ?> - <?= htmlspecialchars($case['crime_type']) ?></p>
                                        <small>Judge: <?= htmlspecialchars($case['judge_name']) ?></small>
                                        <div class="mt-2 d-flex justify-content-end">
                                            <?php if ($case['is_paid']): ?>
                                                <a href="case_details.php?cin=<?= htmlspecialchars($case['cin']) ?>" class="btn btn-sm btn-success payment-btn">
                                                    <i class="bi bi-eye"></i> View Case
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-primary payment-btn pay-case-button"
                                                        data-case-id="<?= htmlspecialchars($case['case_id']) ?>"
                                                        data-cin="<?= htmlspecialchars($case['cin']) ?>"
                                                        data-defendant="<?= htmlspecialchars($case['defendant_name']) ?>"
                                                        data-crime="<?= htmlspecialchars($case['crime_type']) ?>">
                                                    <i class="bi bi-credit-card"></i> Pay $10 to View
                                                </button>
                                            <?php endif; ?>
                                        </div>
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
                        <h5 class="card-title mb-0"><i class="bi bi-clock-history"></i> Payment History</h5>
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
                                            <th>Amount</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $total_fees = 0; ?>
                                        <?php while($access = $access_history->fetch_assoc()): ?>
                                            <?php $total_fees += $access['fee_amount']; ?>
                                            <tr class="paid-case">
                                                <td><?= htmlspecialchars($access['access_date']) ?></td>
                                                <td><?= htmlspecialchars($access['cin']) ?></td>
                                                <td><?= htmlspecialchars($access['defendant_name']) ?></td>
                                                <td><?= htmlspecialchars($access['crime_type']) ?></td>
                                                <td>$<?= number_format($access['fee_amount'], 2) ?></td>
                                                <td>
                                                    <a href="case_details.php?cin=<?= htmlspecialchars($access['cin']) ?>" class="btn btn-sm btn-success">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                        <tr class="table-info">
                                            <td colspan="4" class="text-end fw-bold">Total Paid:</td>
                                            <td class="fw-bold">$<?= number_format($total_fees, 2) ?></td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No payment history</p>
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
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> You are about to pay $10 to access this case record.
                    </div>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="card-title">Case Details</h6>
                            <p class="mb-1"><strong>CIN:</strong> <span id="modalCaseCin"></span></p>
                            <p class="mb-1"><strong>Defendant:</strong> <span id="modalDefendant"></span></p>
                            <p class="mb-1"><strong>Crime Type:</strong> <span id="modalCrimeType"></span></p>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                        <label class="form-check-label" for="agreeTerms">
                            I agree to pay $10 for accessing this case record
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmPaymentBtn" disabled>
                        <i class="bi bi-credit-card"></i> Confirm Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Processing Modal -->
    <div class="modal fade" id="processingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Processing Payment</h5>
                </div>
                <div class="modal-body text-center">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Please wait while we process your payment...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Payment Successful</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                    <p class="mt-3">Your payment of $10 has been processed successfully!</p>
                    <p>You can now access the case details.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-success" id="viewCaseBtn">
                        <i class="bi bi-eye"></i> View Case Now
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Payment flow handling
        let currentCase = {
            id: null,
            cin: null,
            defendant: null,
            crimeType: null
        };

        // Set up payment buttons
        document.querySelectorAll('.pay-case-button').forEach(btn => {
            btn.addEventListener('click', function() {
                currentCase = {
                    id: this.dataset.caseId,
                    cin: this.dataset.cin,
                    defendant: this.dataset.defendant,
                    crimeType: this.dataset.crime
                };
                
                // Update modal with case details
                document.getElementById('modalCaseCin').textContent = currentCase.cin;
                document.getElementById('modalDefendant').textContent = currentCase.defendant;
                document.getElementById('modalCrimeType').textContent = currentCase.crimeType;
                
                // Reset and show modal
                document.getElementById('agreeTerms').checked = false;
                document.getElementById('confirmPaymentBtn').disabled = true;
                
                const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
                paymentModal.show();
            });
        });

        // Enable confirm button when terms are checked
        document.getElementById('agreeTerms').addEventListener('change', function() {
            document.getElementById('confirmPaymentBtn').disabled = !this.checked;
        });

        // Handle payment confirmation
        document.getElementById('confirmPaymentBtn').addEventListener('click', function() {
            const paymentModal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
            paymentModal.hide();
            
            // Show processing modal
            const processingModal = new bootstrap.Modal(document.getElementById('processingModal'));
            processingModal.show();
            
            // Make AJAX call to process payment
            fetch('process_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `case_id=${currentCase.id}&cin=${currentCase.cin}`
            })
            .then(response => response.json())
            .then(data => {
                processingModal.hide();
                if (data.success) {
                    // Update the UI to show "View Case" button
                    const caseElement = document.getElementById(`case-${currentCase.cin}`);
                    caseElement.classList.remove('unpaid-case');
                    caseElement.classList.add('paid-case');
                    
                    // Replace Pay button with View button
                    const payButton = caseElement.querySelector('.pay-case-button');
                    if (payButton) {
                        payButton.outerHTML = `
                            <a href="case_details.php?cin=${currentCase.cin}" class="btn btn-sm btn-success payment-btn">
                                <i class="bi bi-eye"></i> View Case
                            </a>
                        `;
                    }
                    
                    // Show success modal
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    document.getElementById('viewCaseBtn').href = `case_details.php?cin=${currentCase.cin}`;
                    successModal.show();
                } else {
                    alert('Payment failed: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                processingModal.hide();
                alert('Payment processing error: ' + error.message);
            });
        });

        // Check for payment success in URL
        if (window.location.search.includes('payment=success')) {
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
        }
    </script>
</body>
</html>