<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'judge') {
    header('Location: login.php');
    exit();
}

$message = '';

// Handle judgment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $case_id = mysqli_real_escape_string($conn, $_POST['case_id']);
    $judgment_date = mysqli_real_escape_string($conn, $_POST['judgment_date']);
    $judgment_text = mysqli_real_escape_string($conn, $_POST['judgment_text']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $sql = "INSERT INTO judgments (case_id, judge_id, judgment_date, judgment_text, status) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $case_id, $_SESSION['user_id'], $judgment_date, $judgment_text, $status);

    if ($stmt->execute()) {
        $message = 'Judgment recorded successfully!';
    } else {
        $message = 'Error recording judgment: ' . $conn->error;
    }
}

// Fetch cases assigned to this judge
$cases_sql = "SELECT * FROM cases WHERE judge_id = ? AND status = 'pending'";
$stmt = $conn->prepare($cases_sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$cases_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Judgment - JIS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Record Judgment</h2>
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
                            <?php echo htmlspecialchars($case['cin'] . ' - ' . $case['title']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="judgment_date">Judgment Date</label>
                <input type="date" class="form-control" id="judgment_date" name="judgment_date" required>
            </div>

            <div class="form-group">
                <label for="judgment_text">Judgment Text</label>
                <textarea class="form-control" id="judgment_text" name="judgment_text" rows="5" required></textarea>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select class="form-control" id="status" name="status" required>
                    <option value="draft">Draft</option>
                    <option value="final">Final</option>
                    <option value="appealed">Appealed</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Record Judgment</button>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>