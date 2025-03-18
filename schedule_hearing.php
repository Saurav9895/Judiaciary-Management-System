<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'registrar') {
    header('Location: login.php');
    exit();
}

$message = '';

// Fetch all lawyers and judges
$lawyers_sql = "SELECT user_id, full_name FROM users WHERE role = 'lawyer'";
$judges_sql = "SELECT user_id, full_name FROM users WHERE role = 'judge'";
$lawyers_result = $conn->query($lawyers_sql);
$judges_result = $conn->query($judges_sql);

// Handle hearing scheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $case_id = mysqli_real_escape_string($conn, $_POST['case_id']);
    $hearing_date = mysqli_real_escape_string($conn, $_POST['hearing_date']);
    $hearing_type = mysqli_real_escape_string($conn, $_POST['hearing_type']);
    $proceedings_summary = mysqli_real_escape_string($conn, $_POST['proceedings_summary']); // Updated column name
    $lawyer_id = mysqli_real_escape_string($conn, $_POST['lawyer_id']);
    $judge_id = mysqli_real_escape_string($conn, $_POST['judge_id']);

    // Assign lawyer and judge to the case
    $assign_sql = "UPDATE cases SET lawyer_id = ?, judge_id = ? WHERE case_id = ?";
    $assign_stmt = $conn->prepare($assign_sql);
    $assign_stmt->bind_param("iii", $lawyer_id, $judge_id, $case_id);
    $assign_stmt->execute();

    // Schedule hearing
    $hearing_sql = "INSERT INTO hearings (case_id, hearing_date, hearing_type, proceedings_summary) VALUES (?, ?, ?, ?)"; // Updated column name
    $hearing_stmt = $conn->prepare($hearing_sql);
    $hearing_stmt->bind_param("isss", $case_id, $hearing_date, $hearing_type, $proceedings_summary); // Updated column name

    if ($hearing_stmt->execute()) {
        $message = 'Hearing scheduled successfully!';
    } else {
        $message = 'Error scheduling hearing: ' . $conn->error;
    }
}

// Fetch pending cases (use correct column names)
$cases_sql = "SELECT case_id, cin, defendant_name, crime_type FROM cases WHERE status = 'pending'";
$cases_result = $conn->query($cases_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Hearing - JIS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Schedule Hearing</h2>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        <form method="POST" class="mt-4">
            <div class="form-group">
                <label for="case_id">Select Case</label>
                <select class="form-control" id="case_id" name="case_id" required>
                    <option value="">Select a case...</option>
                    <?php while ($case = $cases_result->fetch_assoc()): ?>
                        <option value="<?php echo $case['case_id']; ?>">
                            <?php echo htmlspecialchars($case['cin'] . ' - ' . $case['defendant_name'] . ' (' . $case['crime_type'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="lawyer_id">Assign Lawyer</label>
                <select class="form-control" id="lawyer_id" name="lawyer_id" required>
                    <option value="">Select a lawyer...</option>
                    <?php while ($lawyer = $lawyers_result->fetch_assoc()): ?>
                        <option value="<?php echo $lawyer['user_id']; ?>">
                            <?php echo htmlspecialchars($lawyer['full_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="judge_id">Assign Judge</label>
                <select class="form-control" id="judge_id" name="judge_id" required>
                    <option value="">Select a judge...</option>
                    <?php while ($judge = $judges_result->fetch_assoc()): ?>
                        <option value="<?php echo $judge['user_id']; ?>">
                            <?php echo htmlspecialchars($judge['full_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="hearing_date">Hearing Date</label>
                <input type="datetime-local" class="form-control" id="hearing_date" name="hearing_date" required>
            </div>
            <div class="form-group">
                <label for="hearing_type">Hearing Type</label>
                <select class="form-control" id="hearing_type" name="hearing_type" required>
                    <option value="preliminary">Preliminary</option>
                    <option value="trial">Trial</option>
                    <option value="appeal">Appeal</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="proceedings_summary">Proceedings Summary</label> <!-- Updated column name -->
                <textarea class="form-control" id="proceedings_summary" name="proceedings_summary" rows="3"></textarea> <!-- Updated column name -->
            </div>
            <button type="submit" class="btn btn-primary">Schedule Hearing</button>
            <a href="registrar_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </form>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>