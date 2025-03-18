<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lawyer') {
    header('Location: login.php');
    exit();
}

$lawyer_id = $_SESSION['user_id'];
$message = '';

// Handle billing record submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $case_id = mysqli_real_escape_string($conn, $_POST['case_id']);
    $hours_spent = floatval($_POST['hours_spent']);
    $rate_per_hour = floatval($_POST['rate_per_hour']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $sql = "INSERT INTO billing_records (lawyer_id, case_id, billing_date, hours_spent, rate_per_hour, description) 
            VALUES (?, ?, CURDATE(), ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iidds', $lawyer_id, $case_id, $hours_spent, $rate_per_hour, $description);

    if ($stmt->execute()) {
        $message = 'Billing record added successfully!';
    } else {
        $message = 'Error adding billing record: ' . $conn->error;
    }
}

// Fetch lawyer's billing records
$sql = "SELECT br.*, c.cin, c.title 
        FROM billing_records br 
        JOIN cases c ON br.case_id = c.case_id 
        WHERE br.lawyer_id = ? 
        ORDER BY br.billing_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $lawyer_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch available cases for the lawyer
$cases_sql = "SELECT c.case_id, c.cin, c.title 
             FROM cases c 
             JOIN case_assignments ca ON c.case_id = ca.case_id 
             WHERE ca.user_id = ? AND ca.role = 'lawyer' AND ca.status = 'active'";
$cases_stmt = $conn->prepare($cases_sql);
$cases_stmt->bind_param('i', $lawyer_id);
$cases_stmt->execute();
$cases_result = $cases_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Management - JIS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Billing Management</h2>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <h3>Add Billing Record</h3>
                <form method="POST" class="mt-4">
                    <div class="form-group">
                        <label for="case_id">Select Case</label>
                        <select class="form-control" id="case_id" name="case_id" required>
                            <option value="">Select a case...</option>
                            <?php while ($case = $cases_result->fetch_assoc()): ?>
                                <option value="<?php echo $case['case_id']; ?>">
                                    <?php echo htmlspecialchars($case['cin'] . ' - ' . $case['title']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="hours_spent">Hours Spent</label>
                        <input type="number" step="0.25" class="form-control" id="hours_spent" name="hours_spent" required>
                    </div>

                    <div class="form-group">
                        <label for="rate_per_hour">Rate per Hour ($)</label>
                        <input type="number" step="0.01" class="form-control" id="rate_per_hour" name="rate_per_hour" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Add Billing Record</button>
                </form>
            </div>

            <div class="col-md-6">
                <h3>Billing History</h3>
                <div class="table-responsive mt-4">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Case</th>
                                <th>Hours</th>
                                <th>Rate</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($record = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['billing_date']); ?></td>
                                    <td><?php echo htmlspecialchars($record['cin'] . ' - ' . $record['title']); ?></td>
                                    <td><?php echo htmlspecialchars($record['hours_spent']); ?></td>
                                    <td>$<?php echo htmlspecialchars($record['rate_per_hour']); ?></td>
                                    <td>$<?php echo number_format($record['hours_spent'] * $record['rate_per_hour'], 2); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($record['status'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <a href="dashboard.php" class="btn btn-secondary mt-4">Back to Dashboard</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>